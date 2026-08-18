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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Returns the current TopoMojo gamespace readiness through Moodle.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$gamespaceid = required_param('gamespaceid', PARAM_ALPHANUMEXT);
require_sesskey();

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'topomojo');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/topomojo:view', $context);

$PAGE->set_url(new moodle_url('/mod/topomojo/gamespace_status.php', ['cmid' => $cmid]));
$PAGE->set_context($context);

$params = [
    'topomojoid' => $cm->instance,
    'userid' => $USER->id,
    'gamespaceid' => $gamespaceid,
];
$sql = "SELECT ta.id
          FROM {topomojo_attempts} ta
         WHERE ta.topomojoid = :topomojoid
           AND ta.userid = :userid
           AND " . $DB->sql_compare_text('ta.eventid') . " = " . $DB->sql_compare_text(':gamespaceid');

if (!$DB->record_exists_sql($sql, $params)) {
    require_capability('mod/topomojo:manage', $context);
}

// Do not hold the Moodle session lock while waiting for TopoMojo.
\core\session\manager::write_close();

try {
    $client = setup();
    if (!$client) {
        throw new RuntimeException('Unable to authenticate with TopoMojo');
    }
    $client->setopt(['CURLOPT_TIMEOUT' => 10]);

    $gamespace = get_event($client, $gamespaceid);
    if (!$gamespace) {
        throw new RuntimeException('Unable to retrieve TopoMojo gamespace');
    }

    $hasvms = isset($gamespace->vms) && is_array($gamespace->vms) && !empty($gamespace->vms);
    $response = [
        'active' => !empty($gamespace->isActive),
        'ready' => $hasvms,
    ];

    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($response);
} catch (\Throwable $e) {
    debugging('Could not retrieve TopoMojo gamespace status: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(502);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'Unable to retrieve TopoMojo gamespace status']);
}
