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
 * Unit tests for grading attempts that carry no questions.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\utils;

use mod_topomojo\questionmanager;
use mod_topomojo\topomojo;
use mod_topomojo\topomojo_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/lib.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

/**
 * Tests for \mod_topomojo\utils\grade.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_topomojo\utils\grade
 */
final class grade_test extends \advanced_testcase {

    /**
     * Builds an activity plus an attempt that has no question usage, which is what both bulk deploy
     * and a variant with no importable questions leave behind.
     *
     * @return array [topomojo, topomojo_attempt]
     */
    private function activity_with_questionless_attempt(): array {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $record = $this->getDataGenerator()->create_module('topomojo', [
            'course' => $course->id,
            'name' => 'Questionless lab',
            'workspaceid' => 'ws-questionless',
            'grade' => 100,
            // Set explicitly: the column defaults to 0, which apply_grading_method() rejects with
            // "Invalid grade method". Only mod_form guarantees a valid value.
            'grademethod' => \mod_topomojo\utils\scaletypes::TOPOMOJO_HIGHESTATTEMPTGRADE,
        ]);
        $cm = get_coursemodule_from_instance('topomojo', $record->id);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // No questionusageid and no layout: the column default is 0 and nothing populates either.
        $row = (object) [
            'topomojoid' => $record->id,
            'userid' => $user->id,
            'eventid' => 'gs-questionless',
            'workspaceid' => 'ws-questionless',
            'launchpointurl' => '',
            'state' => topomojo_attempt::INPROGRESS,
            'preview' => 0,
            'timestart' => time(),
            'timemodified' => time(),
            'timefinish' => null,
            'endtime' => time() + HOURSECS,
            'variant' => 1,
            'score' => 0,
        ];
        $row->id = $DB->insert_record('topomojo_attempts', $row);

        $object = new topomojo($cm, $course, $record);
        $questionmanager = new questionmanager($object, $object->renderer);
        $attempt = new topomojo_attempt(
            $questionmanager,
            $DB->get_record('topomojo_attempts', ['id' => $row->id])
        );

        // Loading an attempt emits developer-level tracing that would otherwise fail the test.
        $this->resetDebugging();

        return [$object, $attempt];
    }

    /**
     * An attempt with no question usage has to grade as zero rather than fatal.
     *
     * Before the guard, getSlots() returned [''] for a null layout and grading dereferenced the
     * null question usage: "Call to a member function get_question_max_mark() on null".
     */
    public function test_grading_an_attempt_with_no_question_usage_returns_zero(): void {
        [$object, $attempt] = $this->activity_with_questionless_attempt();

        $this->assertNull($attempt->get_quba(), 'A layout-less attempt should have no question usage.');
        $this->assertSame([], $attempt->getSlots(), 'A layout-less attempt should report no slots.');

        $grader = new grade($object);
        $this->assertSame(0, $grader->calculate_attempt_grade($attempt));
        $this->resetDebugging();
    }

    /**
     * The same attempt has to survive the full grading pass, which is what challenge.php and
     * viewattempt.php actually call when a lab is stopped or reviewed.
     */
    public function test_processing_an_attempt_with_no_question_usage_does_not_fatal(): void {
        [$object, $attempt] = $this->activity_with_questionless_attempt();

        $grader = new grade($object);
        $grader->process_attempt($attempt);
        $this->resetDebugging();

        $grades = grade::get_user_grade($object->topomojo, $attempt->userid);
        foreach ($grades as $awarded) {
            $this->assertEquals(0, $awarded);
        }
    }
}
