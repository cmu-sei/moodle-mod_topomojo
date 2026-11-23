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
 * Tests for the bulk deployment form.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/classes/form/bulkdeploy_form.php');

/**
 * Tests for the bulk deployment form.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\form\bulkdeploy_form::class)]
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
