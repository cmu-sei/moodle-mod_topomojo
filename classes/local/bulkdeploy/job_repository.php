<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Data access for topomojo_bulkdeploy_job and topomojo_bulkdeploy_user tables.
 */
class job_repository {

    public function create_job(
        int $topomojoid,
        int $courseid,
        int $initiatorid,
        int $batchsize,
        ?string $rolefilter,
        array $userids
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

    public function get_job(int $jobid): ?\stdClass {
        global $DB;
        $row = $DB->get_record('topomojo_bulkdeploy_job', ['id' => $jobid]);
        return $row ?: null;
    }

    public function get_user_rows(int $jobid): array {
        global $DB;
        return $DB->get_records('topomojo_bulkdeploy_user', ['jobid' => $jobid], 'id ASC');
    }

    /**
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

    public function set_job_cancelled_by(int $jobid, int $userid): void {
        global $DB;
        $DB->update_record('topomojo_bulkdeploy_job', (object) [
            'id' => $jobid,
            'cancelledby' => $userid,
            'timecancelled' => time(),
        ]);
    }

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

    private static function truncate255(string $s): string {
        return strlen($s) <= 255 ? $s : substr($s, 0, 252) . '...';
    }
}
