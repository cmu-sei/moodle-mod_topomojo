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
 * Unit tests for lib.php functions.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/lib.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

/**
 * Unit tests for topomojo lib functions.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::topomojo_supports
 * @covers ::topomojo_add_instance
 * @covers ::topomojo_update_instance
 * @covers ::topomojo_delete_instance
 */
class lib_test extends \advanced_testcase {

    /**
     * Test topomojo_supports function.
     *
     * @dataProvider supports_provider
     */
    public function test_topomojo_supports($feature, $expected) {
        $this->resetAfterTest();
        $result = topomojo_supports($feature);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for test_topomojo_supports.
     *
     * @return array
     */
    public static function supports_provider() {
        return [
            'MOD_ARCHETYPE' => [FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER],
            'GROUPS' => [FEATURE_GROUPS, false],
            'GROUPINGS' => [FEATURE_GROUPINGS, false],
            'MOD_INTRO' => [FEATURE_MOD_INTRO, true],
            'COMPLETION_TRACKS_VIEWS' => [FEATURE_COMPLETION_TRACKS_VIEWS, true],
            'GRADE_HAS_GRADE' => [FEATURE_GRADE_HAS_GRADE, true],
            'GRADE_OUTCOMES' => [FEATURE_GRADE_OUTCOMES, false],
            'BACKUP_MOODLE2' => [FEATURE_BACKUP_MOODLE2, true],
            'SHOW_DESCRIPTION' => [FEATURE_SHOW_DESCRIPTION, true],
            'UNKNOWN_FEATURE' => ['unknown_feature', null],
        ];
    }

    /**
     * Test topomojo_get_extra_capabilities function.
     */
    public function test_topomojo_get_extra_capabilities() {
        $this->resetAfterTest();
        $caps = topomojo_get_extra_capabilities();
        $this->assertIsArray($caps);
        $this->assertContains('moodle/site:accessallgroups', $caps);
    }

    /**
     * Test topomojo_reset_userdata function.
     */
    public function test_topomojo_reset_userdata() {
        $this->resetAfterTest();
        $data = new \stdClass();
        $result = topomojo_reset_userdata($data);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test topomojo_get_post_actions function.
     */
    public function test_topomojo_get_post_actions() {
        $this->resetAfterTest();
        $actions = topomojo_get_post_actions();
        $this->assertIsArray($actions);
        $this->assertContains('update', $actions);
        $this->assertContains('add', $actions);
    }

    /**
     * Test topomojo_add_instance function.
     */
    public function test_topomojo_add_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create module data.
        $topomojo = new \stdClass();
        $topomojo->course = $course->id;
        $topomojo->name = 'Test TopoMojo Activity';
        $topomojo->intro = 'Test introduction';
        $topomojo->introformat = FORMAT_HTML;
        $topomojo->workspaceid = 'test-workspace-123';
        $topomojo->timeopen = 0;
        $topomojo->timeclose = 0;
        $topomojo->timelimit = 0;
        $topomojo->isfeatured = 0;
        $topomojo->preferredbehaviour = 'deferredfeedback';

        // Create course module.
        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'topomojo';
        $moduleinfo->course = $course->id;
        $moduleinfo->section = 0;
        $cm = create_module($moduleinfo);
        $topomojo->coursemodule = $cm->coursemodule;

        // Add review options.
        $topomojo->attemptduring = 1;
        $topomojo->attemptimmediately = 1;
        $topomojo->attemptopen = 1;
        $topomojo->attemptclosed = 1;
        $topomojo->correctnessduring = 0;
        $topomojo->correctnessimmediately = 1;
        $topomojo->correctnessopen = 1;
        $topomojo->correctnessclosed = 1;
        $topomojo->marksduring = 0;
        $topomojo->marksimmediately = 1;
        $topomojo->marksopen = 1;
        $topomojo->marksclosed = 1;

        // Call the function.
        $result = topomojo_add_instance($topomojo, null);

        // Assert that instance was created.
        $this->assertNotEmpty($result);
        $this->assertIsInt($result);

        // Verify record in database.
        $record = $DB->get_record('topomojo', ['id' => $result]);
        $this->assertNotEmpty($record);
        $this->assertEquals('Test TopoMojo Activity', $record->name);
        $this->assertEquals('test-workspace-123', $record->workspaceid);
        $this->assertEquals(100, $record->grade); // Default grade
    }

    /**
     * Test topomojo_update_instance function.
     */
    public function test_topomojo_update_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Update data.
        $updatedata = new \stdClass();
        $updatedata->instance = $topomojo->id;
        $updatedata->coursemodule = $topomojo->cmid;
        $updatedata->name = 'Updated TopoMojo Activity';
        $updatedata->workspaceid = 'updated-workspace-456';
        $updatedata->isfeatured = 1;

        // Add review options.
        $updatedata->attemptduring = 1;
        $updatedata->attemptimmediately = 1;
        $updatedata->attemptopen = 1;
        $updatedata->attemptclosed = 1;
        $updatedata->correctnessduring = 0;
        $updatedata->correctnessimmediately = 1;
        $updatedata->correctnessopen = 1;
        $updatedata->correctnessclosed = 1;

        // Call update function.
        $result = topomojo_update_instance($updatedata, null);

        // Assert success.
        $this->assertTrue($result);

        // Verify changes in database.
        $record = $DB->get_record('topomojo', ['id' => $topomojo->id]);
        $this->assertEquals('Updated TopoMojo Activity', $record->name);
        $this->assertEquals('updated-workspace-456', $record->workspaceid);
        $this->assertEquals(1, $record->isfeatured);
    }

