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
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');

$id = optional_param('cmid', 0, PARAM_INT); // Course_module ID, or
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
require_capability('mod/topomojo:manage', $context);

/*
// TODO log an event when edit page is viewed
if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    topomojo_edit($topomojo, $course, $cm, $context);
}
*/

// Print the page header.
$url = new moodle_url ( '/mod/topomojo/edit.php', array ( 'cmid' => $cm->id ) );

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));
$PAGE->set_heading($course->fullname);

// new topomojo class
$pageurl = $url;
$pagevars = array();
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars);

list($pageurl, $contexts, $id, $cm, $topomojo, $pagevars) =
        question_edit_setup('editq', '/mod/topomojo/edit.php', true);


// get workspace info
$object->workspace = get_workspace($object->userauth, $topomojo->workspaceid);
#print_r($object->workspace);

// Update the database.
if ($object->workspace) {
    // TODO get questions from workspace
}


// get current state of workspace
$all_events = $object->list_events();
$moodle_events = $object->moodle_events($all_events);


$renderer_subtype = 'edit';
$renderer = $PAGE->get_renderer('mod_topomojo', $renderer_subtype);
echo $renderer->header();
//\core_question\local\bank\question_edit_contexts $contexts
$questionbankview = new \core_question\local\bank\view($contexts, $pageurl, $course, $cm);

// TODO get questions from db and make the right type of array
$questions = $DB->get_records('topomojo_questions', array('topomojoid' => $topomojo->id));

$renderer->listquestions($topomojo, $questions, $questionbankview, $cm, $pagevars);

// TODO display questions

echo $renderer->footer();


