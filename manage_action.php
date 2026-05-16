<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\local\bulkdeploy\job_repository;
use mod_topomojo\task\bulkdeploy_run;

$action = required_param('action', PARAM_ALPHANUMEXT);
$cmid = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
require_sesskey();

// Debug logging
error_log("manage_action.php: action=" . var_export($action, true));
error_log("manage_action.php: all POST: " . var_export($_POST, true));

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'topomojo');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

$topomojo = $DB->get_record('topomojo', ['id' => $cm->instance], '*', MUST_EXIST);
$returnurl = new moodle_url('/mod/topomojo/manage.php', ['id' => $cmid]);

switch ($action) {
    case 'deploy_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $batchsize = optional_param('batchsize', 0, PARAM_INT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'topomojo'));
            redirect($returnurl);
        }

        // Validate batchsize against plugin max
        $maxbatchsize = get_config('topomojo', 'bulkdeploy_batchsize') ?: 5;
        if ($batchsize < 1 || $batchsize > $maxbatchsize) {
            $batchsize = $maxbatchsize;
        }

        $repo = new job_repository();
        $jobid = $repo->create_job(
            (int) $topomojo->id,
            (int) $course->id,
            (int) $USER->id,
            $batchsize,
            null,
            $userids,
            null
        );

        $task = new bulkdeploy_run();
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->set_component('mod_topomojo');
        $task->set_next_run_time(time()); // Run immediately
        \core\task\manager::queue_adhoc_task($task);

        \core\notification::success(get_string('deployment_queued', 'topomojo', count($userids)));
        redirect(new moodle_url($returnurl, ['deployed' => 1]));
        break;

    case 'schedule_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $scheduledfor = required_param('scheduledfor', PARAM_INT);
        $batchsize = optional_param('batchsize', 0, PARAM_INT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'topomojo'));
            redirect($returnurl);
        }

        if ($scheduledfor <= time()) {
            \core\notification::error(get_string('schedule_past_error', 'topomojo'));
            redirect($returnurl);
        }

        // Validate batchsize against plugin max
        $maxbatchsize = get_config('topomojo', 'bulkdeploy_batchsize') ?: 5;
        if ($batchsize < 1 || $batchsize > $maxbatchsize) {
            $batchsize = $maxbatchsize;
        }

        $repo = new job_repository();
        $jobid = $repo->create_job(
            (int) $topomojo->id,
            (int) $course->id,
            (int) $USER->id,
            $batchsize,
            null,
            $userids,
            $scheduledfor
        );

        $task = new bulkdeploy_run();
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->set_component('mod_topomojo');
        $task->set_next_run_time($scheduledfor);
        \core\task\manager::queue_adhoc_task($task);

        \core\notification::success(get_string('deployment_scheduled', 'topomojo', count($userids)));
        redirect(new moodle_url($returnurl, ['deployed' => 1]));
        break;

    case 'cancel_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'topomojo'));
            redirect($returnurl);
        }

        $auth = setup();
        $cancelled = 0;
        $jobsToCancel = [];

        foreach ($userids as $uid) {
            $deployrow = $DB->get_record_sql(
                "SELECT bdu.*, bdj.id as jobid, bdj.scheduledfor
                 FROM {topomojo_bulkdeploy_user} bdu
                 INNER JOIN {topomojo_bulkdeploy_job} bdj ON bdj.id = bdu.jobid
                 WHERE bdu.userid = :userid
                 AND bdj.topomojoid = :topomojoid
                 AND bdu.status IN ('pending', 'launched')
                 ORDER BY bdu.id DESC
                 LIMIT 1",
                ['userid' => $uid, 'topomojoid' => $topomojo->id]
            );

            if ($deployrow) {
                if (!empty($deployrow->gamespaceid) && $auth) {
                    stop_event($auth, $deployrow->gamespaceid);
                }

                $repo = new job_repository();
                $repo->set_user_status($deployrow->id, 'cancelled', 'Manually cancelled', '');
                $cancelled++;

                // Track jobs that need adhoc task cancellation
                if (!empty($deployrow->scheduledfor)) {
                    $jobsToCancel[$deployrow->jobid] = true;
                }
            }
        }

        // Cancel adhoc tasks for scheduled jobs
        foreach (array_keys($jobsToCancel) as $jobid) {
            $DB->execute(
                "DELETE FROM {task_adhoc} WHERE component = 'mod_topomojo'
                 AND classname = '\\\\mod_topomojo\\\\task\\\\bulkdeploy_run'
                 AND customdata LIKE :jobid",
                ['jobid' => '%"jobid":' . $jobid . '%']
            );
        }

        \core\notification::success(get_string('deployments_cancelled', 'topomojo', $cancelled));
        redirect($returnurl);
        break;

    case 'end_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'topomojo'));
            redirect($returnurl);
        }

        $auth = setup();
        if (!$auth) {
            \core\notification::error('Could not initialize TopoMojo API');
            redirect($returnurl);
        }

        $ended = 0;

        foreach ($userids as $uid) {
            $attempt = $DB->get_record('topomojo_attempts', [
                'topomojoid' => $topomojo->id,
                'userid' => $uid,
                'state' => 10, // INPROGRESS
            ], '*', IGNORE_MULTIPLE);

            if ($attempt && !empty($attempt->eventid)) {
                stop_event($auth, $attempt->eventid);
                $attempt->state = 'finished';
                $attempt->timefinish = time();
                $attempt->timemodified = time();
                $DB->update_record('topomojo_attempts', $attempt);
                $ended++;
            }
        }

        \core\notification::success(get_string('attempts_ended', 'topomojo', $ended));
        redirect($returnurl);
        break;

    default:
        throw new moodle_exception('Invalid action');
}
