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

//require('../../config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/mod/topomojo/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$c = optional_param('c', 0, PARAM_INT);  // Instance ID - it should be named as the first character of the module.
global $USER;

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
$url = new moodle_url ( '/mod/topomojo/review.php', ['id' => $cm->id]);
$returnurl = new moodle_url ( '/mod/topomojo/view.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));

// new topomojo class
$pageurl = $url;
$pagevars = [];
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars);

$renderer = $PAGE->get_renderer('mod_topomojo');
echo $renderer->header();
// $renderer->display_return_form($returnurl, $id);

if (optional_param('deleteall', 0, PARAM_BOOL) && confirm_sesskey() && $object->is_instructor()) {
    $object->delete_all_attempts_and_grades();
    \core\notification::success(get_string('attemptsdeleted', 'mod_topomojo'));
}

if ($object->is_instructor()) {
    $attempts = $object->getall_attempts('closed', $review = true);
    echo $renderer->display_attempts($attempts, $showgrade = true, $showuser = true);
    $deleteurl = new moodle_url($PAGE->url, ['deleteall' => 1]);
    echo $OUTPUT->single_button($deleteurl, get_string('deleteallattempts', 'mod_topomojo'), 'post');
} else {
    $userid = $USER->id;
    $attempts = $object->get_attempts_by_user($userid, 'closed');
    echo $renderer->display_attempts($attempts, $showgrade = true, $showuser = false);
}

echo $renderer->footer();
