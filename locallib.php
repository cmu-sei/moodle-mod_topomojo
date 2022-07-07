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

function setup() {
        $client = new curl;
        $x_api_key = get_config('topomojo', 'apikey');
        $topoHeaders = array( 'x-api-key: ' . $x_api_key, 'content-type: application/json' );
        $client->setHeader($topoHeaders);
	#debugging("api key $x_api_key", DEBUG_DEVELOPER);
        return $client;
}

function get_workspace($client, $id) {
    global $USER;
    if ($client == null) {
        print_error('could not setup session');
    }

    // web request
    $url = get_config('topomojo', 'alloyapiurl') . "/workspace/" . $id;
    #echo "GET $url<br>";

    $response = $client->get($url);

    if ($client->info['http_code'] !== 200) {
        #debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        print_error($client->info['http_code'] . " for $url " . $client->response['raw-response']);
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

function tasksort($a, $b) {
    return strcmp($a->name, $b->name);
}

// filter for tasks the user can see and sort by name
function filter_tasks($tasks, $visible = 0, $gradable = 0) {
    global $DB;
    if (is_null($tasks)) {
        return;
    }
    $filtered = array();
    foreach ($tasks as $task) {

        $rec = $DB->get_record_sql('SELECT * from {topomojo_tasks} WHERE '
                . $DB->sql_compare_text('dispatchtaskid') . ' = '
                . $DB->sql_compare_text(':dispatchtaskid'), ['dispatchtaskid' => $task->id]);

        if ($rec === false) {
            // do not display tasks we do not have in the db
            debugging('could not find task in db ' . $task->id, DEBUG_DEVELOPER);
            continue;
        }

        if ($visible === (int)$rec->visible ) {
            $task->points = $rec->points;
            $filtered[] = $task;
        }

        // TODO show automatic checks or show manual tasks only?
        //if ($task->triggerCondition == "Manual") {
        //    $filtered[] = $task;
        //}
        //$filtered[] = $task;
    }
    // sort the array by name
    usort($filtered, "tasksort");
    return $filtered;
}

function get_workspaces($client) {

    if ($client == null) {
        debugging('error with client in get_workspaces', DEBUG_DEVELOPER);
        return;
    }

    // web request
    $url = get_config('topomojo', 'alloyapiurl') . "/workspaces";
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

function start_event($client, $id) {
    global $USER;
    debugging("starting gamespace from workspace $id", DEBUG_DEVELOPER);

    if ($client == null) {
        debugging('error with client in start_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'alloyapiurl') . "/gamespace";
    echo "POST $url<br>";

    //generate post data
    $payload = new stdClass();
    $payload->resourceId = $id;
    $payload->startGamespace = true;
    $payload->allowPreview = false;
    $payload->allowReset = false;
    $payload->maxAttempts = 1; // TODO get this from settings
    $payload->maxMinutes = 120; // TODO get this from settings
    $payload->points = 100; // TODO get this from settings
    $payload->variant = 0; // TODO get this from settings
    $payload->players = array();
    $payload->players[0] = new stdClass();
    $payload->players[0]->subjectId = explode( "@", $USER->email )[0];
    $payload->players[0]->subjectName = $USER->username;
    $json = json_encode($payload);
    print_r($json);


    $client->setopt( array( 'CURLOPT_POSTFIELDS' => $json) );

    $response = $client->post($url, $json);
    if (!$response) {
        debugging('no response received by start_event response code ' , $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }
    echo "response:<br><pre>$response</pre>";
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


function stop_event($client, $id) {

    if ($client == null) {
        debugging('error with client in stop_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'alloyapiurl') . "/events/" . $id . "/end";
    //echo "DELETE $url<br>";

    $response = $client->delete($url);

    if ($client->info['http_code']  !== 204) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
    }

    //if (!$response) {
        //throw new \Exception($response);
    //    return;
    //}
    //echo "response:<br><pre>$response</pre>";
    return;
}

function run_task($client, $id) {

    if ($client == null) {
        return;
    }

    // web request
    $url = get_config('topomojo', 'steamfitterapiurl') . "/tasks/" . $id . "/execute";

    $response = $client->post($url);

    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }

    $r = json_decode($response);

    return $r;
}

function extend_event($client, $data) {

    if ($client == null) {
        return;
    }

    // web request
    $url = get_config('topomojo', 'alloyapiurl') . "/events/" . $data->id;
    $client->setHeader('Content-Type: application/json-patch+json');

    $response = $client->put($url, json_encode($data));

    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }

    $r = json_decode($response);

    return $r;
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
    $url = get_config('topomojo', 'alloyapiurl') . "/gamespace/" . $id;
    echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_event for $url", DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json for $url", DEBUG_DEVELOPER);
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

function get_task($client, $id) {

    if ($client == null) {
        debugging('error with client in get_tasks', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_tasks', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('topomojo', 'steamfitterapiurl') . "/tasks/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging('no response received by get_tasks', DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code']  === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

function endDate($a, $b) {
    return strnatcmp($a['endDate'], $b['endDate']);
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
}

function get_token($client) {
    $access_token = $client->get_accesstoken();
    return $access_token->token;
}

function get_refresh_token($client) {
    $refresh_token = $client->get_refresh_token();
    return $refresh_token->token;
}

function get_scopes($client) {
    $access_token = $client->get_accesstoken();
    return $access_token->scope;
}

function get_clientid($client) {
    $issuer = $client->get_issuer();
    return $issuer->get('clientid');
}

function get_clientsecret($client) {
    $issuer = $client->get_issuer();
    return $issuer->get('clientsecret');
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

// this functions returns all the vms in a view
function get_allvms($auth, $id) {
    if ($auth == null) {
        echo 'error with auth<br>';
        return;
    }

    if ($id == null) {
        echo 'error with id<br>';
        return;
    }

    // web request
    $url = "https://s3vm.cyberforce.site/api/views/" . $id . "/vms";
    //echo "GET $url<br>";

    $response = $auth->get($url);
    if (!$response) {
        debugging("no response received by get_allvms $url", DEBUG_DEVELOPER);
        return;
    }
    if ($auth->info['http_code']  !== 200) {
        debugging('response code ' . $auth->info['http_code'], DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    if ($response === "[]") {
        return;
    }

    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
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
