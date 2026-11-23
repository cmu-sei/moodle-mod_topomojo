<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Redirects to the launch URL of one gamespace started by a bulk deployment job.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

use mod_topomojo\local\bulkdeploy\job_repository;

$jobid = required_param('jobid', PARAM_INT);
$rowid = required_param('rowid', PARAM_INT);
$gamespaceid = required_param('gamespaceid', PARAM_TEXT);
require_sesskey();

$repo = new job_repository();
$job = $repo->get_job($jobid);
if (!$job) {
    throw new moodle_exception('invaliddata');
}

$cm = get_coursemodule_from_instance('topomojo', $job->topomojoid, 0, false, MUST_EXIST);
[$course, $cm] = get_course_and_cm_from_cmid($cm->id, 'topomojo');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

// Get the row to verify it belongs to this job.
$row = $DB->get_record('topomojo_bulkdeploy_user', ['id' => $rowid, 'jobid' => $jobid], '*', MUST_EXIST);

// Initialize TopoMojo auth client.
$auth = setup();

if (!$auth) {
    throw new moodle_exception('TopoMojo authentication not configured');
}

// Get the gamespace details to get launch URL.
$url = get_topomojo_api_url() . "/gamespace/" . $gamespaceid;
$auth->get($url);
$response = $auth->getResponse();

if ($auth->getinfo()['http_code'] !== 200) {
    throw new moodle_exception('Failed to get gamespace details');
}

$gamespace = json_decode($response);
if (!$gamespace || empty($gamespace->launchpointUrl)) {
    throw new moodle_exception('Gamespace has no launch URL');
}

// Redirect to the gamespace launch URL.
redirect($gamespace->launchpointUrl);
