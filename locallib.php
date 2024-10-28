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
 * Private topomojo module utility functions
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once($CFG->libdir . '/questionlib.php');

/**
 * Sets up and returns a cURL client with the required headers.
 *
 * This function initializes a cURL client and configures it with the necessary headers,
 * including the API key for the TopoMojo service. The API key is retrieved from the configuration.
 *
 * @return curl The configured cURL client instance.
 */
function setup() {
        $client = new curl;
        $xapikey = get_config('topomojo', 'apikey');
        $topoheaders = array('x-api-key: ' . $xapikey, 'content-type: application/json');
        $client->setHeader($topoheaders);
        //debugging("api key $xapikey", DEBUG_DEVELOPER);
        return $client;
}

/**
 * Retrieves and filters events from the gamespaces endpoint.
 *
 * This function makes a web request to the TopoMojo API to list gamespaces that match the given name.
 * It filters the results based on the active status of the events and the provided name. The function
 * also handles HTTP errors and JSON decoding issues.
 *
 * @param curl $client The cURL client instance used to make the API request.
 * @param string $name The name of the event to filter by.
 *
 * @return array An array of events that match the provided name and are active. If no events match, an empty array is returned.
 *
 * @throws moodle_exception If the cURL client is null.
 */
function list_events($client, $name) {
    //debugging("listing events", DEBUG_DEVELOPER);
    if ($client == null) {
        throw new moodle_exception('error with userauth');
        return;
    }

    // Web request
    //echo $name . "<br>";
    //$url = get_config('topomojo', 'topomojoapiurl') . "/gamespaces?WantsAll=false&Term=" . rawurlencode("Wireless") . "&Filter=all";
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespaces?WantsAll=false&Term=" . rawurlencode($name) . "&WantsActive=true";
    //echo "GET $url<br>";

    $response = $client->get($url);

    if ($client->info['http_code'] !== 200) {
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
    $matches = [];
    foreach ($r as $event) {
        // Filter by name
        $name = preg_replace('/ - \d+$/', '', $name); ///CHECK THIS - 0 IS BEING ADDED TO THE NAME DON'T KNOW WHY
        if (($event['name'] === $name) && ($event['isActive'])) {
            array_push($matches, $event);
        }
    }
    debugging("list_events found " . count($matches) . " active events", DEBUG_DEVELOPER);

    usort($matches, 'whencreated');
    return $matches;
}

/**
 * Filters events to include only those managed by a specific user.
 *
 * This function processes a list of events and filters them based on the manager's name.
 * Only events managed by "Adam Welle" are included in the resulting array.
 *
 * @param array $events An array of events to filter. Each event should be an associative array with a 'managerName' key.
 *
 * @return array An array of events where the 'managerName' is "Adam Welle".
 * If no events match or if the input is not an array, an empty array is returned.
 */
function moodle_events($events) {
    $eventsmoodle = [];
    if (!is_array($events)) {
        debugging("no events to parse in eventsmoodle", DEBUG_DEVELOPER);
        return;
    }
    foreach ($events as $event) {
        $managername = get_config('topomojo', 'managername');
        if ($event['managerName'] == $managername) {
            array_push($eventsmoodle, $event);
        }
    }
    //debugging("found " . count($eventsmoodle) . " events started by moodle", DEBUG_DEVELOPER);
    return $eventsmoodle;
}

/**
 * Filters events to include only those associated with the current user.
 *
 * This function iterates over a list of events and retrieves additional information
 * about each event from the TopoMojo API. It then checks if the current user is
 * listed as a player in each event and includes only those events where the user is found.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param array $events An array of events, where each event is expected to have an 'id' key.
 *
 * @return array An array of events where the current user is listed as a player.
 * If no events match or if there is an error, an empty array or error will be returned.
 *
 * @throws moodle_exception If there is an error with user authentication, an error communicating with TopoMojo,
 * or an issue decoding the JSON response.
 */
function user_events($client, $events) {
    global $USER;
    //debugging("filtering events for user", DEBUG_DEVELOPER);
    if ($client == null) {
        throw new moodle_exception('error with userauth');
        return;
    }
    $userevents = [];

    if (!is_array($events)) {
        debugging("cannot parse for userevents if events is not an array", DEBUG_DEVELOPER);
        return;
    }

    foreach ($events as $event) {
        // Web request
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
            throw new moodle_exception("Error communicating with TopoMojo after $count attempts: " . $response);
            return;
        }

        $r = json_decode($response, true);

        if (!$r) {
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            throw new moodle_exception("Error communicating with TopoMojo after $count attempts: " . $response);
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
                array_push($userevents, $r);
            }
        }
    }
    //debugging("found " . count($userevents) . " events for this user", DEBUG_DEVELOPER);
    return $userevents;
}

