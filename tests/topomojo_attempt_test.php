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
 * Unit tests for topomojo_attempt class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the topomojo_attempt class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_topomojo\topomojo_attempt
 */
class topomojo_attempt_test extends \advanced_testcase {

    /**
     * Test attempt class constants.
     */
    public function test_attempt_constants() {
        $this->assertEquals(0, topomojo_attempt::NOTSTARTED);
        $this->assertEquals(10, topomojo_attempt::INPROGRESS);
        $this->assertEquals(20, topomojo_attempt::ABANDONED);
        $this->assertEquals(30, topomojo_attempt::FINISHED);
    }

    /**
     * Test attempt construction with no data (new attempt).
     */
    public function test_attempt_construction_new() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $attempt = new topomojo_attempt(null);

        $this->assertInstanceOf(topomojo_attempt::class, $attempt);
        $this->assertInstanceOf(\stdClass::class, $attempt->get_attempt());
    }

    /**
     * Test attempt construction with existing data.
     */
    public function test_attempt_construction_existing() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $dbattempt = new \stdClass();
        $dbattempt->id = 123;
        $dbattempt->topomojoid = 1;
        $dbattempt->userid = 2;
        $dbattempt->state = topomojo_attempt::INPROGRESS;
        $dbattempt->timestart = time();
        $dbattempt->timemodified = time();

        $attempt = new topomojo_attempt(null, $dbattempt);

        $this->assertInstanceOf(topomojo_attempt::class, $attempt);
        $this->assertEquals(123, $attempt->id);
        $this->assertEquals(topomojo_attempt::INPROGRESS, $attempt->state);
    }

    /**
     * Test getState method.
     *
     * @dataProvider state_provider
     */
    public function test_getstate($statevalue, $expected) {
        $this->resetAfterTest();

        $dbattempt = new \stdClass();
        $dbattempt->id = 1;
        $dbattempt->state = $statevalue;

        $attempt = new topomojo_attempt(null, $dbattempt);

        $this->assertEquals($expected, $attempt->getState());
    }

    /**
     * Data provider for test_getstate.
     *
     * @return array
     */
    public static function state_provider() {
        return [
            'notstarted' => [topomojo_attempt::NOTSTARTED, 'notstarted'],
            'inprogress' => [topomojo_attempt::INPROGRESS, 'inprogress'],
            'abandoned' => [topomojo_attempt::ABANDONED, 'abandoned'],
            'finished' => [topomojo_attempt::FINISHED, 'finished'],
        ];
    }

    /**
     * Test getState with invalid state throws exception.
     */
    public function test_getstate_invalid() {
        $this->resetAfterTest();

        $dbattempt = new \stdClass();
        $dbattempt->id = 1;
        $dbattempt->state = 999; // Invalid state.

        $attempt = new topomojo_attempt(null, $dbattempt);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('undefined status for attempt');
        $attempt->getState();
    }

    /**
     * Test setState method.
     */
    public function test_setstate() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and topomojo.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Create attempt in database.
        $dbattempt = new \stdClass();
        $dbattempt->topomojoid = $topomojo->id;
        $dbattempt->userid = 2;
        $dbattempt->state = topomojo_attempt::NOTSTARTED;
        $dbattempt->timestart = time();
        $dbattempt->timemodified = time();
        $dbattempt->endtime = time() + 3600;
        $dbattempt->id = $DB->insert_record('topomojo_attempts', $dbattempt);

        $attempt = new topomojo_attempt(null, $dbattempt);

        // Change state to inprogress.
        $result = $attempt->setState('inprogress');
        $this->assertTrue($result);
        $this->assertEquals(topomojo_attempt::INPROGRESS, $attempt->state);

        // Verify in database.
        $record = $DB->get_record('topomojo_attempts', ['id' => $dbattempt->id]);
        $this->assertEquals(topomojo_attempt::INPROGRESS, $record->state);
    }

    /**
     * Test setState with invalid state.
     */
    public function test_setstate_invalid() {
        $this->resetAfterTest();

        $dbattempt = new \stdClass();
        $dbattempt->id = 1;
        $dbattempt->state = topomojo_attempt::NOTSTARTED;

        $attempt = new topomojo_attempt(null, $dbattempt);

        $result = $attempt->setState('invalid_state');
        $this->assertFalse($result);
    }

    /**
     * Test save method for new attempt.
     */
    public function test_save_new_attempt() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Create new attempt.
        $attempt = new topomojo_attempt(null);
        $attempt->topomojoid = $topomojo->id;
        $attempt->userid = 2;
        $attempt->state = topomojo_attempt::INPROGRESS;
        $attempt->timestart = time();
        $attempt->endtime = time() + 3600;

        // Save attempt.
        $result = $attempt->save();
        $this->assertTrue($result);

        // Verify it was saved.
        $this->assertNotEmpty($attempt->id);
        $record = $DB->get_record('topomojo_attempts', ['id' => $attempt->id]);
        $this->assertNotEmpty($record);
        $this->assertEquals($topomojo->id, $record->topomojoid);
    }

    /**
     * Test save method for existing attempt.
     */
    public function test_save_existing_attempt() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Create attempt in database.
        $dbattempt = new \stdClass();
        $dbattempt->topomojoid = $topomojo->id;
        $dbattempt->userid = 2;
        $dbattempt->state = topomojo_attempt::INPROGRESS;
        $dbattempt->timestart = time();
        $dbattempt->timemodified = time();
        $dbattempt->endtime = time() + 3600;
        $dbattempt->id = $DB->insert_record('topomojo_attempts', $dbattempt);

        $attempt = new topomojo_attempt(null, $dbattempt);

        // Modify and save.
        $attempt->score = 100;
        $result = $attempt->save();
        $this->assertTrue($result);

        // Verify changes.
        $record = $DB->get_record('topomojo_attempts', ['id' => $dbattempt->id]);
        $this->assertEquals(100, $record->score);
    }

    /**
     * Test close_attempt method.
     */
    public function test_close_attempt() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Create open attempt.
        $dbattempt = new \stdClass();
        $dbattempt->topomojoid = $topomojo->id;
        $dbattempt->userid = 2;
        $dbattempt->state = topomojo_attempt::INPROGRESS;
        $dbattempt->timestart = time();
        $dbattempt->timemodified = time();
        $dbattempt->endtime = time() + 3600;
        $dbattempt->id = $DB->insert_record('topomojo_attempts', $dbattempt);

        $attempt = new topomojo_attempt(null, $dbattempt);

        // Close attempt.
        $result = $attempt->close_attempt();
        $this->assertTrue($result);
        $this->assertEquals(topomojo_attempt::FINISHED, $attempt->state);
        $this->assertNotEmpty($attempt->timefinish);

        // Verify in database.
        $record = $DB->get_record('topomojo_attempts', ['id' => $dbattempt->id]);
        $this->assertEquals(topomojo_attempt::FINISHED, $record->state);
        $this->assertNotEmpty($record->timefinish);
    }

    /**
     * Test magic __get method.
     */
    public function test_magic_get() {
        $this->resetAfterTest();

        $dbattempt = new \stdClass();
        $dbattempt->id = 123;
        $dbattempt->userid = 456;
        $dbattempt->state = topomojo_attempt::INPROGRESS;

        $attempt = new topomojo_attempt(null, $dbattempt);

        $this->assertEquals(123, $attempt->id);
        $this->assertEquals(456, $attempt->userid);
        $this->assertEquals(topomojo_attempt::INPROGRESS, $attempt->state);
    }

    /**
     * Test magic __get with undefined property.
     */
    public function test_magic_get_undefined() {
        $this->resetAfterTest();

        $dbattempt = new \stdClass();
        $dbattempt->id = 123;

        $attempt = new topomojo_attempt(null, $dbattempt);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('undefined property(nonexistent)');
        $value = $attempt->nonexistent;
    }

    /**
     * Test magic __set method.
     */
    public function test_magic_set() {
        $this->resetAfterTest();

        $attempt = new topomojo_attempt(null);

        $attempt->userid = 789;
        $attempt->score = 95.5;

        $this->assertEquals(789, $attempt->userid);
        $this->assertEquals(95.5, $attempt->score);
    }

    /**
     * Test getSlots method.
     */
    public function test_getslots() {
        $this->resetAfterTest();

        $dbattempt = new \stdClass();
        $dbattempt->id = 1;
        $dbattempt->layout = '1,2,3,4';

        $attempt = new topomojo_attempt(null, $dbattempt);

        $slots = $attempt->getSlots();

        $this->assertIsArray($slots);
        $this->assertEquals(['1', '2', '3', '4'], $slots);
    }

    /**
     * Test getSlots with empty layout.
     */
    public function test_getslots_empty() {
        $this->resetAfterTest();

        $dbattempt = new \stdClass();
        $dbattempt->id = 1;

        $attempt = new topomojo_attempt(null, $dbattempt);

        $slots = $attempt->getSlots();

        $this->assertIsArray($slots);
        $this->assertEquals([''], $slots);
    }

    /**
     * Test get_question_number method.
     */
    public function test_get_question_number() {
        $this->resetAfterTest();

        $attempt = new topomojo_attempt(null);

        // First call should return "1".
        $num = $attempt->get_question_number();
        $this->assertEquals('1', $num);

        // Subsequent call should still return "1" (only incremented by separate method).
        $num = $attempt->get_question_number();
        $this->assertEquals('1', $num);
    }
}
