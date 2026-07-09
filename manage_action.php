<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\local\bulkdeploy\job_repository;
use mod_topomojo\task\bulkdeploy_run;

function mod_topomojo_parse_bulkdeploy_schedule(string $value): ?int {
    $timezone = \core_date::get_user_timezone_object();
    foreach (['!Y-m-d\TH:i', '!Y-m-d\TH:i:s'] as $format) {
        $date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($date !== false) {
            return $date->getTimestamp();
        }
    }
    return null;
}

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
        redirect($returnurl);
        break;

    case 'schedule_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $scheduledfor = optional_param('scheduledfor', 0, PARAM_INT);
        $scheduledforlocal = optional_param('scheduledforlocal', '', PARAM_RAW_TRIMMED);
        $batchsize = optional_param('batchsize', 0, PARAM_INT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'topomojo'));
            redirect($returnurl);
        }

        if ($scheduledforlocal !== '') {
            $scheduledfor = mod_topomojo_parse_bulkdeploy_schedule($scheduledforlocal) ?? 0;
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
        redirect($returnurl);
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
        $jobsToRefresh = [];
        $repo = new job_repository();

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

                $repo->set_user_status($deployrow->id, 'cancelled', 'Manually cancelled', '');
                $cancelled++;
                $jobsToRefresh[$deployrow->jobid] = true;

                // Track jobs that need adhoc task cancellation
                if (!empty($deployrow->scheduledfor)) {
                    $jobsToCancel[$deployrow->jobid] = true;
                }
            }
        }

        // Cancel adhoc tasks for scheduled jobs
        foreach (array_keys($jobsToCancel) as $jobid) {
            // Find matching tasks
            $tasks = $DB->get_records('task_adhoc', [
                'component' => 'mod_topomojo',
                'classname' => '\\mod_topomojo\\task\\bulkdeploy_run'
            ]);
            foreach ($tasks as $task) {
                $data = json_decode($task->customdata);
                if (!empty($data->jobid) && $data->jobid == $jobid) {
                    $DB->delete_records('task_adhoc', ['id' => $task->id]);
                }
            }
        }

        foreach (array_keys($jobsToRefresh) as $jobid) {
            if (!$repo->get_active_user_rows($jobid)) {
                $repo->set_job_status($jobid, \mod_topomojo\local\bulkdeploy\job_status::CANCELLED);
                $repo->set_job_cancelled_by($jobid, (int) $USER->id);
            }
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
                'state' => \mod_topomojo\topomojo_attempt::INPROGRESS,
            ], '*', IGNORE_MULTIPLE);

            if ($attempt && !empty($attempt->eventid)) {
                stop_event($auth, $attempt->eventid);
                $attempt->state = \mod_topomojo\topomojo_attempt::FINISHED;
                $attempt->timefinish = time();
                $attempt->timemodified = time();
                $DB->update_record('topomojo_attempts', $attempt);
                $ended++;
            }
        }

        \core\notification::success(get_string('attempts_ended', 'topomojo', $ended));
        redirect($returnurl);
        break;

    case 'extend_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'topomojo'));
            redirect($returnurl);
        }

        $maxextendinterval = topomojo_get_max_extend_interval();
        $extendinterval = optional_param('extendinterval', (int) $topomojo->extendinterval, PARAM_INT);
        if ($extendinterval < 1 || $extendinterval > $maxextendinterval) {
            \core\notification::error(get_string('extendintervalinvalid', 'topomojo'));
            redirect($returnurl);
        }

        $auth = setup();
        if (!$auth) {
            \core\notification::error('Could not initialize TopoMojo API');
            redirect($returnurl);
        }

        $extended = 0;
        $failed = 0;

        foreach ($userids as $uid) {
            $attempt = $DB->get_record('topomojo_attempts', [
                'topomojoid' => $topomojo->id,
                'userid' => $uid,
                'state' => \mod_topomojo\topomojo_attempt::INPROGRESS,
            ], '*', IGNORE_MULTIPLE);

            if (!$attempt || empty($attempt->eventid)) {
                continue;
            }

            try {
                $event = get_event($auth, $attempt->eventid);
                if (!$event || empty($event->expirationTime)) {
                    $failed++;
                    continue;
                }

                $data = new stdClass();
                $timestamp = new DateTime($event->expirationTime);
                $timestamp->add(new DateInterval('PT' . $extendinterval . 'M'));
                $data->id = $attempt->eventid;
                $data->expirationTime = $timestamp->format('Y-m-d\TH:i:s.u\Z');

                if (extend_event($auth, $data)) {
                    $attempt->endtime = $timestamp->getTimestamp();
                    $attempt->timemodified = time();
                    $DB->update_record('topomojo_attempts', $attempt);
                    $extended++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                $failed++;
                debugging("Failed to extend attempt for user $uid: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        \core\notification::success(get_string('attempts_extended', 'topomojo',
            (object) ['count' => $extended, 'minutes' => $extendinterval]));
        if ($failed > 0) {
            \core\notification::warning(get_string('attempts_extend_failed', 'topomojo', $failed));
        }
        redirect($returnurl);
        break;

    default:
        throw new moodle_exception('Invalid action');
}
