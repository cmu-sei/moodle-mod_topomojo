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
 * Unit tests for questionmanager question-order resolution.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/classes/questionmanager.php');

/**
 * Tests that a non-empty questionorder resolving to zero usable questions is handled cleanly.
 *
 * This is the interaction between the upgrade hygiene step (which prunes stale questionorder ids
 * and can leave questionorder = NULL) and view.php's zero-question notice: get_questions() must
 * return an empty array so the notice fires, and the stale-id case must prune questionorder to
 * NULL - the state view.php then has to render without erroring.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_topomojo\questionmanager
 */
class questionmanager_test extends \advanced_testcase {

    /**
     * Build a questionmanager for a topomojo record without going through the topomojo class
     * (which would call setup() and hit the TopoMojo API).
     *
     * @param \stdClass $topomojorecord fresh DB record for the activity
     * @param \stdClass $cm course module
     * @return questionmanager
     */
    private function make_manager($topomojorecord, $cm): questionmanager {
        $object = new \stdClass();
        $object->topomojo = $topomojorecord;
        $object->cm = $cm;
        $pagevars = ['pageurl' => new \moodle_url('/mod/topomojo/view.php', ['id' => $cm->id])];
        return new questionmanager($object, null, $pagevars);
    }

    /**
     * A questionorder pointing at a topomojo_questions id that no longer exists resolves to zero
     * questions and is pruned to NULL - exactly what the upgrade step produces for orphaned activities.
     */
    public function test_stale_questionorder_id_resolves_empty_and_prunes_to_null() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id);

        // questionorder references a topomojo_questions id that does not exist.
        $DB->set_field('topomojo', 'questionorder', '99999', ['id' => $topomojo->id]);
        $record = $DB->get_record('topomojo', ['id' => $topomojo->id], '*', MUST_EXIST);

        $qm = $this->make_manager($record, $cm);

        // Ticket 2 signal: zero usable questions -> view.php shows the "no challenge questions" notice.
        $this->assertSame([], $qm->get_questions());

        // The lazy prune (mirrored in bulk by the upgrade step) leaves questionorder = NULL.
        $reloaded = $DB->get_record('topomojo', ['id' => $topomojo->id], '*', MUST_EXIST);
        $this->assertNull($reloaded->questionorder);
    }

    /**
     * An orphan topomojo_questions link (its question row is gone) resolves to zero questions.
     * questionorder still points at a live topomojo_questions row, so it is NOT pruned - this is the
     * other zero-question case view.php must handle (questionorder non-empty, no usable questions).
     */
    public function test_orphan_topomojo_questions_link_resolves_empty() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id);

        // topomojo_questions row referencing a question id that does not exist.
        $tqid = $DB->insert_record('topomojo_questions', (object)[
            'topomojoid' => $topomojo->id,
            'questionid' => 88888,
            'points' => 1.0,
        ]);
        $DB->set_field('topomojo', 'questionorder', (string)$tqid, ['id' => $topomojo->id]);
        $record = $DB->get_record('topomojo', ['id' => $topomojo->id], '*', MUST_EXIST);

        $qm = $this->make_manager($record, $cm);

        // No usable question, but the link exists so questionorder is retained (not pruned).
        $this->assertSame([], $qm->get_questions());
        $reloaded = $DB->get_record('topomojo', ['id' => $topomojo->id], '*', MUST_EXIST);
        $this->assertEquals((string)$tqid, $reloaded->questionorder);
    }

    /**
     * Sanity check: an empty questionorder resolves to zero questions without touching the record.
     */
    public function test_empty_questionorder_resolves_empty() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id);

        $record = $DB->get_record('topomojo', ['id' => $topomojo->id], '*', MUST_EXIST);
        $record->questionorder = null;

        $qm = $this->make_manager($record, $cm);

        $this->assertSame([], $qm->get_questions());
    }
}
