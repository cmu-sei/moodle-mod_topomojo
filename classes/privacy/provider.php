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
 * Privacy Subsystem implementation for mod_topomojo.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for mod_topomojo.
 *
 * The activity stores a userid in topomojo_attempts, topomojo_grades and
 * topomojo_bulkdeploy_user, and each attempt owns a question usage, so this is a
 * full provider rather than a null_provider.
 *
 * @copyright  Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('topomojo_attempts', [
            'userid' => 'privacy:metadata:topomojo_attempts:userid',
            'workspaceid' => 'privacy:metadata:topomojo_attempts:workspaceid',
            'eventid' => 'privacy:metadata:topomojo_attempts:eventid',
            'launchpointurl' => 'privacy:metadata:topomojo_attempts:launchpointurl',
            'state' => 'privacy:metadata:topomojo_attempts:state',
            'score' => 'privacy:metadata:topomojo_attempts:score',
            'variant' => 'privacy:metadata:topomojo_attempts:variant',
            'preview' => 'privacy:metadata:topomojo_attempts:preview',
            'timestart' => 'privacy:metadata:topomojo_attempts:timestart',
            'timefinish' => 'privacy:metadata:topomojo_attempts:timefinish',
            'endtime' => 'privacy:metadata:topomojo_attempts:endtime',
            'timemodified' => 'privacy:metadata:topomojo_attempts:timemodified',
        ], 'privacy:metadata:topomojo_attempts');

        $collection->add_database_table('topomojo_grades', [
            'userid' => 'privacy:metadata:topomojo_grades:userid',
            'grade' => 'privacy:metadata:topomojo_grades:grade',
            'timemodified' => 'privacy:metadata:topomojo_grades:timemodified',
        ], 'privacy:metadata:topomojo_grades');

        $collection->add_database_table('topomojo_bulkdeploy_user', [
            'userid' => 'privacy:metadata:topomojo_bulkdeploy_user:userid',
            'gamespaceid' => 'privacy:metadata:topomojo_bulkdeploy_user:gamespaceid',
            'status' => 'privacy:metadata:topomojo_bulkdeploy_user:status',
            'errormessage' => 'privacy:metadata:topomojo_bulkdeploy_user:errormessage',
            'timestarted' => 'privacy:metadata:topomojo_bulkdeploy_user:timestarted',
            'timecompleted' => 'privacy:metadata:topomojo_bulkdeploy_user:timecompleted',
        ], 'privacy:metadata:topomojo_bulkdeploy_user');

        $collection->add_database_table('topomojo_bulkdeploy_job', [
            'initiatorid' => 'privacy:metadata:topomojo_bulkdeploy_job:initiatorid',
            'cancelledby' => 'privacy:metadata:topomojo_bulkdeploy_job:cancelledby',
            'rolefilter' => 'privacy:metadata:topomojo_bulkdeploy_job:rolefilter',
            'status' => 'privacy:metadata:topomojo_bulkdeploy_job:status',
            'timecreated' => 'privacy:metadata:topomojo_bulkdeploy_job:timecreated',
        ], 'privacy:metadata:topomojo_bulkdeploy_job');

        // Each attempt owns a question usage, so the question engine holds the responses.
        $collection->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * A user has data in a TopoMojo activity if they have an attempt, a grade, a bulk-deploy row,
     * if they triggered or cancelled a bulk-deploy job, or if they took a step in a question usage
     * owned by an attempt in that activity — the last case covers a teacher who manually graded.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $frommodule = "FROM {context} c
                       JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                       JOIN {modules} m ON m.id = cm.module AND m.name = 'topomojo'
                       JOIN {topomojo} t ON t.id = cm.instance";

        // Attempts and grades.
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {topomojo_attempts} ta ON ta.topomojoid = t.id
                 WHERE ta.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        $sql = "SELECT c.id
                  $frommodule
                  JOIN {topomojo_grades} tg ON tg.topomojoid = t.id
                 WHERE tg.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        // Bulk-deploy: the user the gamespace was deployed for.
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {topomojo_bulkdeploy_job} bj ON bj.topomojoid = t.id
                  JOIN {topomojo_bulkdeploy_user} bu ON bu.jobid = bj.id
                 WHERE bu.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        // Bulk-deploy: the user who started or cancelled the job.
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {topomojo_bulkdeploy_job} bj ON bj.topomojoid = t.id
                 WHERE bj.initiatorid = :initiatorid OR bj.cancelledby = :cancelledby";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'initiatorid' => $userid,
            'cancelledby' => $userid,
        ]);

        // Question usages the user took a step in, whoever owns the attempt.
        $qubaid = \core_question\privacy\provider::get_related_question_usages_for_user(
            'rel',
            'mod_topomojo',
            'ta.questionusageid',
            $userid
        );
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {topomojo_attempts} ta ON ta.topomojoid = t.id
                  {$qubaid->from}
                 WHERE {$qubaid->where()}";
        $contextlist->add_from_sql($sql, array_merge(
            ['contextlevel' => CONTEXT_MODULE],
            $qubaid->from_where_params()
        ));

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this
     *                           context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'topomojo'];

        $frommodule = "FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       JOIN {topomojo} t ON t.id = cm.instance";

        $sql = "SELECT ta.userid
                  $frommodule
                  JOIN {topomojo_attempts} ta ON ta.topomojoid = t.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT tg.userid
                  $frommodule
                  JOIN {topomojo_grades} tg ON tg.topomojoid = t.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT bu.userid
                  $frommodule
                  JOIN {topomojo_bulkdeploy_job} bj ON bj.topomojoid = t.id
                  JOIN {topomojo_bulkdeploy_user} bu ON bu.jobid = bj.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT bj.initiatorid
                  $frommodule
                  JOIN {topomojo_bulkdeploy_job} bj ON bj.topomojoid = t.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('initiatorid', $sql, $params);

        $sql = "SELECT bj.cancelledby
                  $frommodule
                  JOIN {topomojo_bulkdeploy_job} bj ON bj.topomojoid = t.id
                 WHERE cm.id = :cmid AND bj.cancelledby IS NOT NULL";
        $userlist->add_from_sql('cancelledby', $sql, $params);

        // Anyone who took a step in one of this activity's question usages, which picks up
        // teachers who manually graded a student's attempt.
        $sql = "SELECT ta.questionusageid
                  $frommodule
                  JOIN {topomojo_attempts} ta ON ta.topomojoid = t.id
                 WHERE cm.id = :cmid AND ta.questionusageid <> 0";
        \core_question\privacy\provider::get_users_in_context_from_sql($userlist, 'qn', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('topomojo', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $topomojo = $DB->get_record('topomojo', ['id' => $cm->instance]);
            if (!$topomojo) {
                continue;
            }

            // The module's own settings, name and intro, which core expects every activity to write.
            $contextdata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $contextdata);

            self::export_attempts($userid, $context, $topomojo);
            self::export_grade($userid, $context, $topomojo);
            self::export_bulkdeploy($userid, $context, $topomojo);
        }
    }

    /**
     * Export the user's attempts in one activity, and the question usage behind each.
     *
     * Preview attempts are included. mod_quiz filters them out of its export, but an instructor's
     * preview is still that instructor's own attempt data, and omitting it from a subject access
     * request would be the same class of gap this provider exists to close.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $topomojo The topomojo activity record.
     */
    protected static function export_attempts(int $userid, \context_module $context, \stdClass $topomojo) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

        $attempts = $DB->get_recordset('topomojo_attempts', ['topomojoid' => $topomojo->id], 'id ASC');

        // AFTER_CLOSE, because a subject access request is by definition after the fact: the
        // question engine still honours the activity's review settings, so a hidden mark stays
        // hidden, but nothing is withheld merely because the lab is still open.
        $options = \mod_topomojo_display_options::make_from_topomojo(
            $topomojo,
            \mod_topomojo_display_options::AFTER_CLOSE
        );

        foreach ($attempts as $attempt) {
            $isowner = ((int)$attempt->userid === $userid);
            $subcontext = [
                get_string('privacy:path:attempts', 'mod_topomojo'),
                $attempt->id,
            ];

            if (!empty($attempt->questionusageid)) {
                \core_question\privacy\provider::export_question_usage(
                    $userid,
                    $context,
                    $subcontext,
                    $attempt->questionusageid,
                    $options,
                    $isowner
                );
            }

            if (!$isowner) {
                // Not this user's attempt. Whatever they contributed to the question usage has
                // been exported above; the attempt row itself is someone else's data.
                continue;
            }

            $data = (object)[
                'state' => $attempt->state,
                'workspaceid' => $attempt->workspaceid,
                'eventid' => $attempt->eventid,
                'launchpointurl' => $attempt->launchpointurl,
                'variant' => $attempt->variant,
                'preview' => transform::yesno($attempt->preview),
                'timestart' => transform::datetime($attempt->timestart),
                'timemodified' => transform::datetime($attempt->timemodified),
            ];
            if (!empty($attempt->timefinish)) {
                $data->timefinish = transform::datetime($attempt->timefinish);
            }
            if (!empty($attempt->endtime)) {
                $data->endtime = transform::datetime($attempt->endtime);
            }
            if ($options->marks >= \question_display_options::MARK_AND_MAX) {
                $data->score = $attempt->score;
            }

            writer::with_context($context)->export_data($subcontext, $data);
        }
        $attempts->close();
    }

    /**
     * Export the user's stored grade for one activity.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $topomojo The topomojo activity record.
     */
    protected static function export_grade(int $userid, \context_module $context, \stdClass $topomojo) {
        global $DB;

        $grades = $DB->get_records('topomojo_grades', ['topomojoid' => $topomojo->id, 'userid' => $userid]);
        foreach ($grades as $grade) {
            writer::with_context($context)->export_data(
                [get_string('privacy:path:grades', 'mod_topomojo'), $grade->id],
                (object)[
                    'grade' => $grade->grade,
                    'timemodified' => transform::datetime($grade->timemodified),
                ]
            );
        }
    }

    /**
     * Export the user's bulk-deploy involvement for one activity, as a subject and as an initiator.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $topomojo The topomojo activity record.
     */
    protected static function export_bulkdeploy(int $userid, \context_module $context, \stdClass $topomojo) {
        global $DB;

        $jobs = $DB->get_records('topomojo_bulkdeploy_job', ['topomojoid' => $topomojo->id], 'id ASC');
        foreach ($jobs as $job) {
            $subcontext = [get_string('privacy:path:bulkdeploy', 'mod_topomojo'), $job->id];

            if ((int)$job->initiatorid === $userid || (int)$job->cancelledby === $userid) {
                writer::with_context($context)->export_data($subcontext, (object)[
                    'role' => (int)$job->initiatorid === $userid ? 'initiator' : 'cancelledby',
                    'status' => $job->status,
                    'rolefilter' => $job->rolefilter,
                    'totalusers' => $job->totalusers,
                    'timecreated' => transform::datetime($job->timecreated),
                ]);
            }

            $rows = $DB->get_records('topomojo_bulkdeploy_user', ['jobid' => $job->id, 'userid' => $userid]);
            foreach ($rows as $row) {
                $data = (object)[
                    'gamespaceid' => $row->gamespaceid,
                    'status' => $row->status,
                    'errormessage' => $row->errormessage,
                ];
                if (!empty($row->timestarted)) {
                    $data->timestarted = transform::datetime($row->timestarted);
                }
                if (!empty($row->timecompleted)) {
                    $data->timecompleted = transform::datetime($row->timecompleted);
                }
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:path:deployment', 'mod_topomojo')]),
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $CFG, $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('topomojo', $context->instanceid);
        if (!$cm) {
            return;
        }
        $topomojo = $DB->get_record('topomojo', ['id' => $cm->instance]);
        if (!$topomojo) {
            return;
        }

        // The shared topomojo_delete_all_attempts() already removes the question usages, the attempts, the
        // grades and the bulk-deploy rows, and stops any gamespaces still running on the TopoMojo
        // side. Reusing it keeps one definition of "wipe this activity" rather than two that can
        // drift apart.
        require_once($CFG->dirroot . '/mod/topomojo/lib.php');
        topomojo_delete_all_attempts($topomojo);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('topomojo', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_users_in_activity((int)$cm->instance, [$userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('topomojo', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_users_in_activity((int)$cm->instance, $userlist->get_userids());
    }

    /**
     * Remove the given users' attempts, grades and bulk-deploy rows from one activity.
     *
     * @param int $topomojoid The topomojo activity id.
     * @param array $userids The users to remove.
     */
    protected static function delete_users_in_activity(int $topomojoid, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params = array_merge(['topomojoid' => $topomojoid], $inparams);

        // Question usages first: once the attempt rows are gone there is nothing left to find them
        // by, since every qubaid condition here resolves usages through topomojo_attempts.
        //
        // Built inline rather than through qubaids_for_topomojo, which filters by activity, preview
        // and state but not by user. Widening that class would change what the three existing
        // callers in lib.php match.
        [$qbinsql, $qbinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'qbusr');
        \question_engine::delete_questions_usage_by_activities(new \qubaid_join(
            '{topomojo_attempts} topomojoa',
            'topomojoa.questionusageid',
            "topomojoa.topomojoid = :qbtopomojoid AND topomojoa.userid $qbinsql",
            array_merge(['qbtopomojoid' => $topomojoid], $qbinparams)
        ));

        $DB->delete_records_select('topomojo_attempts', "topomojoid = :topomojoid AND userid $insql", $params);
        $DB->delete_records_select('topomojo_grades', "topomojoid = :topomojoid AND userid $insql", $params);

        // Bulk-deploy rows for these users, in this activity's jobs. The job rows themselves are
        // shared state describing the activity rather than one person's data, so they stay: only
        // the initiatorid and cancelledby references are scrubbed.
        $jobids = $DB->get_fieldset_select(
            'topomojo_bulkdeploy_job',
            'id',
            'topomojoid = :topomojoid',
            ['topomojoid' => $topomojoid]
        );
        if ($jobids) {
            [$jobsql, $jobparams] = $DB->get_in_or_equal($jobids, SQL_PARAMS_NAMED, 'job');
            $DB->delete_records_select(
                'topomojo_bulkdeploy_user',
                "jobid $jobsql AND userid $insql",
                array_merge($jobparams, $inparams)
            );

            // The initiatorid column is NOT NULL, so it cannot be blanked; point it at the anonymous
            // "guest-like" id 0 the way core does for unattributable rows. cancelledby is
            // nullable and can simply be cleared.
            $DB->set_field_select(
                'topomojo_bulkdeploy_job',
                'initiatorid',
                0,
                "id $jobsql AND initiatorid $insql",
                array_merge($jobparams, $inparams)
            );
            $DB->set_field_select(
                'topomojo_bulkdeploy_job',
                'cancelledby',
                null,
                "id $jobsql AND cancelledby $insql",
                array_merge($jobparams, $inparams)
            );
        }
    }
}
