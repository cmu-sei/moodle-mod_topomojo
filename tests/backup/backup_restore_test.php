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
 * Backup and restore tests for mod_topomojo.
 *
 * @package    mod_topomojo
 * @category   backup
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\backup;

use mod_topomojo\questionmanager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');
require_once($CFG->dirroot . '/mod/topomojo/lib.php');
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');
require_once($CFG->dirroot . '/mod/topomojo/classes/questionmanager.php');

/**
 * Round-trips a topomojo activity through backup and restore.
 *
 * The activity settings live in a single wide table, so the failure mode these tests exist to catch
 * is a column being added to db/install.xml without being added to the backup structure: the restore
 * silently falls back to the column default and the teacher's setting is lost with no error anywhere.
 * test_backup_xml_covers_every_activity_column() is the guard for that; the rest assert the actual
 * values, the question links and the intro files survive.
 *
 * @package    mod_topomojo
 * @category   backup
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \backup_topomojo_activity_structure_step
 * @covers \restore_topomojo_activity_structure_step
 */
final class backup_restore_test extends \restore_date_testcase {

    /**
     * Columns of the topomojo table that are deliberately not carried through backup.
     *
     * Anything else missing from the backup structure is a bug, so a new column added to
     * db/install.xml fails test_backup_xml_covers_every_activity_column() until someone decides
     * which of the two lists it belongs in.
     *
     * @var string[]
     */
    private const NOT_BACKED_UP = [
        // Set by the restore itself, not carried from the source course.
        'id',
        'course',
        // Cached copy of the workspace markdown fetched from TopoMojo; refetched on next view.
        'content',
        'contentformat',
        // Featured Lab flag. Not carried, so a restored copy does not compete with the original
        // for featured status.
        'isfeatured',
    ];

    /**
     * Every topomojo column that backup does carry, set to a value distinguishable from its
     * install.xml default so a dropped column shows up as a failed assertion rather than a
     * coincidental match.
     *
     * @return array column => value
     */
    private function distinctive_settings(): array {
        return [
            'name' => 'Round-trip lab',
            'intro' => '<p>Lab intro with <em>markup</em>.</p>',
            'introformat' => FORMAT_HTML,
            'workspaceid' => 'c0ffee00-1111-2222-3333-444455556666',
            'embed' => 0,
            'clock' => 1,
            'extendevent' => 1,
            'extendinterval' => 45,
            'timeopen' => $this->startdate + DAYSECS,
            'timeclose' => $this->startdate + (2 * DAYSECS),
            'grade' => 75,
            'grademethod' => 2,
            'timecreated' => $this->startdate,
            'timemodified' => $this->startdate + HOURSECS,
            'reviewattempt' => 0x11110,
            'reviewcorrectness' => 0x10000,
            'reviewmarks' => 0x01000,
            'reviewspecificfeedback' => 0x00100,
            'reviewgeneralfeedback' => 0x00010,
            'reviewrightanswer' => 0x11000,
            'reviewoverallfeedback' => 0x00110,
            'reviewmanualcomment' => 0x10100,
            'shuffleanswers' => 1,
            'preferredbehaviour' => 'immediatefeedback',
            'duration' => 5400,
            'importchallenge' => 1,
            'endlab' => 1,
            'variant' => 3,
            'attempts' => 4,
            'submissions' => 7,
            'contentlicense' => 'CC-BY-4.0',
            'showcontentlicense' => 1,
        ];
    }

    /**
     * Creates a course holding one topomojo whose every backed-up column is set to a distinctive
     * value.
     *
     * @return array [course, topomojo record as stored]
     */
    private function create_course_with_configured_topomojo(): array {
        global $DB;

        [$course, $topomojo] = $this->create_course_and_module('topomojo');

        // Write the settings straight to the table rather than through the form: the point of these
        // tests is the backup structure, and mod_form defaults would mask a dropped column.
        $settings = $this->distinctive_settings();
        $settings['id'] = $topomojo->id;
        $DB->update_record('topomojo', (object)$settings);

        return [$course, $DB->get_record('topomojo', ['id' => $topomojo->id], '*', MUST_EXIST)];
    }

