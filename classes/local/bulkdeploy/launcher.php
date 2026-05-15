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
        $this->wait_phase($launchresults);
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

    private function wait_phase(array $launched): void {
        if (!$launched) {
            return;
        }
        $start = $this->now();
        $remaining = $launched;
        $laststatus = [];

        while ($remaining) {
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

            $stillpending = [];
            foreach ($responses as $i => $resp) {
                $entry = $reqindex[$i];
                $laststatus[$entry['gamespaceid']] = $this->extract_state($resp->body);
                if ($resp->httpcode === 200) {
                    $decoded = json_decode($resp->body);
                    if (is_object($decoded)
                        && !empty($decoded->isActive)
                        && !empty($decoded->vms)
                        && is_array($decoded->vms)
                    ) {
                        $this->repo->set_user_status($entry['rowid'], user_status::READY);
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
}
