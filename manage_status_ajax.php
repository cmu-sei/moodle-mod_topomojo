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

/**
 * Returns the current deployment status of a TopoMojo activity as JSON.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\local\bulkdeploy\management_repository;
use mod_topomojo\local\bulkdeploy\job_status;

$cmid = required_param('cmid', PARAM_INT);
require_sesskey();

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'topomojo');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

$topomojo = $DB->get_record('topomojo', ['id' => $cm->instance], '*', MUST_EXIST);

$manrepo = new management_repository();
$activejobs = $manrepo->get_active_jobs($topomojo->id);
$scheduledjobs = $manrepo->get_scheduled_jobs($topomojo->id);
$nextscheduled = $scheduledjobs ? min(array_map(function ($job) {
    return (int) $job->scheduledfor;
}, $scheduledjobs)) : null;

$response = [
    'has_active'       => !empty($activejobs),
    'next_scheduled'   => $nextscheduled,
    'jobs'             => [],
    'updated_users'    => [],
    'progress_summary' => ['ready' => 0, 'total' => 0],
];

foreach ($activejobs as $job) {
    $progress = $manrepo->get_job_progress($job->id);
    $response['jobs'][] = [
        'jobid'    => $job->id,
        'status'   => $job->status,
        'progress' => [
            'ready'    => $progress->ready,
            'failed'   => $progress->failed,
            'pending'  => $progress->pending,
            'launched' => $progress->launched,
            'total'    => $job->totalusers,
        ],
    ];
    $response['progress_summary']['ready'] += (int) $progress->ready;
    $response['progress_summary']['total'] += (int) $job->totalusers;
}

$users = $manrepo->get_enrolled_users_with_state($topomojo->id, $course->id);

$attemptids = array_filter(array_map(function ($u) {
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
    $hasquestions = !empty($u->attemptid) && isset($attemptswithq[$u->attemptid]);
    $state = $manrepo->format_user_state($u, $hasquestions);

    $response['updated_users'][] = [
        'userid'         => (int) $u->userid,
        'status_label'   => $state['status_label'],
        'status_class'   => $state['status_class'],
        'gamespace_text' => $state['gamespace_text'],
        'scheduled_text' => $state['scheduled_text'],
        'end_time_text'  => $state['end_time_text'],
        'tooltip_html'   => $state['tooltip_html'],
        'action_html'    => $state['action_html'],
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
