<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\local\bulkdeploy\job_repository;
use mod_topomojo\local\bulkdeploy\management_repository;

$cmid = required_param('id', PARAM_INT);
$rolefilter = optional_param_array('rolefilter', [], PARAM_INT);
$sort = optional_param('sort', 'firstname', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'topomojo');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

$topomojo = $DB->get_record('topomojo', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/topomojo/manage.php', ['id' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manage_pageheading', 'topomojo'));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->requires->js_call_amd('mod_topomojo/manage', 'init', [$cmid, sesskey()]);

$manrepo = new management_repository();
$users = $manrepo->get_enrolled_users_with_state($topomojo->id, $course->id, $rolefilter);

// Note: Expired gamespaces are detected by the cleanup_gamespaces scheduled task

$coursecontext = context_course::instance($course->id);
$userroles = [];
foreach ($users as $u) {
    $roles = get_user_roles($coursecontext, $u->userid);
    $rolenames = [];
    foreach ($roles as $role) {
        $rolenames[] = role_get_name($role, $coursecontext);
    }
    $userroles[$u->userid] = implode(', ', $rolenames);
    $u->roletext = $userroles[$u->userid];
}

// Sort users
usort($users, function($a, $b) use ($sort, $dir) {
    $val1 = $a->$sort ?? '';
    $val2 = $b->$sort ?? '';
    if ($sort === 'scheduledfor') {
        $val1 = (int)$val1;
        $val2 = (int)$val2;
        $cmp = $val1 <=> $val2;
    } else {
        $cmp = strcasecmp($val1, $val2);
    }
    return $dir === 'DESC' ? -$cmp : $cmp;
});

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_pageheading', 'topomojo'));

// Check for active deployments and compute progress summary.
$has_active_deploys = false;
foreach ($users as $u) {
    if (in_array($u->deploystatus, ['pending', 'launched'])) {
        $has_active_deploys = true;
        break;
    }
}

$summary_ready = 0;
$summary_total = 0;
if ($has_active_deploys) {
    $activejobs = $manrepo->get_active_jobs($topomojo->id);
    foreach ($activejobs as $job) {
        $progress = $manrepo->get_job_progress($job->id);
        $summary_ready += (int) $progress->ready;
        $summary_total += (int) $job->totalusers;
    }
}

$adhocurl = new moodle_url('/admin/tool/task/adhoctasks.php', [
    'classname' => '\\mod_topomojo\\task\\bulkdeploy_run',
]);
$linkhtml = html_writer::link($adhocurl, get_string('manage_deploy_running_link', 'topomojo'),
    ['target' => '_blank']);
$progresshtml = html_writer::span((string) $summary_ready, 'deploy-summary-ready')
    . '/'
    . html_writer::span((string) $summary_total, 'deploy-summary-total');
$summaryhtml = get_string('manage_deploy_running_summary', 'topomojo',
    (object) ['progress' => $progresshtml, 'link' => $linkhtml]);

$notifstyle = $has_active_deploys ? '' : 'display:none;';
echo html_writer::start_div('alert alert-info', [
    'id' => 'deploy-notification',
    'role' => 'alert',
    'style' => $notifstyle,
]);
echo $summaryhtml;
echo html_writer::end_div();

$roleopts = [0 => get_string('bulkdeploy_rolefilter_all', 'topomojo')];
foreach (get_roles_used_in_context($context) as $role) {
    $roleopts[$role->id] = role_get_name($role, $context);
}

