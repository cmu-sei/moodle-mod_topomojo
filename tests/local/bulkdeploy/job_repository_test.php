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
 * Tests for the job_repository class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\local\bulkdeploy\job_repository::class)]
final class job_repository_test extends \advanced_testcase {
    public function test_create_job_with_users_persists_rows(): void {
        $this->resetAfterTest();
        $repo = new job_repository();

        $jobid = $repo->create_job(
            topomojoid: 7,
            courseid: 9,
            initiatorid: 11,
            batchsize: 5,
            rolefilter: '5,6',
            userids: [101, 102, 103],
        );

        $job = $repo->get_job($jobid);
        $this->assertSame(7, (int)$job->topomojoid);
        $this->assertSame(9, (int)$job->courseid);
        $this->assertSame(11, (int)$job->initiatorid);
        $this->assertSame(5, (int)$job->batchsize);
        $this->assertSame('5,6', $job->rolefilter);
        $this->assertSame(3, (int)$job->totalusers);
        $this->assertSame(job_status::QUEUED, $job->status);

        $rows = $repo->get_user_rows($jobid);
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame(user_status::PENDING, $row->status);
            $this->assertNull($row->gamespaceid);
        }
    }

    public function test_set_job_status_updates_status_and_timecompleted(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 5, null, [1, 2]);

        $repo->set_job_status($jobid, job_status::RUNNING);
        $this->assertSame(job_status::RUNNING, $repo->get_job($jobid)->status);
        $this->assertNotNull($repo->get_job($jobid)->timestarted);

        $repo->set_job_status($jobid, job_status::COMPLETED);
        $this->assertSame(job_status::COMPLETED, $repo->get_job($jobid)->status);
        $this->assertNotNull($repo->get_job($jobid)->timecompleted);
    }

    public function test_get_active_user_rows_returns_only_non_terminal(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 5, null, [1, 2, 3, 4]);
        $rows = array_values($repo->get_user_rows($jobid));
        $repo->set_user_status($rows[0]->id, user_status::READY);
        $repo->set_user_status($rows[1]->id, user_status::FAILED, 'boom');
        $repo->set_user_status($rows[2]->id, user_status::LAUNCHED);
        // rows[3] stays pending

        $active = array_values($repo->get_active_user_rows($jobid));

        $this->assertCount(2, $active);
        $statuses = array_column($active, 'status');
        sort($statuses);
        $this->assertSame([user_status::LAUNCHED, user_status::PENDING], $statuses);
    }

    public function test_mark_pending_cancelled_only_touches_pending(): void {
        $this->resetAfterTest();
        $repo = new job_repository();
        $jobid = $repo->create_job(1, 1, 1, 5, null, [1, 2, 3]);
        $rows = array_values($repo->get_user_rows($jobid));
        $repo->set_user_status($rows[0]->id, user_status::READY);
        $repo->set_user_status($rows[1]->id, user_status::LAUNCHED);
        // rows[2] still pending

        $repo->mark_pending_cancelled($jobid);

        $after = $repo->get_user_rows($jobid);
        $byid = [];
        foreach ($after as $r) {
            $byid[$r->id] = $r->status;
        }
        $this->assertSame(user_status::READY, $byid[$rows[0]->id]);
        $this->assertSame(user_status::LAUNCHED, $byid[$rows[1]->id]);
        $this->assertSame(user_status::CANCELLED, $byid[$rows[2]->id]);
    }
}
