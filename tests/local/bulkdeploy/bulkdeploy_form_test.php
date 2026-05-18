<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/classes/form/bulkdeploy_form.php');

/**
 * @covers \mod_topomojo\form\bulkdeploy_form
 */
final class bulkdeploy_form_test extends \advanced_testcase {
    public function test_validate_rejects_batchsize_zero(): void {
        $form = new \mod_topomojo\form\bulkdeploy_form();
        $errors = $form->validation_for_test(['batchsize' => 0, 'rolefilter' => []], []);
        $this->assertArrayHasKey('batchsize', $errors);
    }

    public function test_validate_rejects_batchsize_above_50(): void {
        $form = new \mod_topomojo\form\bulkdeploy_form();
        $errors = $form->validation_for_test(['batchsize' => 51, 'rolefilter' => []], []);
        $this->assertArrayHasKey('batchsize', $errors);
    }

    public function test_validate_accepts_empty_rolefilter(): void {
        $form = new \mod_topomojo\form\bulkdeploy_form();
        $errors = $form->validation_for_test(['batchsize' => 5, 'rolefilter' => []], []);
        $this->assertSame([], $errors);
    }

    public function test_validate_accepts_default_batchsize_5(): void {
        $form = new \mod_topomojo\form\bulkdeploy_form();
        $errors = $form->validation_for_test(['batchsize' => 5, 'rolefilter' => [5]], []);
        $this->assertSame([], $errors);
    }
}
