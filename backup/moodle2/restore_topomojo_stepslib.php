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
 * @package   mod_topomojo
 * @category  backup
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_topomojo_activity_task
 */

/**
 * Structure step to restore one topomojo activity
 */
class restore_topomojo_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $paths[] = new restore_path_element('topomojo', '/activity/topomojo');

        $quizquestioninstance = new restore_path_element('quiz_question_instance',
            '/activity/quiz/question_instances/question_instance');
        $paths[] = $quizquestioninstance;

        $this->add_question_references($quizquestioninstance, $paths);
        $this->add_question_set_references($quizquestioninstance, $paths);

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_topomojo($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.

        // time setting
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        // TODO set/test default behaviour
        if (!isset($data->preferredbehaviour)) {
            if (empty($data->optionflags)) {
                $data->preferredbehaviour = 'deferredfeedback';
            } else if (empty($data->penaltyscheme)) {
                $data->preferredbehaviour = 'adaptivenopenalty';
            } else {
                $data->preferredbehaviour = 'adaptive';
            }
            unset($data->optionflags);
            unset($data->penaltyscheme);
        }

        // TODO test review settings

        // insert the topomojo record
        $newitemid = $DB->insert_record('topomojo', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function after_execute() {
        // Add topomojo related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_topomojo', 'intro', null);
    }
}
