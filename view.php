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

use \mod_topomojo\topomojo;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/mod/topomojo/locallib.php");
require_once($CFG->libdir . '/completionlib.php');



$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$c = optional_param('c', 0, PARAM_INT);  // instance ID - it should be named as the first character of the module.

try {
    if ($id) {
        $cm         = get_coursemodule_from_id('topomojo', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $topomojo   = $DB->get_record('topomojo', array('id' => $cm->instance), '*', MUST_EXIST);
    } else if ($c) {
        $topomojo   = $DB->get_record('topomojo', array('id' => $c), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $topomojo->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('topomojo', $topomojo->id, $course->id, false, MUST_EXIST);
    }
} catch (Exception $e) {
    print_error("invalid course module id passed");
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/topomojo:view', $context);

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    topomojo_view($topomojo, $course, $cm, $context);
}

// Print the page header.
$url = new moodle_url ( '/mod/topomojo/view.php', array ( 'id' => $cm->id ) );

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));
$PAGE->set_heading($course->fullname);

// new topomojo class
$pageurl = $url;
$pagevars = array();
$pagevars['pageurl'] = $pageurl;
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars);


// get workspace info
$object->workspace = get_workspace($object->userauth, $topomojo->workspaceid);
#print_r($object->workspace);

// Update the database.
if ($object->workspace) {
    // Update the database.
    $topomojo->name = $object->workspace->name;
    $topomojo->intro = $object->workspace->description;
    $DB->update_record('topomojo', $topomojo);
    // this next line nay generate lots of hvp module errors
    rebuild_course_cache($topomojo->course);
}

// get current state of workspace
$all_events = $object->list_events();
$moodle_events = $object->moodle_events($all_events);
$history = $object->user_events($moodle_events);
$object->event = get_active_event($history);

// get active attempt for user: true/false
$attempt = $object->get_open_attempt();
if ($attempt == true) {
    debugging("get_open_attempt returned attemptid " . $object->openAttempt->id, DEBUG_DEVELOPER);
} else if ($attempt == false) {
    debugging("get_open_attempt returned false", DEBUG_DEVELOPER);
}

//TODO send instructor to a different page

// handle start/stop form action
if ($_SERVER['REQUEST_METHOD'] == "POST" and isset($_POST['start'])) {
    debugging("start request received", DEBUG_DEVELOPER);

    // check not started already
    if (!$object->event) {

        //TODO check for open attempt and check for status of its event


        $object->event = start_event($object->userauth, $object->topomojo->workspaceid, $object->topomojo);
        if ($object->event) {
            debugging("new event created " .$object->event->id, DEBUG_DEVELOPER);
            //$object->event = get_event($object->userauth, $eventid);
            $attempt = $object->init_attempt();
            debugging("init_attempt returned $attempt", DEBUG_DEVELOPER);
            if (!$attempt) {
                debugging("init_attempt failed");
                print_error('init_attempt failed');
            }
            topomojo_start($cm, $context, $topomojo);
        } else {
            debugging("start_event failed", DEBUG_DEVELOPER);
            print_error("start_event failed");
        }
    } else {
        debugging("event has already been started", DEBUG_DEVELOPER);
    }
} else if ($_SERVER['REQUEST_METHOD'] == "POST" and isset($_POST['stop'])) {
    debugging("stop request received", DEBUG_DEVELOPER);
    if ($object->event) {
        if ($object->event->isActive) {
            if (!$attempt) {
                debugging('no attempt to close', DEBUG_DEVELOPER);
                print_error('no attempt to close');
            }

            $grader = new \mod_topomojo\utils\grade($object);
            $grader->process_attempt($object->openAttempt);
            $object->openAttempt->close_attempt();

            stop_event($object->userauth, $object->event->id);
            $object->event = get_event($object->userauth, $object->event->id); //why call this again? just to check that it is ending
            debugging("stop_attempt called, get_event returned " . $object->event->isActive, DEBUG_DEVELOPER);
            topomojo_end($cm, $context, $topomojo);
            redirect($url);
        }
    }
}

if ((!$object->event) && ($attempt)) {
    debugging("active attempt with no event", DEBUG_DEVELOPER);
    //print_error('attemptalreadyexists', 'topomojo');
    $grader = new \mod_topomojo\utils\grade($object);
    $grader->process_attempt($object->openAttempt);
    $object->openAttempt->close_attempt();
}

