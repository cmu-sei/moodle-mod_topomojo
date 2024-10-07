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
Copyright 2024 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. 
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, 
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. 
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  
Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of Third-Party Software each subject to its own license.
DM24-1175
*/

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * topomojo Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_topomojo
 * @copyright   2020 Carnegie Mellon Univeristy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topomojo_attempt {

    /** Constants for the status of the attempt */

    /** @var int NOTSTARTED Indicates that the attempt has not started */
    const NOTSTARTED = 0;

    /** @var int INPROGRESS Indicates that the attempt is currently in progress */
    const INPROGRESS = 10;

    /** @var int ABANDONED Indicates that the attempt has been abandoned */
    const ABANDONED = 20;

    /** @var int FINISHED Indicates that the attempt has finished */
    const FINISHED = 30;

    /** @var \stdClass The attempt record */
    protected $attempt;

    // TODO remove context if we dont use it
    /** @var \context_module $context The context for this attempt */
    protected $context;


    /** @var questionmanager $questionmanager $the queestion manager for the class */
    protected $questionmanager;

    /** @var \question_usage_by_activity $quba the question usage by activity for this attempt */
    protected $quba;

    /** @var int $qnum The question number count when rendering questions */
    protected $qnum;


    /**
     * Construct the class.  if a dbattempt object is passed in set it,
     * otherwise initialize empty class
     *
     * @param questionmanager $questionmanager
     * @param \stdClass
     * @param \context_module $context
     */
    public function __construct($questionmanager, $dbattempt = null, $context = null) {
        $this->questionmanager = $questionmanager;
        $this->context = $context;

        // If empty create new attempt
        if (empty($dbattempt)) {
            $this->attempt = new \stdClass();
            // Create a new quba since we're creating a new attempt
            $this->quba = \question_engine::make_questions_usage_by_activity('mod_topomojo',
                    $this->questionmanager->gettopomojo()->getContext());
            // TODO get from module settings
            //$this->quba->set_preferred_behaviour('immediatefeedback');
            //$this->quba->set_preferred_behaviour('deferredfeedback');
            $this->quba->set_preferred_behaviour($this->questionmanager->gettopomojo()->topomojo->preferredbehaviour);

            $attemptlayout = $this->questionmanager->add_questions_to_quba($this->quba);
            // Add the attempt layout to this instance
            $this->attempt->layout = implode(',', $attemptlayout);

            \question_engine::save_questions_usage_by_activity($this->quba);

        } else { // Else load it up in this class instance
            $this->attempt = $dbattempt;
            $this->quba = \question_engine::load_questions_usage_by_activity($this->attempt->questionusageid);
        }
    }

    /**
     * Get the attempt stdClass object
     *
     * @return null|\stdClass
     */
    public function get_attempt() {
        return $this->attempt;
    }

    /**
     * returns a string representation of the status that is actually stored
     *
     * @return string
     * @throws \Exception throws exception upon an undefined status
     */
    public function getState() {

        switch ($this->attempt->state) {
            case self::NOTSTARTED:
                return 'notstarted';
            case self::INPROGRESS:
                return 'inprogress';
            case self::ABANDONED:
                return 'abandoned';
            case self::FINISHED:
                return 'finished';
            default:
                throw new \Exception('undefined status for attempt');
                break;
        }
    }

    /**
     * Set the status of the attempt and then save it
     *
     * @param string $status
     *
     * @return bool
     */
    public function setState($status) {

        switch ($status) {
            case 'notstarted':
                $this->attempt->state = self::NOTSTARTED;
                break;
            case 'inprogress':
                $this->attempt->state = self::INPROGRESS;
                $this->questionmanager->update_answers($this->quba, $this->attempt->eventid);
                break;
            case 'abandoned':
                $this->attempt->state = self::ABANDONED;
                break;
            case 'finished':
                $this->attempt->state = self::FINISHED;
                break;
            default:
                return false;
                break;
        }

        // save the attempt
        return $this->save();
    }

    /**
     * saves the current attempt class
     *
     * @return bool
     */
    public function save() {
        global $DB;
        // TODO check for undefined
        if (is_null($this->attempt->endtime)) {
            debugging("null endtime passed to attempt->save for " . $this->attempt->id, DEBUG_DEVELOPER);
        }

        // first save the question usage by activity object
        \question_engine::save_questions_usage_by_activity($this->quba);

        // this is here because for new usages there is no id until we save it
        $this->attempt->questionusageid = $this->quba->get_id();

        $this->attempt->timemodified = time();

        if (isset($this->attempt->id)) { // update the record

            try {
                $DB->update_record('topomojo_attempts', $this->attempt);
            } catch (\Exception $e) {
                debugging($e->getMessage());

                return false; // return false on failure
            }
        } else {
            // insert new record
            try {
                $newid = $DB->insert_record('topomojo_attempts', $this->attempt);
                $this->attempt->id = $newid;
            } catch (\Exception $e) {
                return false; // return false on failure
            }
        }

        return true; // return true if we get here
    }

    /**
     * Closes the attempt
     *
     * @param \mod_topomojo\topomojo
     *
     * @return bool Weather or not it was successful
     */
    public function close_attempt() {
        global $USER;
        $this->quba->finish_all_questions(time());
        $this->attempt->state = self::FINISHED;
        $this->attempt->timefinish = time();
        $this->save();

        $params = [
            'objectid'      => $this->attempt->topomojoid,
            'context'       => $this->context,
            'relateduserid' => $USER->id,
        ];

        // TODO verify this info is gtg and send the event
        //$event = \mod_topomojo\event\attempt_ended::create($params);
        //$event->add_record_snapshot('topomojo_attempts', $this->attempt);
        //$event->trigger();

        return true;
    }

    /**
     * Magic get method for getting attempt properties
     *
     * @param string $prop The property desired
     *
     * @return mixed
     * @throws \Exception Throws exception when no property is found
     */
    public function __get($prop) {

        if (property_exists($this->attempt, $prop)) {
            return $this->attempt->$prop;
        }

        // otherwise throw a new exception
        throw new \Exception('undefined property(' . $prop . ') on topomojo attempt');

    }

    /**
     * magic setter method for this class
     *
     * @param string $prop
     * @param mixed  $value
     *
     * @return topomojo_attempt
     */
    public function __set($prop, $value) {
        if (is_null($this->attempt)) {
            $this->attempt = new \stdClass();
        }
        $this->attempt->$prop = $value;

        return $this;
    }

    /**
     * returns quba layout as an array as these are the "slots" or questionids
     * that the question engine is expecting
     *
     * @return array
     */
    public function getSlots() {
        return explode(',', $this->attempt->layout);
    }

    /**
     * returns an integer representing the question number
     *
     * @return int
     */
    public function get_question_number() {
        if (is_null($this->qnum)) {
            $this->qnum = 1;

            return (string)1;
        } else {
            return (string)$this->qnum;
        }
    }

    /**
     * Uses the quba object to render the slotid's question
     *
     * @param int              $slotid
     * @param bool             $review Whether or not we're reviewing the attempt
     * @param string|\stdClass $reviewoptions Can be string for overall actions like "edit" or an object of review options
     * @return string the HTML fragment for the question
     */
    public function render_question($slotid, $review = false, $reviewoptions = '', $when = null) {
        $displayoptions = $this->get_display_options($review, $reviewoptions, $when);
        $questionnum = $this->get_question_number(); // set to 1 if it doesnt exist
        $this->add_question_number(); // increment qnum

        $qa = $this->quba->get_question_attempt($slotid);

        global $PAGE;
        $page = $PAGE;
        $question = $qa->get_question();
        $qoutput = $page->get_renderer('question');
        $qtoutput = $question->get_renderer($page);
        $behaviour = $qa->get_behaviour();
        $displayoptions->context = $this->context;
        $displayoptions->context = $this->questionmanager->gettopomojo()->getContext();
        return $behaviour->render($displayoptions, $questionnum, $qoutput, $qtoutput);

    }
    /**
     * Adds 1 to the current qnum, effectively going to the next question
     *
     */
    protected function add_question_number() {
        $this->qnum = $this->qnum + 1;
    }

    /**
     * sets up the display options for the question
     *
     * @return \question_display_options
     */
    protected function get_display_options($review = false, $reviewoptions = '', $when = null) {
        $options = new \question_display_options();
        $options->flags = \question_display_options::HIDDEN;
        $options->context = $this->context;

        // if we're reviewing set up display options for review
        if ($review) {

            // default display options for review
            $options->readonly = true;
            $options->marks = \question_display_options::HIDDEN;
            $options->hide_all_feedback();

            // special case for "edit" reviewoptions value
            if ($reviewoptions === 'edit') {
                $options->correctness = \question_display_options::VISIBLE;
                $options->marks = \question_display_options::MARK_AND_MAX;
                $options->feedback = \question_display_options::VISIBLE;
                $options->numpartscorrect = \question_display_options::VISIBLE;
                $options->manualcomment = \question_display_options::EDITABLE;
                $options->generalfeedback = \question_display_options::VISIBLE;
                $options->rightanswer = \question_display_options::VISIBLE;
                $options->history = \question_display_options::VISIBLE;
            } else if ($reviewoptions instanceof \stdClass) {
                foreach ($reviewoptions as $field => $data) {
                    if ($when == 'closed') {
                        if (($field == 'reviewmarks') &&
                            ($data == \mod_topomojo_display_options::AFTER_CLOSE)) {
                            $options->marks = \question_display_options::MARK_AND_MAX;
                        } else {
                            $options->$field = \question_display_options::VISIBLE;
                        }
                        if (($field == 'reviewrightanswer') &&
                                ($data == \mod_topomojo_display_options::AFTER_CLOSE)) {
                            $options->rightanswer = \question_display_options::VISIBLE;
                        }
                    }
                }
                $state = \mod_topomojo_display_options::LATER_WHILE_OPEN;
                if ($when == 'closed') {
                    $state = \mod_topomojo_display_options::AFTER_CLOSE;
                }

                foreach (\mod_topomojo\topomojo::$reviewfields as $field => $data) {
                    $name = 'review' . $field;
                    if ($reviewoptions->{$name} & $state) {
                        if ($field == 'marks') {
                            $options->$field = \question_display_options::MARK_AND_MAX;
                        } else {
                                $options->$field = \question_display_options::VISIBLE;
                        }
                    }
                }

            }
        } else {
            // Default options for during quiz
            $options->rightanswer = \question_display_options::HIDDEN;
            $options->numpartscorrect = \question_display_options::HIDDEN;
            $options->manualcomment = \question_display_options::HIDDEN;
            $options->manualcommentlink = \question_display_options::HIDDEN;
        }

        return $options;
    }

    /**
     * Saves a question attempt from the topomojo question
     *
     * @return bool
     */
    public function save_question() {
        global $DB;

        $timenow = time();
        $transaction = $DB->start_delegated_transaction();
        $this->quba->process_all_actions($timenow);
        $this->attempt->timemodified = time();

        $this->save();

        $transaction->allow_commit();

        return true; // Return true if we get to here
    }

    /**
     * Returns the class instance of the quba
     *
     * @return \question_usage_by_activity
     */
    public function get_quba() {
        return $this->quba;
    }

    /**
     * Gets the mark for a slot from the quba
     *
     * @param int $slot
     * @return number|null
     */
    public function get_slot_mark($slot) {
        return $this->quba->get_question_mark($slot);
    }


    /**
     * Get the total points for this slot
     *
     * @param int $slot
     * @return number
     */
    public function get_slot_max_mark($slot) {
        return $this->quba->get_question_max_mark($slot);
    }


    /**
     * Process a comment for a particular question on an attempt
     *
     * @param int                        $slot
     * @param \mod_topomojo\topomojo $topomojo
     *
     * @return bool
     */
    public function process_comment($topomojo, $slot = null) {
        global $DB;

        // If there is no slot return false
        if (empty($slot)) {
            return false;
        }

        // Process any data that was submitted.
        if (data_submitted() && confirm_sesskey()) {
            if (optional_param('submit', false, PARAM_BOOL) &&
                \question_engine::is_manual_grade_in_range($this->attempt->questionusageid, $slot)
            ) {
                $transaction = $DB->start_delegated_transaction();
                $this->quba->process_all_actions(time());
                $this->save();
                $transaction->allow_commit();

                // Trigger event for question manually graded
                $params = array(
                    'objectid' => $this->quba->get_question($slot)->id,
                    //'courseid' => $topomojo->getCourse()->id,
                    'context'  => $this->context,
                    'other'    => [
                        'topomojoid'     => $topomojo->id,
                        'attemptid' => $this->attempt->id,
                        'slot'      => $slot,
                    ],
                );
                // TODO create event
                //$event = \mod_topomojo\event\question_manually_graded::create($params);
                //$event->trigger();

                return true;
            } else {
                // TODO maybe add button to go back
                echo "value entered is not in range";
                exit;
            }
        }

        return false;
    }

}
