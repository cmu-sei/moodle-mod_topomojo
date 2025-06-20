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

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

use mod_topomojo\form\edit\add_question_form;
use stdClass;

 /**
  * Question manager class
  *
  * Provides utility functions to manage questions for a topomojo
  *
  * Basically this class provides an interface to internally map the questions added to a topomojo quiz to
  * questions in the question bank.  calling get_questions() will return an ordered array of question objects
  * from the questions table and not the topomojo_questions table.  That table is only used internally by this
  * class.
  *
  * @package     mod_topomojo
  * @copyright   2024 Carnegie Mellon University
  * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class questionmanager {

    /** @var topomojo */
    protected $topomojo;

    /** @var array */
    protected $pagevars;

    /** @var \mod_topomojo_renderer */
    protected $renderer;

    /** @var array internal use only as we'll always just give out the qbank ordered questions */
    protected $topomojoquestions;

    /** @var array */
    protected $qbankorderedquestions;

    /** @var \moodle_url */
    protected $baseurl;

    /**
     * @var array $orderedquestions List of ordered questions.
     */
    protected $orderedquestions;

    /**
     * @var object $object Generic object for internal use.
     */
    protected $object;

    /**
     * Construct an instance of question manager
     *
     * @param object $topomojo
     * @param \mod_topomojo_renderer $renderer The renderer to render visual elements
     * @param array $pagevars page variables array
     */
    public function __construct($object, $renderer, $pagevars = []) {
        global $DB;

        $this->object = $object;
        $this->renderer = $renderer;
        $this->pagevars = $pagevars;
        $this->orderedquestions = [];

        if (!empty($this->pagevars)) {
            $this->baseurl = $this->pagevars['pageurl'];
        } else {
            $params = ['cmid' => $this->object->cm->id];
            $this->baseurl = new \moodle_url('/mod/topomojo/edit.php', $params);
        }

        // Load questions
        $this->refresh_questions();
    }

    /**
     * return this class's reference of topomojo
     *
     * @return topomojo
     */
    public function gettopomojo() {
        return $this->object;
    }


    /**
     * Edit a topomojo question
     *
     * @param int $questionid the topomojo questionid
     *
     * @return mixed
     */
    public function edit_question($questionid) {
        global $DB;

        $actionurl = clone($this->baseurl);
        $actionurl->param('action', 'editquestion');
        $actionurl->param('topomojoquestionid', $questionid);

        $topomojoquestion = $DB->get_record('topomojo_questions', ['id' => $questionid], '*', MUST_EXIST);
        $qrecord = $DB->get_record('question', ['id' => $topomojoquestion->questionid], '*', MUST_EXIST);

        $mform = new add_question_form($actionurl,
            [
                'topomojo' => $topomojoquestion->topomojoid,
                'questionname' => $qrecord->name,
                'defaultmark' => $topomojoquestion->points,
                'edit' => true]);

        // Form handling
        if ($mform->is_cancelled()) {
            // Redirect back to list questions page
            $this->baseurl->remove_params('action');
            redirect($this->baseurl, null, 0);

        } else if ($data = $mform->get_data()) {
            // Process data from the form
            if (number_format($data->points, 2) != $topomojoquestion->points) {
                // If we have a different points, update any existing sessions/attempts max points and regrade.
                $this->update_points(number_format($data->points, 2), $topomojoquestion, $qrecord);
            }

            $question = new \stdClass();
            $question->id = $topomojoquestion->id;
            $question->topomojoid = $topomojoquestion->topomojoid;
            $question->questionid = $topomojoquestion->questionid;
            $question->points = number_format($data->points, 2);

            $DB->update_record('topomojo_questions', $question);

            // Ensure there is no action or questionid in the baseurl
            $this->baseurl->remove_params('action', 'questionid');
            redirect($this->baseurl, null, 0);

        } else {
            // Display the form
            $mform->set_data(['points' => number_format($topomojoquestion->points, 2)]);
            $this->renderer->addquestionform($mform);
        }
    }

    /**
     * Delete a question on the topomojo
     *
     * @param int $questionid The topomojo questionid to delete
     *
     * @return bool
     */
    public function delete_question($questionid) {
        // TODO disable this if attempts exist
        global $DB;

        try {
            $DB->delete_records('topomojo_questions', ['id' => $questionid]);
            $this->update_questionorder('deletequestion', $questionid);
        } catch (\Exception $e) {
            return false; // Return false on error
        }

        // If we get here return true
        return true;
    }

    /**
     * Add a question on the topomojo
     *
     * @param int $questionid The topomojo questionid to add
     *
     * @return bool
     */
    public function add_question($questionid) {
        global $DB;

        if ($this->is_question_already_present($questionid)) {
            debugging("Question with ID $questionid is already present, it cannot be added", DEBUG_DEVELOPER);
            return false;
        }

        $question = new \stdClass();
        $question->topomojoid = $this->object->topomojo->id;
        $question->questionid = $questionid;
        $qrecord = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
        $question->points = number_format($qrecord->defaultmark, 2);

        $topomojoquestionid = $DB->insert_record('topomojo_questions', $question);

        $this->update_questionorder('addquestion', $topomojoquestionid);

        // Get the course context ID dynamically
        $courseid = $this->object->course->id;
        $contextid = \context_course::instance($courseid)->id;

        // Find the 'top' category for this course context
        $category = $DB->get_record('question_categories', [
            'contextid' => $contextid,
            'name' => 'top'
        ], 'id');

        // If no 'top' category exists, fallback to the default category for this course
        if (!$category) {
            debugging("No 'top' category found for course $courseid. Using default category.", DEBUG_DEVELOPER);
            $category = $DB->get_record('question_categories', [
                'contextid' => $contextid
            ], 'id', IGNORE_MULTIPLE);
        }

        // Ensure we have a valid category ID
        if (!$category) {
            debugging("Error: No valid question category found for course $courseid.", DEBUG_DEVELOPER);
            return false;
        }

        $categoryid = $category->id;

        $qbankentry = $DB->get_record('question_bank_entries', ['id' => $questionid]);
        if (!$qbankentry) {
            debugging("no question_bank_entries found for $questionid", DEBUG_DEVELOPER);
            $qbankentry = new stdClass();
            $qbankentry->questioncategoryid = $categoryid;
            $qbankentry->name = $qrecord->name;
            $qbankentry->id = $DB->insert_record('question_bank_entries', $qbankentry);
        } else {
            if ($qbankentry->questioncategoryid != $categoryid) {
                $qbankentry->questioncategoryid = $categoryid;
                debugging("changing question bank entry questioncategoryid from $qbankentry->questioncategoryid to $categoryid", DEBUG_DEVELOPER);
                $DB->update_record('question_bank_entries', $qbankentry);
            }
        }

        // quiz_slots is equivalent to topomojo_questions
        $slotid = $topomojoquestionid;

        // Update or insert record in question_reference table.
        $sql = "SELECT DISTINCT qr.id, qr.itemid
              FROM {question} q
              JOIN {question_versions} qv ON q.id = qv.questionid
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_references} qr ON qbe.id = qr.questionbankentryid AND qr.version = qv.version
              JOIN {topomojo_questions} qs ON qs.id = qr.itemid
             WHERE q.id = ?
               AND qs.id = ?
               AND qr.component = ?
               AND qr.questionarea = ?";
        $qreferenceitem = $DB->get_record_sql($sql, [$questionid, $slotid, 'mod_topomojo', 'slot']);

        // add question_reference
        if (!$qreferenceitem) {
            // Create a new reference record for questions created already.
            $questionreferences = new stdClass();
            $questionreferences->usingcontextid = \context_module::instance($this->gettopomojo()->cm->id)->id;
            $questionreferences->component = 'mod_topomojo';
            $questionreferences->questionarea = 'slot';
            $questionreferences->itemid = $slotid;
            $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
            $questionreferences->version = null; // Always latest.
            $DB->insert_record('question_references', $questionreferences);
        } else if ($qreferenceitem->itemid === 0 || $qreferenceitem->itemid === null) {
            $questionreferences = new stdClass();
            $questionreferences->id = $qreferenceitem->id;
            $questionreferences->itemid = $slotid;
            $DB->update_record('question_references', $questionreferences);
        } else {
            // If the reference record exists for another topomojo.
            $questionreferences = new stdClass();
            $questionreferences->usingcontextid = \context_module::instance($this->gettopomojo()->cm->id)->id;
            $questionreferences->component = 'mod_topomojo';
            $questionreferences->questionarea = 'slot';
            $questionreferences->itemid = $slotid;
            $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
            $questionreferences->version = null; // Always latest.
            $DB->insert_record('question_references', $questionreferences);
        }

        // If we get here return true
        debugging("Success: Question ID $questionid added to category $categoryid and TopoMojo $question->topomojoid", DEBUG_DEVELOPER);
        return true;
    }


    /**
     * Moves a question on the question order for this topomojo 
     *
     * @param string $direction 'up'||'down'
     * @param int $questionid The topomojo questionid
     *
     * @return bool
     */
    public function move_question($direction, $questionid) {

        if ($direction !== 'up' && $direction != 'down') {
            return false; // Return false if the direction is not up or down
        }

        return $this->update_questionorder('movequestion' . $direction, $questionid);
    }

    /**
     * Public API function for setting the full order of the questions on the topomojo
     *
     * Please note that full order must be an array with no specialized keys as only array values are taken
     *
     * @param array $fullorder
     * @return bool
     */
    public function set_full_order($fullorder = []) {

        if (!is_array($fullorder)) {
            return false;
        }

        $fullorder = array_values($fullorder);

        return $this->update_questionorder('replaceorder', null, $fullorder);
    }

    /**
     * Returns the questions in the specified question order
     *
     * @return array of the question bank ordered questions of \mod_topomojo\topomojo_question objects
     */
    public function get_questions() {
        return $this->qbankorderedquestions;
    }

    /**
     * Gets the question type for the specified question number
     *
     * @param int $qnum The question number to get the questiontype
     *
     *
     * @return string
     */
    public function get_questiontype_byqnum($qnum) {

        // Get the actual key for the qbank question
        $qbankkeys = array_keys($this->qbankorderedquestions);
        $desiredkey = $qbankkeys[$qnum - 1];
        $topomojoquestion = $this->qbankorderedquestions[$desiredkey];

        return $topomojoquestion->getQuestion()->qtype;
    }

    /**
     * shortcut to get the first question
     *
     * @param \mod_topomojo\topomojo_attempt $attempt
     *
     * @return \mod_topomojo\topomojo_question
     */
    public function get_first_question($attempt) {
        return $this->get_question_with_slot(1, $attempt);
    }

    /**
     * Gets a topomojo_question object with the slot set
     *
     * @param int $slotnum The index of the slot we want, i.e. the question number
     * @param \mod_topomojo\topomojo_attempt $attempt The current attempt
     *
     * @return \mod_topomojo\topomojo_question
     */
    public function get_question_with_slot($slotnum, $attempt) {

        $slots = $attempt->getSlots();
        $quba = $attempt->get_quba();

        // First check if this is the last question
        if (empty($slots[$slotnum])) {
            $attempt->islastquestion(true);
        } else {
            $attempt->islastquestion(false);
        }

        // Since arrays are indexed starting at 0 and we reference questions starting with 1, we subtract 1
        $slotnum = $slotnum - 1;

        // Get the first question
        $qubaquestion = $quba->get_question($slots[$slotnum]);

        foreach ($this->qbankorderedquestions as $qbankquestion) {
            if ($qbankquestion->getQuestion()->id == $qubaquestion->id) {
                // Set the slot on the qbank question as this is the actual id we're using for question number
                $qbankquestion->set_slot($slots[$slotnum]);

                return $qbankquestion;
            }
        }

        // If we get here return null due to no question
        return null;
    }

    /**
     * Updates the answers for questions in the given quiz attempt based on the gamespace ID.
     *
     * This function retrieves the event and challenge information associated with the provided
     * gamespace ID. It then iterates over the challenge sections and questions to match them
     * with existing questions in the Moodle database. If a match is found and the variant
     * corresponds, it updates the answers accordingly.
     *
     * @param \question_usage_by_activity $quba The question usage object for the quiz attempt.
     * @param int $gamespaceid The ID of the gamespace associated with the current challenge.
     *
     * @return void
     */
    public function update_answers($quba, $gamespaceid) {
        global $DB;

            // TODO use the right variant
            //$variant = 1;
            $event = get_event($this->gettopomojo()->userauth, $gamespaceid);
            $variant = $event->variant;
            debugging("this event has variant $variant", DEBUG_DEVELOPER);
            $challenge = get_gamespace_challenge($this->gettopomojo()->userauth, $gamespaceid);
        if (!isset($challenge->challenge->sections)) {
                debugging("no sections set!", DEBUG_DEVELOPER);
                return;
        }
        foreach ($challenge->challenge->sections as $section) {
            foreach ($section->questions as $question) {
                    $questionid = 0;
                    debugging("checking for question with variant $variant", DEBUG_DEVELOPER);
                    $sql = "select * from {question} where " . $DB->sql_compare_text('questiontext') . " = ? ";
                    $records = $DB->get_records_sql($sql, [$question->text]);
                if (count($records)) {
                        //echo "<br>" . count($records) . " questions exists with text: $question->text <br>";
                    foreach ($records as $record) {
                            $options = $DB->get_record('qtype_mojomatch_options', ['questionid' => $record->id]);
                        if ($options) {
                            if ($variant == $options->variant) {
                                debugging("question exists for variant " . $options->variant, DEBUG_DEVELOPER);
                                $questionid = $record->id;
                                break;
                            } else {
                                debugging("event $variant not a match to question variant $record->variant", DEBUG_DEVELOPER);
                            }
                        } else {
                            debugging("no options found for question", DEBUG_DEVELOPER);
                        }
                    }
                }

                if ($questionid) {
                    debugging("question found with id $questionid", DEBUG_DEVELOPER);
                        //$table = 'question_attempts';
                        //$questionusageid = $quba->get_id();
                        //$dataobject = $DB->get_record($table, array('questionusageid' => $questionusageid, 'questionid' => $questionid));

                        // TODO check the variant and the number
                        //$dataobject->rightanswer = $question->answer;
                        //  update quba with the correct answer
                        //$DB->update_record($table, $dataobject);

                } else {
                    debugging("question was not found on moodle", DEBUG_DEVELOPER);
                }
            }
        }
    }

    /**
     * add the questions to the question usage
     * This is called by the question_attmept class on construct of a new attempt
     *
     * @param \question_usage_by_activity $quba
     *
     * @return array
     */
    public function add_questions_to_quba(\question_usage_by_activity $quba) {

        // We need the questionids of our questions
        $questionids = [];
        foreach ($this->qbankorderedquestions as $qbankquestion) {
            if (!in_array($qbankquestion->getQuestion()->id, $questionids)) {
                $questionids[] = $qbankquestion->getQuestion()->id;
            }
        }
        $questions = question_load_questions($questionids);

        //print_r($questions);

        // Loop through the ordered question bank questions and add them to the quba
        // Object
        $attemptquestionorder = [];
        foreach ($this->qbankorderedquestions as $qbankquestion) {
            $questionid = $qbankquestion->getQuestion()->id;
            $q = \question_bank::make_question($questions[$questionid]);
            $attemptquestionorder[$qbankquestion->getId()] = $quba->add_question($q, $qbankquestion->getPoints());
        }

        // Start the questions in the quba
        $quba->start_all_questions();

        /*
         * Return the attempt questionorder which is a set of ids that are the slot ids from the
         * question engine usage by activity instance
         * These are what are used during an actual attempt rather than the questionid themselves,
         * since the question engine will handle
         * The translation
         */
        return $attemptquestionorder;
    }

    /**
     * Gets the question order from the topomojo object
     *
     * @return string
     */
    protected function get_question_order() {
        return $this->object->topomojo->questionorder;
    }

    /**
     * Updates question order on topomojo object and then persists to the database
     *
     * @param string
     * @return bool
     */
    protected function set_question_order($questionorder) {
        $this->object->topomojo->questionorder = $questionorder;
        return $this->object->save();

    }

    /**
     * Updates the question order for the question manager
     *
     * @param string $action
     * @param int $questionid the topomojo question id, NOT the question engine question id
     * @param array $fullorder An array of question objects to sort as is.
     *     This is mainly used for the dragdrop callback on the edit page.  If the full order is not specified
     *     with all questions currently on the quiz, the case will return false
     *
     * @return bool true/false if it was successful
     */
    protected function update_questionorder($action, $questionid, $fullorder = []) {

        switch ($action) {
            case 'addquestion':

                $questionorder = $this->get_question_order();
                if (empty($questionorder)) {
                    $questionorder = $questionid;
                } else {
                    $questionorder .= ',' . $questionid;
                }

                $this->set_question_order($questionorder);

                // refresh question list
                $this->refresh_questions();

                return true;
                break;
            case 'deletequestion':

                $questionorder = $this->get_question_order();
                $questionorder = explode(',', $questionorder);

                foreach ($questionorder as $index => $qorder) {

                    if ($qorder == $questionid) {
                        unset($questionorder[$index]);
                        break;
                    }
                }
                $newquestionorder = implode(',', $questionorder);

                // set the question order and refresh the questions
                $this->set_question_order($newquestionorder);
                $this->refresh_questions();

                return true;

                break;
            case 'movequestionup':

                $questionorder = $this->get_question_order();
                $questionorder = explode(',', $questionorder);

                foreach ($questionorder as $index => $qorder) {

                    if ($qorder == $questionid) {

                        if ($index == 0) {
                            return false; // can't move first question up
                        }

                        // if ids match replace the previous index with the current one
                        // and make the previous index qid the current index
                        $prevqorder = $questionorder[$index - 1];
                        $questionorder[$index - 1] = $questionid;
                        $questionorder[$index] = $prevqorder;
                        break;
                    }
                }

                $newquestionorder = implode(',', $questionorder);

                // set the question order and refresh the questions
                $this->set_question_order($newquestionorder);
                $this->refresh_questions();

                return true;

                break;
            case 'movequestiondown':

                $questionorder = $this->get_question_order();
                $questionorder = explode(',', $questionorder);

                $questionordercount = count($questionorder);

                foreach ($questionorder as $index => $qorder) {

                    if ($qorder == $questionid) {

                        if ($index == $questionordercount - 1) {
                            return false; // can't move last question down
                        }

                        // if ids match replace the next index with the current one
                        // and make the next index qid the current index
                        $nextqorder = $questionorder[$index + 1];
                        $questionorder[$index + 1] = $questionid;
                        $questionorder[$index] = $nextqorder;
                        break;
                    }
                }

                $newquestionorder = implode(',', $questionorder);

                // set the question order and refresh the questions
                $this->set_question_order($newquestionorder);
                $this->refresh_questions();

                return true;

                break;
            case 'replaceorder':

                $questionorder = $this->get_question_order();
                $questionorder = explode(',', $questionorder);

                // if we don't have the same number of questions return error
                if (count($fullorder) !== count($questionorder)) {
                    return false;
                }

                // next validate that the questions sent all match to a question in the current order
                $allmatch = true;
                foreach ($questionorder as $qorder) {
                    if (!in_array($qorder, $fullorder)) {
                        $allmatch = false;
                    }
                }

                if ($allmatch) {

                    $newquestionorder = implode(',', $fullorder);
                    $this->set_question_order($newquestionorder);
                    $this->refresh_questions();

                    return true;
                } else {
                    return false;
                }

                break;
        }

        return false; // if we get here, there's an error so return false
    }

    /**
     * check whether the question id has already been added
     *
     * @param int $questionid
     *
     * @return bool
     */
    protected function is_question_already_present($questionid) {

        // loop through the db topomojo questions and see if we find a match
        foreach ($this->topomojoquestions as $dbtopomojoquestion) {
            if ($dbtopomojoquestion->questionid == $questionid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refreshes question information from the DB
     *
     * This is the function that should be called so that questions are loaded
     * in the correct order
     *
     */
    protected function refresh_questions() {
        $this->init_topomojo_questions();
        $this->init_qbank_questions();
    }

    /**
     * Gets the list of questions from the DB
     *
     */
    private function init_topomojo_questions() {
        global $DB;
        $this->topomojoquestions = $DB->get_records('topomojo_questions', ['topomojoid' => $this->object->topomojo->id]);
    }

    /**
     * Orders the questions and then
     * puts question bank ordered questions into the qbankorderedquestions var
     *
     */
    private function init_qbank_questions() {
        global $DB;

        // Start by ordering the topomojo question ids into an array
        $questionorder = $this->object->topomojo->questionorder;

        // Generate empty array for ordered questions for no question order
        if (empty($questionorder) ) {

            $this->qbankorderedquestions = [];

            return;

        } else { // Otherwise explode it and continue on
            $questionorder = explode(',', $questionorder);
        }

        // Using the question order saved in topomojo object, get the qbank question ids from the topomojo questions
        $orderedquestionids = [];
        foreach ($questionorder as $qorder) {
            // Store the topomojo question id as the key so that it can be used later when adding question time to
            // Question bank question object
            $orderedquestionids[$qorder] = $this->topomojoquestions[$qorder]->questionid;
        }

        // Get qbank questions based on the question ids from the topomojo questions table
        list($sql, $params) = $DB->get_in_or_equal($orderedquestionids);
        $query = 'SELECT * FROM {question} WHERE id ' . $sql;
        $questions = $DB->get_records_sql($query, $params);

        // Now order the qbank questions based on the order that we got above
        $qbankorderedquestions = [];
        foreach ($orderedquestionids as $topomojoqid => $questionid) {
            // Log the question object for debugging
            //debugging("Checking question with ID {$questionid}", DEBUG_DEVELOPER);
            //debugging(print_r($questions[$questionid], true), DEBUG_DEVELOPER);

            // Check if the question and question text are not empty
            if (!empty($questions[$questionid]) && !empty($questions[$questionid]->questiontext)) {
                debugging("Updating questionbankorder to have question with ID {$questionid} and text: " . $questions[$questionid]->questiontext, DEBUG_DEVELOPER);

                // Create topomojo question and add it to the array if questiontext is not null
                $topomojoquestion = new \mod_topomojo\topomojo_question(
                    $topomojoqid,
                    $this->topomojoquestions[$topomojoqid]->points,
                    $questions[$questionid]
                );
                $qbankorderedquestions[$topomojoqid] = $topomojoquestion;
            } else {
                debugging("Skipping question with ID {$questionid} because questiontext is null or empty", DEBUG_DEVELOPER);
            }
        }

        $this->qbankorderedquestions = $qbankorderedquestions;
    }

    /**
     * Updates the points for a specific question based on the provided records.
     *
     * This function adjusts the points associated with a question by using the provided
     * question record and question record objects. It throws exceptions if issues are
     * encountered during the update process.
     *
     * @param float $newpoints The new points value to be assigned to the question.
     * @param \stdClass $questionrecord The record of the question being updated.
     * @param \stdClass $qrecord The record containing additional question data.
     *
     * @throws \moodle_exception Throws a Moodle exception if a slot isn't found or if grading fails.
     * @return bool Returns true if the update is successful, otherwise false.
     */
    public function update_points($newpoints, $questionrecord, $qrecord) {
        global $DB;

        $q = new \mod_topomojo\topomojo_question(
            $questionrecord->id,
            $newpoints,
            $qrecord
        );

        $attempts = $this->object->getall_attempts('all');

        foreach ($attempts as $attempt) {
            if ($slot = $attempt->get_question_slot($q)) {
                $quba = $attempt->get_quba();
                $quba->set_max_mark($slot, $newpoints);
                $quba->regrade_question($slot, false, $newpoints);
                $attempt->save();
            } else {
                throw new \moodle_exception('invalidslot', 'mod_topomojo', '', null, $attempt->get_attempt());
            }
        }

        $grader = new \mod_topomojo\utils\grade($this->object);

        // re-save all grades after regrading the question attempts for the slot.
        if ($grader->save_all_grades(true)) {
            return true;
        } else {
            throw new \moodle_exception('cannotgrade', 'mod_topomojo');
        }
    }
  
    public function detect_mismatched_questions($object, $variant, $challenge) {
        global $DB;
    
        $mismatched = [];
        $currentquestions = $this->get_questions();
    
        $expected = [];
        foreach ($challenge->variants[$variant]->sections as $section) {
            foreach ($section->questions as $q) {
                $expected[trim(strip_tags($q->text))] = trim($q->answer);
            }
        }
    
        foreach ($currentquestions as $tq) {
            $q = $tq->getQuestion();
            if ($q->qtype !== 'mojomatch') continue;
    
            $questiontext = trim(strip_tags($q->questiontext));
            $questionid = $q->id;
    
            $answer = $DB->get_field('question_answers', 'answer', ['question' => $questionid]);
    
            if (!isset($expected[$questiontext]) || $expected[$questiontext] !== trim($answer)) {
                $mismatched[] = [
                    'id' => $tq->getId(),
                    'questiontext' => $questiontext,
                    'name' => $q->name
                ];
            }
        }
    
        return $mismatched;
    }    


    /**
     * Processes and updates questions for a specific variant and challenge, adding them to the quiz if necessary.
     *
     * This method removes existing questions from the quiz that do not match the current variant,
     * then processes and adds new questions from the specified challenge and variant. It updates
     * questions in the database and optionally adds them to the quiz.
     *
     * @param \context $context The context in which the questions are being processed (e.g., course context).
     * @param stdClass $object An object containing information related to the topomojo instance.
     * @param int $variant The variant number of the questions to process.
     * @param stdClass $challenge The challenge object containing the sections and questions.
     * @param bool $addtoquiz Flag indicating whether to add the questions to the quiz.
     *
     * @return void
     */
    public function process_variant_questions($context, $object, $variant, $challenge, $addtoquiz) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/type/mojomatch/questiontype.php');
        $currentquestions = $this->get_questions();

         // Step 1: Get questions from topomojo
        $expected_questiontexts = [];
        foreach ($challenge->variants[$variant]->sections as $section) {
            foreach ($section->questions as $q) {
                $expected_questiontexts[] = trim(strip_tags($q->text));
            }
        }

        $mismatched_questions = [];
        $deleted_questiontexts = [];

        // Step 2: Delete moodle activity questions if they do not match those in topomojo
        foreach ($currentquestions as $tq) {
            $q = $tq->getQuestion();
            if ($q->qtype !== "mojomatch") {
                continue;
            }
        
            $cleantext = trim(strip_tags($q->questiontext));
            $questionid = $q->id;
        
            // Check if question text exists in TopoMojo
            if (!in_array($cleantext, $expected_questiontexts)) {
                $mismatched_questions[] = $q->name;
                debugging("Deleting question '{$q->name}' (not part of current challenge)", DEBUG_DEVELOPER);
                $this->delete_question($tq->getId());
                question_delete_question($questionid);
                continue;
            }
        
            // Get answer from topomojo
            $expected_answer = null;
            foreach ($challenge->variants[$variant]->sections as $section) {
                foreach ($section->questions as $topoq) {
                    if (trim(strip_tags($topoq->text)) === $cleantext) {
                        $expected_answer = trim($topoq->answer);
                        break 2;
                    }
                }
            }

            // Get answer from moodle db and compare it with topomojo, if they do not match, delete question
            if ($expected_answer !== null) {
                $storedanswer = $DB->get_field('question_answers', 'answer', ['question' => $questionid]);
        
                if (trim($storedanswer) !== $expected_answer) {
                    $mismatched_questions[] = $q->name;
                    $deleted_questiontexts[] = $cleantext;
                    debugging("Deleting question '{$q->name}' (answer mismatch)", DEBUG_DEVELOPER);
                    $this->delete_question($tq->getId());
                    question_delete_question($questionid);
                }
            }
        }        
        
        if (!empty($mismatched_questions) || empty($currentquestions)) {
            $questionnumber = 0;
            $type = 'info';
            $message = '';

            // Add new questions when necessary, if found in array (answers), or if question texts do not match
            foreach ($challenge->variants[$variant]->sections as $section) {
                $count = count($section->questions);
                debugging("Found $count question(s) for variant $variant on TopoMojo server", DEBUG_DEVELOPER);

                foreach ($section->questions as $question) {
                    $questionnumber++;
                    $cleantext = trim(strip_tags($question->text));

                    $qexists    = 0;
                    $questionid = 0;

                    // ─── do not recycle anything when quiz is still empty ───────────
                    if (!empty($currentquestions) && !in_array($cleantext, $deleted_questiontexts, true)) {

                        // try to find *exact* match (text + answer + workspace + variant)
                        $sql = "SELECT q.id AS questionid
                                FROM {question} q
                                JOIN {qtype_mojomatch_options} o ON q.id = o.questionid
                                JOIN {question_answers}        qa ON qa.question = q.id
                                WHERE " . $DB->sql_compare_text('q.questiontext') . " = " . $DB->sql_compare_text(':questiontext') . "
                                AND qa.answer        = :answer
                                AND o.workspaceid    = :workspaceid
                                AND o.variant        = :variant";
                        $params = [
                            'questiontext' => $cleantext,
                            'answer'       => trim($question->answer),
                            'workspaceid'  => $object->topomojo->workspaceid,
                            'variant'      => $variant
                        ];

                        if ($rec = $DB->get_record_sql($sql, $params)) {
                            $qexists    = 1;
                            $questionid = $rec->questionid;
                        }
                    }

                    // Create a new question if it doesn't exist
                    if (!$qexists) {
                        debugging("Adding a new mojomatch question to database", DEBUG_DEVELOPER);

                        $form = new stdClass();
                        if ($question->grader == 'matchAll') {
                            $form->matchtype = '1'; // matchall
                        } else if ($question->grader == 'matchAny') {
                            $form->matchtype = '2'; // matchany
                        } else if ($question->grader == 'matchAlpha') {
                            $form->matchtype = '0'; // matchalpha
                        } else if ($question->grader == 'match') {
                            $form->matchtype = '3'; // match
                        } else {
                            $type = 'warning';
                            $message .= "<br>we need to handle mojomatch type $question->grader";
                            break;
                        }

                        $q = new stdClass();
                        $saq = new \qtype_mojomatch();
                        $cat = question_get_default_category($context->id);
                        $q->qtype = 'mojomatch';
                        $form->category = $cat->id;
                        $form->name = $object->topomojo->name . " - $variant - $questionnumber ";
                        $form->questiontext['text'] = $question->text;
                        $form->questiontext['format'] = '0'; //TODO find out nonhtml
                        $form->defaultmark = 1;
                        if (is_numeric($question->weight)) {
                            if (floor($question->weight) != $question->weight) {
                                $form->defaultmark = $question->weight * 10;
                            } else {
                                $form->defaultmark = $question->weight;
                            }
                        }
                        if ($form->defaultmark == 0) {
                            $form->defaultmark = 1;
                        }
                        $form->usecase = '0'; // Case sensitive, topomojo does tolower() on responses
                        $form->answer = [$question->answer];
                        $form->fraction = ['1'];
                        $form->feedback[0] = ['text' => '', 'format' => '1'];
                        $form->variant = $variant;
                        $form->workspaceid = $object->topomojo->workspaceid;
                        $form->transforms = 0;
                        $form->qorder = $questionnumber;

                        // TODO check for hint and add as feedback

                        if (preg_match('/##.*##/', $question->answer)) {
                            $form->transforms = 1;
                            $form->feedback[0] = ['text' => 'This answer is randomly generated at runtime.', 'format' => '1'];
                        }

                        $saq->save_defaults_for_new_questions($form);
                        $newq = $saq->save_question($q, $form);
                        $questionid = $newq->id;
                        debugging("Added new question $questionid to the database", DEBUG_DEVELOPER);
                    }
                    if (!$qexists && $questionid && $addtoquiz) {
                        // attempt to add question to topomojo quiz
                        if ($this->add_question($questionid)) {
                            \core\notification::success(get_string('questionsynced', 'topomojo'));
                        } else {
                            debugging("Could not add new mojomatch question with id $questionid to the db - it may be present already", DEBUG_DEVELOPER);
                            \core\notification::error(get_string('questionaddfailed', 'topomojo'));
                        }                        
                    }
                }
                if ($message) {
                    $this->renderer->setMessage($type, $message);
                }
            }
            }        
    }

    public function notify_instructors_of_mismatch($cm, $activityname) {
        // Get users with the 'mod/topomojo:manage' capability (typically teachers/admins)
        $context = \context_module::instance($cm->id); // Context of the TopoMojo activity
        $capability = 'mod/topomojo:manage';

        $users = get_users_by_capability($context, $capability, 'u.*', 'u.lastname ASC');  
    
        if (!$users) {
            debugging("No instructors found to notify for mismatched questions.");
            return;
        }
    
        $subject = "TopoMojo question mismatch in '{$activityname}' activity.";
        $body = "\n\nPlease review the activity question/answer list, ";
        $body .= "it no longer matches the current question list from the TopoMojo challenge:\n\n";
    
        foreach ($users as $user) {
            $eventdata = new \core\message\message();
            $eventdata->notification      = 1;
            $eventdata->component         = 'mod_topomojo';
            $eventdata->name              = 'notification';
            $eventdata->userfrom          = \core_user::get_noreply_user();
            $eventdata->userto            = $user;
            $eventdata->subject           = $subject;
            $eventdata->fullmessage       = $body;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = nl2br(s($body));
            $eventdata->smallmessage      = $subject;
            $eventdata->notification      = 1;
            $result = message_send($eventdata);
        }
    }
    
}


