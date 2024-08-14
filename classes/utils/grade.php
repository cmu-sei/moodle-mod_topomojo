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

namespace mod_topomojo\utils;

defined('MOODLE_INTERNAL') || die();

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

/**
 * topomojo Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_topomojo
 * @copyright   2020 Carnegie Mellon Univeristy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade {

    /** @var \mod_topomojo\topomojo */
    public $topomojo;

    /**
     * Construct for the grade utility class
     *
     * @param \mod_topomojo\topomojo $topomojo
     */
    public function __construct($topomojo) {
        $this->topomojo = $topomojo;
    }

    /**
     * Get the attempt's grade
     *
     * For now this will always be the last attempt for the user
     *
     * @param \mod_topomojo\TOPOMOJO_Attempt $attempt
     * @param int                                $userid The userid to get the grade for
     * @return array($forgroupid, $number)
     */
    public function get_attempt_grade($attempt) {
        return [$attempt->userid, $this->calculate_attempt_grade($attempt)];
    }

    /**
     * Gets the user grade, userid can be 0, which will return all grades for the topomojo
     *
     * @param $topomojo
     * @param $userid
     * @return array
     */
    public static function get_user_grade($topomojo, $userid = 0) {

        global $DB;
        $recs = $DB->get_records_select('topomojo_grades', 'userid = ? AND topomojoid = ?',
                [$userid, $topomojo->id], 'grade');
        $grades = [];
        foreach ($recs as $rec) {
            array_push($grades, $rec->grade);
        }
        // User should only have one grade entry in the topomojo grades table for each activity
        debugging("user $userid has " . count($grades) . " grades for $topomojo->id", DEBUG_DEVELOPER);
        return $grades;
    }

    /**
     * Processes and updates grades for a given attempt, handling transactions and gradebook updates.
     *
     * @param \stdClass $attempt The attempt object containing attempt details.
     * @return bool True on success, false on failure.
     */
    public function process_attempt($attempt) {
        global $DB;

        // Get this attempt grade
        $this->calculate_attempt_grade($attempt);

        // Get all attempt grades
        $grades = [];
        $attemptsgrades = [];

        // TODO should we be processing just one user here?
        $attempts = $this->topomojo->getall_attempts('');

        foreach ($attempts as $attempt) {
            array_push($attemptsgrades, $attempt->score);
        }

        $grade = $this->apply_grading_method($attemptsgrades);
        $grades[$attempt->userid] = $grade;
        debugging("new grade for $attempt->userid in topomojo " . $this->topomojo->topomojo->id . " is $grade", DEBUG_DEVELOPER);

        // Run the whole thing on a transaction (persisting to our table and gradebook updates).
        $transaction = $DB->start_delegated_transaction();

        // Now that we have the final grades persist the grades to topomojo grades table.
        // TODO we could possibly remove this table and just look at the grade_grades table
        $this->persist_grades($grades, $transaction);

        // Update grades to gradebookapi.
        $updated = topomojo_update_grades($this->topomojo->topomojo, $attempt->userid, $grade);

        if ($updated === GRADE_UPDATE_FAILED) {
            $transaction->rollback(new \Exception('Unable to save grades to gradebook'));
        }

        // Allow commit if we get here
        $transaction->allow_commit();

        // If everything passes to here return true
        return true;

    }

    /**
     * Calculate the grade for attempt passed in
     *
     * This function does the scaling down to what was desired in the topomojo settings
     *
     * Is public function so that tableviews can get an attempt calculated grade
     *
     * @param \mod_topomojo\TOPOMOJO_Attempt $attempt
     * @return number The grade to save
     */
    public function calculate_attempt_grade($attempt) {
        global $DB;

        $totalpoints = 0;
        $totalslotpoints = 0;

        if (is_null($attempt)) {
            debugging("invalid attempt passed to calculate_attempt_grade", DEBUG_DEVELOPER);
            return $totalslotpoints;
        }

        $quba = $attempt->get_quba();

        $totalpoints = 0;
        $totalslotpoints = 0;
        foreach ($attempt->getSlots() as $slot) {
            $totalpoints = $totalpoints + $quba->get_question_max_mark($slot);
            $slotpoints = $quba->get_question_mark($slot);
            if (!empty($slotpoints)) {
                $totalslotpoints = $totalslotpoints + $slotpoints;
            }
        }
        $scaledpoints = ($totalslotpoints / $totalpoints) * $this->topomojo->topomojo->grade;

        debugging("$scaledpoints = ($totalslotpoints / $totalpoints) * " . $this->topomojo->topomojo->grade, DEBUG_DEVELOPER);
        debugging("new score for $attempt->id is $scaledpoints", DEBUG_DEVELOPER);

        $attempt->score = $scaledpoints;
        $attempt->save();

        return $scaledpoints;
    }

    /**
     * Helper function that returns the grade to pass.
     *
     * @return string
     */
    public function get_grade_item_passing_grade() {
        global $DB;

        $gradetopass = $DB->get_field('grade_items', 'gradepass',
                            ['iteminstance' => $this->topomojo->topomojo->id, 'itemmodule' => 'topomojo']);

        return $gradetopass;
    }

    /**
     * Applies the grading method chosen
     *
     * @param array $grades The grades for each attempts for a particular user
     * @return number
     * @throws \Exception When there is no valid scaletype throws new exception
     */
    protected function apply_grading_method($grades) {
        debugging("grade method is " . $this->topomojo->topomojo->grademethod . " for " .
                  $this->topomojo->topomojo->id, DEBUG_DEVELOPER);
        switch ($this->topomojo->topomojo->grademethod) {
            case \mod_topomojo\utils\scaletypes::TOPOMOJO_FIRSTATTEMPT:
                // Take the first record (as there should only be one since it was filtered out earlier)
                reset($grades);
                return current($grades);

                break;
            case \mod_topomojo\utils\scaletypes::TOPOMOJO_LASTATTEMPT:
                // Take the last grade (there should only be one, as the last attempt was filtered out earlier)
                return end($grades);

                break;
            case \mod_topomojo\utils\scaletypes::TOPOMOJO_ATTEMPTAVERAGE:
                // Average the grades
                $gradecount = count($grades);
                $gradetotal = 0;
                foreach ($grades as $grade) {
                    $gradetotal = $gradetotal + $grade;
                }
                return $gradetotal / $gradecount;

                break;
            case \mod_topomojo\utils\scaletypes::TOPOMOJO_HIGHESTATTEMPTGRADE:
                // Find the highest grade
                $highestgrade = 0;
                foreach ($grades as $grade) {
                    if ($grade > $highestgrade) {
                        $highestgrade = $grade;
                    }
                }
                return $highestgrade;

                break;
            default:
                throw new \Exception('Invalid grade method');
                break;
        }
    }

    /**
     * Persist the passed in grades (keyed by userid) to the database
     *
     * @param array               $grades
     * @param \moodle_transaction $transaction
     *
     * @return bool
     */
    protected function persist_grades($grades, \moodle_transaction $transaction) {
        global $DB;

        foreach ($grades as $userid => $grade) {

            if ($usergrade = $DB->get_record('topomojo_grades',
                                  ['userid' => $userid, 'topomojoid' => $this->topomojo->topomojo->id])) {
                // We're updating
                $usergrade->grade = $grade;
                $usergrade->timemodified = time();

                if (!$DB->update_record('topomojo_grades', $usergrade)) {
                    $transaction->rollback(new \Exception('Can\'t update user grades'));
                }
            } else {
                // We're adding

                $usergrade = new \stdClass();
                $usergrade->topomojoid = $this->topomojo->topomojo->id;
                $usergrade->userid = $userid;
                $usergrade->grade = $grade;
                $usergrade->timemodified = time();

                if (!$DB->insert_record('topomojo_grades', $usergrade)) {
                    $transaction->rollback(new \Exception('Can\'t insert user grades'));
                }

            }
            debugging("persisted $grade for $userid in topomojo " . $this->topomojo->topomojo->id, DEBUG_DEVELOPER);
        }

        return true;

    }


    /**
     * Save and (re)calculate grades for this lab
     *
     * @param bool $regradeattempts Regrade the question attempts themselves through the question engine
     * @return bool
     */
    public function save_all_grades($regradeattempts = false) {

        $attempts = $this->topomojo->getall_attempts($open = 'closed');

        foreach ($attempts as $attempt) {
            // If we're regrading attempts, send them off to be re-graded before processing all sessions.
            if ($regradeattempts) {
                $this->process_attempt($attempt);
            }
        }
        return true;
    }

}
