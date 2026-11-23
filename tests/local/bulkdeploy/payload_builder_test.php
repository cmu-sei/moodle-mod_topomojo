<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for the payload_builder class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the payload builder.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\local\bulkdeploy\payload_builder::class)]
final class payload_builder_test extends \advanced_testcase {
    public function test_builds_payload_for_given_user(): void {
        $topomojo = (object) [
            'submissions' => 5,
            'duration'    => 3600, // seconds.
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
