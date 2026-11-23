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

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\local\bulkdeploy\management_repository::class)]
final class management_repository_test extends \advanced_testcase {
    public function test_get_active_jobs_excludes_future_scheduled_jobs(): void {
        $this->resetAfterTest();

        $jobrepo = new job_repository();
        $immediateid = $jobrepo->create_job(7, 9, 11, 5, null, [101]);
        $scheduledid = $jobrepo->create_job(7, 9, 11, 5, null, [102], time() + 3600);
        $dueid = $jobrepo->create_job(7, 9, 11, 5, null, [103], time() - 60);
        $jobrepo->create_job(8, 9, 11, 5, null, [104]);

        $repo = new management_repository();
        $activejobs = $repo->get_active_jobs(7);
        $scheduledjobs = $repo->get_scheduled_jobs(7);

        $this->assertArrayHasKey($immediateid, $activejobs);
        $this->assertArrayHasKey($dueid, $activejobs);
        $this->assertArrayNotHasKey($scheduledid, $activejobs);
        $this->assertArrayHasKey($scheduledid, $scheduledjobs);
    }
}
