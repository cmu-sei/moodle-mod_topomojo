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
 * Form for starting a bulk deployment.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for starting a bulk deployment for the users with chosen roles.
 */
class bulkdeploy_form extends \moodleform {
    /**
     * Defines the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];
        $cmid = (int) ($customdata['cmid'] ?? 0);
        $roles = $customdata['roles'] ?? [];

        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);

        $rolemenu = $mform->addElement(
            'select',
            'rolefilter',
            get_string('bulkdeploy_rolefilter', 'topomojo'),
            $roles,
            ['multiple' => 'multiple', 'size' => min(8, max(3, count($roles)))]
        );
        $rolemenu->setMultiple(true);
        $mform->setType('rolefilter', PARAM_INT);
        $mform->addHelpButton('rolefilter', 'bulkdeploy_rolefilter', 'topomojo');

        $mform->addElement('text', 'batchsize', get_string('bulkdeploy_batchsize', 'topomojo'));
        $mform->setType('batchsize', PARAM_INT);
        $mform->setDefault('batchsize', 5);
        $mform->addHelpButton('batchsize', 'bulkdeploy_batchsize', 'topomojo');

        $this->add_action_buttons(true, get_string('bulkdeploy_submit', 'topomojo'));
    }

    /**
     * Checks that the batch size is between 1 and 50.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Errors by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $bs = (int) ($data['batchsize'] ?? 0);
        if ($bs < 1 || $bs > 50) {
            $errors['batchsize'] = get_string('bulkdeploy_batchsize_invalid', 'topomojo');
        }
        return $errors;
    }

    /**
     * Test-only entry point; normal form callers go through Moodle's validation pipeline.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Errors by element name.
     */
    public function validation_for_test(array $data, array $files): array {
        return $this->validation($data, $files);
    }
}
