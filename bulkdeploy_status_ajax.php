<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

use mod_topomojo\local\bulkdeploy\job_repository;

$jobid = required_param('jobid', PARAM_INT);
require_sesskey();

$repo = new job_repository();
$job = $repo->get_job($jobid);
if (!$job) {
    throw new moodle_exception('invaliddata');
}

$cm = get_coursemodule_from_instance('topomojo', $job->topomojoid, 0, false, MUST_EXIST);
[, $cm] = get_course_and_cm_from_cmid($cm->id, 'topomojo');
$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

$rows = $repo->get_user_rows($jobid);
$rowsout = [];
foreach ($rows as $r) {
    $rowsout[] = [
        'rowid'        => (int) $r->id,
        'status'       => $r->status,
        'gamespaceid'  => (string) ($r->gamespaceid ?? ''),
        'errormessage' => (string) ($r->errormessage ?? ''),
    ];
}

echo json_encode([
    'status'      => $job->status,
    'counts'      => $repo->count_user_rows_by_status($jobid),
    'totalusers'  => (int) $job->totalusers,
    'rows'        => $rowsout,
]);
