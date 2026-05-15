<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\form\bulkdeploy_form;
use mod_topomojo\local\bulkdeploy\job_repository;
use mod_topomojo\task\bulkdeploy_run;

$cmid = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'topomojo');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

$topomojo = $DB->get_record('topomojo', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/topomojo/bulkdeploy.php', ['id' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('bulkdeploy_pageheading', 'topomojo'));
$PAGE->set_heading(format_string($course->fullname));

// Build role options.
$roleopts = [];
foreach (get_roles_used_in_context($context) as $role) {
    $roleopts[$role->id] = role_get_name($role, $context);
}

$form = new bulkdeploy_form(null, ['cmid' => $cmid, 'roles' => $roleopts]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/topomojo/view.php', ['id' => $cmid]));
}

if ($data = $form->get_data()) {
    if (empty($topomojo->workspaceid)) {
        \core\notification::error(get_string('bulkdeploy_no_workspace', 'topomojo'));
        redirect($PAGE->url);
    }
    $rolefilter = is_array($data->rolefilter) ? array_filter(array_map('intval', $data->rolefilter)) : [];
    $coursecontext = context_course::instance($course->id);

    $enrolled = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);
    if ($rolefilter) {
        $userids = [];
        foreach ($rolefilter as $roleid) {
            foreach (get_role_users($roleid, $coursecontext, false, 'u.id', 'u.id') as $u) {
                $userids[$u->id] = true;
            }
        }
        $enrolled = array_filter($enrolled, fn($u) => isset($userids[$u->id]));
    }

    if (!$enrolled) {
        \core\notification::error(get_string('bulkdeploy_no_users_match', 'topomojo'));
        redirect($PAGE->url);
    }

    $repo = new job_repository();
    $jobid = $repo->create_job(
        (int) $topomojo->id,
        (int) $course->id,
        (int) $USER->id,
        (int) $data->batchsize,
        $rolefilter ? implode(',', $rolefilter) : null,
        array_map(fn($u) => (int) $u->id, $enrolled)
    );

    $task = new bulkdeploy_run();
    $task->set_custom_data((object) ['jobid' => $jobid]);
    $task->set_component('mod_topomojo');
    \core\task\manager::queue_adhoc_task($task);

    redirect(new moodle_url('/mod/topomojo/status.php', ['jobid' => $jobid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkdeploy_pageheading', 'topomojo'));
$form->display();
echo $OUTPUT->footer();
