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
TopoMojo Plugin for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO,
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full
terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.
Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1175
*/

/**
 * topomojo module main user interface
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_topomojo\topomojo;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/mod/topomojo/locallib.php");
require_once($CFG->libdir . '/completionlib.php');
require_once("$CFG->dirroot/tag/lib.php");
require_once("$CFG->dirroot/lib/licenselib.php");



$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$c = optional_param('c', 0, PARAM_INT);  // Instance ID - it should be named as the first character of the module.

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

global $USER;

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    topomojo_view($topomojo, $course, $cm, $context);
}

// Print the page header.
$url = new moodle_url ( '/mod/topomojo/view.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));

// New topomojo class
$pageurl = $url;
$pagevars = [];
$pagevars['pageurl'] = $pageurl;
$object = new topomojo($cm, $course, $topomojo, $pageurl, $pagevars);

// Get current state of workspace
$allevents = list_events(client: $object->userauth, name: $object->topomojo->name);
$eventsmoodle = moodle_events(events: $allevents);
$history = user_events($object->userauth, events: $eventsmoodle);
$object->event = get_active_event($history);
$renderer = $object->renderer;
echo $renderer->header();

$userid = $USER->id;

// Get active attempt for user: true/false
$activeattempt = $object->get_open_attempt();

$max_attempts = $topomojo->attempts;
$variant = $object->topomojo->variant -1;
$challenge = get_challenge($object->userauth, $object->topomojo->workspaceid);

// Only check if challenge is valid
if ($challenge && isset($challenge->variants[$variant])) {
    if (method_exists($object, 'get_question_manager')) {
        try {
            $qm = $object->get_question_manager();
    
            if ($qm instanceof \mod_topomojo\questionmanager) {
                $mismatches = $qm->detect_mismatched_questions($object, $variant, $challenge);
    
                if (!empty($mismatches)) {
                    $qm->notify_instructors_of_mismatch($cm, $topomojo->name);
                }
            }
        } catch (Throwable $e) {
            debugging("Skipping question mismatch check: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }    
}


// Getting completed attempts by user
$current_attempt_count = $DB->count_records('topomojo_attempts', [
    'topomojoid' => $topomojo->id,
    'userid' => $userid,
    'state' => 30
]);

// If the maximum attempts are reached, display the max attempts template and exit
if ($current_attempt_count >= $max_attempts && $max_attempts != 0) {
    $markdown = get_markdown($object->userauth, $topomojo->workspaceid);
    $markdowncutline = "<<!-- cut -->>";
    $parts = preg_split($markdowncutline, $markdown);
    $renderer->display_detail_max_attempts($topomojo, $max_attempts, $current_attempt_count, $parts[0]);
    echo $renderer->footer();
    exit;
}

//Getting manager name
$managername = get_config('topomojo', 'managername');

//Getting gamespace limit for that manager in topomojo
$gamespacelimit = get_gamespace_limit($object->userauth, $managername);

//Getting all active events without filters
$allActiveEvents = list_all_active_events($object->userauth) ?? [];
$all_active_current_deployed_count = count($allActiveEvents);

//Verify if gamespace limit is reached for that manager, if so disable deployment/start of lab
if ($all_active_current_deployed_count > $gamespacelimit && $gamespacelimit != 0) {
    $markdown = get_markdown($object->userauth, $topomojo->workspaceid);
    $markdowncutline = "<<!-- cut -->>";
    $parts = preg_split($markdowncutline, $markdown);
    $renderer->display_detail_max_gamespaces($topomojo, $parts[0]);
    echo $renderer->footer();
    exit;
}

$activeUserEvents = [];

//Getting active user event for specific user
$activeUserEvents = user_events($object->userauth, $allActiveEvents);
//Count of those active user events
$user_current_deployed_count  = count($activeUserEvents);

//Getting max deployed lab for each user in moodle
$max_deployed_labs = get_config('topomojo', 'maxdeployedlabs');

$lab_names =  [];
$lab_ids = [];

//Getting workspace names and ids
foreach ($activeUserEvents as $event) {
    $lab_names[] = $event['name'];
    $lab_ids[] = $event['workspaceId'];
}

//If the maximum deployed labs are reached, display the deployed labs template and exit
if (!in_array($topomojo->workspaceid, $lab_ids) && $user_current_deployed_count >= $max_deployed_labs) {
    // If the current workspace is not deployed and max deployments are reached, display the deployed labs template and exit
    $markdown = get_markdown($object->userauth, $topomojo->workspaceid);
    $markdowncutline = "<<!-- cut -->>";
    $parts = preg_split($markdowncutline, $markdown);
    $renderer->display_detail_max_deployed_labs($topomojo, $max_deployed_labs, $user_current_deployed_count, $parts[0], $lab_names);
    echo $renderer->footer();
    exit;
}


// Handle start/stop form action
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['start_confirmed']) && $_POST['start_confirmed'] === "yes") {
    debugging("start request received", DEBUG_DEVELOPER);    
    // Check not started already
    if (!$object->event) {
        // Attempt to start the event
        $object->event = start_event($object->userauth, $object->topomojo->workspaceid, $object->topomojo);
        
        if ($object->event) {
            // If the event is created successfully, proceed
            debugging("new event created " . $object->event->id, DEBUG_DEVELOPER);
            $eventid = $object->event->id;
            $activeattempt = $object->init_attempt();
            
            debugging("init_attempt returned $activeattempt", DEBUG_DEVELOPER);
            
            if (!$activeattempt) {
                debugging("init_attempt failed");
                throw new moodle_exception('init_attempt failed');
            }
            
            // Log event start in Moodle
            topomojo_start($cm, $context, $topomojo);
            
        } else {
            // Event creation failed, possibly due to an empty response
            debugging("start_event failed, stopping any partial event", DEBUG_DEVELOPER);
            throw new moodle_exception("Failed to start event: no response from server. Please refresh to end the lab and launch again.");
        }
        
        debugging("new event created with variant " . $object->event->variant, DEBUG_DEVELOPER);
        
    } else {
        // If event already exists, no action needed
        debugging("event has already been started", DEBUG_DEVELOPER);
    }
} else if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['stop_confirmed']) && $_POST['stop_confirmed'] === "yes") {
    debugging("stop request received", DEBUG_DEVELOPER);
    if ($object->event) {
        if ($object->event->isActive) {
            if (!$activeattempt) {
                debugging('no attempt to close', DEBUG_DEVELOPER);
                throw new moodle_exception('no attempt to close');
            }
            debugging("but no live event for " . $object->openAttempt->id, DEBUG_DEVELOPER);
            if ($object->openAttempt->questionusageid) {
                $object->openAttempt->save_question();
            }
            $object->openAttempt->close_attempt();
            stop_event($object->userauth, $object->event->id);
            topomojo_end($cm, $context, $topomojo);

            $reviewattempturl = new moodle_url('/mod/topomojo/review.php', ['id' => $cm->id]);
            redirect($reviewattempturl);
        }
    }
}

if ($object->event) {
    if (($object->event->isActive) && (!$activeattempt)) {
        debugging("active event with no attempt", DEBUG_DEVELOPER);
        $activeattempt = $object->init_attempt();
    }
    // Check age and get new link, checking for 30 minute timeout of the url
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

// Pull values from the settings
$embed = $topomojo->embed;
$license_info = null;

$grader = new \mod_topomojo\utils\grade($object);
$gradepass = $grader->get_grade_item_passing_grade();
debugging("grade to pass is $gradepass", DEBUG_DEVELOPER);

// Show grade only if a grade is set
if ((int)$object->topomojo->grade > 0) {
    $showgrade = true;
} else {
    $showgrade = false;
}

if ($object->event) {

    if (!isset($object->event->vms) || !is_array($object->event->vms) || empty($object->event->vms)) {
        // If VMs array is missing or not valid, display error and stop further processing
        print_error("no vms");
        stop_event($object->userauth, $object->event->id);
        topomojo_end($cm, $context, $topomojo);

        $markdown = get_markdown($object->userauth, $object->topomojo->workspaceid);
        $markdowncutline = "<!-- cut -->";
        $parts = preg_split($markdowncutline, $markdown);

        $renderer->display_detail_no_vms($topomojo, $topomojo->duration);

        if ($showgrade) {
            $renderer->display_grade($topomojo);
        }

        if ($object->topomojo->showcontentlicense) {
            $license_id = $object->topomojo->contentlicense;
            $license_info = license_manager::get_license_by_shortname($license_id);
        }
    
        // Display start form
        $renderer->display_startform($url, $object->topomojo->workspaceid, $parts[0], $license_info);
    } else {

        $code = substr($object->event->id, 0, 8);

        $renderer->display_detail($topomojo, $topomojo->duration, $code);

        $jsoptions = ['keepaliveinterval' => 1];

        $PAGE->requires->js_call_amd('mod_topomojo/keepalive', 'init', [$jsoptions]);

        $extend = false;
        if ($object->userauth && $topomojo->extendevent) {
            $extend = true;
        }

        $renderer->display_controls($starttime, $endtime, $extend, $url, $object->topomojo->workspaceid);
        // No matter what, start our session timer
        $PAGE->requires->js_call_amd('mod_topomojo/clock', 'init',
                ['starttime' => $starttime, 'endtime' => $endtime, 'id' => $object->event->id]);
        if ($topomojo->clock == 1) {
            $PAGE->requires->js_call_amd('mod_topomojo/clock', 'countdown');
        } else if ($topomojo->clock == 2) {
            $PAGE->requires->js_call_amd('mod_topomojo/clock', 'countup');
        }

        $jsoptions = ['id' => $object->event->id, 'topomojo_api_url' => get_config('topomojo', 'topomojoapiurl')];
        $PAGE->requires->js_call_amd('mod_topomojo/invite', 'init', [$jsoptions]);

        if ($embed == 1) {
            $vmlist = [];
            if (!is_array($object->event->vms)) {
                throw new moodle_exception("No VMs visible to user");
            }
            $jsoptions = ['id' => $object->event->id];
            $PAGE->requires->js_call_amd('mod_topomojo/ticket', 'init', [$jsoptions]);

            foreach ($object->event->vms as $vm) {
                if (is_array($vm)) {
                    if ($vm['isVisible']) {
                        $vmdata['url'] = get_config('topomojo', 'topomojobaseurl') .
                                "/mks/?f=1&s=" . $vm['isolationId'] . "&v=" . $vm['name'];
                        $vmdata['name'] = $vm['name'];
                        array_push($vmlist, $vmdata);
                    }
                } else {
                    if ($vm->isVisible) {
                        $vmdata['url'] = get_config('topomojo', 'topomojobaseurl') .
                                "/mks/?f=1&s=" . $vm->isolationId . "&v=" . $vm->name;
                        $vmdata['name'] = $vm->name;
                        array_push($vmlist, $vmdata);
                    }
                }
            }

            $renderer->display_embed_page($object->event->markdown, $vmlist);
        } else {
            $renderer->display_link_page($object->openAttempt->launchpointurl);
        }
    }

} else {
    if ($showgrade) {
        $renderer->display_grade($topomojo);
    }

    // TODO check whether the user has any attempts left

    $markdown = get_markdown($object->userauth, $object->topomojo->workspaceid);
    $markdowncutline = "/\n<!-- cut -->\n/";
    $parts = preg_split($markdowncutline, $markdown);
    $renderer->display_detail($topomojo, $topomojo->duration);

    if ($object->topomojo->showcontentlicense) {
        $license_id = $object->topomojo->contentlicense;
        $license_info = license_manager::get_license_by_shortname($license_id);
    }

    // Display start form
    $renderer->display_startform($url, $object->topomojo->workspaceid, $parts[0], $license_info);
}

echo $renderer->footer();

