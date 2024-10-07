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

/*
Copyright 2024 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. 
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, 
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. 
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  
Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of Third-Party Software each subject to its own license.
DM24-1175
*/

/**
 * topomojo module main user interface
 *
 * @package    mod_topomojo
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_topomojo\topomojo;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/mod/topomojo/locallib.php");
require_once($CFG->libdir . '/completionlib.php');



$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$c = optional_param('c', 0, PARAM_INT);  // Instance ID - it should be named as the first character of the module.
$attemptid = optional_param('attemptid', 0, PARAM_INT);

try {
    if ($id) {
        $cm         = get_coursemodule_from_id('topomojo', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $topomojo   = $DB->get_record('topomojo', ['id' => $cm->instance], '*', MUST_EXIST);
    } else if ($c) {
        $topomojo   = $DB->get_record('topomojo', ['id' => $c], '*', MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $topomojo->course], '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('topomojo', $topomojo->id, $course->id, false, MUST_EXIST);
    }
} catch (Exception $e) {
    throw new moodle_exception("invalid course module id passed");
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/topomojo:view', $context);

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    topomojo_view($topomojo, $course, $cm, $context);
}

// Print the page header.
$url = new moodle_url ( '/mod/topomojo/challenge.php', ['id' => $cm->id]);
$returnurl = new moodle_url ( '/mod/topomojo/view.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));
$PAGE->set_heading($course->fullname);

// New topomojo class
$pageurl = $url;
$pagevars = [];
$pagevars['pageurl'] = $pageurl;
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars);

// Get current state of workspace
$allevents = list_events($object->userauth, $object->topomojo->name);
$eventsmoodle = moodle_events($allevents);
$history = user_events($object->userauth, $eventsmoodle);
$object->event = get_active_event($history);

// Get active attempt for user: true/false
$activeattempt = $object->get_open_attempt();
if ($activeattempt == true) {
    debugging("get_open_attempt returned attemptid " . $object->openAttempt->id, DEBUG_DEVELOPER);
} else if ($activeattempt == false) {
    debugging("get_open_attempt returned false", DEBUG_DEVELOPER);
    redirect($returnurl);
}

// Handle start/stop form action
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['start'])) {
    debugging("start request received", DEBUG_DEVELOPER);

    // Check not started already
    if (!$object->event) {

        // TODO check for open attempt and check for status of its event
        $object->event = start_event($object->userauth, $object->topomojo->workspaceid, $object->topomojo);
        if ($object->event) {
            debugging("new event created " .$object->event->id, DEBUG_DEVELOPER);
            //$object->event = get_event($object->userauth, $eventid);
            $activeattempt = $object->init_attempt();
            debugging("init_attempt returned $activeattempt", DEBUG_DEVELOPER);
            if (!$activeattempt) {
                debugging("init_attempt failed");
                throw new moodle_exception('init_attempt failed');
            }
            topomojo_start($cm, $context, $topomojo);
        } else {
            debugging("start_event failed", DEBUG_DEVELOPER);
            throw new moodle_exception("start_event failed");
        }
        debugging("new event created with variant " .$object->event->variant, DEBUG_DEVELOPER);
        if ($object->topomojo->importchallenge && $object->topomojo->variant == 0) {
            $challenge = get_gamespace_challenge($object->userauth, $object->event->id);
            //$object->get_question_manager()->create_questions_from_challenge($challenge);
        }
        // Contact topomojo and pull the correct answers for this attempt
        // TODO verify is this works for random attempts
        $object->get_question_manager()->update_answers($object->openAttempt->get_quba(), $object->openAttempt->eventid);

    } else {
        debugging("event has already been started", DEBUG_DEVELOPER);
    }
} else if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['stop'])) {
    debugging("stop request received", DEBUG_DEVELOPER);
    if ($object->event) {
        if ($object->event->isActive) {
            if (!$activeattempt) {
                debugging('no attempt to close', DEBUG_DEVELOPER);
                throw new moodle_exception('no attempt to close');
            }

            $object->openAttempt->save_question();
            $object->openAttempt->close_attempt();
            $grader = new \mod_topomojo\utils\grade($object);
            $grader->process_attempt($object->openAttempt);

            if ($object->topomojo->endlab) {
                stop_event($object->userauth, $object->event->id);
                topomojo_end($cm, $context, $topomojo);
            }

            $viewattempturl = new moodle_url ( '/mod/topomojo/viewattempt.php',
                              ['a' => $object->openAttempt->id, 'action' => 'view']);
            redirect($viewattempturl);
        }
    }
}

if ((!$object->event) && ($activeattempt)) {
    debugging("active attempt with no event", DEBUG_DEVELOPER);
    //throw new moodle_exception(('attemptalreadyexists', 'topomojo');
    $grader = new \mod_topomojo\utils\grade($object);
    $grader->process_attempt($object->openAttempt);
    $object->openAttempt->close_attempt();
}

$grader = new \mod_topomojo\utils\grade($object);
$gradepass = $grader->get_grade_item_passing_grade();
debugging("grade to pass is $gradepass", DEBUG_DEVELOPER);

// Show grade only if a grade is set
if ((int)$object->topomojo->grade > 0) {
    $showgrade = true;
} else {
    $showgrade = false;
}

$renderer = $object->renderer;
echo $renderer->header();

$action = optional_param('action', '', PARAM_ALPHA);

switch($action) {
    case "submitquiz":
        debugging("submitquiz request received", DEBUG_DEVELOPER);
        if ($object->event) {
            if ($object->event->isActive) {
                if (!$activeattempt) {
                    debugging('no active attempt', DEBUG_DEVELOPER);
                    throw new moodle_exception('no active attempt');
                }

                // TODO if we are submitting answers, dont close, just save
                $object->openAttempt->save_questions();

                // TODO maybe dont reload?
                redirect($url);
            }
        }

        break;
    default:
        if ($object->openAttempt) {
            if (count($object->get_question_manager()->get_questions())) {
                if ($object->event->id) {
                    $challenge = get_gamespace_challenge($object->userauth, $object->event->id);
                    if ($challenge->text) {
                        $renderer->render_challenge_instructions($challenge->text);
                    }
                }
                $renderer->render_quiz($object->openAttempt, $pageurl, $id);
            }
        }
}
// Attempts may differ from events pulled from history on server

echo $renderer->footer();
