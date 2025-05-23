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
 * Define all the backup steps that will be used by the backup_topomojo_activity_task
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

 /**
 * Define the complete topomojo structure for backup, with file and id annotations
 */
class backup_topomojo_activity_structure_step extends backup_questions_activity_structure_step {

    protected function define_structure() {

        // Define each element separated
        $topomojo = new backup_nested_element('topomojo', array('id'), array(
            'name', 'intro', 'introformat', 'workspaceid', 'embed',
            'clock', 'extendevent', 'timeopen', 'timeclose', 'grade',
            'grademethod', 'timecreated', 'timemodified', 'reviewattempt',
            'reviewcorrectness', 'reviewmarks', 'reviewspecificfeedback',
            'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback',
            'reviewmanualcomment', 'questionorder', 'shuffleanswers',
            'preferredbehaviour', 'duration', 'importchallenge', 'endlab',
            'variant', 'attempts', 'submissions', 'contentlicense',
            'showcontentlicense'));
        $this->get_logger()->process("topomojo activity settings added", backup::LOG_DEBUG);

        $qinstances = new backup_nested_element('question_instances');
        $qinstance = new backup_nested_element('question_instance', ['id'],
                ['topomojoid', 'questionid', 'points']);
        //element, component, questionarea
        $this->add_question_references($qinstance, 'mod_topomojo', 'slot');
        $this->add_question_set_references($qinstance, 'mod_topomojo', 'questionid');
        $this->get_logger()->process("topomojo questions added", backup::LOG_DEBUG);

        // Build the tree
        $topomojo->add_child($qinstances);
        $qinstances->add_child($qinstance);

        // Define sources
        $topomojo->set_source_table('topomojo', array('id' => backup::VAR_ACTIVITYID));
        $qinstance->set_source_table('topomojo_questions', ['topomojoid' => backup::VAR_ACTIVITYID]);

        // Define id annotations
        //module has no id annotations

        // Define file annotations
        $topomojo->annotate_files('mod_topomojo', 'intro', null); // This file area hasn't itemid

        // Return the root element (topomojo), wrapped into standard activity structure
        return $this->prepare_activity_structure($topomojo);

    }
}
