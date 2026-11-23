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
 * Form for managing deployments with scheduling.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for managing deployments with scheduling.
 * @package mod_topomojo
 */
class manage_form extends \moodleform {
    protected function definition() {
        $mf = $this->_form;
        $roles = $this->_customdata['roles'];

        $mf->addElement('header', 'deployoptions', get_string('deploy_options', 'topomojo'));

        $rolecount = count($roles);
        $mf->addElement('select', 'rolefilter', get_string('bulkdeploy_rolefilter', 'topomojo'), $roles, ['multiple' => 'multiple', 'size' => $rolecount, 'style' => 'width: 140px;']);
        $mf->addHelpButton('rolefilter', 'bulkdeploy_rolefilter', 'topomojo');

        $mf->addElement('text', 'batchsize', get_string('bulkdeploy_batchsize', 'topomojo'), ['size' => 5]);
        $mf->setType('batchsize', PARAM_INT);
        $mf->setDefault('batchsize', 5);
        $mf->addHelpButton('batchsize', 'bulkdeploy_batchsize', 'topomojo');

        $scheduletypes = [
            'immediate' => get_string('deploynow', 'topomojo'),
            'scheduled' => get_string('scheduledeploy', 'topomojo'),
        ];
        $mf->addElement('select', 'scheduletype', get_string('scheduledfor', 'topomojo'), $scheduletypes);
        $mf->setDefault('scheduletype', 'immediate');

        $mf->addElement('date_time_selector', 'scheduledfor', get_string('scheduledfor', 'topomojo'));
        $mf->hideIf('scheduledfor', 'scheduletype', 'eq', 'immediate');

        $this->add_action_buttons(true, get_string('bulkdeploy_submit', 'topomojo'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['batchsize'] < 1 || $data['batchsize'] > 50) {
            $errors['batchsize'] = get_string('bulkdeploy_batchsize_invalid', 'topomojo');
        }

        if ($data['scheduletype'] === 'scheduled') {
            if ($data['scheduledfor'] <= time()) {
                $errors['scheduledfor'] = get_string('schedule_past_error', 'topomojo');
            }
        }

        return $errors;
    }
}
