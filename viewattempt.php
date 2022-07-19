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

//require('../../config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/mod/topomojo/locallib.php");

$a = optional_param('a', '', PARAM_INT);  // attempt ID 
$action = optional_param('action', 'list', PARAM_TEXT);
$actionitem = optional_param('id', 0, PARAM_INT);

if (!$a) {
    $a = required_param('attemptid', PARAM_INT);  // attempt ID
}

try {
        $attempt    = $DB->get_record('topomojo_attempts', array('id' => $a), '*', MUST_EXIST);
        $topomojo   = $DB->get_record('topomojo', array('id' => $attempt->topomojoid), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $topomojo->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('topomojo', $topomojo->id, $course->id, false, MUST_EXIST);
} catch (Exception $e) {
    print_error("invalid attempt id passed");
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
// TODO create review attempt capability
require_capability('mod/topomojo:view', $context);

// TODO log event attempt views
if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    //topomojo_view($topomojo, $course, $cm, $context);
}

// Print the page header.
$url = new moodle_url ( '/mod/topomojo/viewattempt.php', array ( 'a' => $a ) );

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));
$PAGE->set_heading($course->fullname);

// new topomojo class
$pageurl = null;
$pagevars = array();
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars);

// get workspace info
$object->workspace = get_workspace($object->userauth, $topomojo->workspaceid);

// Update the database.
if ($object->workspace) {
    // Update the database.
    $topomojo->name = $object->workspace->name;
    $topomojo->intro = $object->workspace->description;
    $DB->update_record('topomojo', $topomojo);
    // this generates lots of hvp module errors
    //rebuild_course_cache($topomojo->course);
}

//TODO send instructor to a different page where manual grading can occur

$eventid = null;
$viewid = null;
$startime = null;
$endtime = null;

$grader = new \mod_topomojo\utils\grade($object);
$gradepass = $grader->get_grade_item_passing_grade();
debugging("grade pass is $gradepass", DEBUG_DEVELOPER);

// show grade only if a passing grade is set
if ((int)$gradepass >0) {
    $showgrade = true;
} else {
    $showgrade = false;
}

$renderer = $PAGE->get_renderer('mod_topomojo');
echo $renderer->header();
$renderer->display_detail($topomojo, $topomojo->duration);


$isinstructor = has_capability('mod/topomojo:manage', $context);

if ($isinstructor) {
    // TODO display attempt user with formatting
    $user = $DB->get_record('user', array("id" => $attempt->userid));
    echo "Username: " . fullname($user);
}

// TODO why should display attempt show the form to start the lab? shouldnt this be a return form instead?
//$renderer->display_form($url, $object->topomojo->workspaceid);

global $DB;



//get tasks from db
if ($isinstructor) {

    if ($showgrade) {
        $renderer->display_grade($topomojo, $attempt->userid);
        $renderer->display_score($attempt->id);
    }


} else {

    if ($showgrade) {
        $renderer->display_grade($topomojo);
        $renderer->display_score($attempt->id);
    }
    echo "<br>Student view: displaying all visible and gradable tasks";

}