if ($object->event) {
    if (($object->event->isActive) && (!$attempt)) {
        // this should not happend because we create the attempt when we start it
        debugging("active event with no attempt", DEBUG_DEVELOPER);
        //print_error('eventwithoutattempt', 'topomojo');
        // TODO give user a popup to confirm they are starting an attempt
        $attempt = $object->init_attempt();
    }
    // check age and get new link, chekcing for 30 minute timeout of the url
    if (($object->openAttempt->state == 10) &&
            ((time() - $object->openAttempt->timemodified) > 3600 )) {
        debugging("getting new launchpointurl", DEBUG_DEVELOPER);
        $object->event = start_event($object->userauth, $object->topomojo->workspaceid, $object->topomojo);
        $object->openAttempt->launchpointurl = $object->event->launchpointUrl;
        $object->openAttempt->save();
    }
    $eventid = $object->event->id;
    $starttime = strtotime($object->event->startTime);
    $endtime = strtotime($object->event->expirationTime);
} else {
    $eventid = null;
    $startime = null;
    $endtime = null;
}

// pull values from the settings
$embed = $topomojo->embed;

$grader = new \mod_topomojo\utils\grade($object);
$gradepass = $grader->get_grade_item_passing_grade();
debugging("grade to pass is $gradepass", DEBUG_DEVELOPER);

// show grade only if a passing grade is set
if ((int)$gradepass > 0) {
    $showgrade = true;
} else {
    $showgrade = false;
}

//$renderer = $PAGE->get_renderer('mod_topomojo');
$renderer = $object->renderer;
echo $renderer->header();

if ($object->event) {
    $code = substr($object->event->id, 0, 8);
    $renderer->display_detail($topomojo, $topomojo->duration, $code);
    
    
    $jsoptions = ['keepaliveinterval' => 1];

    $PAGE->requires->js_call_amd('mod_topomojo/keepalive', 'init', [$jsoptions]);

    $extend = false;
    if ($object->userauth && $topomojo->extendevent) {
        $extend = true;
    }

    $renderer->display_controls($starttime, $endtime, $extend, $url, $object->topomojo->workspaceid);
    // no matter what, start our session timer
    $PAGE->requires->js_call_amd('mod_topomojo/clock', 'init', array('starttime' => $starttime, 'endtime' => $endtime, 'id' => $object->event->id));
    if ($topomojo->clock == 1) {
        $PAGE->requires->js_call_amd('mod_topomojo/clock', 'countdown');
    } else if ($topomojo->clock == 2) {
        $PAGE->requires->js_call_amd('mod_topomojo/clock', 'countup');
    }

    $jsoptions = ['id' => $object->event->id, 'topomojo_api_url' => get_config('topomojo', 'topomojoapiurl')];
    $PAGE->requires->js_call_amd('mod_topomojo/invite', 'init', [$jsoptions]);

    if ($embed == 1) {
  
        $vmlist = array();
        if (!is_array($object->event->vms)) {
            print_error("No VMs visible to user");
        }
        foreach ($object->event->vms as $vm) {
            if (is_array($vm)) {
                if ($vm['isVisible']) {
                    $vmdata['url'] = get_config('topomojo', 'playerappurl') . "/mks/?f=1&s=" . $vm['isolationId'] . "&v=" . $vm['name'];
                    $vmdata['name'] = $vm['name'];
                    array_push($vmlist, $vmdata);
                }
            } else {
                if ($vm->isVisible) {
                    $vmdata['url'] = get_config('topomojo', 'playerappurl') . "/mks/?f=1&s=" . $vm->isolationId . "&v=" . $vm->name;
                    $vmdata['name'] = $vm->name;
                    array_push($vmlist, $vmdata);
                }
            }

        }

        $renderer->display_embed_page($object->openAttempt->launchpointurl, $object->event->markdown, $vmlist); 
    } else {
        $renderer->display_link_page($object->openAttempt->launchpointurl);
    }
    
} else {
    $renderer->display_detail($topomojo, $topomojo->duration);

    if ($showgrade) {
        $renderer->display_grade($topomojo);
    }
    // display start form
    $renderer->display_startform($url, $object->topomojo->workspaceid);
}

$action = optional_param('action', '', PARAM_ALPHA);

switch($action) {
    case "submitquiz": 
        debugging("stop request received", DEBUG_DEVELOPER);
        if ($object->event) {
            if ($object->event->isActive) {
                if (!$attempt) {
                    debugging('no attempt to close', DEBUG_DEVELOPER);
                    print_error('no attempt to close');
                }
    
                $grader = new \mod_topomojo\utils\grade($object);
                $grader->process_attempt($object->openAttempt);
                $object->openAttempt->close_attempt();
    
                stop_event($object->userauth, $object->event->id);
                topomojo_end($cm, $context, $topomojo);
                redirect($url);
            }
        }
    
        break;
    default:
        if ($object->openAttempt) {
            if (count($object->get_question_manager()->get_questions())) {
                $renderer->render_quiz($object->openAttempt);
            }
        }
}
// attempts may differ from events pulled from history on server
$attempts = $object->getall_attempts('closed', $review = false);
echo $renderer->display_attempts($attempts, $showgrade);


echo $renderer->footer();