    /**
     * Test topomojo_delete_instance function.
     */
    public function test_topomojo_delete_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Verify it exists.
        $this->assertTrue($DB->record_exists('topomojo', ['id' => $topomojo->id]));

        // Delete instance.
        $result = topomojo_delete_instance($topomojo->id);

        // Assert success.
        $this->assertTrue($result);

        // Verify it's deleted.
        $this->assertFalse($DB->record_exists('topomojo', ['id' => $topomojo->id]));
    }

    /**
     * Test topomojo_review_option_form_to_db function.
     */
    public function test_topomojo_review_option_form_to_db() {
        $this->resetAfterTest();

        $fromform = new \stdClass();
        $fromform->attemptduring = 1;
        $fromform->attemptimmediately = 1;
        $fromform->attemptopen = 1;
        $fromform->attemptclosed = 1;

        $result = topomojo_review_option_form_to_db($fromform, 'attempt');

        // Result should be a bitmask of the enabled options.
        $expected = mod_topomojo_display_options::DURING |
                    mod_topomojo_display_options::IMMEDIATELY_AFTER |
                    mod_topomojo_display_options::LATER_WHILE_OPEN |
                    mod_topomojo_display_options::AFTER_CLOSE;

        $this->assertEquals($expected, $result);
    }

    /**
     * Test topomojo_view function.
     */
    public function test_topomojo_view() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id);
        $context = \context_module::instance($cm->id);

        // Trigger the view function.
        $sink = $this->redirectEvents();
        topomojo_view($topomojo, $course, $cm, $context);
        $events = $sink->get_events();
        $sink->close();

        // Assert event was triggered.
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_topomojo\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($topomojo->id, $event->objectid);
    }

    /**
     * Test topomojo_get_user_grades function.
     */
    public function test_topomojo_get_user_grades() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Create a grade record.
        $grade = new \stdClass();
        $grade->topomojoid = $topomojo->id;
        $grade->userid = $user->id;
        $grade->grade = 85.5;
        $grade->timemodified = time();
        $DB->insert_record('topomojo_grades', $grade);

        // Create an attempt record.
        $attempt = new \stdClass();
        $attempt->topomojoid = $topomojo->id;
        $attempt->userid = $user->id;
        $attempt->state = 30; // FINISHED
        $attempt->timestart = time();
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $attempt->endtime = time() + 3600;
        $DB->insert_record('topomojo_attempts', $attempt);

        // Get grades for user.
        $grades = topomojo_get_user_grades($topomojo, $user->id);

        // Assert grade was retrieved.
        $this->assertNotEmpty($grades);
        $this->assertArrayHasKey($user->id, $grades);
        $this->assertEquals(85.5, $grades[$user->id]->rawgrade);
    }

    /**
     * Test topomojo_update_grades function.
     */
    public function test_topomojo_update_grades() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);

        // Call update grades.
        topomojo_update_grades($topomojo);

        // This should not throw any errors.
        $this->assertTrue(true);
    }

    /**
     * Test topomojo_get_coursemodule_info function.
     */
    public function test_topomojo_get_coursemodule_info() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', [
            'course' => $course->id,
            'name' => 'Test Activity',
            'intro' => 'Test introduction',
        ]);
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id);

        // Get course module info.
        $info = topomojo_get_coursemodule_info($cm);

        // Assert info is correct.
        $this->assertNotEmpty($info);
        $this->assertInstanceOf('cached_cm_info', $info);
        $this->assertEquals('Test Activity', $info->name);
    }
}
