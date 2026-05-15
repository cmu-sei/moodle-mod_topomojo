<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Verifies capability gating on bulkdeploy.php and status.php.
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
