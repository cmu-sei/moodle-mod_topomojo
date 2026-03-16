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
 * Unit tests for topomojo class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the topomojo class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_topomojo\topomojo
 */
class topomojo_test extends \advanced_testcase {

    /**
     * Setup function for creating common test data.
     */
    protected function setup_test_data() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a topomojo activity.
        $topomojo = $this->getDataGenerator()->create_module('topomojo', [
            'course' => $course->id,
            'name' => 'Test TopoMojo',
            'workspaceid' => 'test-workspace',
        ]);

        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id);

        return [$course, $topomojo, $cm];
    }

    /**
     * Test topomojo class construction.
     */
    public function test_topomojo_construction() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        // Create a topomojo instance without question manager (null pageurl).
        $topomojo = new topomojo($cm, $course, $topomojorecord);

        // Assertions.
        $this->assertInstanceOf(topomojo::class, $topomojo);
        $this->assertEquals($cm->id, $topomojo->getCM()->id);
        $this->assertInstanceOf(\context_module::class, $topomojo->getContext());
    }

    /**
     * Test has_capability method.
     */
    public function test_has_capability() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        $topomojo = new topomojo($cm, $course, $topomojorecord);

        // Admin should have manage capability.
        $result = $topomojo->has_capability('mod/topomojo:manage');
        $this->assertTrue($result);
    }

    /**
     * Test is_instructor method.
     */
    public function test_is_instructor() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        $topomojo = new topomojo($cm, $course, $topomojorecord);

        // Admin should be instructor.
        $this->assertTrue($topomojo->is_instructor());

        // Create a student user.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $topomojo2 = new topomojo($cm, $course, $topomojorecord);

        // Student should not be instructor.
        $this->assertFalse($topomojo2->is_instructor());
    }

    /**
     * Test get_openclose_state method.
     */
    public function test_get_openclose_state() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        // Test open state (no time restrictions).
        $topomojorecord->timeopen = 0;
        $topomojorecord->timeclose = 0;
        $topomojo = new topomojo($cm, $course, $topomojorecord);
        $this->assertEquals('open', $topomojo->get_openclose_state());

        // Test unopen state.
        $topomojorecord->timeopen = time() + 3600; // Opens in 1 hour.
        $topomojorecord->timeclose = time() + 7200;
        $topomojo = new topomojo($cm, $course, $topomojorecord);
        $this->assertEquals('unopen', $topomojo->get_openclose_state());

        // Test closed state.
        $topomojorecord->timeopen = time() - 7200;
        $topomojorecord->timeclose = time() - 3600; // Closed 1 hour ago.
        $topomojo = new topomojo($cm, $course, $topomojorecord);
        $this->assertEquals('closed', $topomojo->get_openclose_state());
    }

    /**
     * Test get_review_options method.
     */
    public function test_get_review_options() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        // Set review options.
        $topomojorecord->reviewattempt = 31; // All times.
        $topomojorecord->reviewcorrectness = 16; // After close only.
        $topomojorecord->reviewmarks = 31;
        $topomojorecord->reviewspecificfeedback = 0;
        $topomojorecord->reviewgeneralfeedback = 0;
        $topomojorecord->reviewrightanswer = 0;
        $topomojorecord->reviewoverallfeedback = 0;
        $topomojorecord->reviewmanualcomment = 0;

        $topomojo = new topomojo($cm, $course, $topomojorecord);
        $options = $topomojo->get_review_options();

        $this->assertInstanceOf(\stdClass::class, $options);
        $this->assertEquals(31, $options->reviewattempt);
        $this->assertEquals(16, $options->reviewcorrectness);
        $this->assertEquals(31, $options->reviewmarks);
    }

    /**
     * Test canreviewmarks method.
     */
    public function test_canreviewmarks() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        $options = new \stdClass();
        $options->reviewmarks = \mod_topomojo_display_options::LATER_WHILE_OPEN |
                                \mod_topomojo_display_options::AFTER_CLOSE;

        $topomojo = new topomojo($cm, $course, $topomojorecord);

        // Test open state - should be true.
        $this->assertTrue($topomojo->canreviewmarks($options, 'open'));

        // Test closed state - should be true.
        $this->assertTrue($topomojo->canreviewmarks($options, 'closed'));

        // Test with no review marks allowed.
        $options->reviewmarks = 0;
        $this->assertFalse($topomojo->canreviewmarks($options, 'open'));
        $this->assertFalse($topomojo->canreviewmarks($options, 'closed'));
    }

    /**
     * Test canreviewattempt method.
     */
    public function test_canreviewattempt() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        $options = new \stdClass();
        $options->reviewattempt = \mod_topomojo_display_options::LATER_WHILE_OPEN |
                                  \mod_topomojo_display_options::AFTER_CLOSE;

        $topomojo = new topomojo($cm, $course, $topomojorecord);

        // Test open state - should be true.
        $this->assertTrue($topomojo->canreviewattempt($options, 'open'));

        // Test closed state - should be true.
        $this->assertTrue($topomojo->canreviewattempt($options, 'closed'));

        // Test with no review attempt allowed.
        $options->reviewattempt = 0;
        $this->assertFalse($topomojo->canreviewattempt($options, 'open'));
        $this->assertFalse($topomojo->canreviewattempt($options, 'closed'));
    }

    /**
     * Test save method.
     */
    public function test_save() {
        global $DB;
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        $topomojo = new topomojo($cm, $course, $topomojorecord);

        // Modify a property.
        $topomojo->topomojo->name = 'Modified Name';

        // Save the record.
        $result = $topomojo->save();
        $this->assertTrue($result);

        // Verify in database.
        $record = $DB->get_record('topomojo', ['id' => $topomojorecord->id]);
        $this->assertEquals('Modified Name', $record->name);
    }

    /**
     * Test getall_attempts with no attempts.
     */
    public function test_getall_attempts_empty() {
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        $topomojo = new topomojo($cm, $course, $topomojorecord);
        $attempts = $topomojo->getall_attempts();

        // Should return empty array when no attempts exist.
        $this->assertIsArray($attempts);
        $this->assertEmpty($attempts);
    }

    /**
     * Test getall_attempts with existing attempts.
     */
    public function test_getall_attempts_with_data() {
        global $DB, $USER;
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        // Create an attempt record.
        $attempt = new \stdClass();
        $attempt->topomojoid = $topomojorecord->id;
        $attempt->userid = $USER->id;
        $attempt->state = topomojo_attempt::INPROGRESS;
        $attempt->timestart = time();
        $attempt->timemodified = time();
        $DB->insert_record('topomojo_attempts', $attempt);

        $topomojo = new topomojo($cm, $course, $topomojorecord);
        $attempts = $topomojo->getall_attempts('open');

        // Should return the attempt.
        $this->assertIsArray($attempts);
        $this->assertCount(1, $attempts);
        $this->assertInstanceOf(topomojo_attempt::class, $attempts[0]);
    }

    /**
     * Test get_attempts_by_user.
     */
    public function test_get_attempts_by_user() {
        global $DB;
        list($course, $topomojorecord, $cm) = $this->setup_test_data();

        // Create a user.
        $user = $this->getDataGenerator()->create_user();

        // Create an attempt for the user.
        $attempt = new \stdClass();
        $attempt->topomojoid = $topomojorecord->id;
        $attempt->userid = $user->id;
        $attempt->state = topomojo_attempt::FINISHED;
        $attempt->timestart = time();
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $DB->insert_record('topomojo_attempts', $attempt);

        $topomojo = new topomojo($cm, $course, $topomojorecord);
        $attempts = $topomojo->get_attempts_by_user($user->id, 'closed');

        // Should return the attempt.
        $this->assertIsArray($attempts);
        $this->assertCount(1, $attempts);
        $this->assertInstanceOf(topomojo_attempt::class, $attempts[0]);
    }

    /**
     * Test review fields static property.
     */
    public function test_review_fields() {
        $fields = topomojo::$reviewfields;

        $this->assertIsArray($fields);
        $this->assertArrayHasKey('attempt', $fields);
        $this->assertArrayHasKey('correctness', $fields);
        $this->assertArrayHasKey('marks', $fields);
        $this->assertArrayHasKey('specificfeedback', $fields);
        $this->assertArrayHasKey('generalfeedback', $fields);
        $this->assertArrayHasKey('rightanswer', $fields);
        $this->assertArrayHasKey('overallfeedback', $fields);
    }
}
