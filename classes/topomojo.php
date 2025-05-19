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

/**
 * topomojo Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_topomojo
 * @copyright   2024 Carnegie Mellon Univeristy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topomojo {

    /**
     * @var \stdClass Contains information about the current event, such as launch point URL, workspace ID, and expiration time
     */
    public $event;

    /**
     * @var \stdClass The topomojo record associated with this instance
     */
    public $topomojo;

    /**
     * @var topomojo_attempt The currently open attempt
     */
    public $openAttempt;

    /**
     * @var \context_module The context for the course module
     */
    protected $context;

    /**
     * @var bool Whether the current user is an instructor
     */
    protected $isinstructor;

    /**
     * @var mixed User authentication information
     */
    public $userauth;

    /**
     * @var \mod_topomojo\questionmanager Manages questions for the topomojo activity
     */
    private $questionmanager;

    /**
     * @var \stdClass The course module instance
     */
    public $cm;

    /**
     * @var array Variables and options for the page
     */
    public $pagevars;

    /**
     * @var \renderer_base Renderer instance for the topomojo activity
     */
    public $renderer;

    /**
     * @var \stdClass The course object for the activity
     */
    public $course;

    /**
     * @var array $review fields Static review fields to add as options
     */
    public static $reviewfields = [
        'attempt'          => ['theattempt', 'topomojo'],
        'correctness'      => ['whethercorrect', 'question'],
        'marks'            => ['marks', 'topomojo'],
        'specificfeedback' => ['specificfeedback', 'question'],
        'generalfeedback'  => ['generalfeedback', 'question'],
        'rightanswer'      => ['rightanswer', 'question'],
        'overallfeedback'  => ['overallfeedback', 'question'],
        'manualcomment'    => ['manualcomment', 'topomojo'],
    ];

    /**
     * Construct class
     *
     * @param object $cm The course module instance
     * @param object $course The course object the activity is contained in
     * @param object $topomojo The specific topomojo record for this activity
     * @param \moodle_url $pageurl The page url
     * @param array  $pagevars The variables and options for the page
     * @param string $renderersubtype Renderer sub-type to load if requested
     *
     */
    public function __construct($cm, $course, $topomojo, $pageurl = null, $pagevars = [], $renderersubtype = null) {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->topomojo = $topomojo;
        $this->pagevars = $pagevars;

        $this->context = \context_module::instance($cm->id);
        $PAGE->set_context($this->context);
        $this->renderer = $PAGE->get_renderer('mod_topomojo', $renderersubtype);

        if ($pageurl) {
            // skip this initialization during cron task
            $this->renderer->init($this, $pageurl, $pagevars);
            if (str_contains($pageurl->get_path(), "edit.php")) {
                $this->questionmanager = new \mod_topomojo\questionmanager($this, $this->renderer, $this->pagevars);
	    } else if ((str_contains($pageurl->get_path(), "/view.php")) ||
		    (str_contains($pageurl->get_path(), "challenge.php")) ||
		    (str_contains($pageurl->get_path(), "viewattempt.php"))) {
		// if there are questions added to the challenge, load questionmanager on the other pages
                if (isset($this->topomojo->questionorder)) {
                        $this->questionmanager = new \mod_topomojo\questionmanager($this, $this->renderer, $this->pagevars);
                }
            }
            $this->userauth = setup();
        }
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
            // Pass in userid if there is one
            return has_capability($capability, $this->context, $userid);
        } else {
            // Just do standard check with current user
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

    /**
     * Retrieves a specific attempt record by its ID.
     *
     * @param int $attemptid The ID of the attempt record to retrieve.
     * @return \mod_topomojo\topomojo_attempt A `topomojo_attempt` object representing the attempt.
     */
    public function get_attempt($attemptid) {
        global $DB;

        $dbattempt = $DB->get_record('topomojo_attempts', ["id" => $attemptid]);

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

    /**
     * Retrieves the currently open attempt.
     *
     * This function checks for attempts with the state 'open'. It logs debug messages if there are multiple or no open attempts.
     * If exactly one open attempt is found, it sets this attempt as the current open attempt and returns `true`.
     * If no open attempts are found or if there is more than one open attempt, it returns `false`.
     *
     * @return bool Returns `true` if an open attempt is found and set; otherwise, returns `false`.
     */
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

        // Get the first (and only) value in the array
        $this->openAttempt = reset($attempts);

        return true;
    }

    /**
     * Retrieves all attempts based on the specified state and review access.
     *
     * This function fetches attempts from the database based on the state of the attempt and the review access settings.
     * It returns an array of `topomojo_attempt` objects created from the retrieved database records.
     *
     * @param string $state The state of the attempts to retrieve. Can be 'open', 'closed', or 'all'. Default is 'all'.
     * @param bool $review Indicates whether review access is permitted. Default is `false`.
     * @return topomojo_attempt[] An array of `topomojo_attempt` objects representing the attempts matching the criteria.
     */
    public function getall_attempts($state = 'all', $review = false) {
        global $DB, $USER;

        $sqlparams = [];
        $where = [];

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
                // Add no condition for state when 'all' or something other than open/closed
        }

        if ((!$review) || (!$this->is_instructor())) {
            // Debugging("getall_attempts for user", DEBUG_DEVELOPER);
            $where[] = 'userid = ?';
            $sqlparams[] = $USER->id;
        }

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT * FROM {topomojo_attempts} WHERE $wherestring ORDER BY timemodified DESC";
        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = [];
        // Create array of class attempts from the db entry
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new topomojo_attempt($this->questionmanager, $dbattempt);
        }

        return $attempts;

    }

    /**
     * Retrieves attempts for a specific user based on the given state and review access settings.
     *
     * This function fetches attempts from the database for a specified user.
     * The attempts are filtered based on their state (`'open'`, `'closed'`, or `'all'`) and the review access settings.
     * It returns an array of `topomojo_attempt` objects created from the retrieved database records.
     *
     * @param int $userid The ID of the user for whom attempts are to be retrieved.
     * @param string $state The state of the attempts to retrieve. Can be `'open'`, `'closed'`, or `'all'`. Default is `'all'`.
     * @param bool $review Indicates whether review access is permitted. Default is `false`.
     * @return topomojo_attempt[] An array of `topomojo_attempt` objects representing the attempts for the specified user.
     */
    public function get_attempts_by_user($userid, $state = 'all', $review = false) {
        global $DB;

        $sqlparams = [];
        $where = [];

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
                // Add no condition for state when 'all' or something other than open/closed
        }

        if ((!$review) || (!$this->is_instructor())) {
            // Debugging("get_attempts_by_user for user", DEBUG_DEVELOPER);
            $where[] = 'userid = ?';
            $sqlparams[] = $userid;
        }

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT * FROM {topomojo_attempts} WHERE $wherestring ORDER BY timemodified DESC";
        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = [];
        // Create array of class attempts from the db entry
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new topomojo_attempt($this->questionmanager, $dbattempt);
        }
        return $attempts;
    }

    /**
     * Initializes an attempt for the current user, either by finding an existing open attempt or creating a new one.
     *
     * This function checks if there is an existing open attempt for the current user.
     * If an open attempt is found, it logs the attempt ID and returns `true`.
     * If no open attempt is found, it creates a new `topomojo_attempt` object, initializes it with relevant details,
     * saves it to the database, and sets its state to `'inprogress'`.
     * The function returns `true` on successful creation or updating of an attempt, and `false` if the attempt could not be saved.
     *
     * @return bool Returns `true` if an open attempt is found or a new attempt is successfully created and started,
     * otherwise `false`.
     */
    public function init_attempt() {
        global $DB, $USER;

        $attempt = $this->get_open_attempt();
        if ($attempt === true) {
            debugging("init_attempt found " . $this->openAttempt->id, DEBUG_DEVELOPER);
            return true;
        }
        debugging("init_attempt could not find attempt by calling get_open_attempt", DEBUG_DEVELOPER);

        // Create a new attempt
        $attempt = new topomojo_attempt(questionmanager: $this->questionmanager);
        $attempt->launchpointurl = $this->event->launchpointUrl;
        $attempt->workspaceid = $this->topomojo->workspaceid;
        $attempt->userid = $USER->id;
        $attempt->state = \mod_topomojo\topomojo_attempt::INPROGRESS;
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
            debugging("saved attempt", DEBUG_DEVELOPER);
        } else {
            debugging("could not save new attempt", DEBUG_DEVELOPER);
            return false;
        }

        //$attempt->setState('inprogress');

        // TODO call start attempt event class from here
        return true;
    }
    /**
     * Returns the class instance of the question manager
     *
     * @return \mod_topomojo\questionmanager
     */
    public function get_question_manager(): questionmanager {
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

    /**
     * Determines the current state of the topomojo activity based on the current time and the defined open and close times.
     *
     * This function evaluates the current time against the `timeopen` and `timeclose` properties of the `topomojo` object
     * to determine and return the state of the activity. The possible states are:
     * - `'unopen'` if the current time is before the `timeopen`.
     * - `'closed'` if the current time is after the `timeclose`.
     * - `'open'` if the current time is within the open and close times.
     *
     * @return string Returns the state of the activity. Possible values are `'unopen'`, `'open'`, or `'closed'`.
     */
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

    /**
     * Determines if the user can review marks based on the current state of the activity and review options.
     *
     * This function checks if marks can be reviewed by comparing the current state of the activity
     * with the review options provided. The ability to review marks depends on:
     * - If the activity is 'open' and the review options allow reviewing marks while the activity is open.
     * - If the activity is 'closed' and the review options allow reviewing marks after the activity has closed.
     *
     * @param stdClass $reviewoptions An object containing review options, including flags for when marks can be reviewed.
     * @param string $state The current state of the activity, which can be 'open' or 'closed'.
     * @return bool Returns `true` if the user can review marks based on the state and review options, otherwise `false`.
     */
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

    /**
     * Determines if the user can review the attempt based on the current state of the activity and review options.
     *
     * This function checks if an attempt can be reviewed by comparing the current state of the activity
     * with the review options provided. The ability to review an attempt depends on:
     * - If the activity is 'open' and the review options allow reviewing attempts while the activity is open.
     * - If the activity is 'closed' and the review options allow reviewing attempts after the activity has closed.
     *
     * @param stdClass $reviewoptions An object containing review options, including flags for when attempts can be reviewed.
     * @param string $state The current state of the activity, which can be 'open' or 'closed'.
     * @return bool Returns `true` if the user can review the attempt based on the state and review options, otherwise `false`.
     */
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

    public function delete_all_attempts_and_grades() {
        global $DB;
    
        // Delete all attempts linked to this activity
        $DB->delete_records('topomojo_attempts', ['topomojoid' => $this->topomojo->id]);
    
        // Delete all grades linked to this activity
        $DB->delete_records('topomojo_grades', ['topomojoid' => $this->topomojo->id]);
    
        // Remove grades from Moodle gradebook
        require_once($GLOBALS['CFG']->libdir . '/gradelib.php');
        grade_update('mod/topomojo', $this->course->id, 'mod', 'topomojo', $this->topomojo->id, 0, null, ['deleted' => 1]);
    }

}
