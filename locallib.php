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

/**
 * Private topomojo module utility functions
 *
 * @package    mod_topomojo
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once($CFG->libdir . '/questionlib.php');

function setup() {
        $client = new curl;
        $x_api_key = get_config('topomojo', 'apikey');
        $topoHeaders = array( 'x-api-key: ' . $x_api_key, 'content-type: application/json' );
        $client->setHeader($topoHeaders);
	//debugging("api key $x_api_key", DEBUG_DEVELOPER);
        return $client;
}

/* this function currently returns all gamespaces deployed from a workspace with the same name.
 * this list is not specific to moodle-deployed labs or labs for the current user.
 * filter on the managerName to make it moodle-specifc or remove the Filter=All string.
 * removing the filter=all prevents the term= from working.
 * the records returned do not listed players.
 */
function list_events($client, $name) {
	//debugging("listing events", DEBUG_DEVELOPER);
    if ($client == null) {
        print_error('error with userauth');
        return;
    }

    // web request
    //echo $name . "<br>";
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespaces?WantsAll=false&Term=" . rawurlencode("Wireless") . "&Filter=all";
    //$url = get_config('topomojo', 'topomojoapiurl') . "/gamespaces?WantsAll=false&Term=" . rawurlencode($name) . "&WantsActive=true";
    //echo "GET $url<br>";

    $response = $client->get($url);

    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'] . " $url", DEBUG_DEVELOPER);
        return;
    }
    if (!$response) {
        debugging("no response received by list_events $url", DEBUG_DEVELOPER);
        return;
    }

    $r = json_decode($response, true);

    if (!$r) {
        debugging("could not decode json $url", DEBUG_DEVELOPER);
        return;
    }
    usort($r, 'whenCreated');
    return $r;
}

function moodle_events($events) {
    $moodle_events = array();
    if (!is_array($events)) {
        debugging("no events to parse in moodle_events", DEBUG_DEVELOPER);
        return;
    }
    foreach ($events as $event) {
        if ($event['managerName'] == "Adam Welle") {
            //echo "<br>got moodle user<br>";
            array_push($moodle_events, $event);
        }
    }
    //debugging("found " . count($moodle_events) . " events started by moodle", DEBUG_DEVELOPER);
    return $moodle_events;
}

function user_events($client, $events) {
    global $USER;
    //debugging("filtering events for user", DEBUG_DEVELOPER);
    if ($client == null) {
        print_error('error with userauth');
        return;
    }
    $user_events = array();

    if (!is_array($events)) {
        debugging("cannot parse for user_events if events is not an array", DEBUG_DEVELOPER);
        return;
    }

    foreach ($events as $event) {
        // web request
        $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $event['id'];
        //echo "<br>GET $url<br>";

        $count = 1;
        $response = null;
        do {
            $response = $client->get($url);
            //print_r($response);

            if (!$response) {
                debugging("no response received by $url in attempt $count", DEBUG_DEVELOPER);
                $count++;
            }
        } while (!$response && ($count < 4));
        if (!$response) {
            print_error("Error communicating with Topomojo after $count attempts: " . $response);
            return;
        }

        $r = json_decode($response, true);

        if (!$r) {
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            print_error("Error communicating with Topomojo after $count attempts: " . $response);
            return;
        }

        //debugging("returned array with " . count($r) . " elements", DEBUG_DEVELOPER);
        $players = $r['players'];
        //print_r($players);

        $subjectid = explode( "@", $USER->email )[0];
        //echo "<br>subjectid $subjectid<br>";

        if (!is_array($players)) {
            debugging("no players for this event " + $event->id, DEBUG_DEVELOPER);
            return;

        }
        foreach ($players as $player) {
            //print_r($player);
            if ($player['subjectId'] == $subjectid) {
                //echo "found user";
                array_push($user_events, $r);
            }
        }
    }
    //debugging("found " . count($user_events) . " events for this user", DEBUG_DEVELOPER);
    return $user_events;
}

