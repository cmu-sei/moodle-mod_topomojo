<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/fake_curl_multi_client.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\local\bulkdeploy\launcher::class)]
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

    /**
     * A batch entry's user stub.
     *
     * The id stays 0 deliberately: launcher::wait_phase() only calls create_attempt_for_user()
     * for a truthy id, so these tests exercise the polling transitions without also reaching
     * attempt creation, which needs a real topomojo record. Setting it at all keeps the launcher
     * from reading an undefined property.
     *
     * @param string $email
     * @return \stdClass
     */
    private function user(string $email = 'a@b'): \stdClass {
        return (object) ['id' => 0, 'email' => $email, 'username' => 'u'];
    }

    /**
     * A poll response for one gamespace.
     *
     * @param string $id gamespace id
     * @param bool $active whether TopoMojo reports the gamespace as running
     * @param bool $hasvms whether TopoMojo reports any VMs yet
     * @param array $extra further fields to merge in, e.g. variant or expirationTime
     * @return curl_response
     */
    private function gamespace_response(string $id, bool $active, bool $hasvms, array $extra = []): curl_response {
        $body = json_encode($extra + [
            'id' => $id,
            'isActive' => $active,
            'vms' => $hasvms ? [(object)['id' => 'vm-1']] : [],
        ]);
        return new curl_response(200, 0, $body);
    }

    /**
     * A real topomojo activity in a real course, plus an enrolled student to deploy for.
     *
     * The tests above get by with stubs because they never reach attempt creation. These ones do,
     * and building a question usage needs a course module to take a context from.
     *
     * @return array [\stdClass $topomojo, \stdClass $student]
     */
    private function activity_and_student(): array {
        global $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('topomojo', [
            'course' => $course->id,
            'workspaceid' => 'ws',
            'variant' => 0,
            'duration' => 60,
            'submissions' => 1,
            'grade' => 100,
        ]);
        $topomojo = $DB->get_record('topomojo', ['id' => $module->id], '*', MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        return [$topomojo, $student];
    }

    /**
     * Attaches one question to a variant of an activity.
     *
     * shortanswer rather than mojomatch: qtype_mojomatch ships no get_..._form_data_... helper, so
     * core_question_generator cannot build one, and shortanswer is the type it is modelled on and
     * loads through the same question_bank path. Variant membership is not a property of the
     * question anyway - both queries involved read it from the qtype_mojomatch_options row below.
     *
     * @param \stdClass $topomojo activity record
     * @param int $variant variant number, 1-based
     * @return int the question id
     */
    private function add_variant_question(\stdClass $topomojo, int $variant): int {
        global $DB;
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id, $topomojo->course, false, MUST_EXIST);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category([
            'contextid' => \context_module::instance($cm->id)->id,
        ]);
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);

        $DB->insert_record('qtype_mojomatch_options', (object) [
            'questionid' => $question->id,
            'usecase' => 0,
            'matchtype' => 0,
            'variant' => $variant,
            'workspaceid' => $topomojo->workspaceid,
            'transforms' => 0,
            'qorder' => 1,
        ]);
        $DB->insert_record('topomojo_questions', (object) [
            'topomojoid' => $topomojo->id,
            'questionid' => $question->id,
            'points' => 1,
        ]);

        return (int) $question->id;
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

        $afterrows = $repo->get_user_rows($jobid);
        $after = reset($afterrows);
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

        $afterrows = $repo->get_user_rows($jobid);
        $after = reset($afterrows);
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

        $afterrows = $repo->get_user_rows($jobid);
        $after = reset($afterrows);
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

        $afterrows = $repo->get_user_rows($jobid);
        $after = reset($afterrows);
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

        $afterrows = $repo->get_user_rows($jobid);
        $after = reset($afterrows);
        $this->assertSame(user_status::FAILED, $after->status);
        $this->assertStringContainsString('timeout waiting for VMs', $after->errormessage);
    }

    public function test_attempt_for_a_variant_with_questions_gets_a_question_usage(): void {
        global $DB;
        $this->resetAfterTest();
        [$topomojo, $student] = $this->activity_and_student();
        $this->add_variant_question($topomojo, 1);

        $repo = new job_repository();
        $jobid = $repo->create_job($topomojo->id, $topomojo->course, 2, 1, null, [$student->id]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-1'])));
        $fake->queue('GET', 'https://api/gamespace/gs-1', $this->gamespace_response('gs-1', true, true, [
            // TopoMojo reports the variant 0-based; the column is 1-based.
            'variant' => 0,
            'expirationTime' => '2030-01-01T00:00:00Z',
        ]));

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 0, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $student],
        ], $topomojo);
        // The attempt and question-engine code paths are chatty at DEBUG_DEVELOPER.
        $this->resetDebugging();

        $attempt = $DB->get_record('topomojo_attempts', [
            'topomojoid' => $topomojo->id,
            'userid' => $student->id,
        ]);
        $this->assertNotEmpty($attempt, 'bulk deploy must create an attempt for a ready gamespace');

        // The point of the whole exercise: without a question usage, challenge.php shows the
        // student "There are no challenge questions to review" and grading finds nothing to mark.
        $this->assertGreaterThan(
            0,
            (int) $attempt->questionusageid,
            'a bulk-deployed attempt must have a question usage'
        );
        $quba = \question_engine::load_questions_usage_by_activity((int) $attempt->questionusageid);
        $this->assertCount(1, $quba->get_slots(), 'the variant question must be in the usage');
        $this->assertSame($topomojo->preferredbehaviour, $quba->get_preferred_behaviour());
        $this->assertNotEmpty($attempt->layout, 'layout must name the slots the usage created');

        // These moved when attempt creation stopped being a hand-built insert.
        $this->assertSame(\mod_topomojo\topomojo_attempt::INPROGRESS, $attempt->state);
        $this->assertSame(1, (int) $attempt->variant);
        $this->assertSame('gs-1', $attempt->eventid);
        $this->assertSame(0, (int) $attempt->preview);
        $this->assertSame(strtotime('2030-01-01T00:00:00Z'), (int) $attempt->endtime);
    }

    public function test_attempt_is_created_when_the_variant_has_no_questions(): void {
        global $DB;
        $this->resetAfterTest();
        [$topomojo, $student] = $this->activity_and_student();

        $repo = new job_repository();
        $jobid = $repo->create_job($topomojo->id, $topomojo->course, 2, 1, null, [$student->id]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-1'])));
        $fake->queue(
            'GET',
            'https://api/gamespace/gs-1',
            $this->gamespace_response('gs-1', true, true, ['variant' => 0])
        );

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 0, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $student],
        ], $topomojo);
        // Includes the failed import attempt: no API is configured here, so reaching out to
        // TopoMojo for the missing questions fails, which is exactly what must not be fatal.
        $this->resetDebugging();

        $attempt = $DB->get_record('topomojo_attempts', [
            'topomojoid' => $topomojo->id,
            'userid' => $student->id,
        ]);
        $this->assertNotEmpty($attempt, 'a lab with no questions is still a usable lab');
        $this->assertSame(0, (int) $attempt->questionusageid, 'an empty usage must not be saved');
        // An empty string rather than null is what distinguishes the two paths: only
        // topomojo_attempt sets the column at all.
        $this->assertSame('', $attempt->layout);
        $this->assertSame(user_status::READY, $repo->get_user_rows($jobid)[$row->id]->status);
    }

    public function test_each_user_in_a_batch_gets_their_own_question_usage(): void {
        global $DB;
        $this->resetAfterTest();
        [$topomojo, $first] = $this->activity_and_student();
        $second = $this->getDataGenerator()->create_and_enrol(
            $DB->get_record('course', ['id' => $topomojo->course], '*', MUST_EXIST),
            'student'
        );
        $this->add_variant_question($topomojo, 1);

        $repo = new job_repository();
        $jobid = $repo->create_job($topomojo->id, $topomojo->course, 2, 2, null, [$first->id, $second->id]);
        $rows = array_values($repo->get_user_rows($jobid));

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-1'])));
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-2'])));
        $fake->queue(
            'GET',
            'https://api/gamespace/gs-1',
            $this->gamespace_response('gs-1', true, true, ['variant' => 0])
        );
        $fake->queue(
            'GET',
            'https://api/gamespace/gs-2',
            $this->gamespace_response('gs-2', true, true, ['variant' => 0])
        );

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 0, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $rows[0]->id, 'user' => $first],
            ['rowid' => $rows[1]->id, 'user' => $second],
        ], $topomojo);
        $this->resetDebugging();

        $usageids = $DB->get_records_menu(
            'topomojo_attempts',
            ['topomojoid' => $topomojo->id],
            'userid ASC',
            'userid, questionusageid'
        );
        $this->assertCount(2, $usageids, 'every deployed user needs their own attempt');
        foreach ($usageids as $userid => $usageid) {
            $this->assertGreaterThan(0, (int) $usageid, "user $userid got no question usage");
        }
        $this->assertCount(2, array_unique($usageids), 'attempts must not share a question usage');
    }

    public function test_attempt_is_still_created_when_the_question_usage_cannot_be_built(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();

        // An activity id that resolves to no course module, so the question manager cannot be
        // built at all. The VMs are already running by the time attempt creation happens, so the
        // user must still end up with an attempt - no questions, but a usable lab, which is where
        // every bulk-deployed attempt used to end up.
        $topomojo = $this->topomojo();
        $topomojo->id = 999999;
        $topomojo->course = 999999;

        $repo = new job_repository();
        $jobid = $repo->create_job($topomojo->id, $topomojo->course, 2, 1, null, [$student->id]);
        $row = array_values($repo->get_user_rows($jobid))[0];

        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://api/gamespace', new curl_response(200, 0, json_encode(['id' => 'gs-1'])));
        $fake->queue('GET', 'https://api/gamespace/gs-1', $this->gamespace_response('gs-1', true, true));

        $launcher = new launcher($repo, $fake, 'https://api', [], 60, 0, 600);
        $launcher->run_batch($jobid, [
            ['rowid' => $row->id, 'user' => $student],
        ], $topomojo);

        $messages = array_map(fn($m) => $m->message, $this->getDebuggingMessages());
        $this->resetDebugging();
        $this->assertNotEmpty(
            array_filter($messages, fn($m) => str_contains($m, 'could not load the question manager')),
            'the fallback must say why it fell back'
        );

        $attempt = $DB->get_record('topomojo_attempts', ['topomojoid' => $topomojo->id]);
        $this->assertNotEmpty($attempt, 'a failure to attach questions must not cost the attempt');
        $this->assertSame((int) $student->id, (int) $attempt->userid);
        $this->assertSame('gs-1', $attempt->eventid);
        $this->assertSame(0, (int) $attempt->questionusageid);
        // Nothing set the column, which is what tells this apart from the path above.
        $this->assertNull($attempt->layout);
        $this->assertSame(user_status::READY, $repo->get_user_rows($jobid)[$row->id]->status);
    }
}