/**
 * Retrieves workspace information from the TopoMojo API.
 *
 * This function sends a request to the TopoMojo API to get details about a specific workspace
 * based on the provided workspace ID. It handles HTTP response codes and errors during the
 * request. The function assumes that the API returns a JSON response containing workspace details.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param string $id The ID of the workspace to retrieve.
 *
 * @return mixed The workspace details decoded from the JSON response. Returns null if the workspace
 *                could not be found or if there was an error processing the response.
 *
 * @throws moodle_exception If the HTTP request fails or if the response code is not 200.
 */
function get_workspace($client, $id) {
    global $USER;
    if ($client == null) {
        throw new moodle_exception('could not setup session');
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/workspace/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);

    // TODO handle network error

    if ($client->info['http_code'] !== 200) {
        //debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        //print_r($client->response);
        // TODO we dont have an httpp_code if the connection failed
        throw new moodle_exception($client->info['http_code'] . " for $url");
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

/**
 * Retrieves a list of workspaces from the TopoMojo API.
 *
 * This function sends a request to the TopoMojo API to get details about all workspaces. It handles
 * HTTP response codes and errors during the request. The function assumes that the API returns a
 * JSON response containing a list of workspaces.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 *
 * @return mixed The list of workspaces decoded from the JSON response. Returns null if there
 *                was an error retrieving or processing the response.
 *
 * @throws moodle_exception If the HTTP request fails or if the response code is not 200.
 */
function get_workspaces($client) {

    if ($client == null) {
        debugging('error with client in get_workspaces', DEBUG_DEVELOPER);
        return;
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/workspaces";
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_workspaces for $url", DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    if ($client->info['http_code'] !== 200) {
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

/**
 * Retrieves the challenge associated with a specific gamespace from the TopoMojo API.
 *
 * This function sends a request to the TopoMojo API to get the challenge details for a gamespace
 * identified by its ID. It handles HTTP response codes and potential errors during the request.
 * The function assumes that the API returns a JSON response containing the challenge details.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param string $id The ID of the gamespace for which the challenge details are to be retrieved.
 *
 * @return mixed The challenge details decoded from the JSON response. Returns null if there
 *                was an error retrieving or processing the response.
 *
 * @throws moodle_exception If the HTTP request fails or if the response code is not 200.
 */
function get_gamespace_challenge($client, $id) {
    global $USER;
    if ($client == null) {
        throw new moodle_exception('could not setup session');
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $id . "/challenge";
    //echo "GET $url<br>";

    $response = $client->get($url);

    // TODO handle network error

    if ($client->info['http_code'] !== 200) {
        //debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        //print_r($client->response);
        // TODO we dont have an httpp_code if the connection failed
        throw new moodle_exception($client->info['http_code'] . " for $url");
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

/**
 * Retrieves a document in Markdown format from the TopoMojo API.
 *
 * This function sends a request to the TopoMojo API to get the content of a document identified
 * by its ID. It handles HTTP response codes and potential errors during the request. The function
 * assumes that the API returns the document content directly as a response.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param string $id The ID of the document to be retrieved.
 *
 * @return string The document content in Markdown format. Returns null if there was an error
 *                retrieving or processing the response.
 *
 * @throws moodle_exception If the HTTP request fails or if the response code is not 200.
 */
function get_markdown($client, $id) {
    global $USER;
    if ($client == null) {
        throw new moodle_exception('could not setup session');
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/document/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);

    // TODO handle network error

    if ($client->info['http_code'] !== 200) {
        //debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        //print_r($client->response);
        // TODO we dont have an httpp_code if the connection failed
        throw new moodle_exception($client->info['http_code'] . " for $url");
    }

    if (!$response) {
        debugging('no response received by get_document', DEBUG_DEVELOPER);
    }

    return $response;
}

/**
 * Starts a new gamespace from a specified workspace.
 *
 * This function sends a request to the TopoMojo API to initiate a new gamespace using the provided
 * workspace ID and TopoMojo settings. It configures various parameters such as maximum attempts,
 * duration, points, and player information before making the request. The function returns the
 * response from the API if successful, or handles errors if the request fails.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param string $id The ID of the workspace from which to start the gamespace.
 * @param object $topomojo An object containing configuration settings for the gamespace, including
 *                         duration, grade, and variant.
 *
 * @return object|null The response from the API if the request is successful, or null if the request
 *                     fails or if the response cannot be decoded.
 *
 * @throws moodle_exception If there is an issue with the HTTP request or if the response code is not 200.
 */
function start_event($client, $id, $topomojo) {
    global $USER;
    debugging("starting gamespace from workspace $id", DEBUG_DEVELOPER);

    if ($client == null) {
        debugging('error with client in start_event', DEBUG_DEVELOPER);;
        return;
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace";
    //echo "POST $url<br>";

    // Generate post data
    $payload = new stdClass();
    $payload->resourceId = $id;
    $payload->startGamespace = true;
    $payload->allowPreview = false;
    $payload->allowReset = false;
    $payload->maxAttempts = $topomojo->submissions;
    var_dump($topomojo);
    $payload->maxMinutes = $topomojo->duration / 60;
    $payload->points = $topomojo->grade;
    $payload->variant = $topomojo->variant;
    $payload->players = [];
    $payload->players[0] = new stdClass();
    $payload->players[0]->subjectId = explode( "@", $USER->email )[0];
    $payload->players[0]->subjectName = $USER->username;
    $json = json_encode($payload);
    //print_r($json);

    $client->setopt(['CURLOPT_POSTFIELDS' => $json]);

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

    // Success
    if ($client->info['http_code'] === 200) {
        return $r;
    }

    //echo "response code ". $client->info['http_code'] . "<br>";
    debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    //print_r($r);

    return;
}

/**
 * Stops and completes a gamespace identified by the given ID.
 *
 * This function sends a POST request to the TopoMojo API to mark a gamespace as completed. It handles
 * the request using the provided HTTP client and processes the response. If the request fails or returns
 * an error code, the function logs debugging information. If no response is received, an exception is thrown.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param string $id The ID of the gamespace to be marked as completed.
 *
 * @return void
 *
 * @throws \Exception If no response is received from the API.
 */
function stop_event($client, $id) {

    if ($client == null) {
        debugging('error with client in stop_event', DEBUG_DEVELOPER);;
        return;
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $id . "/complete";
    //echo "POST $url<br>";

    $response = $client->post($url);

    if ($client->info['http_code'] !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
    }

    if (!$response) {
        throw new \Exception($response);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    return;
}

/**
 * Retrieves a ticket from the TopoMojo API for the current user.
 *
 * This function sends a GET request to the TopoMojo API to obtain a ticket associated with the current user.
 * It handles the request using the provided HTTP client and processes the response. If the response code is
 * 200, it returns the ticket. If the response code is 500, it logs debugging information. If the response
 * cannot be decoded or if there is an error, the function returns `null`.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 *
 * @return string|null The ticket if the request is successful and the response code is 200, or `null` if
 *                     the request fails or the response cannot be decoded.
 */
function get_ticket($client) {

    if ($client == null) {
        debugging('error with client in get_ticket', DEBUG_DEVELOPER);;
        return;
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/user/ticket";
    //echo "POST $url<br>";

    $response = $client->get($url);

    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //echo "could not decode json<br>";
        return;
    }

    // Success
    if ($client->info['http_code'] === 200) {
        return $r->ticket;
    }
    if ($client->info['http_code'] === 500) {
        //echo "response code ". $client->info['http_code'] . "<br>";
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

/**
 * Retrieves an invitation for a specific gamespace from the TopoMojo API.
 *
 * This function sends a POST request to the TopoMojo API to obtain an invitation for a gamespace identified
 * by the provided ID. It handles the request using the provided HTTP client and processes the response. If
 * the response code is 200, it returns the invitation details. If the response code is 500, it logs debugging
 * information. If the response cannot be decoded or if there is an error, the function returns `null`.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param string $id The ID of the gamespace for which to retrieve the invitation.
 *
 * @return mixed The invitation details if the request is successful and the response code is 200, or `null`
 *               if the request fails or the response cannot be decoded.
 */
function get_invite($client, $id) {

    if ($client == null) {
        debugging('error with client in get_invite', DEBUG_DEVELOPER);;
        return;
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace/" . $id . "/invite";
    //echo "POST $url<br>";

    $response = $client->post($url);

    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //echo "could not decode json<br>";
        return;
    }

    // Success
    if ($client->info['http_code'] === 200) {
        return $r;
    }
    if ($client->info['http_code'] === 500) {
        //echo "response code ". $client->info['http_code'] . "<br>";
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

/**
 * Extends or updates a gamespace with the provided data using the TopoMojo API.
 *
 * This function sends a PUT request to the TopoMojo API to update a gamespace with the data provided. It sets
 * the appropriate header for JSON Patch format and processes the response. If the response code is 200, it
 * indicates a successful update and the function returns `true`. If the response code is not 200, it logs the
 * response code and returns `false`.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param array $data The data to be sent in the request body to update the gamespace.
 *
 * @return bool Returns `true` if the request is successful and the response code is 200, or `false` otherwise.
 */
function extend_event($client, $data) {

    if ($client == null) {
        return;
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/gamespace";
    $client->setHeader('Content-Type: application/json-patch+json');

    $response = $client->put($url, json_encode($data));

    if ($client->info['http_code'] !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return false;
    }

    return true;
}

/**
 * Retrieves a gamespace event from the TopoMojo API.
 *
 * This function sends a GET request to the TopoMojo API to retrieve details of a gamespace event identified
 * by the provided ID. It processes the response and returns the decoded event data if the request is successful.
 * If the request fails or the response cannot be decoded, it logs debugging information and throws an exception.
 *
 * @param curl $client An instance of the curl class used for making HTTP requests.
 * @param string $id The ID of the gamespace event to retrieve.
 *
 * @return object|null Returns the decoded event data as an object if the request is successful and the response
 *                     code is 200. Returns `null` if the response is not successful or if an error occurs.
 *
 * @throws moodle_exception Throws an exception if the response cannot be decoded or if the response code is not 200.
 */
function get_event($client, $id) {

    if ($client == null) {
        debugging('error with client in get_event', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_event', DEBUG_DEVELOPER);;
        return;
    }

    // Web request
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
        throw new moodle_exception($response);
        return;
    }

    if ($client->info['http_code'] === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        throw new moodle_exception($r->detail);
    }
    return;
}

/**
 * Compares two associative arrays based on their 'whenCreated' timestamps.
 *
 * This function compares the 'whenCreated' values of two associative arrays using a natural order comparison.
 * It returns an integer less than, equal to, or greater than zero if the first 'whenCreated' is considered
 * to be less than, equal to, or greater than the second 'whenCreated' respectively.
 *
 * @param array $a The first associative array containing a 'whenCreated' key.
 * @param array $b The second associative array containing a 'whenCreated' key.
 *
 * @return int Returns an integer less than, equal to, or greater than zero depending on whether the 'whenCreated'
 *             value of $a is less than, equal to, or greater than the 'whenCreated' value of $b.
 */
function whencreated($a, $b) {
    return strnatcmp($a['whencreated'], $b['whencreated']);
}

/**
 * Retrieves the first active event from a history array.
 *
 * This function iterates through an array of event history and returns the first event marked as active.
 * If no active event is found, it returns null. If the input history is null, it also returns null.
 *
 * @param array|null $history An array of events, where each event is an associative array containing at least an 'isActive' key.
 *                            If null, the function will return null immediately.
 *
 * @return object|null Returns the first active event as an object, or null if no active event is found or if the input is null.
 */
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
 * Retrieves the grading options for calculating the TopoMojo grade.
 *
 * This function returns an associative array where the keys are grading option constants
 * and the values are corresponding language strings
 * that describe each grading option. The grading options determine how the TopoMojo grade
 * is calculated from individual attempt grades.
 *
 * @return array An associative array where the keys are grading option constants and the values are language strings.
 */
function topomojo_get_grading_options() {
    return [
        TOPOMOJO_GRADEHIGHEST => get_string('gradehighest', 'topomojo'),
        TOPOMOJO_GRADEAVERAGE => get_string('gradeaverage', 'topomojo'),
        TOPOMOJO_ATTEMPTFIRST => get_string('attemptfirst', 'topomojo'),
        TOPOMOJO_ATTEMPTLAST  => get_string('attemptlast', 'topomojo'),
    ];
}

/**
 * Retrieves the language string for a given grading option.
 *
 * This function returns the language string corresponding to the specified grading option constant.
 *
 * @param int $option One of the grading option constants (e.g., TOPOMOJO_GRADEHIGHEST, TOPOMOJO_GRADEAVERAGE,
 *                    TOPOMOJO_ATTEMPTFIRST, TOPOMOJO_ATTEMPTLAST).
 * @return string The language string for the specified grading option.
 */
function topomojo_get_grading_option_name($option) {
    $strings = topomojo_get_grading_options();
    return $strings[$option];
}

/**
 * Creates and triggers an event when a TopoMojo attempt ends.
 *
 * This function logs the end of a TopoMojo attempt by creating and triggering a Moodle event.
 *
 * @param stdClass $cm The course module object containing the module ID.
 * @param context $context The context in which the event is triggered.
 * @param stdClass $topomojo The TopoMojo instance object.
 * @return void
 */
function topomojo_end($cm, $context, $topomojo) {
    global $USER;
    $params = [
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id,
    ];
    $event = \mod_topomojo\event\attempt_ended::create($params);
    $event->add_record_snapshot('topomojo', $topomojo);
    $event->trigger();
}

/**
 * Creates and triggers an event when a TopoMojo attempt starts.
 *
 * This function logs the start of a TopoMojo attempt by creating and triggering a Moodle event.
 *
 * @param stdClass $cm The course module object containing the module ID.
 * @param context $context The context in which the event is triggered.
 * @param stdClass $topomojo The TopoMojo instance object.
 * @return void
 */
function topomojo_start($cm, $context, $topomojo) {
    global $USER;
    $params = [
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id,
    ];
    $event = \mod_topomojo\event\attempt_started::create($params);
    $event->add_record_snapshot('topomojo', $topomojo);
    $event->trigger();
}

/**
 * Retrieves a challenge from the TopoMojo API based on the provided ID.
 *
 * This function sends a request to the TopoMojo API to fetch details of a specific challenge by its ID.
 * It handles the response and errors, and returns the challenge data as a decoded JSON object.
 *
 * @param object $client The HTTP client used to make the API request.
 * @param int $id The ID of the challenge to retrieve.
 * @return object|null The decoded challenge data object if successful, or null if an error occurs or the data cannot be decoded.
 * @throws moodle_exception If there is an issue with the client or the response code is not 200.
 */
function get_challenge($client, $id) {
    global $USER;
    if ($client == null) {
        throw new moodle_exception('could not setup session');
    }

    // Web request
    $url = get_config('topomojo', 'topomojoapiurl') . "/challenge/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);

    // TODO handle network error

    if ($client->info['http_code'] !== 200) {
        //debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        //print_r($client->response);
        // TODO we dont have an httpp_code if the connection failed
        throw new moodle_exception($client->info['http_code'] . " for $url");
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

/**
 * Retrieves all attempts for a given course from the TopoMojo module.
 *
 * This function queries the database to fetch all attempts related to the specified course.
 * It then creates an array of `topomojo_attempt` objects from the retrieved records.
 *
 * @param int $course The ID of the course for which attempts are to be retrieved.
 * @return array An array of `\mod_topomojo\topomojo_attempt` objects representing the attempts for the specified course.
 */
function getall_course_attempts($course) {
    global $DB, $USER;

    $sqlparams = [];
    $where = [];

    $where[] = '{topomojo}.course= ?';
    $sqlparams[] = $course;

    $wherestring = implode(' AND ', $where);

    $sql = "SELECT {topomojo_attempts}.* FROM {topomojo_attempts} JOIN {topomojo}
           ON {topomojo_attempts}.topomojoid = {topomojo}.id WHERE $wherestring";
    $dbattempts = $DB->get_records_sql($sql, $sqlparams);

    $attempts = [];
    // Create array of class attempts from the db entry
    foreach ($dbattempts as $dbattempt) {
        $attempts[] = new \mod_topomojo\topomojo_attempt($dbattempt);
    }

    return $attempts;

}

/**
 * Retrieves all TopoMojo attempts from the database.
 *
 * This function queries the database to fetch all records from the `topomojo_attempts` table
 * and then creates an array of `topomojo_attempt` objects from the retrieved records.
 *
 * @return array An array of `\mod_topomojo\topomojo_attempt` objects representing all TopoMojo attempts.
 */
function getall_topomojo_attempts($course) {
    global $DB, $USER;

    $sql = "SELECT * FROM {topomojo_attempts}";
    $dbattempts = $DB->get_records_sql($sql);

    $attempts = [];
    // Create array of class attempts from the db entry
    foreach ($dbattempts as $dbattempt) {
        $attempts[] = new \mod_topomojo\topomojo_attempt($dbattempt);
    }

    return $attempts;

}

/**
 * Generates a string representation of a question with optional details.
 *
 * This function creates an HTML string representing a question object, including
 * its name, ID number, tags, and text. The output can be customized based on the
 * flags provided.
 *
 * @param object $question The question object containing details such as name, idnumber, and text.
 * @param bool $showicon Whether to display an icon for the question. Default is false.
 * @param bool $showquestiontext Whether to display the question text. Default is true.
 * @param bool $showidnumber Whether to display the question ID number. Default is false.
 * @param mixed $showtags If true, retrieves and displays tags associated with the question.
 *                         If an array, uses it directly. Default is false.
 *
 * @return string An HTML string representing the formatted question details.
 */
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
                $question->questiontextformat, ['noclean' => true, 'para' => false]);
        $questiontext = shorten_text($questiontext, 50);
        if ($questiontext) {
            $result .= ' ' . html_writer::span(s($questiontext), 'questiontext');
        }
    }

    return $result;

}

/**
 * Generates a URL to preview a specific question from a topomojo quiz.
 *
 * @param object $topomojo The topomojo settings containing quiz configurations.
 * @param object $question The question object to be previewed.
 * @param int|null $variant (optional) The specific question variant to preview. Defaults to null.
 * @return moodle_url The URL to preview the question with the given options.
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
 * Checks if the specified topomojo has any attempts recorded.
 *
 * @param int $topomojoid The ID of the topomojo to check.
 * @return bool True if there are attempts, false otherwise.
 */
function topomojo_has_attempts($topomojoid) {
    global $DB;
    return $DB->record_exists('topomojo_attempts', ['topomojoid' => $topomojoid]);

}

/**
 * An extension of question_display_options that includes the extra options used
 * by the quiz.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_topomojo_display_options extends question_display_options {
    /**
     * Apply display options during the quiz attempt.
     *
     * This flag indicates that display options should be shown while the quiz is in progress.
     *
     * @var integer
     */
    const DURING = 0x10000;

    /**
     * Apply display options immediately after the quiz attempt.
     *
     * This flag indicates that display options should be shown as soon as the quiz attempt is completed.
     *
     * @var integer
     */
    const IMMEDIATELY_AFTER = 0x01000;

    /**
     * Apply display options later while the quiz attempt is still open.
     *
     * This flag indicates that display options should be shown at a later time while the quiz attempt is still active.
     *
     * @var integer
     */
    const LATER_WHILE_OPEN = 0x00100;

    /**
     * Apply display options after the quiz attempt has closed.
     *
     * This flag indicates that display options should be shown only after the quiz attempt is closed.
     *
     * @var integer
     */
    const AFTER_CLOSE = 0x00010;

    /**
     * @var bool if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var bool if this is false, then the student is not allowed to review
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
    public static function make_from_topomojo($topomojo, $when) {
        $options = new self();
        // TODO remove if not used
        $options->attempt = self::extract($topomojo->reviewattempt, $when, true, false);
        $options->correctness = self::extract($topomojo->reviewcorrectness, $when);
        $options->marks = self::extract($topomojo->reviewmarks, $when,
                self::MARK_AND_MAX, self::MAX_ONLY);
        $options->feedback = self::extract($topomojo->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($topomojo->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($topomojo->reviewrightanswer, $when);
        // TODO remove if not used$options->overallfeedback = self::extract($topomojo->reviewoverallfeedback, $when);
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

    /**
     * Extracts a visibility setting based on a bitmask and a specific bit.
     *
     * This method checks if a specific bit is set in a bitmask and returns the corresponding
     * visibility setting. If the bit is set, the function returns the value of `$whenset`; otherwise,
     * it returns the value of `$whennotset`.
     *
     * @param int $bitmask The bitmask value representing various visibility settings.
     * @param int $bit The specific bit to check in the bitmask.
     * @param int $whenset The value to return if the specific bit is set in the bitmask (default is `self::VISIBLE`).
     * @param int $whennotset The value to return if the specific bit is not set in the bitmask (default is `self::HIDDEN`).
     *
     * @return int The visibility setting based on whether the bit is set or not in the bitmask.
     */
    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}
