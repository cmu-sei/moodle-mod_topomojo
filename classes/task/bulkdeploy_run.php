<?php
namespace mod_topomojo\task;

use mod_topomojo\local\bulkdeploy\curl_multi_client;
use mod_topomojo\local\bulkdeploy\job_repository;
use mod_topomojo\local\bulkdeploy\job_status;
use mod_topomojo\local\bulkdeploy\launcher;
use mod_topomojo\local\bulkdeploy\user_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad-hoc task that drives a bulk-deploy job to completion in concurrent batches.
 */
class bulkdeploy_run extends \core\task\adhoc_task {

    private ?curl_multi_client $testclient = null;
    private string $testapibaseurl = '';
    private array $testauthheaders = [];
    private int $testrequesttimeout = 60;
    private int $testpollintervalsec = 5;
    private int $testwaitceilingsec = 600;
    private ?\Closure $afterbatchhook = null;

    public function get_component(): string {
        return 'mod_topomojo';
    }

    public function set_test_client(
        curl_multi_client $client,
        string $apibaseurl,
        array $authheaders,
        int $requesttimeout,
        int $pollintervalsec,
        int $waitceilingsec
    ): void {
        $this->testclient = $client;
        $this->testapibaseurl = $apibaseurl;
        $this->testauthheaders = $authheaders;
        $this->testrequesttimeout = $requesttimeout;
        $this->testpollintervalsec = $pollintervalsec;
        $this->testwaitceilingsec = $waitceilingsec;
    }

    public function set_after_batch_hook(\Closure $hook): void {
        $this->afterbatchhook = $hook;
    }

    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

        $custom = $this->get_custom_data();
        $jobid = (int) ($custom->jobid ?? 0);
        if ($jobid <= 0) {
            mtrace("bulkdeploy_run: missing jobid in custom_data; aborting");
            return;
        }

        $repo = new job_repository();
        $job = $repo->get_job($jobid);
        if (!$job) {
            mtrace("bulkdeploy_run: job $jobid not found; aborting");
            return;
        }
        if (job_status::is_terminal($job->status)) {
            mtrace("bulkdeploy_run: job $jobid status={$job->status} (terminal); skipping");
            return;
        }

        try {
            $repo->set_job_status($jobid, job_status::RUNNING);
            mtrace("bulkdeploy_run: job $jobid started (batchsize={$job->batchsize})");

            $topomojo = $DB->get_record('topomojo', ['id' => $job->topomojoid], '*', MUST_EXIST);
            $launcher = $this->build_launcher($repo, $topomojo);

            $rows = $repo->get_active_user_rows($jobid);
            $userids = array_map(fn($r) => (int)$r->userid, $rows);
            $users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];

            $batch = [];
            $batchno = 0;
            foreach ($rows as $row) {
                $user = $users[$row->userid] ?? null;
                if (!$user) {
                    $repo->set_user_status($row->id, user_status::SKIPPED, 'user not found');
                    continue;
                }
                $batch[] = ['rowid' => (int) $row->id, 'user' => $user];

                if (count($batch) >= (int) $job->batchsize) {
                    $batchno++;
                    if (!$this->run_one_batch($repo, $launcher, $jobid, $batchno, $batch, $topomojo)) {
                        return;
                    }
                    $batch = [];
                }
            }
            if ($batch) {
                $batchno++;
                if (!$this->run_one_batch($repo, $launcher, $jobid, $batchno, $batch, $topomojo)) {
                    return;
                }
            }

            $current = $repo->get_job($jobid);
            if ($current->status === job_status::CANCELLING) {
                $repo->mark_pending_cancelled($jobid);
                $repo->set_job_status($jobid, job_status::CANCELLED);
                mtrace("bulkdeploy_run: job $jobid cancelled");
            } else if ($current->status === job_status::RUNNING) {
                $repo->set_job_status($jobid, job_status::COMPLETED);
                mtrace("bulkdeploy_run: job $jobid completed");
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            mtrace("bulkdeploy_run: job $jobid FAILED: $msg");
            mtrace($e->getTraceAsString());
            debugging("bulkdeploy_run job $jobid failed: $msg\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            $repo->set_job_status($jobid, job_status::FAILED, $msg);
        }
    }

