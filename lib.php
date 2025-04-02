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
 * TopoMojo mod callbacks.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

use mod_topomojo\question\qubaids_for_topomojo;

/**
 * List of features supported in topomojo module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function topomojo_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}
/**
 * Returns all other caps used in module
 * @return array
 */
function topomojo_get_extra_capabilities() {
    return ['moodle/site:accessallgroups'];
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function topomojo_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return [];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function topomojo_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Add topomojo instance.
 * @param object $topomojo
 * @param object $mform
 * @return int new topomojo instance id
 */
function topomojo_add_instance($topomojo, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/topomojo/locallib.php');

    $cmid = $topomojo->coursemodule;

    $result = topomojo_process_options($topomojo);
    if ($result && is_string($result)) {
        return $result;
    }

    $topomojo->created = time();
    $topomojo->grade = 100; // Default
    $topomojo->id = $DB->insert_record('topomojo', $topomojo);

    // Do the processing required after an add or an update.
    topomojo_after_add_or_update($topomojo);

    return $topomojo->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * Update topomojo instance.
 * @param object $topomojo
 * @param object $mform
 * @return bool true
 */
function topomojo_update_instance(stdClass $topomojo, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

    // Process the options from the form.
    $result = topomojo_process_options($topomojo);
    if ($result && is_string($result)) {
        return $result;
    }
    // Get the current value, so we can see what changed.
    //$oldtopomojo = $DB->get_record('topomojo', array('id' => $topomojo->instance));

    // Update the database.
    $topomojo->id = $topomojo->instance;
    $DB->update_record('topomojo', $topomojo);

    // Do the processing required after an add or an update.
    topomojo_after_add_or_update($topomojo);

    // Do the processing required after an add or an update.
    return true;

}

/**
 * This function is called at the end of quiz_add_instance
 * and quiz_update_instance, to do the common processing.
 *
 * @param object $quiz the quiz object.
 */
function topomojo_after_add_or_update($topomojo) {
    global $DB;
    $cmid = $topomojo->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $topomojo->id, ['id' => $cmid]);
    $context = context_module::instance($cmid);

    // Update related grade item.
    topomojo_grade_item_update($topomojo);
}

/**
 * Processes and updates options for the TopoMojo module.
 *
 * This function takes a TopoMojo object, updates its properties based on the form input,
 * and sets various review options by calling helper functions.
 *
 * @param stdClass $topomojo The TopoMojo object to be updated. This object represents
 *                           the current instance of the TopoMojo module with various
 *                           settings and options.
 *
 * @return void
 */
function topomojo_process_options($topomojo) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/mod/topomojo/locallib.php');
    $topomojo->timemodified = time();
    // Combing the individual settings into the review columns.
    $topomojo->reviewattempt = topomojo_review_option_form_to_db($topomojo, 'attempt');
    $topomojo->reviewcorrectness = topomojo_review_option_form_to_db($topomojo, 'correctness');
    $topomojo->reviewmarks = topomojo_review_option_form_to_db($topomojo, 'marks');
    $topomojo->reviewspecificfeedback = topomojo_review_option_form_to_db($topomojo, 'specificfeedback');
    $topomojo->reviewgeneralfeedback = topomojo_review_option_form_to_db($topomojo, 'generalfeedback');
    $topomojo->reviewrightanswer = topomojo_review_option_form_to_db($topomojo, 'rightanswer');
    $topomojo->reviewoverallfeedback = topomojo_review_option_form_to_db($topomojo, 'overallfeedback');
    $topomojo->reviewattempt |= mod_topomojo_display_options::DURING;
    $topomojo->reviewoverallfeedback &= ~mod_topomojo_display_options::DURING;
    $topomojo->reviewmanualcomment = topomojo_review_option_form_to_db($topomojo, 'manualcomment');
}

/**
 * Helper function for {@link topomojo_process_options()}.
 * @param object $fromform the sumbitted form date.
 * @param string $field one of the review option field names.
 */
function topomojo_review_option_form_to_db($fromform, $field) {
    static $times = [
        'during' => mod_topomojo_display_options::DURING,
        'immediately' => mod_topomojo_display_options::IMMEDIATELY_AFTER,
        'open' => mod_topomojo_display_options::LATER_WHILE_OPEN,
        'closed' => mod_topomojo_display_options::AFTER_CLOSE,
    ];

    $review = 0;
    foreach ($times as $whenname => $when) {
        $fieldname = $field . $whenname;
        if (isset($fromform->$fieldname)) {
            $review |= $when;
            unset($fromform->$fieldname);
        }
    }

    return $review;
}

