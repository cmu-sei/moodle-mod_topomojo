<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \mod_topomojo\local\bulkdeploy\management_repository
 */
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
