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
 * Unit tests for mod_topomojo events.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for mod_topomojo events.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_topomojo\event\course_module_viewed
 * @covers \mod_topomojo\event\attempt_started
 * @covers \mod_topomojo\event\attempt_ended
 */
class events_test extends \advanced_testcase {

    /**
     * Setup function for creating common test data.
     */
    protected function setup_test_data() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', [
            'course' => $course->id,
            'name' => 'Test TopoMojo Activity',
        ]);
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id);
        $context = \context_module::instance($cm->id);

        return [$course, $topomojo, $cm, $context];
    }

    /**
     * Test course_module_viewed event.
     */
    public function test_course_module_viewed_event() {
        list($course, $topomojo, $cm, $context) = $this->setup_test_data();

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $params = [
            'context' => $context,
            'objectid' => $topomojo->id,
        ];

        $event = \mod_topomojo\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('topomojo', $topomojo);
        $event->trigger();

        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf('\mod_topomojo\event\course_module_viewed', $event);
        $this->assertEquals($topomojo->id, $event->objectid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals('r', $event->crud);
        $this->assertEquals($event::LEVEL_PARTICIPATING, $event->edulevel);
        $this->assertEquals('topomojo', $event->objecttable);
    }

    /**
     * Test course_module_viewed event get_objectid_mapping.
     */
    public function test_course_module_viewed_get_objectid_mapping() {
        $mapping = \mod_topomojo\event\course_module_viewed::get_objectid_mapping();

        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('db', $mapping);
        $this->assertArrayHasKey('restore', $mapping);
        $this->assertEquals('topomojo', $mapping['db']);
        $this->assertEquals('topomojo', $mapping['restore']);
    }

    /**
     * Test attempt_started event.
     */
    public function test_attempt_started_event() {
        global $USER;
        list($course, $topomojo, $cm, $context) = $this->setup_test_data();

        // Create an attempt.
        $attempt = new \stdClass();
        $attempt->id = 1;
        $attempt->topomojoid = $topomojo->id;
        $attempt->userid = $USER->id;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $params = [
            'context' => $context,
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'other' => [
                'topomojoid' => $topomojo->id,
            ],
        ];

        $event = \mod_topomojo\event\attempt_started::create($params);
        $event->trigger();

        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf('\mod_topomojo\event\attempt_started', $event);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
    }

    /**
     * Test attempt_ended event.
     */
    public function test_attempt_ended_event() {
        global $USER;
        list($course, $topomojo, $cm, $context) = $this->setup_test_data();

        // Create an attempt.
        $attempt = new \stdClass();
        $attempt->id = 1;
        $attempt->topomojoid = $topomojo->id;
        $attempt->userid = $USER->id;

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $params = [
            'context' => $context,
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'other' => [
                'topomojoid' => $topomojo->id,
            ],
        ];

        $event = \mod_topomojo\event\attempt_ended::create($params);
        $event->trigger();

        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf('\mod_topomojo\event\attempt_ended', $event);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
    }

    /**
     * Test course_module_instance_list_viewed event.
     */
    public function test_course_module_instance_list_viewed_event() {
        list($course, $topomojo, $cm, $context) = $this->setup_test_data();

        $coursecontext = \context_course::instance($course->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $params = [
            'context' => $coursecontext,
        ];

        $event = \mod_topomojo\event\course_module_instance_list_viewed::create($params);
        $event->trigger();

        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf('\mod_topomojo\event\course_module_instance_list_viewed', $event);
        $this->assertEquals($coursecontext->id, $event->contextid);
    }

    /**
     * Test that event data is properly set.
     */
    public function test_event_snapshot_data() {
        list($course, $topomojo, $cm, $context) = $this->setup_test_data();

        $sink = $this->redirectEvents();

        $params = [
            'context' => $context,
            'objectid' => $topomojo->id,
        ];

        $event = \mod_topomojo\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('topomojo', $topomojo);
        $event->trigger();

        $events = $sink->get_events();
        $event = reset($events);
        $sink->close();

        // Test that snapshots are available.
        $this->assertNotEmpty($event->get_record_snapshot('course_modules', $cm->id));
        $this->assertNotEmpty($event->get_record_snapshot('course', $course->id));
        $this->assertNotEmpty($event->get_record_snapshot('topomojo', $topomojo->id));
    }
}
