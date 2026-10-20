<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
TopoMojo Plugin for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO,
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full
terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.
Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1175
*/

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/lib.php');

/**
 * Unit tests for finding and ending a user's running gamespaces.
 *
 * @package    mod_topomojo
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('topomojo_live_gamespace_ids')]
#[\PHPUnit\Framework\Attributes\CoversFunction('topomojo_end_gamespaces')]
final class end_gamespaces_test extends \advanced_testcase {
    /** @var \stdClass The activity under test. */
    protected $topomojo;

    /** @var \stdClass The student whose gamespaces are looked up. */
    protected $student;

    /** @var \stdClass Another student in the same activity. */
    protected $other;

    /**
     * Build an activity with two students.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('topomojoapiurl', 'https://topomojo.invalid/api', 'topomojo');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->topomojo = $generator->create_module('topomojo', ['course' => $course->id]);
        $this->student = $generator->create_user();
        $this->other = $generator->create_user();
    }

    /**
     * Add a bulk-deploy row for a user to a fresh job in the activity.
     *
     * @param \stdClass $user The user the gamespace was deployed for.
     * @param string $gamespaceid The gamespace GUID.
     * @param string $status The row status.
     */
    protected function add_bulkdeploy(\stdClass $user, string $gamespaceid, string $status): void {
        global $DB;

        $jobid = $DB->insert_record('topomojo_bulkdeploy_job', (object)[
            'topomojoid' => $this->topomojo->id,
            'courseid' => $this->topomojo->course,
            'initiatorid' => $user->id,
            'batchsize' => 5,
            'totalusers' => 1,
            'status' => 'completed',
            'timecreated' => time(),
        ]);
        $DB->insert_record('topomojo_bulkdeploy_user', (object)[
            'jobid' => $jobid,
            'userid' => $user->id,
            'gamespaceid' => $gamespaceid,
            'status' => $status,
            'timestarted' => time(),
        ]);
    }

    /**
     * A stub TopoMojo client that records each request and answers with a fixed status or throws.
     *
     * @param int|null $httpcode Status to report, or null to throw from post().
     * @return object
     */
    protected function stub_client(?int $httpcode): object {
        return new class ($httpcode) {
            /** @var array Last response info, read by stop_event(). */
            public $info = [];

            /** @var array URLs passed to post(). */
            public $urls = [];

            /**
             * Constructor.
             *
             * @param int|null $httpcode Status to report, or null to throw.
             */
            public function __construct(
                /** @var int|null Status to report, or null to throw. */
                private ?int $httpcode
            ) {
            }

            /**
             * Record the request and answer it.
             *
             * @param string $url The request URL.
             * @return string
             */
            public function post($url) {
                $this->urls[] = $url;
                if ($this->httpcode === null) {
                    throw new \moodle_exception('generalexceptionmessage', 'error', '', 'TopoMojo unreachable');
                }
                $this->info = ['http_code' => $this->httpcode];
                return '{}';
            }
        };
    }

    /**
     * Only the user's unfinished attempts and launched or ready deployments count.
     */
    public function test_live_gamespace_ids_limited_to_users(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $generator->create_attempt($this->topomojo, $this->student, ['eventid' => 'live']);
        $generator->create_attempt($this->topomojo, $this->student, [
            'eventid' => 'done',
            'state' => topomojo_attempt::FINISHED,
        ]);
        $generator->create_attempt($this->topomojo, $this->other, ['eventid' => 'other-live']);
        $this->add_bulkdeploy($this->student, 'live', 'ready');
        $this->add_bulkdeploy($this->student, 'launching', 'launched');
        $this->add_bulkdeploy($this->student, 'failed', 'failed');

        $ids = topomojo_live_gamespace_ids($this->topomojo->id, [$this->student->id]);
        sort($ids);
        $this->assertSame(['launching', 'live', 'live'], $ids);
        $this->assertSame([], topomojo_live_gamespace_ids($this->topomojo->id, []));
    }

    /**
     * Each gamespace is completed once, and empty ids are dropped.
     */
    public function test_end_gamespaces_completes_each_once(): void {
        $client = $this->stub_client(200);

        topomojo_end_gamespaces(['gs-1', 'gs-1', '', 'gs-2'], $client);

        $this->assertSame([
            'https://topomojo.invalid/api/gamespace/gs-1/complete',
            'https://topomojo.invalid/api/gamespace/gs-2/complete',
        ], $client->urls);
        $this->assertDebuggingNotCalled();
    }

    /**
     * An exception from the client is logged and the remaining gamespaces are still tried.
     */
    public function test_end_gamespaces_survives_client_exception(): void {
        $client = $this->stub_client(null);

        topomojo_end_gamespaces(['gs-1', 'gs-2'], $client);

        $this->assertCount(2, $client->urls);
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * With no API configured, setup() and the helper each log once and nothing throws.
     */
    public function test_end_gamespaces_without_client(): void {
        topomojo_end_gamespaces(['gs-1']);
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * Nothing to end means no client is resolved and nothing is logged.
     */
    public function test_end_gamespaces_skips_empty_list(): void {
        topomojo_end_gamespaces(['', null, '  ']);
        $this->assertDebuggingNotCalled();
    }
}
