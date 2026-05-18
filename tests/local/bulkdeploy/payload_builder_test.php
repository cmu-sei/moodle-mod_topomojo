<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \mod_topomojo\local\bulkdeploy\payload_builder
 */
final class payload_builder_test extends \advanced_testcase {
    public function test_builds_payload_for_given_user(): void {
        $topomojo = (object) [
            'submissions' => 5,
            'duration'    => 3600,  // seconds.
            'grade'       => 100,
            'variant'     => 2,
        ];
        $user = (object) [
            'email'    => 'alice@example.org',
            'username' => 'alice',
        ];

        $payload = payload_builder::build('ws-guid-123', $topomojo, $user);

        $this->assertSame('ws-guid-123', $payload->resourceId);
        $this->assertTrue($payload->startGamespace);
        $this->assertFalse($payload->allowPreview);
        $this->assertFalse($payload->allowReset);
        $this->assertSame(5, $payload->maxAttempts);
        $this->assertSame(60, $payload->maxMinutes);  // 3600 / 60.
        $this->assertSame(100, $payload->points);
        $this->assertSame(2, $payload->variant);
        $this->assertCount(1, $payload->players);
        $this->assertSame('alice', $payload->players[0]->subjectId);  // local part of email.
        $this->assertSame('alice', $payload->players[0]->subjectName);  // username.
    }

    public function test_email_with_no_at_sign_uses_full_email_as_subject_id(): void {
        $topomojo = (object) ['submissions' => 1, 'duration' => 60, 'grade' => 1, 'variant' => 0];
        $user = (object) ['email' => 'no-at-sign', 'username' => 'bob'];

        $payload = payload_builder::build('ws', $topomojo, $user);

        $this->assertSame('no-at-sign', $payload->players[0]->subjectId);
    }
}