/**
 * Delete topomojo instance.
 * @param int $id
 * @return bool true
 */
function topomojo_delete_instance($id) {
    global $DB;
    $topomojo = $DB->get_record('topomojo', ['id' => $id], '*', MUST_EXIST);

    // Delete all attempts
    topomojo_delete_all_attempts($topomojo);

    topomojo_delete_references($topomojo->id);

    $DB->delete_records('topomojo_questions', ['topomojoid' => $topomojo->id]);

    // Delete calander events
    $events = $DB->get_records('event', ['modulename' => 'topomojo', 'instance' => $topomojo->id]);
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // Delete grade from database
    topomojo_grade_item_delete($topomojo);

    // We must delete the module record after we delete the grade item.
    $DB->delete_records('topomojo', ['id' => $topomojo->id]);

    return true;
}

/**
 * Delete all question references for a topomojo.
 *
 * @param int $topomojoid The id of topomojo.
 */
function topomojo_delete_references($topomojoid): void {
    global $DB;

    $cm = get_coursemodule_from_instance('topomojo', $topomojoid);
    $context = context_module::instance($cm->id);

    debugging("topomojo_delete_references usingcontextid $context->id", DEBUG_DEVELOPER);

    $conditions = [
        'usingcontextid' => $context->id,
        'component' => 'mod_topomojo',
        'questionarea' => 'slot',
    ];

    $DB->delete_records('question_references', $conditions);
    $DB->delete_records('question_set_references', $conditions);
}

/**
 * Delete all the attempts belonging to a topomojo.
 *
 * @param stdClass $topomojo The topomojo object.
 */
function topomojo_delete_all_attempts($topomojo) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/topomojo/locallib.php');
    question_engine::delete_questions_usage_by_activities(new qubaids_for_topomojo($topomojo->id));
    $DB->delete_records('topomojo_attempts', ['topomojoid' => $topomojo->id]);
    $DB->delete_records('topomojo_grades', ['topomojoid' => $topomojo->id]);
}

/**
 * Standard callback used by questions_in_use.
 *
 * @param array $questionids of question ids.
 * @return bool whether any of these questions are used by any instance of this module.
 */
