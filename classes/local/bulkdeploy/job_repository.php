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
 * Data access for the bulk deployment job and user tables.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Data access for topomojo_bulkdeploy_job and topomojo_bulkdeploy_user tables.
 * @package mod_topomojo
 */
class job_repository {
    /**
     * Creates a job and a pending row for each user.
     *
     * @param int $topomojoid The activity ID.
     * @param int $courseid The course ID.
     * @param int $initiatorid The ID of the user who started the job.
     * @param int $batchsize How many users to launch at once.
     * @param string|null $rolefilter The role filter the users were selected by, stored with the job, or null.
     * @param int[] $userids The users to deploy for.
     * @param int|null $scheduledfor Unix time to start at, or null to start now.
     * @return int The job ID.
     */
    public function create_job(
        int $topomojoid,
        int $courseid,
        int $initiatorid,
        int $batchsize,
        ?string $rolefilter,
        array $userids,
        ?int $scheduledfor = null
    ): int {
        global $DB;
        $now = time();
        $job = (object) [
            'topomojoid'    => $topomojoid,
            'courseid'      => $courseid,
            'initiatorid'   => $initiatorid,
            'batchsize'     => $batchsize,
            'rolefilter'    => $rolefilter,
            'totalusers'    => count($userids),
            'status'        => job_status::QUEUED,
            'timecreated'   => $now,
            'scheduledfor'  => $scheduledfor,
        ];
        $jobid = $DB->insert_record('topomojo_bulkdeploy_job', $job);

        $rows = [];
        foreach ($userids as $userid) {
            $rows[] = (object) [
                'jobid'   => $jobid,
                'userid'  => $userid,
                'status'  => user_status::PENDING,
            ];
        }
        if ($rows) {
            $DB->insert_records('topomojo_bulkdeploy_user', $rows);
        }
        return $jobid;
    }

    /**
     * Gets a job.
     *
     * @param int $jobid The job ID.
     * @return \stdClass|null The job record, or null if it does not exist.
     */
    public function get_job(int $jobid): ?\stdClass {
        global $DB;
        $row = $DB->get_record('topomojo_bulkdeploy_job', ['id' => $jobid]);
        return $row ?: null;
    }

    /**
     * Gets all the user rows of a job.
     *
     * @param int $jobid The job ID.
     * @return \stdClass[] The rows, by ID.
     */
    public function get_user_rows(int $jobid): array {
        global $DB;
        return $DB->get_records('topomojo_bulkdeploy_user', ['jobid' => $jobid], 'id ASC');
    }

    /**
     * Look up the current status of the supplied user-row IDs.
     *
     * @param int[] $rowids
     * @return array<int,string> map of rowid => status; rowids that don't exist are omitted
     */
    public function get_user_statuses(array $rowids): array {
        global $DB;
        if (!$rowids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($rowids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'topomojo_bulkdeploy_user',
            "id $insql",
            $params,
            '',
            'id, status'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = $r->status;
        }
        return $out;
    }

    /**
     * Gets the user rows of a job whose status is not yet terminal.
     *
     * @param int $jobid The job ID.
     * @return \stdClass[] rows whose status is not yet terminal
     */
    public function get_active_user_rows(int $jobid): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(user_status::TERMINAL, SQL_PARAMS_NAMED, 'st', false);
        $params['jobid'] = $jobid;
        return $DB->get_records_select(
            'topomojo_bulkdeploy_user',
            "jobid = :jobid AND status $insql",
            $params,
            'id ASC'
        );
    }

    /**
     * Sets the status of a job, with the start or completion time if the status calls for one.
     *
     * @param int $jobid The job ID.
     * @param string $status A job_status constant.
     * @param string|null $errormessage An error to record, cut to 255 bytes.
     * @return void
     */
    public function set_job_status(int $jobid, string $status, ?string $errormessage = null): void {
        global $DB;
        $now = time();
        $update = (object) ['id' => $jobid, 'status' => $status];
        if ($status === job_status::RUNNING) {
            $existing = $this->get_job($jobid);
            if ($existing && empty($existing->timestarted)) {
                $update->timestarted = $now;
            }
        }
        if (job_status::is_terminal($status)) {
            $update->timecompleted = $now;
        }
        if ($errormessage !== null) {
            $update->errormessage = self::truncate255($errormessage);
        }
        $DB->update_record('topomojo_bulkdeploy_job', $update);
    }

    /**
     * Records who cancelled a job, and when.
     *
     * @param int $jobid The job ID.
     * @param int $userid The ID of the user who cancelled it.
     * @return void
     */
    public function set_job_cancelled_by(int $jobid, int $userid): void {
        global $DB;
        $DB->update_record('topomojo_bulkdeploy_job', (object) [
            'id' => $jobid,
            'cancelledby' => $userid,
            'timecancelled' => time(),
        ]);
    }

    /**
     * Sets the status of a user row, with the start or completion time if the status calls for one.
     *
     * @param int $rowid The row ID.
     * @param string $status A user_status constant.
     * @param string|null $errormessage An error to record, cut to 255 bytes.
     * @param string|null $gamespaceid The gamespace ID to record.
     * @return void
     */
    public function set_user_status(int $rowid, string $status, ?string $errormessage = null, ?string $gamespaceid = null): void {
        global $DB;
        $now = time();
        $update = (object) ['id' => $rowid, 'status' => $status];
        if ($status === user_status::LAUNCHED) {
            $existing = $DB->get_record('topomojo_bulkdeploy_user', ['id' => $rowid], 'timestarted');
            if ($existing && empty($existing->timestarted)) {
                $update->timestarted = $now;
            }
        }
        if (user_status::is_terminal($status)) {
            $update->timecompleted = $now;
        }
        if ($errormessage !== null) {
            $update->errormessage = self::truncate255($errormessage);
        }
        if ($gamespaceid !== null) {
            $update->gamespaceid = $gamespaceid;
        }
        $DB->update_record('topomojo_bulkdeploy_user', $update);
    }

    /**
     * Marks every pending user row of a job as cancelled.
     *
     * @param int $jobid The job ID.
     * @return void
     */
    public function mark_pending_cancelled(int $jobid): void {
        global $DB;
        $DB->execute(
            "UPDATE {topomojo_bulkdeploy_user}
                SET status = :newstatus, timecompleted = :tc
              WHERE jobid = :jobid AND status = :oldstatus",
            [
                'newstatus' => user_status::CANCELLED,
                'tc'        => time(),
                'jobid'     => $jobid,
                'oldstatus' => user_status::PENDING,
            ]
        );
    }

    /**
     * Counts the user rows of a job by status.
     *
     * @param int $jobid The job ID.
     * @return int[] Count by status, for the statuses that occur.
     */
    public function count_user_rows_by_status(int $jobid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT status, COUNT(*) AS n
               FROM {topomojo_bulkdeploy_user}
              WHERE jobid = ?
              GROUP BY status",
            [$jobid]
        );
        $counts = [];
        foreach ($rows as $r) {
            $counts[$r->status] = (int) $r->n;
        }
        return $counts;
    }

    /**
     * Cuts a string to 255 bytes, ending it with an ellipsis if it was longer.
     *
     * @param string $s The string.
     * @return string The string, at most 255 bytes long.
     */
    private static function truncate255(string $s): string {
        return strlen($s) <= 255 ? $s : substr($s, 0, 252) . '...';
    }
}
