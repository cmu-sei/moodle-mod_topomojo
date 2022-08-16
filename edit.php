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

$url = new moodle_url ( '/mod/topomojo/edit.php', array ( 'cmid' => $cm->id ) );

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));
$PAGE->set_heading($course->fullname);

$action = optional_param('action', '', PARAM_ALPHA);
$addquestionlist = optional_param('addquestionlist', '', PARAM_ALPHA);

// new topomojo class
$pageurl = $url;
$pagevars = array();
$pagevars['pageurl'] = $pageurl;
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars, 'edit');

list($pageurl, $contexts, $id, $cm, $topomojo, $pagevars) =
        question_edit_setup('editq', '/mod/topomojo/edit.php', true);


$topomojohasattempts = topomojo_has_attempts($topomojo->id);

$questionmanager = $object->get_question_manager();

$renderer = $object->renderer;
$questionbankview = new \mod_topomojo\question\bank\custom_view($contexts, $pageurl, $course, $cm, $topomojo);

if ($addquestionlist) {
    $action = 'addquestionlist';
}

if ($object->topomojo->importchallenge) {
    $challenge = get_challenge($object->userauth, $object->topomojo->workspaceid);
    if (!$challenge) {
        print_error("this lab has no challenge");
    }

    if ($object->topomojo->variant == 0) {
        $type = 'info';
        $message = get_string('importtoporandom', 'topomojo');
        $variants = count($challenge->variants);
        $addtoquiz = false;
        debugging("random variant set for this lab, adding all questions", DEBUG_DEVELOPER);
        for ($variant = 0; $variant < $variants; $variant++) {
            $questionmanager->process_variant_questions($context, $object, $variant, $challenge, $addtoquiz);
        }
    } else if ($object->topomojo->variant > 0) {
        $type = 'info';
        $message = get_string('importtopo', 'topomojo');

        //adjust for offset
        $variant = $object->topomojo->variant - 1;
        $addtoquiz = true;
        $questionmanager->process_variant_questions($context, $object, $variant, $challenge, $addtoquiz);
    }
    $renderer->setMessage($type, $message);
}

// handle actions
switch ($action) {
    case 'addquestionlist':
        $rawdata = (array) data_submitted();
        foreach ($rawdata as $key => $value) { // Parse input for question ids.
            if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
                $key = $matches[1];
                $questionid = $key;
                if (!$questionmanager->add_question($questionid)) {
                    $type = 'error';
                    $message = get_string('qadderror', 'topomojo');
                    $renderer->setMessage($type, $message);
                    break;
                } else {
                    $type = 'success';
                    $message = get_string('qaddsuccess', 'topomojo');
                    $renderer->setMessage($type, $message);
                }
            }
        }
        $renderer->setMessage($type, $message);
        $renderer->print_header();
        $questions = $questionmanager->get_questions();
        $renderer->listquestions($topomojohasattempts, $questions, $questionbankview, $cm, $pagevars);
        break;
    case 'addquestion':
            // Add a single question to the current topomojo.
        if ($topomojohasattempts) {
            debugging(get_string('cannoteditafterattempts', 'topomojo'), DEBUG_DEVELOPER);
        } else {
            $questionid = required_param('questionid', PARAM_INT);
            if ($questionmanager->add_question($questionid)) {
                $type = 'success';
                $message = get_string('qaddsuccess', 'topomojo');
            } else {
                $type = 'error';
                $message = get_string('qadderror', 'topomojo');
            }
            $renderer->setMessage($type, $message);
            $renderer->print_header();
            $questions = $questionmanager->get_questions();
            $renderer->listquestions($topomojohasattempts, $questions, $questionbankview, $cm, $pagevars);
        }
        break;
    case 'deletequestion':
        if ($topomojohasattempts) {
            debugging(get_string('cannoteditafterattempts', 'topomojo'), DEBUG_DEVELOPER);
        } else {

            $questionid = required_param('questionid', PARAM_INT);
            if ($questionmanager->delete_question($questionid)) {
                $type = 'success';
                $message = get_string('qdeletesucess', 'topomojo');
            } else {
                $type = 'error';
                $message = get_string('qdeleteerror', 'topomojo');
            }

            $renderer->setMessage($type, $message);
            $renderer->print_header();
            $questions = $questionmanager->get_questions();
            $renderer->listquestions($topomojohasattempts, $questions, $questionbankview, $cm, $pagevars);
        }
        break;
    case 'dragdrop': // this is a javascript callack case for the drag and drop of questions using ajax.
        if ($topomojohasattempts) {
            debugging(get_string('cannoteditafterattempts', 'topomojo'), DEBUG_DEVELOPER);
        } else {
            $jsonlib = new \mod_topomojo\utils\jsonlib();

            $questionorder = optional_param('questionorder', '', PARAM_RAW);

            if ($questionorder === '') {
                $jsonlib->send_error('invalid request');
            }
            $questionorder = explode(',', $questionorder);

            $result = $questionmanager->set_full_order($questionorder);
            if ($result === true) {
                $jsonlib->set('success', 'true');
                $jsonlib->send_response();
            } else {
                $jsonlib->send_error('unable to re-sort questions');
            }

        }
        break;
    case 'editquestion':
        $this->renderer->print_header();
        $questionid = required_param('topomojoquestionid', PARAM_INT);
        $questionmanager->edit_question($questionid);
        break;
    default:
        $renderer->print_header();
        $questions = $questionmanager->get_questions();
        $renderer->listquestions($topomojohasattempts, $questions, $questionbankview, $cm, $pagevars);
}


$renderer->footer();
