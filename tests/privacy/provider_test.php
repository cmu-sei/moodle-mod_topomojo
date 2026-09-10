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
 * Unit tests for the mod_topomojo privacy provider.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the mod_topomojo privacy provider.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * A course, two TopoMojo activities in it, and two users.
     *
     * @var \stdClass
     */
    protected $course;

    /**
     * First TopoMojo activity.
     *
     * @var \stdClass
     */
    protected $topomojo1;

    /**
     * Second TopoMojo activity, used to prove contexts are reported separately.
     *
     * @var \stdClass
     */
    protected $topomojo2;

    /**
     * The user whose data is being requested.
     *
     * @var \stdClass
     */
    protected $student1;

    /**
     * A second user, whose data must survive every deletion aimed at the first.
     *
     * @var \stdClass
     */
    protected $student2;

    /**
     * Build the course, activities and users shared by every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->topomojo1 = $generator->create_module('topomojo', ['course' => $this->course->id]);
        $this->topomojo2 = $generator->create_module('topomojo', ['course' => $this->course->id]);
        $this->student1 = $generator->create_user();
        $this->student2 = $generator->create_user();
    }

    /**
     * The module context for a TopoMojo activity.
     *
     * @param \stdClass $topomojo The activity.
     * @return \context_module
     */
    protected function context_for(\stdClass $topomojo): \context_module {
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id, $this->course->id, false, MUST_EXIST);
        return \context_module::instance($cm->id);
    }

    /**
     * Create a saved question usage owned by an activity.
     *
     * @param \stdClass $topomojo The activity the usage belongs to.
     * @return int The question usage id.
     */
    protected function create_question_usage(\stdClass $topomojo): int {
        $quba = \question_engine::make_questions_usage_by_activity('mod_topomojo', $this->context_for($topomojo));
        $quba->set_preferred_behaviour('deferredfeedback');
        \question_engine::save_questions_usage_by_activity($quba);
        return $quba->get_id();
    }

    /**
     * Create a bulk-deploy job and one per-user row within it.
     *
     * @param \stdClass $topomojo The activity the job belongs to.
     * @param int $initiatorid The user who started the job.
     * @param int $subjectid The user a gamespace was deployed for.
     * @return array The job id and the per-user row id.
     */
    protected function create_bulkdeploy(\stdClass $topomojo, int $initiatorid, int $subjectid): array {
        global $DB;

        $jobid = $DB->insert_record('topomojo_bulkdeploy_job', (object)[
            'topomojoid' => $topomojo->id,
            'courseid' => $this->course->id,
            'initiatorid' => $initiatorid,
            'batchsize' => 5,
            'totalusers' => 1,
            'status' => 'completed',
            'timecreated' => time(),
        ]);
        $rowid = $DB->insert_record('topomojo_bulkdeploy_user', (object)[
            'jobid' => $jobid,
            'userid' => $subjectid,
            'gamespaceid' => 'gamespace-' . $subjectid,
            'status' => 'ready',
            'timestarted' => time(),
        ]);

        return [$jobid, $rowid];
    }

    /**
     * The provider must describe every table it writes a userid into, and link the question engine.
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('mod_topomojo');
        $collection = provider::get_metadata($collection);

        $names = [];
        foreach ($collection->get_collection() as $item) {
            $names[] = $item->get_name();
        }

        $this->assertContains('topomojo_attempts', $names);
        $this->assertContains('topomojo_grades', $names);
        $this->assertContains('topomojo_bulkdeploy_user', $names);
        $this->assertContains('topomojo_bulkdeploy_job', $names);
        $this->assertContains('core_question', $names);
    }

    /**
     * The plugin must not claim to be a null_provider, which is what the export and delete paths
     * silently skipped before this was implemented.
     */
    public function test_provider_is_not_a_null_provider(): void {
        $this->assertFalse(
            is_subclass_of(provider::class, \core_privacy\local\metadata\null_provider::class),
            'mod_topomojo stores a userid in three tables, so it cannot be a null_provider.'
        );
        $this->assertTrue(is_subclass_of(provider::class, \core_privacy\local\metadata\provider::class));
        $this->assertTrue(is_subclass_of(provider::class, \core_privacy\local\request\plugin\provider::class));
        $this->assertTrue(is_subclass_of(provider::class, \core_privacy\local\request\core_userlist_provider::class));
    }

    /**
     * An attempt in one activity and a grade in another must report both contexts, and no others.
     */
    public function test_get_contexts_for_userid(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $generator->create_attempt($this->topomojo1, $this->student1);
        $generator->create_grade($this->topomojo2, $this->student1, 80.0);

        // The contextids come back as whatever the database returned, which is strings on pgsql,
        // so normalise before comparing rather than relying on loose assertions.
        $contextids = array_map('intval', provider::get_contexts_for_userid($this->student1->id)->get_contextids());

        $this->assertCount(2, $contextids);
        $this->assertContains((int)$this->context_for($this->topomojo1)->id, $contextids);
        $this->assertContains((int)$this->context_for($this->topomojo2)->id, $contextids);

        // A user with nothing in either activity has no contexts.
        $this->assertEmpty(provider::get_contexts_for_userid($this->student2->id)->get_contextids());
    }

    /**
     * Starting a bulk deployment is participation too, so the initiator gets the context back.
     */
    public function test_get_contexts_for_userid_includes_bulkdeploy(): void {
        $this->create_bulkdeploy($this->topomojo1, $this->student1->id, $this->student2->id);

        $initiatorcontexts = provider::get_contexts_for_userid($this->student1->id)->get_contextids();
        $subjectcontexts = provider::get_contexts_for_userid($this->student2->id)->get_contextids();

        $this->assertEquals([$this->context_for($this->topomojo1)->id], $initiatorcontexts);
        $this->assertEquals([$this->context_for($this->topomojo1)->id], $subjectcontexts);
    }

    /**
     * Every user with an attempt, a grade or a bulk-deploy row in the activity must be listed.
     */
    public function test_get_users_in_context(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $generator->create_attempt($this->topomojo1, $this->student1);
        $generator->create_grade($this->topomojo1, $this->student2, 55.0);
        $elsewhere = $this->getDataGenerator()->create_user();
        $generator->create_attempt($this->topomojo2, $elsewhere);

        $userlist = new userlist($this->context_for($this->topomojo1), 'mod_topomojo');
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());

        $this->assertCount(2, $userids);
        $this->assertContains((int)$this->student1->id, $userids);
        $this->assertContains((int)$this->student2->id, $userids);
        $this->assertNotContains((int)$elsewhere->id, $userids);
    }

    /**
     * The export must contain the attempt and the grade, under their own subcontexts.
     */
    public function test_export_user_data(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $attempt = $generator->create_attempt($this->topomojo1, $this->student1, [
            'eventid' => 'gamespace-guid',
            'launchpointurl' => 'https://topomojo.example.org/mks/gamespace-guid',
            'score' => 42.0,
        ]);
        $grade = $generator->create_grade($this->topomojo1, $this->student1, 42.0);

        $context = $this->context_for($this->topomojo1);
        $this->export_context_data_for_user($this->student1->id, $context, 'mod_topomojo');
        $writer = writer::with_context($context);

        $this->assertTrue($writer->has_any_data());

        $attemptdata = $writer->get_data([get_string('privacy:path:attempts', 'mod_topomojo'), $attempt->id]);
        $this->assertEquals('gamespace-guid', $attemptdata->eventid);
        $this->assertEquals('https://topomojo.example.org/mks/gamespace-guid', $attemptdata->launchpointurl);
        $this->assertEquals(\mod_topomojo\topomojo_attempt::INPROGRESS, $attemptdata->state);

        $gradedata = $writer->get_data([get_string('privacy:path:grades', 'mod_topomojo'), $grade->id]);
        $this->assertEquals(42.0, (float)$gradedata->grade);
    }

    /**
     * One user's export must not leak another user's attempt in the same activity.
     */
    public function test_export_user_data_excludes_other_users(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $mine = $generator->create_attempt($this->topomojo1, $this->student1);
        $theirs = $generator->create_attempt($this->topomojo1, $this->student2);

        $context = $this->context_for($this->topomojo1);
        $this->export_context_data_for_user($this->student1->id, $context, 'mod_topomojo');
        $writer = writer::with_context($context);

        $attemptspath = get_string('privacy:path:attempts', 'mod_topomojo');
        $this->assertNotEmpty($writer->get_data([$attemptspath, $mine->id]));
        $this->assertEmpty($writer->get_data([$attemptspath, $theirs->id]));
    }

    /**
     * Deleting one user must remove their attempt, grade and question usage, and leave the other
     * user's untouched.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $usage1 = $this->create_question_usage($this->topomojo1);
        $usage2 = $this->create_question_usage($this->topomojo1);
        $attempt1 = $generator->create_attempt($this->topomojo1, $this->student1, ['questionusageid' => $usage1]);
        $attempt2 = $generator->create_attempt($this->topomojo1, $this->student2, ['questionusageid' => $usage2]);
        $grade1 = $generator->create_grade($this->topomojo1, $this->student1, 10.0);
        $grade2 = $generator->create_grade($this->topomojo1, $this->student2, 20.0);
        // A second activity, to prove the delete is scoped to the approved context.
        $elsewhere = $generator->create_attempt($this->topomojo2, $this->student1);

        $context = $this->context_for($this->topomojo1);
        provider::delete_data_for_user(new approved_contextlist(
            $this->student1,
            'mod_topomojo',
            [$context->id]
        ));

        $this->assertFalse($DB->record_exists('topomojo_attempts', ['id' => $attempt1->id]));
        $this->assertFalse($DB->record_exists('question_usages', ['id' => $usage1]));
        $this->assertFalse($DB->record_exists('topomojo_grades', ['id' => $grade1->id]));

        $this->assertTrue($DB->record_exists('topomojo_attempts', ['id' => $attempt2->id]));
        $this->assertTrue($DB->record_exists('question_usages', ['id' => $usage2]));
        $this->assertTrue($DB->record_exists('topomojo_grades', ['id' => $grade2->id]));
        $this->assertTrue($DB->record_exists('topomojo_attempts', ['id' => $elsewhere->id]));
    }

    /**
     * A user's bulk-deploy row goes; the job row stays but stops naming them.
     *
     * The job describes the activity rather than one person, so deleting it would take the other
     * users in the batch with it.
     */
    public function test_delete_data_for_user_scrubs_bulkdeploy(): void {
        global $DB;

        [$jobid, $rowid] = $this->create_bulkdeploy($this->topomojo1, $this->student1->id, $this->student1->id);

        provider::delete_data_for_user(new approved_contextlist(
            $this->student1,
            'mod_topomojo',
            [$this->context_for($this->topomojo1)->id]
        ));

        $this->assertFalse($DB->record_exists('topomojo_bulkdeploy_user', ['id' => $rowid]));
        $this->assertTrue($DB->record_exists('topomojo_bulkdeploy_job', ['id' => $jobid]));
        $this->assertEquals(0, $DB->get_field('topomojo_bulkdeploy_job', 'initiatorid', ['id' => $jobid]));
    }

    /**
     * Deleting the whole context must clear every user's data in that activity, and only there.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $usage = $this->create_question_usage($this->topomojo1);
        $generator->create_attempt($this->topomojo1, $this->student1, ['questionusageid' => $usage]);
        $generator->create_attempt($this->topomojo1, $this->student2);
        $generator->create_grade($this->topomojo1, $this->student1, 10.0);
        $survivor = $generator->create_attempt($this->topomojo2, $this->student1);

        provider::delete_data_for_all_users_in_context($this->context_for($this->topomojo1));

        $this->assertEquals(0, $DB->count_records('topomojo_attempts', ['topomojoid' => $this->topomojo1->id]));
        $this->assertEquals(0, $DB->count_records('topomojo_grades', ['topomojoid' => $this->topomojo1->id]));
        $this->assertFalse($DB->record_exists('question_usages', ['id' => $usage]));
        $this->assertTrue($DB->record_exists('topomojo_attempts', ['id' => $survivor->id]));
    }

    /**
     * The userlist deletion path must remove exactly the approved users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $student3 = $this->getDataGenerator()->create_user();
        $attempt1 = $generator->create_attempt($this->topomojo1, $this->student1);
        $attempt2 = $generator->create_attempt($this->topomojo1, $this->student2);
        $attempt3 = $generator->create_attempt($this->topomojo1, $student3);

        $context = $this->context_for($this->topomojo1);
        provider::delete_data_for_users(new approved_userlist(
            $context,
            'mod_topomojo',
            [$this->student1->id, $student3->id]
        ));

        $this->assertFalse($DB->record_exists('topomojo_attempts', ['id' => $attempt1->id]));
        $this->assertFalse($DB->record_exists('topomojo_attempts', ['id' => $attempt3->id]));
        $this->assertTrue($DB->record_exists('topomojo_attempts', ['id' => $attempt2->id]));
    }

    /**
     * An empty userlist must be a no-op rather than a delete-everything.
     *
     * get_in_or_equal() throws on an empty array, so without the guard this would be an exception
     * on any request the admin approved for nobody.
     */
    public function test_delete_data_for_users_with_no_users(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $attempt = $generator->create_attempt($this->topomojo1, $this->student1);

        provider::delete_data_for_users(new approved_userlist(
            $this->context_for($this->topomojo1),
            'mod_topomojo',
            []
        ));

        $this->assertTrue($DB->record_exists('topomojo_attempts', ['id' => $attempt->id]));
    }
}
