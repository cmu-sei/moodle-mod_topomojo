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

namespace mod_topomojo\form\edit;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/*
Group Quiz Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

 /**
  * Moodle form for confirming question add and get the time for the question
  * to appear on the page
  *
  * @package     mod_topomojo
  * @copyright   2020 Carnegie Mellon University
  * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class add_question_form extends \moodleform {

    /**
     * Overriding parent function to account for namespace in the class name
     * so that client validation works
     *
     * @return mixed|string
     */
    protected function get_form_identifier() {

        $class = get_class($this);

        return preg_replace('/[^a-z0-9_]/i', '_', $class);
    }


    /**
     * Adds form fields to the form
     *
     */
    public function definition() {

        $mform = $this->_form;
        $topomojo = $this->_customdata['topomojo'];;

        $mform->addElement('static', 'questionid', get_string('question', 'topomojo'), $this->_customdata['questionname']);

        $mform->addElement('text', 'points', get_string('points', 'topomojo'));
        $mform->addRule('points', get_string('invalid_points', 'topomojo'), 'required', null, 'client');
        $mform->addRule('points', get_string('invalid_points', 'topomojo'), 'numeric', null, 'client');
        $mform->setType('points', PARAM_FLOAT);
        $mform->setDefault('points', number_format($this->_customdata['defaultmark'], 2));
        $mform->addHelpButton('points', 'points', 'topomojo');

        if (!empty($this->_customdata['edit'])) {
            $savestring = get_string('savequestion', 'topomojo');
        } else {
            $savestring = get_string('addquestion', 'topomojo');
        }

        $this->add_action_buttons(true, $savestring);

    }

    /**
     * Validate indv question time as int
     *
     * @param array $data
     * @param array $files
     *
     * @return array $errors
     */
    public function validation($data, $files) {

        $errors = [];

        if (!filter_var($data['points'], FILTER_VALIDATE_FLOAT) && filter_var($data['points'], FILTER_VALIDATE_FLOAT) != 0) {
            $errors['points'] = get_string('invalid_points', 'topomojo');
        } else if (filter_var($data['points'], FILTER_VALIDATE_FLOAT) < 0) {
            $errors['points'] = get_string('invalid_points', 'topomojo');
        }

        return $errors;
    }

}