    private function run_one_batch(
        job_repository $repo,
        launcher $launcher,
        int $jobid,
        int $batchno,
        array $batch,
        \stdClass $topomojo
    ): bool {
        $current = $repo->get_job($jobid);
        if ($current->status === job_status::CANCELLING) {
            $repo->mark_pending_cancelled($jobid);
            $repo->set_job_status($jobid, job_status::CANCELLED);
            mtrace("bulkdeploy_run: job $jobid cancelled before batch $batchno");
            return false;
        }

        mtrace("bulkdeploy_run: job $jobid batch $batchno (" . count($batch) . " users) start");
        $launcher->run_batch($jobid, $batch, $topomojo);
        mtrace("bulkdeploy_run: job $jobid batch $batchno end");

        if ($this->afterbatchhook) {
            ($this->afterbatchhook)();
        }
        return true;
    }

    private function build_launcher(job_repository $repo, \stdClass $topomojo): launcher {
        if ($this->testclient !== null) {
            return new launcher(
                $repo,
                $this->testclient,
                $this->testapibaseurl,
                $this->testauthheaders,
                $this->testrequesttimeout,
                $this->testpollintervalsec,
                $this->testwaitceilingsec
            );
        }
        $apibaseurl = (string) get_config('topomojo', 'topomojoapiurl');
        $requesttimeout = (int) (get_config('topomojo', 'deploytimeout') ?: 120);
        $waitceiling = (int) (get_config('topomojo', 'bulkdeploy_waittimeout') ?: 600);
        $authheaders = $this->resolve_auth_headers();
        return new launcher(
            $repo,
            new curl_multi_client(),
            $apibaseurl,
            $authheaders,
            $requesttimeout,
            5,
            $waitceiling
        );
    }

    /**
     * Resolves auth headers for the bulk launcher. Mirrors the auth resolution
     * in locallib.php's setup() so single-user and bulk flows authenticate the
     * same way:
     *  - If the static API key is enabled, send it as x-api-key.
     *  - Else if OAuth2 is enabled, acquire a system access token from the
     *    configured issuer and send it as a Bearer token. The token is fetched
     *    once at task start; for typical batch runs (~minutes) it stays valid
     *    for the whole job. If a longer job needs refresh, that's a follow-up.
     *  - Optionally adds x-api-client when enablemanagername is set.
     *
     * Throws \RuntimeException if no auth method is configured or the OAuth2
     * issuer/client can't be loaded — the task's top-level catch will record
     * the error message on the job and stop without retry.
     */
    private function resolve_auth_headers(): array {
        $headers = [];
        $managername = (string) get_config('topomojo', 'managername');
        if (get_config('topomojo', 'enablemanagername') && $managername !== '') {
            $headers[] = "x-api-client: $managername";
        }

        if (get_config('topomojo', 'enableapikey')) {
            $apikey = (string) get_config('topomojo', 'apikey');
            if ($apikey === '') {
                throw new \RuntimeException('TopoMojo API key is enabled but not set');
            }
            $headers[] = "x-api-key: $apikey";
            return $headers;
        }

        if (get_config('topomojo', 'enableoauth')) {
            $token = $this->fetch_oauth_bearer_token();
            $headers[] = "Authorization: Bearer $token";
            return $headers;
        }

        throw new \RuntimeException('No TopoMojo auth method configured (need enableapikey or enableoauth)');
    }

    /**
     * Acquires a system OAuth2 access token via the configured issuer.
     * Mirrors the path in locallib.php's setup(): get the issuer, then
     * \core\oauth2\api::get_system_oauth_client($issuer). The returned
     * client exposes get_accesstoken() once authenticated.
     */
    private function fetch_oauth_bearer_token(): string {
        $issuerid = (int) get_config('topomojo', 'issuerid');
        if ($issuerid <= 0) {
            throw new \RuntimeException('OAuth2 issuer not set');
        }
        $issuer = \core\oauth2\api::get_issuer($issuerid);
        if (!$issuer) {
            throw new \RuntimeException("Unable to load OAuth2 issuer with ID $issuerid");
        }
        $client = \core\oauth2\api::get_system_oauth_client($issuer);
        if (!$client) {
            throw new \RuntimeException('Failed to initialize system OAuth2 client');
        }
        $tokenobj = $client->get_accesstoken();
        if (!$tokenobj || empty($tokenobj->token)) {
            throw new \RuntimeException('OAuth2 client returned no access token');
        }
        return (string) $tokenobj->token;
    }
}
