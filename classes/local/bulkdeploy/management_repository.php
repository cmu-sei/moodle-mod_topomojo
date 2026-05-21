<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Data access for manage deployments page.
 */
class management_repository {

    /**
     * Get all enrolled users with their current deployment/attempt state.
     *
     * @param int $topomojoid TopoMojo activity ID
     * @param int $courseid Course ID
     * @param array $rolefilter Array of role IDs (empty = all roles)
     * @return array Array of user objects with deployment/attempt info
     */
    public function get_enrolled_users_with_state(
        int $topomojoid,
        int $courseid,
        array $rolefilter = []
    ): array {
        global $DB;

        $coursecontext = \context_course::instance($courseid);
        $enrolled = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);

        if ($rolefilter && !in_array(0, $rolefilter)) {
            // Filter by role assignments (skip if "All roles" is selected)
            $enrolled = array_filter($enrolled, function($u) use ($rolefilter, $coursecontext) {
                $userroles = get_user_roles($coursecontext, $u->id);
                foreach ($userroles as $role) {
                    if (in_array($role->roleid, $rolefilter)) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Add users with attempts who aren't enrolled (e.g., instructor previews)
        $attempts = $DB->get_records('topomojo_attempts', ['topomojoid' => $topomojoid], 'id DESC', 'id, userid, state, eventid');
        foreach ($attempts as $att) {
            if (!isset($enrolled[$att->userid])) {
                $user = $DB->get_record('user', ['id' => $att->userid]);
                if ($user) {
                    $enrolled[$att->userid] = $user;
                }
            }
        }

        if (empty($enrolled)) {
            return [];
        }

        $userids = array_keys($enrolled);
        [$insql1, $params1] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid1');
        [$insql2, $params2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid2');
        [$insql3, $params3] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid3');

        $sql = "
            SELECT u.id AS userid,
                   u.firstname,
                   u.lastname,
                   u.email,
                   att.id AS attemptid,
                   att.state AS attemptstate,
                   att.score AS attemptscore,
                   att.eventid AS attemptgamespaceid,
                   att.timestart AS attempttimestart,
                   att.endtime AS attemptendtime,
                   bd.id AS deployrowid,
                   bd.status AS deploystatus,
                   bd.gamespaceid AS deploygamespaceid,
                   bd.errormessage AS deployerror,
                   bdj.scheduledfor AS scheduledfor
              FROM {user} u
         LEFT JOIN {topomojo_attempts} att ON att.userid = u.id
                   AND att.topomojoid = :topomojoid1
                   AND att.id = (
                       SELECT MAX(id)
                       FROM {topomojo_attempts}
                       WHERE topomojoid = :topomojoid2
                       AND userid = u.id
                   )
         LEFT JOIN {topomojo_bulkdeploy_user} bd ON bd.userid = u.id
                   AND bd.id = (
                       SELECT MAX(bdu.id)
                       FROM {topomojo_bulkdeploy_user} bdu
                       INNER JOIN {topomojo_bulkdeploy_job} bdj ON bdj.id = bdu.jobid
                       WHERE bdj.topomojoid = :topomojoid3
                       AND bdu.userid = u.id
                   )
         LEFT JOIN {topomojo_bulkdeploy_job} bdj ON bdj.id = bd.jobid
             WHERE u.id $insql1
        ";

        $params = $params1;
        $params['topomojoid1'] = $topomojoid;
        $params['topomojoid2'] = $topomojoid;
        $params['topomojoid3'] = $topomojoid;

        $rows = $DB->get_records_sql($sql, $params);

        $results = [];
        foreach ($enrolled as $user) {
            $row = $rows[$user->id] ?? (object) [
                'userid' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
            ];
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Get active jobs for an activity (QUEUED, RUNNING, CANCELLING).
     *
     * @param int $topomojoid TopoMojo activity ID
     * @return array Array of job records
     */
    public function get_active_jobs(int $topomojoid): array {
        global $DB;

        $active = [job_status::QUEUED, job_status::RUNNING, job_status::CANCELLING];
        [$insql, $params] = $DB->get_in_or_equal($active, SQL_PARAMS_NAMED);
        $params['topomojoid'] = $topomojoid;

        return $DB->get_records_select(
            'topomojo_bulkdeploy_job',
            "topomojoid = :topomojoid AND status $insql",
            $params,
            'id DESC'
        );
    }

    /**
     * Get scheduled jobs (QUEUED with scheduledfor > now).
     *
     * @param int $topomojoid TopoMojo activity ID
     * @return array Array of scheduled job records
     */
    public function get_scheduled_jobs(int $topomojoid): array {
        global $DB;

        $now = time();
        return $DB->get_records_select(
            'topomojo_bulkdeploy_job',
            'topomojoid = :topomojoid AND status = :status AND scheduledfor > :now',
            [
                'topomojoid' => $topomojoid,
                'status' => job_status::QUEUED,
                'now' => $now,
            ],
            'scheduledfor ASC'
        );
    }

    /**
     * Get job progress counts.
     *
     * @param int $jobid Job ID
     * @return \stdClass Object with ready, failed, pending, etc. counts
     */
    public function get_job_progress(int $jobid): \stdClass {
        $repo = new job_repository();
        $counts = $repo->count_user_rows_by_status($jobid);

        return (object) [
            'ready' => $counts[user_status::READY] ?? 0,
            'failed' => $counts[user_status::FAILED] ?? 0,
            'pending' => $counts[user_status::PENDING] ?? 0,
            'launched' => $counts[user_status::LAUNCHED] ?? 0,
            'skipped' => $counts[user_status::SKIPPED] ?? 0,
            'cancelled' => $counts[user_status::CANCELLED] ?? 0,
        ];
    }

    /**
     * Format a single enrolled-user row for the manage page.
     *
     * Returns an associative array with:
     *  - status_label   (string): final user-facing status string
     *  - status_class   (string): lowercase status used as the row's data-status attr
     *  - gamespace_text (string): gamespace id or "─"
     *  - scheduled_text (string): formatted userdate or "─"
     *  - tooltip_html   (string|null): pre-rendered <span title="...">Label ⓘ</span> markup, or null
     *  - action_html    (string): pre-rendered <a ...>...</a> markup, or "─"
     *
     * Both manage.php (initial render) and manage_status_ajax.php (polling) call this
     * helper so the rendered cells never disagree.
     *
     * @param \stdClass $row           Row produced by get_enrolled_users_with_state().
     * @param bool      $hasquestions  Whether the row's attempt has a non-empty questionusageid.
     * @return array
     */
    public function format_user_state(\stdClass $row, bool $hasquestions = false): array {
        $now = time();
        $statuslabel = 'None';
        $scheduledtext = '─';
        $tooltiphtml = null;

        $deploystatus = $row->deploystatus ?? null;
        $scheduledfor = $row->scheduledfor ?? null;
        $attemptid = $row->attemptid ?? null;
        $attemptstate = $row->attemptstate ?? null;

        if (!empty($scheduledfor) && $scheduledfor > $now && $deploystatus === 'pending') {
            $statuslabel = 'Scheduled';
            $scheduledtext = userdate($scheduledfor, get_string('strftimedatetime', 'langconfig'));
        } else if (!empty($deploystatus) && in_array($deploystatus, ['pending', 'launched'], true)) {
            $statuslabel = ucfirst($deploystatus);
        } else if (!empty($attemptid)) {
            $statemap = [
                '0'  => 'Not Started',
                '10' => 'Active',
                '20' => 'Abandoned',
                '30' => 'Finished',
            ];
            $statuslabel = $statemap[(string) $attemptstate] ?? (string) ($attemptstate ?? 'unknown');
        } else if (!empty($deploystatus)) {
            $statuslabel = ucfirst($deploystatus);
        }

        if ($statuslabel === 'Failed' && !empty($row->deployerror)) {
            $tooltiphtml = '<span title="' . s($row->deployerror) . '" class="mod-topomojo-status-tooltip">'
                . s($statuslabel) . ' ⓘ</span>';
        } else if (in_array($statuslabel, ['Active', 'Finished'], true)
            && (!empty($row->attempttimestart) || !empty($row->attemptendtime))) {
            $datefmt = get_string('strftimedatetime', 'langconfig');
            $parts = [];
            if (!empty($row->attempttimestart)) {
                $parts[] = get_string('status_started_at', 'topomojo',
                    userdate($row->attempttimestart, $datefmt));
            }
            if (!empty($row->attemptendtime)) {
                $parts[] = get_string('status_ended_at', 'topomojo',
                    userdate($row->attemptendtime, $datefmt));
            }
            $tooltiphtml = '<span title="' . s(implode("\n", $parts)) . '" class="mod-topomojo-status-tooltip">'
                . s($statuslabel) . ' ⓘ</span>';
        }

        $gamespacetext = '─';
        if (!empty($row->deploygamespaceid)) {
            $gamespacetext = (string) $row->deploygamespaceid;
        } else if (!empty($row->attemptgamespaceid)) {
            $gamespacetext = (string) $row->attemptgamespaceid;
        }

        $actionhtml = '─';
        $attemptstateint = $attemptstate !== null ? (int) $attemptstate : null;
        if (!empty($attemptid) && $hasquestions) {
            if ($attemptstateint === 10) {
                $url = new \moodle_url('/mod/topomojo/challenge.php', ['attemptid' => $attemptid]);
                $actionhtml = \html_writer::link($url, get_string('viewattempt', 'mod_topomojo'),
                    ['class' => 'btn btn-sm btn-outline-primary', 'target' => '_blank']);
            } else if (in_array($attemptstateint, [20, 30], true)) {
                $url = new \moodle_url('/mod/topomojo/viewattempt.php', ['a' => $attemptid, 'action' => 'view']);
                $actionhtml = \html_writer::link($url, get_string('viewattempt', 'mod_topomojo'),
                    ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']);
            }
        }

        return [
            'status_label'   => $statuslabel,
            'status_class'   => strtolower($statuslabel),
            'gamespace_text' => $gamespacetext,
            'scheduled_text' => $scheduledtext,
            'tooltip_html'   => $tooltiphtml,
            'action_html'    => $actionhtml,
        ];
    }
}