echo html_writer::start_div('mod-topomojo-manage');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url, 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::tag('label', 'Role Filter: ', ['for' => 'rolefilter']);
echo html_writer::select($roleopts, 'rolefilter[]', $rolefilter, false, ['multiple' => 'multiple', 'size' => count($roleopts), 'style' => 'width: 200px;']);
echo ' ';
echo html_writer::tag('button', 'Filter', ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$maxbatchsize = get_config('topomojo', 'bulkdeploy_batchsize') ?: 5;

echo html_writer::start_div('bulk-actions mb-3');
echo html_writer::tag('button', get_string('select_all', 'topomojo'), [
    'id' => 'select-all-btn',
    'class' => 'btn btn-sm btn-secondary',
    'type' => 'button',
    'title' => get_string('select_all_help', 'topomojo')
]);
echo ' ';
echo html_writer::tag('button', get_string('deselect_all', 'topomojo'), [
    'id' => 'deselect-all-btn',
    'class' => 'btn btn-sm btn-secondary',
    'type' => 'button',
    'title' => get_string('deselect_all_help', 'topomojo')
]);
echo ' ';
echo html_writer::tag('button', get_string('deploy_selected_now', 'topomojo'), [
    'id' => 'deploy-selected-btn',
    'class' => 'btn btn-success',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('deploy_selected_help', 'topomojo')
]);
echo ' ';
echo html_writer::tag('button', get_string('schedule_selected', 'topomojo'), [
    'id' => 'schedule-selected-btn',
    'class' => 'btn btn-primary',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('schedule_selected_help', 'topomojo')
]);
echo ' ';
echo html_writer::tag('button', get_string('cancel_selected', 'topomojo'), [
    'id' => 'cancel-selected-btn',
    'class' => 'btn btn-warning',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('cancel_selected_help', 'topomojo')
]);
echo ' ';
echo html_writer::tag('button', get_string('end_selected', 'topomojo'), [
    'id' => 'end-selected-btn',
    'class' => 'btn btn-danger',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('end_selected_help', 'topomojo')
]);
echo html_writer::end_div();

// Build sort links for column headers
$sorticon = $dir === 'ASC' ? '▲' : '▼';
$sortlink = function($col, $label) use ($PAGE, $sort, $dir, $sorticon, $rolefilter) {
    $newdir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $url = new moodle_url($PAGE->url, ['sort' => $col, 'dir' => $newdir, 'rolefilter' => $rolefilter]);
    $icon = ($sort === $col) ? ' ' . $sorticon : '';
    return html_writer::link($url, $label . $icon);
};

$statusheader = $sortlink('attemptstate', get_string('status', 'topomojo')) .
    ' ' . $OUTPUT->help_icon('status', 'topomojo');

echo html_writer::start_tag('table', ['class' => 'generaltable mod-topomojo-users-table']);
echo '<thead><tr>';
echo '<th><input type="checkbox" id="select-all-checkbox"></th>';
echo '<th>' . $sortlink('firstname', 'User') . '</th>';
echo '<th>' . $sortlink('roletext', 'Role') . '</th>';
echo '<th>' . $statusheader . '</th>';
echo '<th>Current or Last Gamespace</th>';
echo '<th>' . $sortlink('scheduledfor', 'Scheduled For') . '</th>';
echo '<th>Actions</th>';
echo '</tr></thead><tbody>';

$attemptids = array_filter(array_map(function($u) {
    return $u->attemptid ?? null;
}, $users));
$attemptswithq = [];
if ($attemptids) {
    [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
    $records = $DB->get_records_select(
        'topomojo_attempts',
        "id $insql AND questionusageid IS NOT NULL AND questionusageid <> 0",
        $params,
        '',
        'id'
    );
    $attemptswithq = array_flip(array_keys($records));
}

foreach ($users as $u) {
    $fullname = s($u->firstname . ' ' . $u->lastname);
    $roletext = isset($userroles[$u->userid]) ? s($userroles[$u->userid]) : '─';

    $hasquestions = !empty($u->attemptid) && isset($attemptswithq[$u->attemptid]);
    $state = $manrepo->format_user_state($u, $hasquestions);

    $statushtml = $state['tooltip_html'] !== null
        ? $state['tooltip_html']
        : s($state['status_label']);

    echo '<tr data-userid="' . $u->userid . '" data-status="' . s($state['status_class']) . '">';
    echo '<td><input type="checkbox" class="user-checkbox" value="' . $u->userid . '"></td>';
    echo '<td>' . $fullname . '</td>';
    echo '<td>' . $roletext . '</td>';
    echo '<td class="cell-status">' . $statushtml . '</td>';
    echo '<td class="cell-gamespace">' . s($state['gamespace_text']) . '</td>';
    echo '<td class="cell-scheduled">' . s($state['scheduled_text']) . '</td>';
    echo '<td class="cell-actions">' . $state['action_html'] . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

// Hidden forms for modals
echo html_writer::start_tag('form', ['id' => 'deploy-form', 'method' => 'post', 'action' => new moodle_url('/mod/topomojo/manage_action.php'), 'style' => 'display:none;']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deploy_selected']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids', 'id' => 'deploy-userids']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchsize', 'id' => 'deploy-batchsize']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', ['id' => 'schedule-form', 'method' => 'post', 'action' => new moodle_url('/mod/topomojo/manage_action.php'), 'style' => 'display:none;']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'schedule_selected']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids', 'id' => 'schedule-userids']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchsize', 'id' => 'schedule-batchsize']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'scheduledfor', 'id' => 'schedule-timestamp']);
echo html_writer::end_tag('form');

// Modal content templates
echo html_writer::start_div('', ['id' => 'deploy-modal-content', 'style' => 'display:none;']);
echo html_writer::tag('p', get_string('deploy_confirm_message', 'topomojo'));
echo html_writer::tag('label', get_string('bulkdeploy_batchsize', 'topomojo') . ':', ['for' => 'deploy-batchsize-input', 'class' => 'd-block mb-2']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'id' => 'deploy-batchsize-input',
    'value' => $maxbatchsize,
    'min' => 1,
    'max' => $maxbatchsize,
    'class' => 'form-control',
    'style' => 'width: 80px;',
    'required' => 'required'
]);
echo html_writer::tag('small', get_string('bulkdeploy_batchsize_desc', 'topomojo', $maxbatchsize), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::start_div('', ['id' => 'schedule-modal-content', 'style' => 'display:none;']);
echo html_writer::tag('label', get_string('scheduledfor', 'topomojo') . ':', ['for' => 'scheduledfor-input', 'class' => 'd-block mb-2']);
echo html_writer::empty_tag('input', [
    'type' => 'datetime-local',
    'id' => 'scheduledfor-input',
    'value' => '',
    'class' => 'form-control',
    'style' => 'width: 220px;',
    'required' => 'required'
]);
echo html_writer::tag('small', '', ['class' => 'form-text text-muted mb-3', 'id' => 'timezone-display']);
echo html_writer::tag('label', get_string('bulkdeploy_batchsize', 'topomojo') . ':', ['for' => 'schedule-batchsize-input', 'class' => 'd-block mb-2']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'id' => 'schedule-batchsize-input',
    'value' => $maxbatchsize,
    'min' => 1,
    'max' => $maxbatchsize,
    'class' => 'form-control',
    'style' => 'width: 80px;',
    'required' => 'required'
]);
echo html_writer::tag('small', get_string('bulkdeploy_batchsize_desc', 'topomojo', $maxbatchsize), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::end_div();
echo $OUTPUT->footer();