    /**
     * Backs a course up and extracts it, leaving the backup available for inspection or tampering
     * before it is restored.
     *
     * restore_date_testcase::backup_and_restore() does both halves in one call and throws the
     * extracted directory away, which these tests need.
     *
     * @param \stdClass $course course to back up
     * @param string $backupid directory name under $CFG->dataroot/temp/backup
     * @return string absolute path to the extracted backup
     */
    private function backup_course_to_path(\stdClass $course, string $backupid): string {
        global $CFG, $USER;

        // Turn off file logging, otherwise the backup file cannot be deleted afterwards.
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        // Ask for user data, so a backup that comes out without any proves the activity has no user
        // data step rather than proving the setting was off.
        set_config('backup_general_users', 1, 'backup');

        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id);
        $this->assertEquals(1, $bc->get_plan()->get_setting('users')->get_value(),
            'Precondition: the backup was asked to include user data.');
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $path = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);
        $bc->destroy();

        return $path;
    }

    /**
     * Restores a previously extracted backup into a brand new course.
     *
     * @param \stdClass $course the course the backup was taken from, used for naming
     * @param string $backupid directory name passed to backup_course_to_path()
     * @return int id of the new course
     */
    private function restore_to_new_course(\stdClass $course, string $backupid): int {
        global $USER;

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname, $course->shortname . '_restored', $course->category);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $USER->id, \backup::TARGET_NEW_COURSE);
        $rc->get_plan()->get_setting('course_startdate')->set_value($this->restorestartdate);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Reads the single topomojo activity out of an extracted backup.
     *
     * @param string $path path returned by backup_course_to_path()
     * @return \SimpleXMLElement the <topomojo> element
     */
    private function load_activity_xml(string $path): \SimpleXMLElement {
        $files = glob($path . '/activities/topomojo_*/topomojo.xml');
        $this->assertCount(1, $files, 'Expected exactly one topomojo activity in the backup.');

        $xml = simplexml_load_file($files[0]);
        $this->assertNotFalse($xml, 'topomojo.xml is not parseable.');

        $topomojo = $xml->xpath('//topomojo');
        $this->assertCount(1, $topomojo, 'topomojo.xml has no <topomojo> element.');

        return $topomojo[0];
    }

    /**
     * Names of the elements directly under <topomojo> in an extracted backup.
     *
     * @param string $path path returned by backup_course_to_path()
     * @return string[]
     */
    private function activity_child_names(string $path): array {
        $names = [];
        foreach ($this->load_activity_xml($path)->children() as $child) {
            $names[] = $child->getName();
        }

        return $names;
    }

    /**
     * Column names of the topomojo table as declared in db/install.xml.
     *
     * @return string[]
     */
    private function install_xml_columns(): array {
        global $CFG;

        $xml = simplexml_load_file($CFG->dirroot . '/mod/topomojo/db/install.xml');
        $this->assertNotFalse($xml, 'db/install.xml is not parseable.');

        $columns = [];
        foreach ($xml->xpath('//TABLE[@NAME="topomojo"]/FIELDS/FIELD') as $field) {
            $columns[] = (string)$field['NAME'];
        }
        $this->assertNotEmpty($columns, 'No columns found for the topomojo table in db/install.xml.');

        return $columns;
    }

    /**
     * Discards the debugging() calls the restore steps emit at DEBUG_DEVELOPER.
     *
     * restore_topomojo_activity_structure_step traces its own progress with debugging(), and
     * advanced_testcase fails a test that leaves debugging messages unasserted. The exact number
     * varies with the number of questions, so the messages are discarded rather than counted -
     * they are the plugin's own tracing, not a signal about the behaviour under test.
     */
    private function discard_restore_tracing(): void {
        $this->resetDebugging();
    }

    /**
     * Every column of the topomojo table is either in the backup structure or on the
     * deliberately-excluded list.
     *
     * This is the regression guard: a column added to db/install.xml and wired into mod_form but
     * forgotten in backup_topomojo_stepslib.php restores as its default with no error raised
     * anywhere, so nothing but a test like this catches it.
     */
    public function test_backup_xml_covers_every_activity_column(): void {
        [$course] = $this->create_course_with_configured_topomojo();

        $path = $this->backup_course_to_path($course, 'topomojo-coverage');

        $expected = array_values(array_diff($this->install_xml_columns(), self::NOT_BACKED_UP));
        $missing = array_diff($expected, $this->activity_child_names($path));

        $this->assertSame([], array_values($missing),
            'Columns in db/install.xml are absent from the backup structure in '
            . 'backup/moodle2/backup_topomojo_stepslib.php. Either add them to the backup element '
            . 'or add them to backup_restore_test::NOT_BACKED_UP with a reason.');
    }

    /**
     * Activity settings survive a round trip with their values intact.
     */
    public function test_activity_settings_survive_round_trip(): void {
        global $DB;

        [$course, $original] = $this->create_course_with_configured_topomojo();

        $newcourseid = $this->backup_and_restore($course);
        $this->discard_restore_tracing();

        $restored = $DB->get_record('topomojo', ['course' => $newcourseid], '*', MUST_EXIST);

        // timeopen and timeclose are the only settings process_topomojo() rewrites, shifting them by
        // the difference between the old and new course start dates.
        $this->assertFieldsRolledForward($original, $restored, ['timeopen', 'timeclose']);

        // questionorder is rebuilt from the restored question links, so it is covered by
        // test_question_links_survive_round_trip() rather than compared here.
        $carried = array_diff(
            array_keys($this->distinctive_settings()),
            ['timeopen', 'timeclose', 'questionorder']
        );
        $this->assertFieldsNotRolledForward($original, $restored, array_values($carried));
    }

    /**
     * The columns on the excluded list come back as their defaults, not as the source values.
     *
     * Locks in the intent behind backup_restore_test::NOT_BACKED_UP: the cached workspace markdown is
     * dropped so the next view refetches it, and the Featured Lab flag is dropped so the copy does
     * not claim featured status from the original.
     */
    public function test_cached_content_and_featured_flag_are_not_carried(): void {
        global $DB;

        [$course, $original] = $this->create_course_with_configured_topomojo();
        $DB->update_record('topomojo', (object)[
            'id' => $original->id,
            'content' => '# Cached markdown from TopoMojo',
            'contentformat' => FORMAT_MARKDOWN,
            'isfeatured' => 1,
        ]);

        $newcourseid = $this->backup_and_restore($course);
        $this->discard_restore_tracing();

        $restored = $DB->get_record('topomojo', ['course' => $newcourseid], '*', MUST_EXIST);

        $this->assertNull($restored->content, 'Cached workspace markdown should not be carried.');
        $this->assertEquals(0, $restored->contentformat);
        $this->assertEquals(0, $restored->isfeatured, 'A restored copy should not be the Featured Lab.');
    }

    /**
     * Question links survive a round trip, pointing at the restored questions and with
     * questionorder rebuilt to match.
     */
    public function test_question_links_survive_round_trip(): void {
        global $DB;

        [$course, $topomojo] = $this->create_course_with_configured_topomojo();
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id, $course->id, false, MUST_EXIST);

        // Questions live in a question bank activity in the same course so that backing the course
        // up carries them along with the topomojo.
        $generator = $this->getDataGenerator();
        $qbank = $generator->get_plugin_generator('mod_qbank')->create_instance(['course' => $course->id]);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(
            ['contextid' => \context_module::instance($qbank->cmid)->id]);

        $names = ['First question', 'Second question', 'Third question'];
        $questionmanager = $this->make_questionmanager($topomojo, $cm);
        foreach ($names as $name) {
            $question = $questiongenerator->create_question('shortanswer', null,
                ['category' => $category->id, 'name' => $name]);
            $this->assertTrue($questionmanager->add_question($question->id));
        }
        // add_question() traces every insert with debugging(); that is not what is under test here.
        $this->resetDebugging();

        $expectednames = $this->question_names_in_order($topomojo->id);
        $this->assertSame($names, $expectednames, 'Precondition: questions are linked in order.');

        $newcourseid = $this->backup_and_restore($course);
        $this->discard_restore_tracing();

        $restored = $DB->get_record('topomojo', ['course' => $newcourseid], '*', MUST_EXIST);

        // The links are recreated against the restored copies of the questions, in the same order.
        $this->assertSame($names, $this->question_names_in_order($restored->id));

        // questionorder is rebuilt from the new topomojo_questions ids, so it must not still be
        // pointing at the source course's ids.
        $restoredlinkids = array_keys($DB->get_records('topomojo_questions',
            ['topomojoid' => $restored->id], 'id'));
        $this->assertSame($restoredlinkids, array_map('intval', explode(',', $restored->questionorder)));

        // Each link has a question_reference in the restored activity's own context.
        $restoredcm = get_coursemodule_from_instance('topomojo', $restored->id, $newcourseid, false, MUST_EXIST);
        $restoredcontextid = \context_module::instance($restoredcm->id)->id;
        foreach ($restoredlinkids as $linkid) {
            $this->assertTrue(
                $DB->record_exists('question_references', [
                    'usingcontextid' => $restoredcontextid,
                    'component' => 'mod_topomojo',
                    'questionarea' => 'slot',
                    'itemid' => $linkid,
                ]),
                "Restored question link $linkid has no question_reference."
            );
        }
    }

    /**
     * Files attached to the activity intro survive a round trip.
     *
     * restore_topomojo_activity_structure_step::after_execute() is the only thing that brings them
     * across, so a missing annotate_files() on the backup side shows up here.
     */
    public function test_intro_files_survive_round_trip(): void {
        global $DB;

        [$course, $topomojo] = $this->create_course_with_configured_topomojo();
        $cm = get_coursemodule_from_instance('topomojo', $topomojo->id, $course->id, false, MUST_EXIST);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_module::instance($cm->id)->id,
            'component' => 'mod_topomojo',
            'filearea' => 'intro',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'diagram.txt',
        ], 'network diagram');

        $newcourseid = $this->backup_and_restore($course);
        $this->discard_restore_tracing();

        $restored = $DB->get_record('topomojo', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredcm = get_coursemodule_from_instance('topomojo', $restored->id, $newcourseid, false, MUST_EXIST);

        $file = $fs->get_file(\context_module::instance($restoredcm->id)->id, 'mod_topomojo',
            'intro', 0, '/', 'diagram.txt');
        $this->assertNotFalse($file, 'The intro file was not restored.');
        $this->assertSame('network diagram', $file->get_content());
    }

    /**
     * A backup with no workspace id is refused with an explanatory message rather than restoring an
     * activity that can never launch.
     *
     * workspaceid is NOT NULL in db/install.xml, so this state can only arrive from a hand-edited or
     * damaged backup - which is exactly the case the check in process_topomojo() is there for.
     */
    public function test_restore_without_workspaceid_is_refused(): void {
        [$course] = $this->create_course_with_configured_topomojo();

        $backupid = 'topomojo-no-workspace';
        $path = $this->backup_course_to_path($course, $backupid);

        $files = glob($path . '/activities/topomojo_*/topomojo.xml');
        $this->assertCount(1, $files);
        $xml = file_get_contents($files[0]);
        $stripped = preg_replace('#<workspaceid>.*?</workspaceid>#s', '<workspaceid></workspaceid>', $xml, 1);
        $this->assertNotSame($xml, $stripped, 'Precondition: the backup contains a workspaceid.');
        file_put_contents($files[0], $stripped);

        try {
            $this->restore_to_new_course($course, $backupid);
            $this->fail('Expected a restore_step_exception for the missing workspace id.');
        } catch (\restore_step_exception $e) {
            $this->assertMatchesRegularExpression('/workspace/i', $e->getMessage());
        } finally {
            $this->discard_restore_tracing();
            // A restore that throws mid-plan never reaches its own cleanup, and the backup_ids_temp
            // table it leaves behind makes the database complain when the process tears down.
            \restore_controller_dbops::drop_restore_temp_tables($backupid);
        }
    }

    /**
     * Attempts and grades are not carried through backup.
     *
     * The backup structure covers activity settings and question links only - there is no user data
     * step and no userinfo setting - so a restored activity always starts with no student history,
     * including when "include user data" is on. Recorded here so the omission is a decision on the
     * record rather than something rediscovered from a support ticket.
     */
    public function test_attempts_and_grades_are_not_carried(): void {
        global $DB;

        [$course, $topomojo] = $this->create_course_with_configured_topomojo();

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');
        $generator->create_attempt($topomojo, $student, ['eventid' => 'gamespace-must-not-travel']);
        $generator->create_grade($topomojo, $student, 55.0);

        // backup_course_to_path() asks for user data, so the absence of the attempt from the activity
        // XML is the backup structure's doing, not a setting that happened to be off.
        $path = $this->backup_course_to_path($course, 'topomojo-user-data');

        // Nothing beyond the settings and the question links: no per-user collection at all. Note
        // that <attempts> and <grade> are settings, which is why this checks element names rather
        // than searching the XML for those words.
        $allowed = array_merge(array_keys($this->distinctive_settings()),
            ['questionorder', 'question_instances']);
        $this->assertSame([], array_values(array_diff($this->activity_child_names($path), $allowed)),
            'The activity XML carries an element that is neither a setting nor a question link.');

        // And the values themselves are absent, wherever they might have been nested.
        $activityxml = file_get_contents(glob($path . '/activities/topomojo_*/topomojo.xml')[0]);
        $this->assertStringNotContainsString('gamespace-must-not-travel', $activityxml);
        $this->assertStringNotContainsString('55.00', $activityxml);

        $newcourseid = $this->restore_to_new_course($course, 'topomojo-user-data');
        $this->discard_restore_tracing();

        $restored = $DB->get_record('topomojo', ['course' => $newcourseid], '*', MUST_EXIST);

        $this->assertSame(0, $DB->count_records('topomojo_attempts', ['topomojoid' => $restored->id]));
        $this->assertSame(0, $DB->count_records('topomojo_grades', ['topomojoid' => $restored->id]));

        // The source course keeps its own records.
        $this->assertSame(1, $DB->count_records('topomojo_attempts', ['topomojoid' => $topomojo->id]));
        $this->assertSame(1, $DB->count_records('topomojo_grades', ['topomojoid' => $topomojo->id]));
    }

    /**
     * Builds a questionmanager for a topomojo record without going through the topomojo class,
     * which would call setup() and hit the TopoMojo API.
     *
     * @param \stdClass $topomojo the activity record
     * @param \stdClass $cm its course module
     * @return questionmanager
     */
    private function make_questionmanager(\stdClass $topomojo, \stdClass $cm): questionmanager {
        // add_question() persists questionorder through $object->save(), so the stand-in needs that
        // method with the same body as \mod_topomojo\topomojo::save().
        $object = new class {
            /** @var \stdClass the activity record */
            public $topomojo;

            /** @var \stdClass its course module */
            public $cm;

            /**
             * Persists the activity record, as \mod_topomojo\topomojo::save() does.
             *
             * @return bool
             */
            public function save() {
                global $DB;

                return $DB->update_record('topomojo', $this->topomojo);
            }
        };
        $object->topomojo = $topomojo;
        $object->cm = $cm;
        $pagevars = ['pageurl' => new \moodle_url('/mod/topomojo/view.php', ['id' => $cm->id])];

        return new questionmanager($object, null, $pagevars);
    }

    /**
     * Names of the questions linked to an activity, in questionorder sequence.
     *
     * @param int $topomojoid activity id
     * @return string[]
     */
    private function question_names_in_order(int $topomojoid): array {
        global $DB;

        $questionorder = $DB->get_field('topomojo', 'questionorder', ['id' => $topomojoid], MUST_EXIST);
        if (empty($questionorder)) {
            return [];
        }

        $names = [];
        foreach (explode(',', $questionorder) as $linkid) {
            $questionid = $DB->get_field('topomojo_questions', 'questionid', ['id' => $linkid]);
            $this->assertNotFalse($questionid, "questionorder points at missing link $linkid.");
            $names[] = $DB->get_field('question', 'name', ['id' => $questionid], MUST_EXIST);
        }

        return $names;
    }
}
