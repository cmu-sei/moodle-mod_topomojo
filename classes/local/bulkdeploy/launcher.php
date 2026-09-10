<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Drives one batch of bulk-deploy work: launch all gamespaces concurrently,
 * then poll until all are ready (or the wait ceiling fires).
 *
 * Stateless; one instance can be reused for multiple batches.
 */
class launcher {

    public function __construct(
        private job_repository $repo,
        private curl_multi_client $client,
        private string $apibaseurl,
        private array $authheaders,
        private int $requesttimeout,   // per-request cURL timeout (seconds)
        private int $pollintervalsec,   // sleep between poll cycles
        private int $waitceilingsec     // max wait time per batch, in seconds
    ) {}

    /**
     * @param int $jobid
     * @param array $batch each entry: ['rowid' => int, 'user' => stdClass]
     * @param \stdClass $topomojo activity record
     */
    public function run_batch(int $jobid, array $batch, \stdClass $topomojo): void {
        $launchresults = $this->launch_phase($batch, $topomojo);
        $this->wait_phase($launchresults, $topomojo, $batch);
    }

    /**
     * Returns the subset of $batch whose POSTs succeeded — these need polling.
     * @return array each entry: ['rowid' => int, 'gamespaceid' => string]
     */
    private function launch_phase(array $batch, \stdClass $topomojo): array {
        $apicalls = [];
        $apicallindex = [];
        foreach ($batch as $entry) {
            $rowid = $entry['rowid'];
            $user = $entry['user'];
            if (empty($user->email)) {
                $this->repo->set_user_status($rowid, user_status::SKIPPED, 'user has no email address');
                continue;
            }
            $payload = payload_builder::build($topomojo->workspaceid, $topomojo, $user);
            $apicalls[] = [
                'method'  => 'POST',
                'url'     => $this->apibaseurl . '/gamespace',
                'headers' => array_merge(['Content-Type: application/json'], $this->authheaders),
                'body'    => json_encode($payload),
                'timeout' => $this->requesttimeout,
            ];
            $apicallindex[] = $rowid;
        }
        if (!$apicalls) {
            return [];
        }

        $responses = $this->client->execute($apicalls);
        $launched = [];
        foreach ($responses as $i => $resp) {
            $rowid = $apicallindex[$i];
            if ($resp->errno === 28) {
                $this->repo->set_user_status($rowid, user_status::FAILED, 'timeout starting gamespace');
                continue;
            }
            if ($resp->httpcode !== 200) {
                $body = $this->trim_for_message($resp->body);
                $this->repo->set_user_status($rowid, user_status::FAILED, "HTTP {$resp->httpcode}: $body");
                continue;
            }
            $decoded = json_decode($resp->body);
            if (!is_object($decoded) || empty($decoded->id)) {
                $this->repo->set_user_status($rowid, user_status::FAILED, 'malformed response');
                continue;
            }
            $this->repo->set_user_status($rowid, user_status::LAUNCHED, null, (string) $decoded->id);
            $launched[] = ['rowid' => $rowid, 'gamespaceid' => (string) $decoded->id];
        }
        return $launched;
    }

    private function wait_phase(array $launched, \stdClass $topomojo, array $batch): void {
        global $DB;
        if (!$launched) {
            return;
        }

        // Build a map of rowid -> user for looking up user info. A batch entry can carry a user
        // object without an id; the consumer below already tolerates a missing value, so read it
        // defensively rather than raising an "undefined property" notice on the way there.
        $useridmap = [];
        foreach ($batch as $entry) {
            $useridmap[$entry['rowid']] = $entry['user']->id ?? null;
        }

        $start = $this->now();
        $remaining = $launched;
        $laststatus = [];

        while ($remaining) {
            // Drop rows whose status was changed externally (e.g. cancelled from the
            // management page) before issuing more polls. Avoids overwriting their
            // status when the wait ceiling fires.
            $remaining = $this->drop_externally_mutated($remaining);
            if (!$remaining) {
                return;
            }

            $requests = [];
            $reqindex = [];
            foreach ($remaining as $entry) {
                $requests[] = [
                    'method'  => 'GET',
                    'url'     => $this->apibaseurl . '/gamespace/' . $entry['gamespaceid'],
                    'headers' => $this->authheaders,
                    'timeout' => $this->requesttimeout,
                ];
                $reqindex[] = $entry;
            }
            $responses = $this->client->execute($requests);

            // Re-check after the GETs return: a cancellation may have landed while
            // requests were in flight. Build a map and skip writing to those rows.
            $currentstatuses = $this->repo->get_user_statuses(array_map(
                fn($e) => $e['rowid'],
                $reqindex
            ));

            $stillpending = [];
            foreach ($responses as $i => $resp) {
                $entry = $reqindex[$i];
                // External terminal states (CANCELLED, etc.) win over what we observed.
                if (($currentstatuses[$entry['rowid']] ?? null) !== user_status::LAUNCHED) {
                    continue;
                }
                $laststatus[$entry['gamespaceid']] = $this->extract_state($resp->body);
                if ($resp->httpcode === 200) {
                    $decoded = json_decode($resp->body);
                    if (is_object($decoded)
                        && !empty($decoded->isActive)
                        && !empty($decoded->vms)
                        && is_array($decoded->vms)
                    ) {
                        $this->repo->set_user_status($entry['rowid'], user_status::READY);
                        // Create attempt record for the user
                        $userid = $useridmap[$entry['rowid']] ?? null;
                        if ($userid) {
                            $this->create_attempt_for_user(
                                $userid,
                                $topomojo,
                                $decoded,
                                $entry['gamespaceid']
                            );
                        }
                        continue;
                    }
                }
                $stillpending[] = $entry;
            }

            if (!$stillpending) {
                return;
            }

            if (($this->now() - $start) >= $this->waitceilingsec) {
                foreach ($stillpending as $entry) {
                    $last = $laststatus[$entry['gamespaceid']] ?? 'unknown';
                    $this->repo->set_user_status(
                        $entry['rowid'],
                        user_status::FAILED,
                        "timeout waiting for VMs (last seen state: $last)"
                    );
                }
                return;
            }

            $remaining = $stillpending;
            if ($this->pollintervalsec > 0) {
                $this->sleep_seconds($this->pollintervalsec);
            }
        }
    }

