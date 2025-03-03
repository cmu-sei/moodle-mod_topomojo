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
class restore_topomojo_activity_structure_step extends restore_questions_activity_structure_step {

    // TODO will have to implode this to insert it into the db
    private $questionorder = array();

    protected function define_structure() {

        $paths = array();
        $paths[] = new restore_path_element('topomojo', '/activity/topomojo');

        $quizquestioninstance = new restore_path_element('topomojo_question_instance',
            '/activity/topomojo/question_instances/question_instance');
        $paths[] = $quizquestioninstance;

        if ($this->task->get_old_moduleversion() < 2025022800) {
            // no references to restore
        } else {
            $this->add_question_references($quizquestioninstance, $paths);
            $this->add_question_set_references($quizquestioninstance, $paths);
        }

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

        /*
        if (property_exists($data, 'questions')) {
            // Needed by {@link process_quiz_attempt_legacy}, in which case it will be present.
            $this->oldquizlayout = $data->questions;
        }
         */

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

    protected function inform_new_usage_id($newusageid) {
        global $DB;

        return;
    }

    protected function after_execute() {
        global $DB;

        parent::after_execute();
        // Add topomojo related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_topomojo', 'intro', null);

        $module = $DB->get_record('topomojo', ['id' => $this->get_new_parentid('topomojo')]);
        $module->questionorder = implode(",", $this->questionorder);
        $DB->update_record('topomojo', $module);

    }

    /**
     * Process question slots.
     *
     * @param stdClass|array $data
     */
    protected function process_topomojo_question_instance($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->topomojoid = $this->get_new_parentid('topomojo');

        $newitemid = $DB->insert_record('topomojo_questions', $data);
        // Add mapping, restore of slot tags (for random questions) need it.
        $this->set_mapping('topomojo_question_instance', $oldid, $newitemid);
        //TODO questionorder needs to get updated with $newitemid
        $this->questionorder[] = $newitemid;

        if ($this->task->get_old_moduleversion() < 2025022800) {
            $data->id = $newitemid;
            $this->process_topomojo_question_legacy_instance($data);
        }

    }

    /**
     * Process the data for pre 4.0 quiz data where the question_references and question_set_references table introduced.
     *
     * @param stdClass|array $data
     */
    protected function process_topomojo_question_legacy_instance($data) {
        global $DB;

        $questionid = $this->get_mappingid('question', $data->questionid);
        $sql = 'SELECT qbe.id as questionbankentryid,
                       qc.contextid as questioncontextid,
                       qc.id as category,
                       qv.version,
                       q.qtype,
                       q.id as questionid
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE q.id = ?';
        $question = $DB->get_record_sql($sql, [$questionid]);
        $module = $DB->get_record('topomojo', ['id' => $data->topomojoid]);
        if ($question->qtype === 'random') {
            // Set reference data.
            $questionsetreference = new stdClass();
            $questionsetreference->usingcontextid = context_module::instance(get_coursemodule_from_instance(
                "topomojo", $module->id, $module->course)->id)->id;
            $questionsetreference->component = 'mod_topomojo';
            $questionsetreference->questionarea = 'slot';
            $questionsetreference->itemid = $data->id;
            // If, in the orginal quiz that was backed up, this random question was pointing to a
            // category in the quiz question bank, then (for reasons explained in {@see restore_move_module_questions_categories})
            // right now, $question->questioncontextid will incorrectly point to the course contextid.
            // This will get fixed up later in restore_move_module_questions_categories
            // as part of moving the question categories to the right place.
            $questionsetreference->questionscontextid = $question->questioncontextid;
            $filtercondition = new stdClass();
            $filtercondition->questioncategoryid = $question->category;
            $filtercondition->includingsubcategories = $data->includingsubcategories ?? false;
            $questionsetreference->filtercondition = json_encode($filtercondition);
            $DB->insert_record('question_set_references', $questionsetreference);
            $this->oldquestionids[$question->questionid] = 1;
        } else {
            // Reference data.
            $questionreference = new \stdClass();
            $questionreference->usingcontextid = context_module::instance(get_coursemodule_from_instance(
                "topomojo", $module->id, $module->course)->id)->id;
            $questionreference->component = 'mod_topomojo';
            $questionreference->questionarea = 'slot';
            $questionreference->itemid = $data->id;
            $questionreference->questionbankentryid = $question->questionbankentryid;
            $questionreference->version = null; // Default to Always latest.
            $DB->insert_record('question_references', $questionreference);
        }
    }

    /**
     * Process question references which replaces the direct connection to quiz slots to question.
     *  
     * @param array $data the data from the XML file.
     */
    public function process_question_reference($data) {
        global $DB;
        $data = (object) $data;
        $data->usingcontextid = $this->get_mappingid('context', $data->usingcontextid);
        $data->itemid = $this->get_new_parentid('topomojo_question_instance');
        if ($entry = $this->get_mappingid('question_bank_entry', $data->questionbankentryid)) {
            $data->questionbankentryid = $entry;
        }   
        $DB->insert_record('question_references', $data);
    }

}