function get_workspace($client, $id) {
    global $USER;
    if ($client == null) {
        print_error('could not setup session');
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/workspace/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);

    // TODO handle network error

    if ($client->info['http_code'] !== 200) {
        //debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        //print_r($client->response);
        // TODO we dont have an httpp_code if the connection failed
        print_error($client->info['http_code'] . " for $url");
    }

    if (!$response) {
        debugging('no response received by get_workspace', DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);

    if (!$r) {
        debugging("could not find item by id", DEBUG_DEVELOPER);
        return;
    }

    return $r;
}

function get_workspaces($client) {

    if ($client == null) {
        debugging('error with client in get_workspaces', DEBUG_DEVELOPER);
        return;
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/workspaces";
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_workspaces for $url", DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }
    $r = json_decode($response);

    if (!$r) {
        debugging("could not find item by id", DEBUG_DEVELOPER);
        return;
    }
    return $r;
}


function get_gamespace_challenge($client, $id) {
    global $USER;
    if ($client == null) {
        print_error('could not setup session');
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $id . "/challenge";
    //echo "GET $url<br>";

    $response = $client->get($url);

    // TODO handle network error

    if ($client->info['http_code'] !== 200) {
        //debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        //print_r($client->response);
        // TODO we dont have an httpp_code if the connection failed
        print_error($client->info['http_code'] . " for $url");
    }

    if (!$response) {
        debugging('no response received by get_workspace', DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);

    if (!$r) {
        debugging("could not find item by id", DEBUG_DEVELOPER);
        return;
    }

    return $r;
}

function start_event($client, $id, $topomojo) {
    global $USER;
    debugging("starting gamespace from workspace $id", DEBUG_DEVELOPER);

    if ($client == null) {
        debugging('error with client in start_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace";
    //echo "POST $url<br>";

    //generate post data
    $payload = new stdClass();
    $payload->resourceId = $id;
    $payload->startGamespace = true;
    $payload->allowPreview = false;
    $payload->allowReset = false;
    $payload->maxAttempts = 1; // TODO get this from settings
    $payload->maxMinutes = $topomojo->duration / 60;
    $payload->points = $topomojo->grade;
    $payload->variant = $topomojo->variant;
    $payload->players = array();
    $payload->players[0] = new stdClass();
    $payload->players[0]->subjectId = explode( "@", $USER->email )[0];
    $payload->players[0]->subjectName = $USER->username;
    $json = json_encode($payload);
    //print_r($json);


    $client->setopt( array( 'CURLOPT_POSTFIELDS' => $json) );

    $response = $client->post($url, $json);

    if (!$response) {
        debugging('no response received by start_event response code ' , $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //echo "could not decode json<br>";
        return;
    }

    // success
    if ($client->info['http_code']  === 200) {
        return $r;
    }

    //echo "response code ". $client->info['http_code'] . "<br>";
    debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    //print_r($r);

    return;
}


function stop_event($client, $id) {

    if ($client == null) {
        debugging('error with client in stop_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $id . "/complete";
    //echo "POST $url<br>";

    $response = $client->post($url);

    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
    }

    if (!$response) {
        throw new \Exception($response);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    return;
}

function get_ticket($client) {

    if ($client == null) {
        debugging('error with client in get_ticket', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/user/ticket";
    //echo "POST $url<br>";

    $response = $client->get($url);

    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //echo "could not decode json<br>";
        return;
    }

    // success
    if ($client->info['http_code']  === 200) {
        return $r->ticket;
    }
    if ($client->info['http_code']  === 500) {
        //echo "response code ". $client->info['http_code'] . "<br>";
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

function get_invite($client, $id) {

    if ($client == null) {
        debugging('error with client in get_invite', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $id . "/invite";
    //echo "POST $url<br>";

    $response = $client->post($url);

    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //echo "could not decode json<br>";
        return;
    }

    // success
    if ($client->info['http_code']  === 200) {
        return $r;
    }
    if ($client->info['http_code']  === 500) {
        //echo "response code ". $client->info['http_code'] . "<br>";
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

function extend_event($client, $data) {

    if ($client == null) {
        return;
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace";
    $client->setHeader('Content-Type: application/json-patch+json');

    $response = $client->put($url, json_encode($data));

    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return false;
    }

    return true;
}

function get_event($client, $id) {

    if ($client == null) {
        debugging('error with client in get_event', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_event for $url", DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //debugging("could not decode json for $url", DEBUG_DEVELOPER);
        print_error($response);
        return;
    }

    if ($client->info['http_code']  === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        print_error($r->detail);
    }
    return;
}

function whenCreated($a, $b) {
    return strnatcmp($a['whenCreated'], $b['whenCreated']);
}

function get_active_event($history) {
    if ($history == null) {
        return null;
    }
    foreach ($history as $odx) {
        if (($odx['isActive'] == true)) {
            debugging("we found an active event in the history pulled from topomojo", DEBUG_DEVELOPER);
            return (object)$odx;
        }
    }
    debugging("there are no active events in the history pulled from topomojo", DEBUG_DEVELOPER);
    return null;
}

/**
 * @return array int => lang string the options for calculating the topomojo grade
 *      from the individual attempt grades.
 */
function topomojo_get_grading_options() {
    return array(
        TOPOMOJO_GRADEHIGHEST => get_string('gradehighest', 'topomojo'),
        TOPOMOJO_GRADEAVERAGE => get_string('gradeaverage', 'topomojo'),
        TOPOMOJO_ATTEMPTFIRST => get_string('attemptfirst', 'topomojo'),
        TOPOMOJO_ATTEMPTLAST  => get_string('attemptlast', 'topomojo')
    );
}

/**
 * @param int $option one of the values TOPOMOJO_GRADEHIGHEST, TOPOMOJO_GRADEAVERAGE,
 *      TOPOMOJO_ATTEMPTFIRST or TOPOMOJO_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function topomojo_get_grading_option_name($option) {
    $strings = topomojo_get_grading_options();
    return $strings[$option];
}

function topomojo_end($cm, $context, $topomojo) {
    global $USER;
    $params = array(
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id
    );
    $event = \mod_topomojo\event\attempt_ended::create($params);
    $event->add_record_snapshot('topomojo', $topomojo);
    $event->trigger();
}

function topomojo_start($cm, $context, $topomojo) {
    global $USER;
    $params = array(
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id
    );
    $event = \mod_topomojo\event\attempt_started::create($params);
    $event->add_record_snapshot('topomojo', $topomojo);
    $event->trigger();
}

function get_challenge($client, $id) {
    global $USER;
    if ($client == null) {
        print_error('could not setup session');
    }

    // web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/challenge/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);

    // TODO handle network error

    if ($client->info['http_code'] !== 200) {
        //debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        //print_r($client->response);
        // TODO we dont have an httpp_code if the connection failed
        print_error($client->info['http_code'] . " for $url");
    }

    if (!$response) {
        debugging('no response received by get_challenge', DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);

    if (!$r) {
        debugging("could not find item by id", DEBUG_DEVELOPER);
        return;
    }

    return $r;
}

function getall_course_attempts($course) {
    global $DB, $USER;

    $sqlparams = array();
    $where = array();

    $where[] = '{topomojo}.course= ?';
    $sqlparams[] = $course;

    $wherestring = implode(' AND ', $where);

    $sql = "SELECT {topomojo_attempts}.* FROM {topomojo_attempts} JOIN {topomojo} ON {topomojo_attempts}.topomojoid = {topomojo}.id WHERE $wherestring";
    $dbattempts = $DB->get_records_sql($sql, $sqlparams);

    $attempts = array();
    // create array of class attempts from the db entry
    foreach ($dbattempts as $dbattempt) {
        $attempts[] = new \mod_topomojo\topomojo_attempt($dbattempt);
    }

    return $attempts;

}

// not used yet
function getall_topomojo_attempts($course) {
    global $DB, $USER;

    $sql = "SELECT * FROM {topomojo_attempts}";
    $dbattempts = $DB->get_records_sql($sql);

    $attempts = array();
    // create array of class attempts from the db entry
    foreach ($dbattempts as $dbattempt) {
        $attempts[] = new \mod_topomojo\topomojo_attempt($dbattempt);
    }

    return $attempts;

}

function topomojo_question_tostring($question, $showicon = false, $showquestiontext = true,
        $showidnumber = false, $showtags = false) {
    global $OUTPUT;
    $result = '';

    // Question name.
    $name = shorten_text(format_string($question->name), 200);
    if ($showicon) {
        $name .= print_question_icon($question) . ' ' . $name;
    }
    $result .= html_writer::span($name, 'questionname');

    // Question idnumber.
    if ($showidnumber && $question->idnumber !== null && $question->idnumber !== '') {
        $result .= ' ' . html_writer::span(
                html_writer::span(get_string('idnumber', 'question'), 'accesshide') .
                ' ' . s($question->idnumber), 'badge badge-primary');
    }

    // Question tags.
    if (is_array($showtags)) {
        $tags = $showtags;
    } else if ($showtags) {
        $tags = core_tag_tag::get_item_tags('core_question', 'question', $question->id);
    } else {
        $tags = [];
    }
    if ($tags) {
        $result .= $OUTPUT->tag_list($tags, null, 'd-inline', 0, null, true);
    }

    // Question text.
    if ($showquestiontext) {
        $questiontext = question_utils::to_plain_text($question->questiontext,
                $question->questiontextformat, array('noclean' => true, 'para' => false));
        $questiontext = shorten_text($questiontext, 50);
        if ($questiontext) {
            $result .= ' ' . html_writer::span(s($questiontext), 'questiontext');
        }
    }

    return $result;

}

/**
 * @param object $topomojo the topomojo settings
 * @param object $question the question
 * @param int $variant which question variant to preview (optional).
 * @return moodle_url to preview this question with the options from this quiz.
 */
function topomojo_question_preview_url($topomojo, $question, $variant = null) {
    // Get the appropriate display options.
    $displayoptions = topomojo_display_options::make_from_topomojo($topomojo,
            topomojo_display_options::DURING);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correcte preview URL.
    return \qbank_previewquestion\helper::question_preview_url($question->id, $topomojo->preferredbehaviour,
            $maxmark, $displayoptions, $variant);
}

/**
 * @param int $topomojoid The topomojo id.
 * @return bool whether this topomojo has any (non-preview) attempts.
 */
function topomojo_has_attempts($topomojoid) {
    global $DB;
    return $DB->record_exists('topomojo_attempts', array('topomojoid' => $topomojoid));

}
/**
 * An extension of question_display_options that includes the extra options used
 * by the quiz.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_topomojo_display_options extends question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * quiz attempt.
     */
    const DURING =            0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN =  0x00100;
    const AFTER_CLOSE =       0x00010;
    /**#@-*/

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the quiz settings, and a time constant.
     * @param object $topomojo the quiz settings.
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_topomojo_display_options set up appropriately.
     */
    // TODO remove if not used
    public static function make_from_topomojo($topomojo, $when) {
        $options = new self();

        $options->attempt = self::extract($topomojo->reviewattempt, $when, true, false);
        $options->correctness = self::extract($topomojo->reviewcorrectness, $when);
        $options->marks = self::extract($topomojo->reviewmarks, $when,
                self::MARK_AND_MAX, self::MAX_ONLY);
        $options->feedback = self::extract($topomojo->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($topomojo->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($topomojo->reviewrightanswer, $when);            $options->overallfeedback = self::extract($topomojo->reviewoverallfeedback, $when);
        $options->numpartscorrect = $options->feedback;
            $options->manualcomment = $options->feedback;
            //$options->manualcomment = self::extract($topomojo->reviewmanualcomment, $when);

        if ($topomojo->questiondecimalpoints != -1) {
            $options->markdp = $topomojo->questiondecimalpoints;
        } else {
            $options->markdp = $topomojo->decimalpoints;
        }

        return $options;
    }

    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}



