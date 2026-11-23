<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for capability checks on the bulk deployment pages.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Verifies capability gating on bulkdeploy.php and status.php.
 * @package mod_topomojo
 */
final class access_test extends \advanced_testcase {
    public function test_student_cannot_access_bulkdeploy_page(): void {
        $this->resetAfterTest();
        global $CFG;
        $course = $this->getDataGenerator()->create_course();
        $tm = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id, 'workspaceid' => 'ws']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $cm = get_coursemodule_from_instance('topomojo', $tm->id);
        $context = \context_module::instance($cm->id);

        $this->expectException(\required_capability_exception::class);
        require_capability('mod/topomojo:bulkdeploy', $context);
    }

    public function test_editingteacher_has_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $tm = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id, 'workspaceid' => 'ws']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $cm = get_coursemodule_from_instance('topomojo', $tm->id);
        $context = \context_module::instance($cm->id);

        $this->assertTrue(has_capability('mod/topomojo:bulkdeploy', $context));
    }
}