    /**
     * Filters $remaining to entries whose DB status is still LAUNCHED. Anything
     * else (CANCELLED via management page, or any other external change) is dropped.
     *
     * @param array $remaining each entry: ['rowid' => int, 'gamespaceid' => string]
     * @return array
     */
    private function drop_externally_mutated(array $remaining): array {
        if (!$remaining) {
            return [];
        }
        $statuses = $this->repo->get_user_statuses(array_map(fn($e) => $e['rowid'], $remaining));
        $kept = [];
        foreach ($remaining as $entry) {
            if (($statuses[$entry['rowid']] ?? null) === user_status::LAUNCHED) {
                $kept[] = $entry;
            }
        }
        return $kept;
    }

    protected function now(): int {
        return time();
    }

    protected function sleep_seconds(int $seconds): void {
        sleep($seconds);
    }

    private function trim_for_message(string $body): string {
        $body = trim($body);
        return strlen($body) > 200 ? substr($body, 0, 197) . '...' : $body;
    }

    private function extract_state(string $body): string {
        $decoded = json_decode($body);
        if (!is_object($decoded)) {
            return 'no-json';
        }
        $active = !empty($decoded->isActive) ? 'active' : 'inactive';
        $vms = !empty($decoded->vms) ? 'vms-ready' : 'no-vms';
        return "$active,$vms";
    }

    /**
     * Creates an attempt record for a successfully deployed gamespace.
     * @param int $userid
     * @param \stdClass $topomojo activity record
     * @param \stdClass $gamespace decoded gamespace response from TopoMojo API
     * @param string $gamespaceid the id returned by the launch, used when the poll omits it
     */
    private function create_attempt_for_user(
        int $userid,
        \stdClass $topomojo,
        \stdClass $gamespace,
        string $gamespaceid
    ): void {
        global $DB;

        // Check if user already has an open attempt for this activity
        $existing = $DB->get_record('topomojo_attempts', [
            'topomojoid' => $topomojo->id,
            'userid' => $userid,
            'state' => \mod_topomojo\topomojo_attempt::INPROGRESS
        ]);

        if ($existing) {
            // User already has an open attempt, don't create duplicate
            return;
        }

        $attempt = new \stdClass();
        $attempt->topomojoid = $topomojo->id;
        $attempt->userid = $userid;
        // Prefer the id the launch handed us over re-reading it from the poll body: gamespace
        // isolation resolves a user's lab through this column (see #67), so it must not end up null
        // just because the poll response left the field out.
        $attempt->eventid = $gamespace->id ?? $gamespaceid;
        $attempt->workspaceid = $topomojo->workspaceid;
        $attempt->launchpointurl = $gamespace->launchpointUrl ?? '';
        $attempt->state = \mod_topomojo\topomojo_attempt::INPROGRESS;
        $attempt->preview = 0; // Bulk deploy is never preview
        $attempt->timestart = time();
        $attempt->timemodified = time();
        $attempt->timefinish = null;
        $attempt->endtime = $this->resolve_endtime($topomojo, $gamespace);
        // Record the variant TopoMojo actually deployed, as topomojo::init_attempt() does: the
        // API reports it 0-based, the column is 1-based. Without this every bulk-deployed attempt
        // is variant 0, so a workspace with per-variant questions grades against the wrong set.
        $attempt->variant = isset($gamespace->variant)
            ? ((int) $gamespace->variant + 1)
            : (int) $topomojo->variant;
        $attempt->score = 0;

        $DB->insert_record('topomojo_attempts', $attempt);
    }

    /**
     * Works out when a bulk-deployed attempt should expire.
     *
     * topomojo_attempts.endtime is NOT NULL with no default, so an absent or unparseable
     * expirationTime cannot simply be stored as null - that rejects the whole insert and fails the
     * job. Nor can it be stored as 0: close_attempts closes everything whose endtime is in the
     * past, so 0 would reap the attempt on the next cron run.
     *
     * @param \stdClass $topomojo activity record
     * @param \stdClass $gamespace the gamespace as TopoMojo reported it
     * @return int a unix timestamp, never null
     */
    private function resolve_endtime(\stdClass $topomojo, \stdClass $gamespace): int {
        $expiry = empty($gamespace->expirationTime) ? false : strtotime($gamespace->expirationTime);
        if ($expiry !== false) {
            return $expiry;
        }

        // Fall back to the window we asked for via the payload's maxMinutes. A duration of 0 means
        // no limit was requested, so put the attempt beyond the reach of close_attempts instead.
        $duration = (int) $topomojo->duration;
        return time() + ($duration > 0 ? $duration : YEARSECS);
    }
}
