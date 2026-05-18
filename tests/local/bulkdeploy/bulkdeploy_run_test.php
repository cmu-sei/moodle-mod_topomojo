<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/fake_curl_multi_client.php');

/**
 * @covers \mod_topomojo\task\bulkdeploy_run
 */
final class bulkdeploy_run_test extends \advanced_testcase {

    private function make_topomojo_record(): \stdClass {
        global $DB;
        $rec = (object) [
            'course' => 1,
            'name' => 't',
            'workspaceid' => 'ws-guid',
            'submissions' => 1,
            'duration' => 60,
            'grade' => 1,
            'variant' => 0,
            'introformat' => 0,
            'embed' => 1,
            'timeopen' => 0,
            'timeclose' => 0,
            'reviewattempt' => 0,
            'reviewcorrectness' => 0,
            'reviewmarks' => 0,
            'reviewspecificfeedback' => 0,
            'reviewgeneralfeedback' => 0,
            'reviewrightanswer' => 0,
            'reviewoverallfeedback' => 0,
            'reviewmanualcomment' => 0,
            'shuffleanswers' => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'importchallenge' => 0,
            'endlab' => 0,
            'attempts' => 0,
            'showcontentlicense' => 0,
            'isfeatured' => 0,
            'contentformat' => 0,
            'timecreated' => time(),
            'grademethod' => 0,
        ];
        $rec->id = $DB->insert_record('topomojo', $rec);
        return $rec;
    }

    private function make_users(int $n): array {
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $ids[] = $this->getDataGenerator()->create_user(['email' => "u$i@x.test"])->id;
        }
        return $ids;
    }

    private function task_with_fake(\mod_topomojo\local\bulkdeploy\fake_curl_multi_client $fake): \mod_topomojo\task\bulkdeploy_run {
        $task = new \mod_topomojo\task\bulkdeploy_run();
        $task->set_test_client($fake, 'https://api', [], 5, 0, 600);
        return $task;
    }

    public function test_processes_batches_of_configured_size_and_marks_completed(): void {
        $this->resetAfterTest();
        $tm = $this->make_topomojo_record();
        $repo = new job_repository();
        $userids = $this->make_users(3);
        $jobid = $repo->create_job($tm->id, 1, 1, 2, null, $userids);

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'g1'])));
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'g2'])));
        $fake->queue('GET', 'https://api/gamespace/g1', new curl_response(200, 0, json_encode(['isActive' => true, 'vms' => [1]])));
        $fake->queue('GET', 'https://api/gamespace/g2', new curl_response(200, 0, json_encode(['isActive' => true, 'vms' => [1]])));
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'g3'])));
        $fake->queue('GET', 'https://api/gamespace/g3', new curl_response(200, 0, json_encode(['isActive' => true, 'vms' => [1]])));

        $task = $this->task_with_fake($fake);
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->execute();

        $job = $repo->get_job($jobid);
        $this->assertSame(job_status::COMPLETED, $job->status);
        $counts = $repo->count_user_rows_by_status($jobid);
        $this->assertSame(3, $counts[user_status::READY] ?? 0);
    }

    public function test_cancellation_between_batches_stops_remaining_work(): void {
        $this->resetAfterTest();
        $tm = $this->make_topomojo_record();
        $repo = new job_repository();
        $userids = $this->make_users(4);
        $jobid = $repo->create_job($tm->id, 1, 1, 2, null, $userids);

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'g1'])));
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'g2'])));
        $fake->queue('GET', 'https://api/gamespace/g1', new curl_response(200, 0, json_encode(['isActive' => true, 'vms' => [1]])));
        $fake->queue('GET', 'https://api/gamespace/g2', new curl_response(200, 0, json_encode(['isActive' => true, 'vms' => [1]])));

        $task = $this->task_with_fake($fake);
        $task->set_after_batch_hook(function () use ($repo, $jobid) {
            $repo->set_job_status($jobid, job_status::CANCELLING);
        });
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->execute();

        $job = $repo->get_job($jobid);
        $this->assertSame(job_status::CANCELLED, $job->status);
        $counts = $repo->count_user_rows_by_status($jobid);
        $this->assertSame(2, $counts[user_status::READY] ?? 0);
        $this->assertSame(2, $counts[user_status::CANCELLED] ?? 0);
    }

    public function test_unhandled_exception_marks_failed_no_rethrow(): void {
        $this->resetAfterTest();
        $tm = $this->make_topomojo_record();
        $repo = new job_repository();
        $userids = $this->make_users(1);
        $jobid = $repo->create_job($tm->id, 1, 1, 5, null, $userids);

        $fake = new fake_curl_multi_client();

        $task = $this->task_with_fake($fake);
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->execute();

        $this->assertSame(job_status::FAILED, $repo->get_job($jobid)->status);
        $this->assertNotEmpty($repo->get_job($jobid)->errormessage);
    }

    public function test_terminal_job_status_is_skipped(): void {
        $this->resetAfterTest();
        $tm = $this->make_topomojo_record();
        $repo = new job_repository();
        $userids = $this->make_users(1);
        $jobid = $repo->create_job($tm->id, 1, 1, 5, null, $userids);
        $repo->set_job_status($jobid, job_status::CANCELLED);

        $fake = new fake_curl_multi_client();
        $task = $this->task_with_fake($fake);
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->execute();

        $this->assertSame(job_status::CANCELLED, $repo->get_job($jobid)->status);
        $this->assertSame([], $fake->log);
    }
}
