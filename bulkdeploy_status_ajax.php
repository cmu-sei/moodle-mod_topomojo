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
 * Returns the per-user status rows of a bulk deployment job as JSON.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

use mod_topomojo\local\bulkdeploy\job_repository;

$jobid = required_param('jobid', PARAM_INT);
require_sesskey();

$repo = new job_repository();
$job = $repo->get_job($jobid);
if (!$job) {
    throw new moodle_exception('invaliddata');
}

$cm = get_coursemodule_from_instance('topomojo', $job->topomojoid, 0, false, MUST_EXIST);
[, $cm] = get_course_and_cm_from_cmid($cm->id, 'topomojo');
$context = context_module::instance($cm->id);
require_capability('mod/topomojo:bulkdeploy', $context);

$rows = $repo->get_user_rows($jobid);
$rowsout = [];
foreach ($rows as $r) {
    $rowsout[] = [
        'rowid'        => (int) $r->id,
        'status'       => $r->status,
        'gamespaceid'  => (string) ($r->gamespaceid ?? ''),
        'errormessage' => (string) ($r->errormessage ?? ''),
    ];
}

echo json_encode([
    'status'      => $job->status,
    'counts'      => $repo->count_user_rows_by_status($jobid),
    'totalusers'  => (int) $job->totalusers,
    'rows'        => $rowsout,
]);
