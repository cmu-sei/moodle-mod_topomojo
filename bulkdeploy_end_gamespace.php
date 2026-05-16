<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\local\bulkdeploy\job_repository;

$jobid = required_param('jobid', PARAM_INT);
$rowid = required_param('rowid', PARAM_INT);
$gamespaceid = required_param('gamespaceid', PARAM_TEXT);
require_sesskey();

$repo = new job_repository();
$job = $repo->get_job($jobid);
if (!$job) {
    throw new moodle_exception('invaliddata');
}

$cm = get_coursemodule_from_instance('topomojo', $job->topomojoid, 0, false, MUST_EXIST);
[$course, $cm] = get_course_and_cm_from_cmid($cm->id, 'topomojo');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

// Get the row to verify it belongs to this job
$row = $DB->get_record('topomojo_bulkdeploy_user', ['id' => $rowid, 'jobid' => $jobid], '*', MUST_EXIST);

// Initialize TopoMojo auth client
$auth = setup();

if (!$auth) {
    \core\notification::error('TopoMojo authentication not configured');
    redirect(new moodle_url('/mod/topomojo/status.php', ['jobid' => $jobid]));
}

// Stop the gamespace
try {
    stop_event($auth, $gamespaceid);

    // Update the row to clear gamespace ID and mark as ended
    $DB->set_field('topomojo_bulkdeploy_user', 'gamespaceid', '', ['id' => $rowid]);
    $DB->set_field('topomojo_bulkdeploy_user', 'errormessage', 'Manually ended', ['id' => $rowid]);

    \core\notification::success('Gamespace ended successfully');
} catch (Exception $e) {
    \core\notification::error('Failed to end gamespace: ' . $e->getMessage());
}

// Redirect back to status page
redirect(new moodle_url('/mod/topomojo/status.php', ['jobid' => $jobid]));
