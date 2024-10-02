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

defined('MOODLE_INTERNAL') || die;

/**
 * topomojo module upgrade code
 *
 * This file keeps track of upgrades to
 * the resource module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package    mod_topomojo
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_topomojo_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2022070602) {

        // Rename field eventtemplateid on table topomojo to gamespaceid.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('eventtemplateid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'intro');

        // Launch rename field gamespaceid.
        $dbman->rename_field($table, $field, 'gamespaceid');

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022070602, 'topomojo');
    }

    if ($oldversion < 2022070603) {

        // Rename field gamespaceid on table topomojo to workspaceid.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('gamespaceid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'intro');

        // Launch rename field workspaceid.
        $dbman->rename_field($table, $field, 'workspaceid');

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022070603, 'topomojo');
    }

    if ($oldversion < 2022070700) {

        // Rename field scenarioid on table topomojo_attempts to workspaceid.
        $table = new xmldb_table('topomojo_attempts');
        $field = new xmldb_field('scenarioid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'intro');

        // Launch rename field workspaceid.
        $dbman->rename_field($table, $field, 'workspaceid');

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022070700, 'topomojo');
    }

    if ($oldversion < 2022070701) {

        // Define field launchpointurl to be added to topomojo_attempts.
        $table = new xmldb_table('topomojo_attempts');
        $field = new xmldb_field('launchpointurl', XMLDB_TYPE_TEXT, '255', null, null, null, null, null);

        // Conditionally launch add field launchpointurl.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022070701, 'topomojo');
    }

    if ($oldversion < 2022070702) {

        // Define field duration to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);

        // Conditionally launch add field duration.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022070702, 'topomojo');
    }

    if ($oldversion < 2022071401) {

        // Define field reviewattempt to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewattempt', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field reviewattempt.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewcorrectness to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewcorrectness', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'reviewattempt');

        // Conditionally launch add field reviewcorrectness.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewmarks to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewmarks', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'reviewcorrectness');

        // Conditionally launch add field reviewmarks.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewspecificfeedback to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewspecificfeedback', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'reviewmarks');

        // Conditionally launch add field reviewspecificfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewgeneralfeedback to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewgeneralfeedback', XMLDB_TYPE_INTEGER, '6',
                 null, XMLDB_NOTNULL, null, '0', 'reviewspecificfeedback');

        // Conditionally launch add field reviewgeneralfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewrightanswer to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewrightanswer', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL,
                 null, '0', 'reviewgeneralfeedback');

        // Conditionally launch add field reviewrightanswer.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewoverallfeedback to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewoverallfeedback', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL,
                 null, '0', 'reviewrightanswer');

        // Conditionally launch add field reviewoverallfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewmanualcomment to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('reviewmanualcomment', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL,
                 null, '0', 'reviewoverallfeedback');

        // Conditionally launch add field reviewmanualcomment.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field preferredbehaviour to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('preferredbehaviour', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, 'shuffleanswers');

        // Conditionally launch add field preferredbehaviour.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022071401, 'topomojo');
    }

    if ($oldversion < 2022071403) {

        // Rename field vmapp on table topomojo to embed.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('vmapp', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'workspaceid');

        // Launch rename field vmapp.
        $dbman->rename_field($table, $field, 'embed');

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022071403, 'topomojo');
    }

    if ($oldversion < 2022071805) {

        // Define field layout to be added to topomojo_attempts.
        $table = new xmldb_table('topomojo_attempts');
        $field = new xmldb_field('layout', XMLDB_TYPE_TEXT, '255', null, null, null, null, 'launchpointurl');

        // Conditionally launch add field layout.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022071805, 'topomojo');
    }

    if ($oldversion < 2022071901) {

        // Define table topomojo_tasks to be dropped.
        $table = new xmldb_table('topomojo_tasks');

        // Conditionally launch drop table for topomojo_tasks.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        // Define table topomojo_tasks_results to be dropped.
        $table = new xmldb_table('topomojo_tasks_results');

        // Conditionally launch drop table for topomojo_tasks_results.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Rename field quesntionusageid on table topomojo_attempts to questionusageid.
        $table = new xmldb_table('topomojo_attempts');
        $field = new xmldb_field('quesntionusageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'layout');

        // Launch rename field uniqueid.
        $dbman->rename_field($table, $field, 'questionusageid');

        // Define field sumgrades to be dropped from topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('sumgrades');

        // Conditionally launch drop field sumgrades.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Rename field layout on table topomojo to questionorder.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('layout', XMLDB_TYPE_TEXT, '255', null, null, null, null, 'reviewmanualcomment');

        // Launch rename field layout.
        $dbman->rename_field($table, $field, 'questionorder');
        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022071901, 'topomojo');
    }

    if ($oldversion < 2022072000) {

        // Define table topomojo_questions to be created.
        $table = new xmldb_table('topomojo_questions');

        // Adding fields to table topomojo_questions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('topomojoid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('points', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table topomojo_questions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('topomojoid', XMLDB_KEY_FOREIGN, ['id'], 'quiz', ['id']);
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);

        // Conditionally launch create table for topomojo_questions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022072000, 'topomojo');
    }
    if ($oldversion < 2022072001) {

        // Define field duration to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'preferredbehaviour');

        // Conditionally launch add field duration.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022072001, 'topomojo');
    }
    if ($oldversion < 2022072002) {

        // Rename field quesntionusageid on table topomojo_attempts to questionusageid.
        $table = new xmldb_table('topomojo_attempts');
        $field = new xmldb_field('quesntionusageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'layout');

        // Launch rename field quesntionusageid.
        $dbman->rename_field($table, $field, 'questionusageid');

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022072002, 'topomojo');
    }
    if ($oldversion < 2022072003) {

        // Define field duration to be added to topomojo_grades.
        $table = new xmldb_table('topomojo_grades');
        $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'topomojoid');

        // Conditionally launch add field duration.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022072003, 'topomojo');
    }
    if ($oldversion < 2022072004) {

        // Define field duration to be dropped from topomojo_grades.
        $table = new xmldb_table('topomojo_grades');
        $field = new xmldb_field('duration');

        // Conditionally launch drop field duration.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022072004, 'topomojo');
    }
    if ($oldversion < 2022072100) {

        // Define field tasks to be dropped from topomojo_attempts.
        $table = new xmldb_table('topomojo_attempts');
        $field = new xmldb_field('tasks');

        // Conditionally launch drop field tasks.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field importchallenge to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('importchallenge', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'duration');

        // Conditionally launch add field importchallenge.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022072100, 'topomojo');
    }
    if ($oldversion < 2022072101) {

        // Define field variant to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('variant', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'importchallenge');

        // Conditionally launch add field variant.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field variant to be added to topomojo_attempts.
        $table = new xmldb_table('topomojo_attempts');
        $field = new xmldb_field('variant', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'questionusageid');

        // Conditionally launch add field variant.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field endlab to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('endlab', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'duration');

        // Conditionally launch add field endlab.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2022072101, 'topomojo');
    }
    if ($oldversion < 2024070304) {
        // Define field endlab to be added to topomojo.
        $table = new xmldb_table('topomojo');
        $field = new xmldb_field('endlab', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'duration');

        // Conditionally launch add field endlab.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2024070304, 'topomojo');
    }
    if ($oldversion < 2024092201) {

        // Define the table topomojo_questions to update.
        $table = new xmldb_table('topomojo_questions');
    
        // Define the new index for the 'topomojoid' field.
        $index = new xmldb_index('topomojoid_ix', XMLDB_INDEX_NOTUNIQUE, ['topomojoid']);
    
        // Conditionally add the index if it does not exist.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    
        // Topomojo savepoint reached.
        upgrade_mod_savepoint(true, 2024092201, 'topomojo');
    }
    

    return true;
}

