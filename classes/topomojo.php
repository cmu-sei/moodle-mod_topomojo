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

        $this->is_instructor();

        $this->userauth = setup(); //fails when called by runtask

        $this->renderer = $PAGE->get_renderer('mod_topomojo', $renderer_subtype);
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

    /* this function currently returns all gamespaces deployed from a workspace with the same name.
     * this list is not specific to moodle-deployed labs or labs for the current user.
     * filter on the managerName to make it moodle-specifc or remove the Filter=All string.
     * removing the filter=all prevents the term= from working.
     * the records returned do not listed players.
     */
    function list_events() {
	    debugging("listing events", DEBUG_DEVELOPER);
        if ($this->userauth == null) {
            echo 'error with userauth<br>';
            return;
        }

        // web request
        $url = get_config('topomojo', 'alloyapiurl') . "/gamespaces?WantsAll=false&Term=" . rawurlencode($this->topomojo->name) . "&Filter=all";
        //$url = get_config('topomojo', 'alloyapiurl') . "/gamespaces?WantsAll=false&Term=" . rawurlencode($this->topomojo->name);
        echo "GET $url<br>";

        $response = $this->userauth->get($url);

        if ($this->userauth->info['http_code']  !== 200) {
            debugging('response code ' . $this->userauth->info['http_code'] . " $url", DEBUG_DEVELOPER);
            return;
        }

        //echo "response:<br><pre>";
        //print_r($response);
        //echo "</pre>";

        if (!$response) {
            debugging("no response received by list_events $url", DEBUG_DEVELOPER);
            return;
        }

        $r = json_decode($response, true);

        if (!$r) {
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            return;
        }

        debugging("returned " . count($r) . " events with same name", DEBUG_DEVELOPER);

        usort($r, 'whenCreated');
        return $r;
    }

    public function moodle_events($events) {
        $moodle_events = array();
        foreach ($events as $event) {
            if ($event['managerName'] == "Adam Welle") {
                //echo "<br>got moodle user<br>";
                array_push($moodle_events, $event);
            }    
        }
        debugging("found " . count($moodle_events) . " events started by moodle", DEBUG_DEVELOPER);
        return $moodle_events;
    }

    public function user_events($events) {
        global $USER;
	    //debugging("filtering events for user", DEBUG_DEVELOPER);
        if ($this->userauth == null) {
            echo 'error with userauth<br>';
            return;
        }
        $user_events = array();

        foreach ($events as $event) {
            // web request
            $url = get_config('topomojo', 'alloyapiurl') . "/gamespace/" . $event['id'];
            echo "<br>GET $url<br>";

            $response = $this->userauth->get($url);
            //print_r($response);
    
            if (!$response) {
                debugging("no response received by $url", DEBUG_DEVELOPER);
                return;
            }
    
            $r = json_decode($response, true);

            if (!$r) {
                debugging("could not decode json $url", DEBUG_DEVELOPER);
                return;
            }
    
            //debugging("returned array with " . count($r) . " elements", DEBUG_DEVELOPER);
            $players = $r['players'];
            //print_r($players);

            $subjectid = explode( "@", $USER->email )[0];
            //echo "<br>subjectid $subjectid<br>";
            foreach ($players as $player) {
                //print_r($player);
                if ($player['subjectId'] = $subjectid) {
                    //echo "found user";
                    array_push($user_events, $r);
                }
            }
        }
        debugging("found " . count($user_events) . " events for this user", DEBUG_DEVELOPER);
        return $user_events;
    }

    public function get_attempt($attemptid) {
        global $DB;

        $dbattempt = $DB->get_record('topomojo_attempts', array("id" => $attemptid));

        return new topomojo_attempt($dbattempt);
    }


    public function get_open_attempt() {
        $attempts = $this->getall_attempts('open');
        if (count($attempts) !== 1) {
            debugging("could not find a single open attempt", DEBUG_DEVELOPER);
            return false;
        }
        debugging("open attempt found", DEBUG_DEVELOPER);

        // get the first (and only) value in the array
        $this->openAttempt = reset($attempts);

        if (isset($this->event)) {
            // update values if null in attempt but exist in event
            if ((!$this->openAttempt->eventid) && ($this->event->id)) {
                $this->openAttempt->eventid = $this->event->id;
            }

            $this->openAttempt->endtime = strtotime($this->event->expirationTime);
            $this->openAttempt->save();
        }
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
            $attempts[] = new topomojo_attempt($dbattempt);
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
        $attempt = new \mod_topomojo\topomojo_attempt();
        $attempt->launchpointurl = $this->event->launchpointUrl;
        $attempt->workspaceid = $this->topomojo->workspaceid;
        $attempt->userid = $USER->id;
        $attempt->state = \mod_topomojo\topomojo_attempt::NOTSTARTED;
        $attempt->timemodified = time();
        $attempt->timestart = time();
        $attempt->timefinish = null;
        $attempt->topomojoid = $this->topomojo->id;
        $attempt->setState('inprogress');
        $attempt->score = 0;
        $attempt->endtime = strtotime($this->event->expirationTime);
        debugging("endtime for new attempt set to " . $attempt->endtime, DEBUG_DEVELOPER);

        if ($this->event->id) {
            $attempt->eventid = $this->event->id;
        } else {
            $attempt->event->id = 0;
        }

        if ($attempt->save()) {
            $this->openAttempt = $attempt;
        } else {
            return false;
        }

        //TODO call start attempt event class from here
        return true;
    }

}
