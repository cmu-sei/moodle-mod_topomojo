<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\local\bulkdeploy\job_repository;
use mod_topomojo\local\bulkdeploy\job_status;

$jobid = required_param('jobid', PARAM_INT);
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

$PAGE->set_url('/mod/topomojo/status.php', ['jobid' => $jobid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('bulkdeploy_status_pageheading', 'topomojo'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('mod_topomojo/bulkdeploy_status', 'init', [$jobid, sesskey()]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkdeploy_status_pageheading', 'topomojo'));

$counts = $repo->count_user_rows_by_status($jobid);
$rows = $repo->get_user_rows($jobid);

echo html_writer::start_div('mod-topomojo-bulkdeploy-status', ['data-jobid' => $jobid]);
echo html_writer::tag('p', "Status: <span data-role='job-status'>" . s($job->status) . "</span>");
echo html_writer::tag('p',
    "Total: {$job->totalusers} &middot; "
  . "Ready: <span data-role='count-ready'>" . (int)($counts['ready'] ?? 0) . "</span> &middot; "
  . "Failed: <span data-role='count-failed'>" . (int)($counts['failed'] ?? 0) . "</span> &middot; "
  . "Skipped: <span data-role='count-skipped'>" . (int)($counts['skipped'] ?? 0) . "</span> &middot; "
  . "Cancelled: <span data-role='count-cancelled'>" . (int)($counts['cancelled'] ?? 0) . "</span>"
);

if (!job_status::is_terminal($job->status)) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/topomojo/bulkdeploy_cancel.php'),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'jobid', 'value' => $jobid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('bulkdeploy_cancel', 'topomojo')]);
    echo html_writer::end_tag('form');
}

echo html_writer::start_tag('table', ['class' => 'generaltable', 'data-role' => 'rows-table']);
echo '<thead><tr><th>User</th><th>Status</th><th>Gamespace</th><th>Error</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $u = core_user::get_user($r->userid);
    echo '<tr data-rowid="' . (int)$r->id . '">'
       . '<td>' . s(fullname($u)) . '</td>'
       . '<td data-role="row-status">' . s($r->status) . '</td>'
       . '<td data-role="row-gamespace">' . s((string)$r->gamespaceid) . '</td>'
       . '<td data-role="row-error">' . s((string)$r->errormessage) . '</td>'
       . '</tr>';
}
echo '</tbody></table>';
echo html_writer::end_div();
echo $OUTPUT->footer();
