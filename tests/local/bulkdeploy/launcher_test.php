<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/fake_curl_multi_client.php');

/**
 * @covers \mod_topomojo\local\bulkdeploy\launcher
 */
final class launcher_test extends \advanced_testcase {
    private function topomojo(): \stdClass {
        return (object) [
            'workspaceid' => 'ws',
            'submissions' => 1,
            'duration' => 60,
            'grade' => 1,
            'variant' => 0,
        ];
    }

    private function user(string $email = 'a@b'): \stdClass {
        return (object) ['email' => $email, 'username' => 'u'];
    }

    private function gamespace_response(string $id, bool $active, bool $hasvms): curl_response {
        $body = json_encode([
            'id' => $id,
            'isActive' => $active,
            'vms' => $hasvms ? [(object)['id' => 'vm-1']] : [],
        ]);
        return new curl_response(200, 0, $body);
    }

    public function test_concurrent_launch_records_gamespaceid_and_marks_launched(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 2, null, [10, 11]);
        $rows = array_values($repo->get_user_rows($jobid));

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-1'])));
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-2'])));
        $fake->queue('GET', 'https://api/gamespace/gs-1', $this->gamespace_response('gs-1', true, true));
        $fake->queue('GET', 'https://api/gamespace/gs-2', $this->gamespace_response('gs-2', true, true));

        $launcher = new launcher($repo, $fake, 'https://api', ['Authorization: token'], 60, 1, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $rows[0]->id, 'user' => $this->user()],
            ['rowid' => $rows[1]->id, 'user' => $this->user()],
        ], $this->topomojo());

        $after = array_values($repo->get_user_rows($jobid));
        $this->assertSame(user_status::READY, $after[0]->status);
        $this->assertSame('gs-1', $after[0]->gamespaceid);
        $this->assertSame(user_status::READY, $after[1]->status);
        $this->assertSame('gs-2', $after[1]->gamespaceid);
    }

    public function test_user_with_no_email_is_skipped_before_api_call(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 1, null, [10]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 1, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => (object) ['email' => '', 'username' => 'x']],
        ], $this->topomojo());

        $after = $repo->get_user_rows($jobid);
        $r = reset($after);
        $this->assertSame(user_status::SKIPPED, $r->status);
        $this->assertStringContainsString('email', $r->errormessage);
        $this->assertSame([], $fake->log);
    }

    public function test_post_500_marks_failed(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 1, null, [10]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(500, 0, 'internal error'));

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 1, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $this->user()],
        ], $this->topomojo());

        $after = reset($repo->get_user_rows($jobid));
        $this->assertSame(user_status::FAILED, $after->status);
        $this->assertStringContainsString('HTTP 500', $after->errormessage);
    }

    public function test_post_curl_timeout_marks_failed(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 1, null, [10]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(0, 28, ''));

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 1, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $this->user()],
        ], $this->topomojo());

        $after = reset($repo->get_user_rows($jobid));
        $this->assertSame(user_status::FAILED, $after->status);
        $this->assertSame('timeout starting gamespace', $after->errormessage);
    }

    public function test_post_200_with_no_id_marks_failed(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 1, null, [10]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, '{"foo":"bar"}'));

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 1, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $this->user()],
        ], $this->topomojo());

        $after = reset($repo->get_user_rows($jobid));
        $this->assertSame(user_status::FAILED, $after->status);
        $this->assertSame('malformed response', $after->errormessage);
    }

    public function test_polling_transitions_launched_to_ready_after_vms_appear(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 1, null, [10]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-1'])));
        $fake->queue('GET', 'https://api/gamespace/gs-1', $this->gamespace_response('gs-1', true, false));
        $fake->queue('GET', 'https://api/gamespace/gs-1', $this->gamespace_response('gs-1', true, true));

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 0, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $this->user()],
        ], $this->topomojo());

        $after = reset($repo->get_user_rows($jobid));
        $this->assertSame(user_status::READY, $after->status);
    }

    public function test_externally_cancelled_row_is_dropped_and_not_overwritten(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 2, null, [10, 11]);
        $rows = array_values($repo->get_user_rows($jobid));
        $rowid_a = (int) $rows[0]->id;
        $rowid_b = (int) $rows[1]->id;

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-a'])));
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-b'])));
        // First poll: neither ready.
        $fake->queue('GET', 'https://api/gamespace/gs-a', $this->gamespace_response('gs-a', true, false));
        $fake->queue('GET', 'https://api/gamespace/gs-b', $this->gamespace_response('gs-b', true, false));
        // Second poll: only gs-b should be polled (gs-a was cancelled). Ready this time.
        $fake->queue('GET', 'https://api/gamespace/gs-b', $this->gamespace_response('gs-b', true, true));

        // Subclass to inject a cancellation between poll cycles via sleep_seconds().
        $launcher = new class($repo, $fake, 'https://api', [], 60, 1, 600, $rowid_a) extends launcher {
            public function __construct(
                job_repository $repo,
                curl_multi_client $client,
                string $apibaseurl,
                array $authheaders,
                int $requesttimeout,
                int $pollintervalsec,
                int $waitceilingsec,
                private int $cancelrowid
            ) {
                parent::__construct(
                    $repo, $client, $apibaseurl, $authheaders,
                    $requesttimeout, $pollintervalsec, $waitceilingsec
                );
            }
            protected function sleep_seconds(int $seconds): void {
                global $DB;
                $DB->update_record('topomojo_bulkdeploy_user', (object) [
                    'id' => $this->cancelrowid,
                    'status' => user_status::CANCELLED,
                ]);
            }
        };

        $launcher->run_batch($jobid, [
            ['rowid' => $rowid_a, 'user' => $this->user('a@b')],
            ['rowid' => $rowid_b, 'user' => $this->user('c@d')],
        ], $this->topomojo());

        $after = $repo->get_user_rows($jobid);
        $this->assertSame(user_status::CANCELLED, $after[$rowid_a]->status,
            'externally cancelled row must keep CANCELLED status');
        $this->assertSame(user_status::READY, $after[$rowid_b]->status);

        // gs-a should have been polled exactly once (first cycle only).
        $gets_a = array_filter($fake->log, fn($r) => $r['url'] === 'https://api/gamespace/gs-a');
        $this->assertCount(1, $gets_a, 'cancelled gamespace must not be polled after cancellation');
    }

    public function test_wait_ceiling_marks_failed_when_never_ready(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 1, null, [10]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-1'])));
        for ($i = 0; $i < 50; $i++) {
            $fake->queue('GET', 'https://api/gamespace/gs-1', $this->gamespace_response('gs-1', false, false));
        }

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 0, 0);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $this->user()],
        ], $this->topomojo());

        $after = reset($repo->get_user_rows($jobid));
        $this->assertSame(user_status::FAILED, $after->status);
        $this->assertStringContainsString('timeout waiting for VMs', $after->errormessage);
    }
}
