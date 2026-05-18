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

error_log("manage.php: Starting get_enrolled_users_with_state");
$manrepo = new management_repository();
$users = $manrepo->get_enrolled_users_with_state($topomojo->id, $course->id, $rolefilter);
error_log("manage.php: Got " . count($users) . " users");

// Sort users
usort($users, function($a, $b) use ($sort, $dir) {
    $val1 = $a->$sort ?? '';
    $val2 = $b->$sort ?? '';
    $cmp = strcasecmp($val1, $val2);
    return $dir === 'DESC' ? -$cmp : $cmp;
});

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
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_pageheading', 'topomojo'));

// Check for active deployments and show adhoc task link
$has_active_deploys = false;
foreach ($users as $u) {
    if (in_array($u->deploystatus, ['pending', 'launched'])) {
        $has_active_deploys = true;
        break;
    }
}

if ($has_active_deploys) {
    $adhocurl = new moodle_url('/admin/tool/task/adhoctasks.php', [
        'classname' => '\\mod_topomojo\\task\\bulkdeploy_run'
    ]);
    echo $OUTPUT->notification(
        'Deployments are running. ' . html_writer::link($adhocurl, 'View adhoc task details', ['target' => '_blank']),
        \core\output\notification::NOTIFY_INFO
    );
}

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

echo html_writer::start_tag('table', ['class' => 'generaltable mod-topomojo-users-table']);
echo '<thead><tr>';
echo '<th><input type="checkbox" id="select-all-checkbox"></th>';
echo '<th>' . $sortlink('firstname', 'User') . '</th>';
echo '<th>Role</th>';
echo '<th>' . $sortlink('attemptstate', 'Status') . '</th>';
echo '<th>Current or Last Gamespace</th>';
echo '<th>Scheduled For</th>';
echo '<th>Actions</th>';
echo '</tr></thead><tbody>';

foreach ($users as $u) {
    $fullname = s($u->firstname . ' ' . $u->lastname);

    $statusinfo = 'None';

    // Show "Scheduled" first if there's a future scheduled deployment
    if (!empty($u->deploystatus) && !empty($u->scheduledfor) && $u->scheduledfor > time() && $u->deploystatus === 'pending') {
        $statusinfo = 'Scheduled';
    } else if (!empty($u->attemptid)) {
        // Show attempt status
        $statemap = [
            '0' => 'Not Started',
            '10' => 'Active',
            '20' => 'Abandoned',
            '30' => 'Finished',
        ];
        $statusinfo = $statemap[$u->attemptstate] ?? $u->attemptstate ?? 'unknown';
    } else if (!empty($u->deploystatus)) {
        // Show other deployment statuses
        $statusinfo = ucfirst($u->deploystatus);
    }

    // Show gamespace: deployment gamespace OR attempt gamespace as fallback
    $gamespaceid = '';
    if (!empty($u->deploygamespaceid)) {
        // Deployment has a gamespace
        $gamespaceid = $u->deploygamespaceid;
    } else if (!empty($u->attemptgamespaceid)) {
        // Fall back to attempt gamespace
        $gamespaceid = $u->attemptgamespaceid;
    }
    $gamespacetext = $gamespaceid ? s($gamespaceid) : '─';

    // Show scheduled time if deployment is scheduled (pending with future time)
    $scheduledtext = '─';
    if (!empty($u->scheduledfor) && $u->scheduledfor > time() && $u->deploystatus === 'pending') {
        $scheduledtext = userdate($u->scheduledfor, get_string('strftimedatetime', 'langconfig'));
    }

    $roletext = isset($userroles[$u->userid]) ? s($userroles[$u->userid]) : '─';

    echo '<tr data-userid="' . $u->userid . '">';
    echo '<td><input type="checkbox" class="user-checkbox" value="' . $u->userid . '"></td>';
    echo '<td>' . $fullname . '</td>';
    echo '<td>' . $roletext . '</td>';
    echo '<td>' . s($statusinfo) . '</td>';
    echo '<td>' . $gamespacetext . '</td>';
    echo '<td>' . $scheduledtext . '</td>';
    echo '<td>';

    // Show link to view attempt if there's an attempt (active or finished)
    if (!empty($u->attemptid)) {
        // Check if attempt has questions
        $attemptrecord = $DB->get_record('topomojo_attempts', ['id' => $u->attemptid], 'questionusageid');
        $hasquestions = !empty($attemptrecord->questionusageid);

        if ($hasquestions) {
            if ($u->attemptstate == 10) {
                // Active attempt with questions - link to challenge page
                $challengeurl = new moodle_url('/mod/topomojo/challenge.php', [
                    'attemptid' => $u->attemptid,
                ]);
                echo html_writer::link($challengeurl, get_string('viewattempt', 'mod_topomojo'), ['class' => 'btn btn-sm btn-outline-primary', 'target' => '_blank']);
            } else if (in_array($u->attemptstate, [20, 30])) {
                // Finished/abandoned attempt with questions - link to viewattempt
                $viewurl = new moodle_url('/mod/topomojo/viewattempt.php', [
                    'a' => $u->attemptid,
                    'action' => 'view',
                ]);
                echo html_writer::link($viewurl, get_string('viewattempt', 'mod_topomojo'), ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']);
            } else {
                echo '─';
            }
        } else {
            echo '─';
        }
    } else {
        echo '─';
    }

    echo '</td>';
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
