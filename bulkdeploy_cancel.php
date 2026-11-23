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

require_once(__DIR__ . '/../../config.php');

use mod_topomojo\local\bulkdeploy\job_repository;
use mod_topomojo\local\bulkdeploy\job_status;

require_sesskey();
$jobid = required_param('jobid', PARAM_INT);

$repo = new job_repository();
$job = $repo->get_job($jobid);
if (!$job) {
    throw new moodle_exception('invaliddata');
}
$cm = get_coursemodule_from_instance('topomojo', $job->topomojoid, 0, false, MUST_EXIST);
[, $cm] = get_course_and_cm_from_cmid($cm->id, 'topomojo');
$context = context_module::instance($cm->id);
require_login($cm->course, false, $cm);
require_capability('mod/topomojo:bulkdeploy', $context);

global $USER;

if ($job->status === job_status::QUEUED) {
    $repo->set_job_cancelled_by((int)$jobid, (int)$USER->id);
    $repo->mark_pending_cancelled((int)$jobid);
    $repo->set_job_status((int)$jobid, job_status::CANCELLED);
} else if ($job->status === job_status::RUNNING) {
    $repo->set_job_cancelled_by((int)$jobid, (int)$USER->id);
    $repo->set_job_status((int)$jobid, job_status::CANCELLING);
}

redirect(new moodle_url('/mod/topomojo/status.php', ['jobid' => $jobid]));
