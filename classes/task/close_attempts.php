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
Topomojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

namespace mod_topomojo\task;

defined('MOODLE_INTERNAL') || die();

/**
 * topomojo module main user interface
 *
 * @package    mod_topomojo
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_attempts extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcloseattempt', 'mod_topomojo');
    }

    /**
     * Executes the task of closing all expired attempts.
     *
     * This method retrieves all attempts with an 'open' status that have expired
     * and closes them. It outputs a message for each attempt being closed and
     * logs debug information about the operation.
     *
     * @return void
     */
    public function execute() {
        $attempts = $this->getall_expired_attempts('open');

        foreach ($attempts as $attempt) {
            echo "closing attempt $attempt->id<br>";
            debugging("scheduled task is closing attempt $attempt->id", DEBUG_DEVELOPER);
            $attempt->close_attempt();
        }

    }

    /**
     * Retrieves all expired attempts based on their state.
     *
     * This method fetches attempts from the database that have expired, based on their
     * state (`open` or `closed`). It returns a list of attempts that match the specified
     * state and have an end time earlier than the current time.
     *
     * @param string $state The state of the attempts to retrieve. Possible values are:
     *                      'open' to get open (in-progress) attempts,
     *                      'closed' to get closed (finished) attempts.
     *                      Defaults to 'open'.
     *
     * @return \mod_topomojo\topomojo_attempt[] An array of \mod_topomojo\topomojo_attempt
     *                                         objects representing the expired attempts.
     */
    public function getall_expired_attempts($state = 'open') {
        global $DB;

        $sqlparams = [];
        $where = [];

        switch ($state) {
            case 'open':
                $where[] = 'state = ?';
                $sqlparams[] = \mod_topomojo\topomojo_attempt::INPROGRESS;
                break;
            case 'closed':
                $where[] = 'state = ?';
                $sqlparams[] = \mod_topomojo\topomojo_attempt::FINISHED;
                break;
            default:
                // Add no condition for state when 'all' or something other than open/closed
        }

        $where[] = 'endtime < ?';
        $sqlparams[] = time();

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT * FROM {topomojo_attempts} WHERE $wherestring";
        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = [];

        // Create array of class attempts from the db entry
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new \mod_topomojo\topomojo_attempt($dbattempt);
        }
        return $attempts;

    }

}
