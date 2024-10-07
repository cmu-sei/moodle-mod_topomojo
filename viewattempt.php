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

//require('../../config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/mod/topomojo/locallib.php");

$a = required_param('a', PARAM_INT);  // Attempt ID
$action = optional_param('action', 'view', PARAM_TEXT);
$slot = optional_param('slot', '', PARAM_INT);

if (!$a) {
    $a = required_param('attemptid', PARAM_INT);  // Attempt ID
}
$attemptid = $a;

try {
        $attempt    = $DB->get_record('topomojo_attempts', ['id' => $a], '*', MUST_EXIST);
        $topomojo   = $DB->get_record('topomojo', ['id' => $attempt->topomojoid], '*', MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $topomojo->course], '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('topomojo', $topomojo->id, $course->id, false, MUST_EXIST);
} catch (Exception $e) {
    throw new moodle_exception("invalid attempt id passed");
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
// TODO create review attempt capability
require_capability('mod/topomojo:view', $context);

// TODO log event attempt views
// if ($_SERVER['REQUEST_METHOD'] == "GET") {
// Completion and trigger events.
//topomojo_view($topomojo, $course, $cm, $context);
// }

// Print the page header.
$url = new moodle_url ( '/mod/topomojo/viewattempt.php', ['a' => $a]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));
$PAGE->set_heading($course->fullname);

// New topomojo class
$pageurl = $url;
$pagevars = ['a' => $a, 'pageurl' => $pageurl];
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars);
$attempt = $object->get_attempt($attemptid);
$cmid = $object->getCM()->id;

if (!$attempt) {
    $object->renderer->base_header();
    $object->renderer->render_quiz($attempt, $pageurl, $cmid);
    $object->renderer->base_footer();
} else {

    switch ($action) {
        case 'savecomment':

            $success = $attempt->process_comment($object->topomojo, $slot);
            if ($success) {
                // If successful recalculate the grade for the attempt's userid as the grader can update grades on the questions
                $object->renderer->base_header();

                $grader = new \mod_topomojo\utils\grade($object);
                $grader->process_attempt($attempt);

                $object->renderer->setMessage('success', 'Successfully saved comment/grade');
                $object->renderer->render_attempt($attempt);
            } else {
                $object->renderer->setMessage('error', 'Couldn\'t save comment/grade');
                $object->renderer->render_attempt($attempt);
            }
            $object->renderer->base_footer();

            break;
        default:

            // TODO create attempt viewed event
            $params = array(
                'relateduserid' => $USER->id,
                'objectid'      => $pagevars['a'],
                'context'       => $context,
                'other'         => [
                    'topomojoid'   => $object->topomojo->id,
                ],
            );
            //$event = \mod_topomojo\event\attempt_viewed::create($params);
            //$event->add_record_snapshot('topomojo_attempts', $attempt->get_attempt());
            //$event->trigger();

            $object->renderer->base_header();
            $object->renderer->render_attempt($attempt, $pageurl, $cmid);
            $object->renderer->base_footer();

            break;
    }
}

