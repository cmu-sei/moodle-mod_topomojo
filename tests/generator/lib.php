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
 * Generator for mod_topomojo.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * TopoMojo module data generator class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_topomojo_generator extends testing_module_generator {

    /**
     * Creates a new instance of mod_topomojo.
     *
     * @param array|stdClass $record Record of properties for the new instance.
     * @param array $options General options for the module.
     * @return stdClass The created instance.
     */
    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/topomojo/lib.php');

        $record = (object)(array)$record;

        // Set default values if not provided.
        if (!isset($record->name)) {
            $record->name = 'Test TopoMojo ' . time();
        }
        if (!isset($record->intro)) {
            $record->intro = 'Test introduction for TopoMojo activity';
        }
        if (!isset($record->introformat)) {
            $record->introformat = FORMAT_HTML;
        }
        if (!isset($record->workspaceid)) {
            $record->workspaceid = 'test-workspace-' . rand(1000, 9999);
        }
        if (!isset($record->timeopen)) {
            $record->timeopen = 0;
        }
        if (!isset($record->timeclose)) {
            $record->timeclose = 0;
        }
        if (!isset($record->timelimit)) {
            $record->timelimit = 0;
        }
        if (!isset($record->isfeatured)) {
            $record->isfeatured = 0;
        }
        if (!isset($record->preferredbehaviour)) {
            $record->preferredbehaviour = 'deferredfeedback';
        }
        if (!isset($record->grade)) {
            $record->grade = 100;
        }

        // Set default review options.
        if (!isset($record->attemptduring)) {
            $record->attemptduring = 1;
        }
        if (!isset($record->attemptimmediately)) {
            $record->attemptimmediately = 1;
        }
        if (!isset($record->attemptopen)) {
            $record->attemptopen = 1;
        }
        if (!isset($record->attemptclosed)) {
            $record->attemptclosed = 1;
        }
        if (!isset($record->correctnessduring)) {
            $record->correctnessduring = 0;
        }
        if (!isset($record->correctnessimmediately)) {
            $record->correctnessimmediately = 1;
        }
        if (!isset($record->correctnessopen)) {
            $record->correctnessopen = 1;
        }
        if (!isset($record->correctnessclosed)) {
            $record->correctnessclosed = 1;
        }
        if (!isset($record->marksduring)) {
            $record->marksduring = 0;
        }
        if (!isset($record->marksimmediately)) {
            $record->marksimmediately = 1;
        }
        if (!isset($record->marksopen)) {
            $record->marksopen = 1;
        }
        if (!isset($record->marksclosed)) {
            $record->marksclosed = 1;
        }
        if (!isset($record->specificfeedbackduring)) {
            $record->specificfeedbackduring = 0;
        }
        if (!isset($record->specificfeedbackimmediately)) {
            $record->specificfeedbackimmediately = 1;
        }
        if (!isset($record->specificfeedbackopen)) {
            $record->specificfeedbackopen = 1;
        }
        if (!isset($record->specificfeedbackclosed)) {
            $record->specificfeedbackclosed = 1;
        }
        if (!isset($record->generalfeedbackduring)) {
            $record->generalfeedbackduring = 0;
        }
        if (!isset($record->generalfeedbackimmediately)) {
            $record->generalfeedbackimmediately = 1;
        }
        if (!isset($record->generalfeedbackopen)) {
            $record->generalfeedbackopen = 1;
        }
        if (!isset($record->generalfeedbackclosed)) {
            $record->generalfeedbackclosed = 1;
        }
        if (!isset($record->rightanswerduring)) {
            $record->rightanswerduring = 0;
        }
        if (!isset($record->rightanswerimmediately)) {
            $record->rightanswerimmediately = 1;
        }
        if (!isset($record->rightansweropen)) {
            $record->rightansweropen = 1;
        }
        if (!isset($record->rightanswerclosed)) {
            $record->rightanswerclosed = 1;
        }
        if (!isset($record->overallfeedbackduring)) {
            $record->overallfeedbackduring = 0;
        }
        if (!isset($record->overallfeedbackimmediately)) {
            $record->overallfeedbackimmediately = 0;
        }
        if (!isset($record->overallfeedbackopen)) {
            $record->overallfeedbackopen = 1;
        }
        if (!isset($record->overallfeedbackclosed)) {
            $record->overallfeedbackclosed = 1;
        }
        if (!isset($record->manualcommentduring)) {
            $record->manualcommentduring = 0;
        }
        if (!isset($record->manualcommentimmediately)) {
            $record->manualcommentimmediately = 0;
        }
        if (!isset($record->manualcommentopen)) {
            $record->manualcommentopen = 1;
        }
        if (!isset($record->manualcommentclosed)) {
            $record->manualcommentclosed = 1;
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Creates an attempt for a topomojo instance.
     *
     * @param stdClass $topomojo The topomojo instance.
     * @param stdClass $user The user creating the attempt.
     * @param array $record Additional properties for the attempt.
     * @return stdClass The created attempt.
     */
    public function create_attempt($topomojo, $user, $record = []) {
        global $DB;

        $record = (array)$record;

        $attempt = new stdClass();
        $attempt->topomojoid = $topomojo->id;
        $attempt->userid = $user->id;
        $attempt->state = $record['state'] ?? \mod_topomojo\topomojo_attempt::INPROGRESS;
        $attempt->timestart = $record['timestart'] ?? time();
        $attempt->timefinish = $record['timefinish'] ?? null;
        $attempt->timemodified = time();
        $attempt->score = $record['score'] ?? 0;
        $attempt->layout = $record['layout'] ?? '';
        $attempt->questionusageid = $record['questionusageid'] ?? null;
        $attempt->launchpointurl = $record['launchpointurl'] ?? '';
        $attempt->workspaceid = $record['workspaceid'] ?? $topomojo->workspaceid;
        $attempt->eventid = $record['eventid'] ?? '';
        $attempt->endtime = $record['endtime'] ?? time() + 3600;

        $attempt->id = $DB->insert_record('topomojo_attempts', $attempt);

        return $attempt;
    }

    /**
     * Creates a grade record for a topomojo instance.
     *
     * @param stdClass $topomojo The topomojo instance.
     * @param stdClass $user The user.
     * @param float $grade The grade value.
     * @return stdClass The created grade record.
     */
    public function create_grade($topomojo, $user, $grade) {
        global $DB;

        $graderecord = new stdClass();
        $graderecord->topomojoid = $topomojo->id;
        $graderecord->userid = $user->id;
        $graderecord->grade = $grade;
        $graderecord->timemodified = time();

        $graderecord->id = $DB->insert_record('topomojo_grades', $graderecord);

        return $graderecord;
    }
}