function topomojo_questions_in_use($questionids) {
    return question_engine::questions_in_use($questionids,
            new qubaid_join('{topomojo_attempts} topomojoa', 'topomojoa.questionusageid'));
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param object $coursemodule
 * @return cached_cm_info info
 */
function topomojo_get_coursemodule_info($coursemodule) {
    global $DB;

    // Fetch the topomojo instance from the database.
    $dbparams = array('id' => $coursemodule->instance);
    $fields = 'id, name, intro, introformat';
    if (! $topomojo = $DB->get_record('topomojo', $dbparams, $fields)) {
        return false; // Return false if the record cannot be found.
    }

    $result = new cached_cm_info();
    $result->name = $topomojo->name; // Set the module name.

    // Check if we need to display the description.
    if ($coursemodule->showdescription) {
        // Format the intro (description) and add it to the result.
        $result->content = format_module_intro('topomojo', $topomojo, $coursemodule->id, false);
    }

    return $result;
}

/**
 * Handles the viewing of the TopoMojo activity.
 *
 * This function triggers the `course_module_viewed` event and marks the activity as completed
 * if required. It updates the completion status and records the event data for the TopoMojo
 * activity, course, and course module.
 *
 * @param stdClass $topomojo The TopoMojo object representing the activity being viewed.
 * @param stdClass $course   The course object containing information about the course.
 * @param stdClass $cm       The course module object representing the module instance.
 * @param stdClass $context  The context object representing the current context.
 *
 * @return void
 *
 */
function topomojo_view($topomojo, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $topomojo->id,
    ];

    $event = \mod_topomojo\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('topomojo', $topomojo);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function topomojo_check_updates_since(cm_info $cm, $from, $filter = []) {
    $updates = course_check_module_updates_since($cm, $from, ['content'], $filter);
    return $updates;
}
/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_topomojo_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['topomojo'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_topomojo('/mod/topomojo/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $topomojo the topomojo settings.
 * @param int $userid specific user only, 0 means all users.
 * @param bool $nullifnone If a single user is specified and $nullifnone is true a grade item with a null rawgrade will be inserted
 */
function topomojo_update_grades($topomojo, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    if ($topomojo->grade == 0) {
        topomojo_grade_item_update($topomojo);

    } else if ($grades = topomojo_get_user_grades($topomojo, $userid)) {

        $status = topomojo_grade_item_update($topomojo, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        topomojo_grade_item_update($topomojo, $grade);
    } else {
        topomojo_grade_item_update($topomojo);
    }
}

/**
 * Create or update the grade item for given lab
 *
 * @category grade
 * @param object $topomojo object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function topomojo_grade_item_update($topomojo, $grades = null) {
    global $CFG, $OUTPUT;
    require_once($CFG->libdir . '/gradelib.php');

    if (property_exists($topomojo, 'cmidnumber')) { // May not be always present.
        $params = ['itemname' => $topomojo->name, 'idnumber' => $topomojo->cmidnumber];
    } else {
        $params = ['itemname' => $topomojo->name];
    }
    if ($topomojo->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $topomojo->grade;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/topomojo', $topomojo->course, 'mod', 'topomojo', $topomojo->id, 0, $grades, $params);
}


/**
 * Delete grade item for given lab
 *
 * @category grade
 * @param object $topomojo object
 * @return object topomojo
 */
function topomojo_grade_item_delete($topomojo) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/topomojo', $topomojo->course, 'mod', 'topomojo', $topomojo->id, 0,
            null, ['deleted' => 1]);
}

/**
 * Return grade for given user or all users.
 *
 * @param int $topomojo id of topomojo
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none. These are raw grades.
 */
function topomojo_get_user_grades($topomojo, $userid = 0) {
    global $CFG, $DB;

    $params = [$topomojo->id];
    $usertest = '';
    if ($userid) {
        $params[] = $userid;
        $usertest = 'AND u.id = ?';
    }
    return $DB->get_records_sql("
            SELECT
                u.id,
                u.id AS userid,
                cg.grade AS rawgrade,
                cg.timemodified AS dategraded,
                MAX(ca.timefinish) AS datesubmitted

            FROM {user} u
            JOIN {topomojo_grades} cg ON u.id = cg.userid
            JOIN {topomojo_attempts} ca ON ca.topomojoid = cg.topomojoid AND ca.userid = u.id

            WHERE cg.topomojoid = ?
            $usertest
            GROUP BY u.id, cg.grade, cg.timemodified", $params);
}

/**
 * Extends the settings navigation with additional links for the TopoMojo module.
 *
 * This function adds custom navigation nodes to the settings navigation for the TopoMojo module,
 * such as links to challenge, review, and edit pages. The nodes are added at specific positions
 * relative to existing nodes based on the current context.
 *
 * @param settings_navigation $settingsnav The settings navigation object to which nodes are added.
 * @param context $context The context object representing the current context for the navigation.
 *
 * @return void
 */
function topomojo_extend_settings_navigation($settingsnav, $context) {

    $keys = $context->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    $url = new moodle_url('/mod/topomojo/challenge.php', ['id' => $settingsnav->get_page()->cm->id]);
    $node = navigation_node::create(get_string('challengetext', 'mod_topomojo'),
            new moodle_url($url),
            navigation_node::TYPE_SETTING, null, 'mod_topomojo_challenge', new pix_icon('i/grades', 'grades'));
    $context->add_node($node, $beforekey);

    $url = new moodle_url('/mod/topomojo/review.php', ['id' => $settingsnav->get_page()->cm->id]);
    $node = navigation_node::create(get_string('reviewtext', 'mod_topomojo'),
            new moodle_url($url),
            navigation_node::TYPE_SETTING, null, 'mod_topomojo_review', new pix_icon('i/grades', 'grades'));
    $context->add_node($node, $beforekey);

    if (has_capability('mod/topomojo:manage', $settingsnav->get_page()->cm->context)) {
        $url = new moodle_url('/mod/topomojo/edit.php', ['cmid' => $settingsnav->get_page()->cm->id]);
        $node = navigation_node::create(get_string('questions', 'mod_topomojo'),
                new moodle_url($url),
                navigation_node::TYPE_SETTING, null, 'mod_topomojo_edit', new pix_icon('i/edit', ''));
        $context->add_node($node, $beforekey);
    }
}
