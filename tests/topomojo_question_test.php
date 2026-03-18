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
 * Unit tests for topomojo_question class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the topomojo_question class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_topomojo\topomojo_question
 */
class topomojo_question_test extends \advanced_testcase {

    /**
     * Test question construction.
     */
    public function test_question_construction() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 100;
        $questionobj->name = 'Test Question';
        $questionobj->questiontext = 'What is the answer?';

        $question = new topomojo_question(1, 10.0, $questionobj);

        $this->assertInstanceOf(topomojo_question::class, $question);
        $this->assertEquals(1, $question->getId());
        $this->assertEquals(10.0, $question->getPoints());
        $this->assertEquals($questionobj, $question->getQuestion());
    }

    /**
     * Test getId method.
     */
    public function test_getid() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(42, 5.0, $questionobj);

        $this->assertEquals(42, $question->getId());
    }

    /**
     * Test getPoints method.
     */
    public function test_getpoints() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(1, 15.5, $questionobj);

        $this->assertEquals(15.5, $question->getPoints());
    }

    /**
     * Test getPoints with integer value.
     */
    public function test_getpoints_integer() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(1, 10, $questionobj);

        $this->assertEquals(10, $question->getPoints());
    }

    /**
     * Test getQuestion method.
     */
    public function test_getquestion() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 75;
        $questionobj->name = 'Sample Question';
        $questionobj->questiontext = 'What is 2 + 2?';
        $questionobj->qtype = 'multichoice';

        $question = new topomojo_question(1, 5.0, $questionobj);

        $result = $question->getQuestion();

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals(75, $result->id);
        $this->assertEquals('Sample Question', $result->name);
        $this->assertEquals('What is 2 + 2?', $result->questiontext);
        $this->assertEquals('multichoice', $result->qtype);
    }

    /**
     * Test set_slot method.
     */
    public function test_set_slot() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(1, 5.0, $questionobj);

        $question->set_slot(3);

        $this->assertEquals(3, $question->get_slot());
    }

    /**
     * Test get_slot method.
     */
    public function test_get_slot() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(1, 5.0, $questionobj);

        // Set and get slot.
        $question->set_slot(7);
        $result = $question->get_slot();

        $this->assertEquals(7, $result);
    }

    /**
     * Test get_slot before setting.
     */
    public function test_get_slot_not_set() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(1, 5.0, $questionobj);

        $result = $question->get_slot();

        $this->assertNull($result);
    }

    /**
     * Test multiple questions with different properties.
     */
    public function test_multiple_questions() {
        $this->resetAfterTest();

        $questionobj1 = new \stdClass();
        $questionobj1->id = 100;
        $questionobj1->name = 'Question 1';

        $questionobj2 = new \stdClass();
        $questionobj2->id = 200;
        $questionobj2->name = 'Question 2';

        $question1 = new topomojo_question(1, 10.0, $questionobj1);
        $question2 = new topomojo_question(2, 20.0, $questionobj2);

        $this->assertEquals(1, $question1->getId());
        $this->assertEquals(10.0, $question1->getPoints());
        $this->assertEquals('Question 1', $question1->getQuestion()->name);

        $this->assertEquals(2, $question2->getId());
        $this->assertEquals(20.0, $question2->getPoints());
        $this->assertEquals('Question 2', $question2->getQuestion()->name);
    }

    /**
     * Test question with zero points.
     */
    public function test_question_zero_points() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(1, 0, $questionobj);

        $this->assertEquals(0, $question->getPoints());
    }

    /**
     * Test question with negative points (edge case).
     */
    public function test_question_negative_points() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 50;

        $question = new topomojo_question(1, -5.0, $questionobj);

        $this->assertEquals(-5.0, $question->getPoints());
    }

    /**
     * Test question object properties are preserved.
     */
    public function test_question_object_properties_preserved() {
        $this->resetAfterTest();

        $questionobj = new \stdClass();
        $questionobj->id = 123;
        $questionobj->name = 'Complex Question';
        $questionobj->questiontext = 'Describe the OSI model';
        $questionobj->qtype = 'essay';
        $questionobj->defaultmark = 10;
        $questionobj->penalty = 0.3333333;
        $questionobj->length = 1;

        $question = new topomojo_question(5, 10.0, $questionobj);

        $retrieved = $question->getQuestion();

        $this->assertEquals(123, $retrieved->id);
        $this->assertEquals('Complex Question', $retrieved->name);
        $this->assertEquals('Describe the OSI model', $retrieved->questiontext);
        $this->assertEquals('essay', $retrieved->qtype);
        $this->assertEquals(10, $retrieved->defaultmark);
        $this->assertEquals(0.3333333, $retrieved->penalty);
        $this->assertEquals(1, $retrieved->length);
    }
}
