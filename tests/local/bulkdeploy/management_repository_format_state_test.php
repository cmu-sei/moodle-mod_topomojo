<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \mod_topomojo\local\bulkdeploy\management_repository::format_user_state
 */
final class management_repository_format_state_test extends \advanced_testcase {

    public function test_no_deploy_no_attempt_returns_none(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $row = (object) [
            'userid' => 1,
            'attemptid' => null,
            'attemptstate' => null,
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => null,
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('None', $state['status_label']);
        $this->assertSame('none', $state['status_class']);
        $this->assertSame('─', $state['gamespace_text']);
        $this->assertSame('─', $state['scheduled_text']);
        $this->assertNull($state['tooltip_html']);
        $this->assertSame('─', $state['action_html']);
    }

    public function test_scheduled_pending_in_future_shows_scheduled(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $futurets = time() + 3600;
        $row = (object) [
            'userid' => 1,
            'attemptid' => null,
            'attemptstate' => null,
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => 'pending',
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => $futurets,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Scheduled', $state['status_label']);
        $this->assertSame('scheduled', $state['status_class']);
        $expected = userdate($futurets, get_string('strftimedatetime', 'langconfig'));
        $this->assertSame($expected, $state['scheduled_text']);
    }

    public function test_pending_deploy_overrides_attempt_state(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $row = (object) [
            'userid' => 1,
            'attemptid' => 99,
            'attemptstate' => '10',
            'attemptgamespaceid' => 'gs-attempt',
            'attempttimestart' => time() - 100,
            'attemptendtime' => time() + 100,
            'deploystatus' => 'pending',
            'deploygamespaceid' => 'gs-deploy',
            'deployerror' => null,
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Pending', $state['status_label']);
        $this->assertSame('pending', $state['status_class']);
        $this->assertSame('gs-deploy', $state['gamespace_text']);
    }

    public function test_launched_deploy_overrides_attempt_state(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $row = (object) [
            'userid' => 1,
            'attemptid' => 99,
            'attemptstate' => '10',
            'attemptgamespaceid' => 'gs-attempt',
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => 'launched',
            'deploygamespaceid' => 'gs-deploy',
            'deployerror' => null,
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Launched', $state['status_label']);
        $this->assertSame('launched', $state['status_class']);
    }

    public function test_attempt_state_active_with_times_renders_tooltip(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $start = 1700000000;
        $end = 1700003600;
        $row = (object) [
            'userid' => 1,
            'attemptid' => 7,
            'attemptstate' => '10',
            'attemptgamespaceid' => 'gs-attempt',
            'attempttimestart' => $start,
            'attemptendtime' => $end,
            'deploystatus' => null,
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Active', $state['status_label']);
        $this->assertNotNull($state['tooltip_html']);
        $this->assertStringContainsString('Active ⓘ', $state['tooltip_html']);
        $datefmt = get_string('strftimedatetime', 'langconfig');
        $this->assertStringContainsString(
            s(get_string('status_active_at', 'topomojo', userdate($start, $datefmt))),
            $state['tooltip_html']
        );
    }

    public function test_attempt_state_finished_no_tooltip(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $row = (object) [
            'userid' => 1,
            'attemptid' => 7,
            'attemptstate' => '30',
            'attemptgamespaceid' => 'gs',
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => null,
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Finished', $state['status_label']);
        $this->assertSame('finished', $state['status_class']);
        $this->assertNull($state['tooltip_html']);
    }

    public function test_failed_deploy_renders_error_tooltip(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $row = (object) [
            'userid' => 1,
            'attemptid' => null,
            'attemptstate' => null,
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => 'failed',
            'deploygamespaceid' => null,
            'deployerror' => 'Topomojo unreachable: HTTP 502',
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Failed', $state['status_label']);
        $this->assertNotNull($state['tooltip_html']);
        $this->assertStringContainsString('Failed ⓘ', $state['tooltip_html']);
        $this->assertStringContainsString('Topomojo unreachable: HTTP 502', $state['tooltip_html']);
    }

    public function test_other_deploy_status_falls_through(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $row = (object) [
            'userid' => 1,
            'attemptid' => null,
            'attemptstate' => null,
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => 'cancelled',
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Cancelled', $state['status_label']);
        $this->assertSame('cancelled', $state['status_class']);
    }

    public function test_ready_deploy_status_falls_through(): void {
        $this->resetAfterTest();
        $repo = new management_repository();

        $row = (object) [
            'userid' => 1,
            'attemptid' => null,
            'attemptstate' => null,
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => 'ready',
            'deploygamespaceid' => 'gs-ready',
            'deployerror' => null,
            'scheduledfor' => null,
        ];
        $state = $repo->format_user_state($row);

        $this->assertSame('Ready', $state['status_label']);
        $this->assertSame('ready', $state['status_class']);
        $this->assertSame('gs-ready', $state['gamespace_text']);
        $this->assertNull($state['tooltip_html']);
    }

    public function test_active_attempt_with_questions_links_to_challenge(): void {
        $this->resetAfterTest();
        $repo = new management_repository();
        $row = (object) [
            'userid' => 1,
            'attemptid' => 42,
            'attemptstate' => '10',
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => null,
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => null,
        ];

        $state = $repo->format_user_state($row, true);

        $this->assertStringContainsString('challenge.php', $state['action_html']);
        $this->assertStringContainsString('attemptid=42', $state['action_html']);
        $this->assertStringContainsString('btn-outline-primary', $state['action_html']);
    }

    public function test_finished_attempt_with_questions_links_to_viewattempt(): void {
        $this->resetAfterTest();
        $repo = new management_repository();
        $row = (object) [
            'userid' => 1,
            'attemptid' => 42,
            'attemptstate' => '30',
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => null,
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => null,
        ];

        $state = $repo->format_user_state($row, true);

        $this->assertStringContainsString('viewattempt.php', $state['action_html']);
        $this->assertStringContainsString('a=42', $state['action_html']);
        $this->assertStringContainsString('action=view', $state['action_html']);
    }

    public function test_attempt_without_questions_returns_dash(): void {
        $this->resetAfterTest();
        $repo = new management_repository();
        $row = (object) [
            'userid' => 1,
            'attemptid' => 42,
            'attemptstate' => '10',
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => null,
            'deploygamespaceid' => null,
            'deployerror' => null,
            'scheduledfor' => null,
        ];

        $state = $repo->format_user_state($row, false);

        $this->assertSame('─', $state['action_html']);
    }

    public function test_no_attempt_returns_dash(): void {
        $this->resetAfterTest();
        $repo = new management_repository();
        $row = (object) [
            'userid' => 1,
            'attemptid' => null,
            'attemptstate' => null,
            'attemptgamespaceid' => null,
            'attempttimestart' => null,
            'attemptendtime' => null,
            'deploystatus' => 'failed',
            'deploygamespaceid' => null,
            'deployerror' => 'oops',
            'scheduledfor' => null,
        ];

        $state = $repo->format_user_state($row, false);

        $this->assertSame('─', $state['action_html']);
    }
}
