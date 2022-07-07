<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * topomojo module main user interface
 *
 * @package    mod_topomojo
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
Topomojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once("$CFG->dirroot/mod/topomojo/locallib.php");

require_login();
require_sesskey();

$id = required_param('id', PARAM_ALPHANUMEXT);
$cmid = required_param('cmid', PARAM_INT);
$a = required_param('a', PARAM_INT);

if ($cmid) {
    $cm         = get_coursemodule_from_id('topomojo', $cmid, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $topomojo   = $DB->get_record('topomojo', array('id' => $cm->instance), '*', MUST_EXIST);
}

$url = new moodle_url('/mod/topomojo/getresults.php', array('cmid' => $cm->id, 'id' => $id));
$PAGE->set_url($url);

// Require the session key - want to make sure that this isn't called
// maliciously to keep a session alive longer than intended.
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    print_error('invalidsesskey');
}

//$system = setup_system();
//$results = get_taskresults($system, $id);
$response = array();

$tasks = $DB->get_records('topomojo_tasks', array("topomojoid" => $topomojo->id/*, "gradable" => "1"*/));
if ($tasks) {
    $data = array();
    $details = array();
    $index = 0;
    foreach ($tasks as $task) {
        $object = new stdClass();
/*
        $results = $DB->get_records('topomojo_task_results', array("attemptid" => $a, "taskid" => $task->id), "timemodified ASC");
        if ($results) {
            foreach ($results as $result) {
                if ($result->vmname === "SUMMARY") {
                    $object->taskscore = $result->score;
                    $succeeded = 0;
                    $object->status = $result->status;
                }
            }
            debugging("$task->dispatchtaskid has taskscore $object->taskscore", DEBUG_DEVELOPER);

        }
*/
        $summary = $DB->get_record_sql('SELECT * from {topomojo_task_results} WHERE '
                . 'taskid = ' . $task->id . ' AND '
                . 'attemptid = ' . $a . ' AND '
                . $DB->sql_compare_text('vmname') . ' = '
                . $DB->sql_compare_text(':vmname'), ['vmname' => 'SUMMARY']);
        if ($summary) {
            $object->taskscore = $summary->score;
            $object->status = $summary->status;
            debugging("$task->id $task->dispatchtaskid has taskscore $object->taskscore", DEBUG_DEVELOPER);
        } else {
            debugging("no summary task result for $task->id", DEBUG_DEVELOPER);
        }


        // TODO use id from db not from dispatcher
        //$object->taskId = $task->id;
        $object->taskId = $task->dispatchtaskid;
        $object->taskIndex = $index;
        $data[] = $object;
        $index++;
    }

    $rec = $DB->get_record('topomojo_attempts', array("id" => $a));
    $maxgrade = $DB->get_field("topomojo", "grade", array('id' => $rec->topomojoid));
    debugging("retrieved grade of $rec->score for attempt $a maxgrade $maxgrade topomojoid $rec->topomojoid", DEBUG_DEVELOPER);
    $response['score'] = get_string("attemptscore", "topomojo") . "$rec->score / $maxgrade";
}

// TODO child task results will not be retrieved by runtask.php and must be checked here, if desired

/*
$system = setup_system();
$results = get_taskresults($system, $id);
if ($results) {

    $data = array();
    foreach ($results as $result) {

        $task = get_task($system, $result->taskId);

        $dbtask = $DB->get_record_sql('SELECT * from {topomojo_tasks} WHERE '
                . $DB->sql_compare_text('name') . ' = '
                . $DB->sql_compare_text(':name'), ['name' => $task->name]);
        if ($dbtask !== null) {
            $object = new stdClass();
            $object->statusDate = $result->statusDate;
            $object->status = $result->status;
            $object->taskId = $result->taskId;
            if ($object->status === "succeeded") {
                $object->score = $dbtask->points;
            } else {
                $object->score = 0;
            }
            array_push($data, $object);
        }
    }

    header('HTTP/1.1 200 OK');
    $response['parsed'] = $data;
    $response['message'] = "success";
} else {
    $response['message'] = "success";
}
*/
header('HTTP/1.1 200 OK');
$response['parsed'] = $data;
$response['message'] = "success";

$response['id'] = $id;

echo json_encode($response);


