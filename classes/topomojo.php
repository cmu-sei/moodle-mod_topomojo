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

/**
Topomojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

class topomojo {

    public $event;

    public $topomojo;

    public $openAttempt;

    //public $workspaceid;

    protected $context;

    protected $isinstructor;

    public $userauth;

    private $questionmanager;

    public $cm;

    public $pagevars;

    public $renderer;

    public $course;

    /**
     * @var array $review fields Static review fields to add as options
     */
    public static $reviewfields = array(
        'attempt'          => array('theattempt', 'topomojo'),
        'correctness'      => array('whethercorrect', 'question'),
        'marks'            => array('marks', 'topomojo'),
        'specificfeedback' => array('specificfeedback', 'question'),
        'generalfeedback'  => array('generalfeedback', 'question'),
        'rightanswer'      => array('rightanswer', 'question'),
    	'overallfeedback'  => array('overallfeedback', 'question'),
        'manualcomment'    => array('manualcomment', 'topomojo')
    );

    /**
     * Construct class
     *
     * @param object $cm The course module instance
     * @param object $course The course object the activity is contained in
     * @param object $topomojo The specific topomojo record for this activity
     * @param \moodle_url $pageurl The page url
     * @param array  $pagevars The variables and options for the page
     * @param string $renderer_subtype Renderer sub-type to load if requested
     *
     */
    public function __construct($cm, $course, $topomojo, $pageurl, $pagevars = array(), $renderer_subtype = null) {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->topomojo = $topomojo;
        $this->pagevars = $pagevars;

        $this->context = \context_module::instance($cm->id);
        $PAGE->set_context($this->context);

        $this->userauth = setup(); //fails when called by runtask

        $this->renderer = $PAGE->get_renderer('mod_topomojo', $renderer_subtype);
        $this->renderer->init($this, $pageurl, $pagevars);

        $this->questionmanager = new \mod_topomojo\questionmanager($this, $this->renderer, $this->pagevars);
    }


    /**
     * Wrapper for the has_capability function to provide the context
     *
     * @param string $capability
     * @param int    $userid
     *
     * @return bool Whether or not the current user has the capability
     */
    public function has_capability($capability, $userid = 0) {
        if ($userid !== 0) {
            // pass in userid if there is one
            return has_capability($capability, $this->context, $userid);
        } else {
            // just do standard check with current user
            return has_capability($capability, $this->context);
        }
    }

    /**
     * Quick function for whether or not the current user is the instructor/can control the quiz
     *
     * @return bool
     */
    public function is_instructor() {

        if (is_null($this->isinstructor)) {
            $this->isinstructor = $this->has_capability('mod/topomojo:manage');
            return $this->isinstructor;
        } else {
            return $this->isinstructor;
        }
    }

    public function get_attempt($attemptid) {
        global $DB;

        $dbattempt = $DB->get_record('topomojo_attempts', array("id" => $attemptid));

        return new topomojo_attempt($this->questionmanager, $dbattempt);
    }

    /**
     * Get the course module isntance
     *
     * @return object
     */
    public function getCM() {
        return $this->cm;
    }

    /**
     * Gets the context for this instance
     *
     * @return \context_module
     */
    public function getContext() {
        return $this->context;
    }

    public function get_open_attempt() {
        $attempts = $this->getall_attempts('open');
        if (count($attempts) > 1) {
            debugging("we have more than 1 open attempt", DEBUG_DEVELOPER);
            return false;
        } else if (count($attempts) == 0) {
            debugging("could not find an open attempt", DEBUG_DEVELOPER);
            return false;
        }
        debugging("open attempt found", DEBUG_DEVELOPER);

        // get the first (and only) value in the array
        $this->openAttempt = reset($attempts);

        return true;
    }

    public function getall_attempts($state = 'all', $review = false) {
        global $DB, $USER;

        $sqlparams = array();
        $where = array();

        $where[] = 'topomojoid = ?';
        $sqlparams[] = $this->topomojo->id;

        switch ($state) {
            case 'open':
                $where[] = 'state = ?';
                $sqlparams[] = topomojo_attempt::INPROGRESS;
                break;
            case 'closed':
                $where[] = 'state = ?';
                $sqlparams[] = topomojo_attempt::FINISHED;
                break;
            default:
                // add no condition for state when 'all' or something other than open/closed
        }

        if ((!$review) || (!$this->is_instructor())) {
            //debugging("getall_attempts for user", DEBUG_DEVELOPER);
            $where[] = 'userid = ?';
            $sqlparams[] = $USER->id;
        }

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT * FROM {topomojo_attempts} WHERE $wherestring ORDER BY timemodified DESC";
        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = array();
        // create array of class attempts from the db entry
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new topomojo_attempt($this->questionmanager, $dbattempt);
        }
        return $attempts;

    }

    public function init_attempt() {
        global $DB, $USER;

        $attempt = $this->get_open_attempt();
        if ($attempt === true) {
            debugging("init_attempt found " . $this->openAttempt->id, DEBUG_DEVELOPER);
            return true;
        }
        debugging("init_attempt could not find attempt", DEBUG_DEVELOPER);

        // create a new attempt
        $attempt = new topomojo_attempt($this->questionmanager);
        $attempt->launchpointurl = $this->event->launchpointUrl;
        $attempt->workspaceid = $this->topomojo->workspaceid;
        $attempt->userid = $USER->id;
        $attempt->state = \mod_topomojo\topomojo_attempt::NOTSTARTED;
        $attempt->timemodified = time();
        $attempt->timestart = time();
        $attempt->timefinish = null;
        $attempt->topomojoid = $this->topomojo->id;
        $attempt->score = 0;
        $attempt->endtime = strtotime($this->event->expirationTime);
        $attempt->eventid = $this->event->id;
        debugging("endtime for new attempt set to " . $attempt->endtime, DEBUG_DEVELOPER);

        if ($attempt->save()) {
            $this->openAttempt = $attempt;
        } else {
            return false;
        }


        $attempt->setState('inprogress');

        //TODO call start attempt event class from here
        return true;
    }
    /**
     * Returns the class instance of the question manager
     *
     * @return \mod_topomojo\questionmanager
     */
    public function get_question_manager() {
        return $this->questionmanager;
    }

    /**
     * Saves the topomojo instance to the database
     *
     * @return bool
     */
    public function save() {
        global $DB;

        return $DB->update_record('topomojo', $this->topomojo);
    }

    public function get_openclose_state() {
        $state = 'open';
        $timenow = time();
        if ($this->topomojo->timeopen && ($timenow < $this->topomojo->timeopen)) {
            $state = 'unopen';
            } else if ($this->topomojo->timeclose && ($timenow > $this->topomojo->timeclose)) {
            $state = 'closed';
        }

        return $state;
    }

    /**
     * Gets the review options for the specified time
     *
     * @param string $whenname The review options time that we want to get the options for
     *
     * @return \stdClass A class of the options
     */
    public function get_review_options() {

        $reviewoptions = new \stdClass();
	    $reviewoptions->reviewattempt = $this->topomojo->reviewattempt;
        $reviewoptions->reviewcorrectness = $this->topomojo->reviewcorrectness;
        $reviewoptions->reviewmarks = $this->topomojo->reviewmarks;
        $reviewoptions->reviewspecificfeedback = $this->topomojo->reviewspecificfeedback;
        $reviewoptions->reviewgeneralfeedback = $this->topomojo->reviewgeneralfeedback;
        $reviewoptions->reviewrightanswer = $this->topomojo->reviewrightanswer;
        $reviewoptions->reviewoverallfeedback = $this->topomojo->reviewoverallfeedback;
        $reviewoptions->reviewmanualcomment = $this->topomojo->reviewmanualcomment;

        return $reviewoptions;
    }

    public function canreviewmarks($reviewoptions, $state) {
        $canreviewmarks = false;
            if ($state == 'open') {
                if ($reviewoptions->reviewmarks & \mod_topomojo_display_options::LATER_WHILE_OPEN) {
                    $canreviewmarks = true;
                }
            } else if ($state == 'closed') {
                if ($reviewoptions->reviewmarks & \mod_topomojo_display_options::AFTER_CLOSE) {
                    $canreviewmarks = true;
                }
            }
        return  $canreviewmarks;
    }

    public function canreviewattempt($reviewoptions, $state) {
        $canreviewattempt = false;
        if ($state == 'open') {
            if ($reviewoptions->reviewattempt & \mod_topomojo_display_options::LATER_WHILE_OPEN) {
                $canreviewattempt = true;
            }
        } else if ($state == 'closed') {
            if ($reviewoptions->reviewattempt & \mod_topomojo_display_options::AFTER_CLOSE) {
                $canreviewattempt = true;
            }
        }
        return  $canreviewattempt;
    }

}
