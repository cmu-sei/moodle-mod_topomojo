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
 * topomojo module main user interface
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_topomojo\topomojo;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/mod/topomojo/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

global $USER;

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$c = optional_param('c', 0, PARAM_INT);  // Instance ID - it should be named as the first character of the module.
$attemptid = optional_param('attemptid', 0, PARAM_INT);

try {
    if ($id) {
        $cm         = get_coursemodule_from_id('topomojo', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $topomojo   = $DB->get_record('topomojo', ['id' => $cm->instance], '*', MUST_EXIST);
    } else if ($c) {
        $topomojo   = $DB->get_record('topomojo', ['id' => $c], '*', MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $topomojo->course], '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('topomojo', $topomojo->id, $course->id, false, MUST_EXIST);
    }
} catch (Exception $e) {
    throw new moodle_exception("invalid course module id passed");
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/topomojo:view', $context);

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.a
    // TODO make a topomojo challenge view
    topomojo_view($topomojo, $course, $cm, $context);
}

// Print the page header.
$previewparam = optional_param('preview', 0, PARAM_INT);
$url = new moodle_url('/mod/topomojo/challenge.php', ['id' => $cm->id, 'preview' => $previewparam]);
$returnurl = new moodle_url('/mod/topomojo/view.php', ['id' => $cm->id, 'preview' => $previewparam]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($topomojo->name));
$PAGE->set_heading($course->fullname);

// New topomojo class
$pageurl = $url;
$pagevars = [];
$pagevars['pageurl'] = $pageurl;
$object = new \mod_topomojo\topomojo($cm, $course, $topomojo, $pageurl, $pagevars);

// Initialize event as null - will be set from attempt records if user has active gamespace
// CRITICAL: Do NOT use list_events() fallback here as it returns ALL gamespaces for this workspace,
// causing cross-contamination when multiple activities share the same workspace.
// Each activity must only see gamespaces created from its own attempts.
$object->event = null;

// Check if this is a preview attempt from URL parameter
$ispreview = $previewparam;
$isinstructor = has_capability('mod/topomojo:manage', $context);

// If instructor and no preview param in URL, check for existing attempts to detect preview mode
if ($isinstructor && $ispreview == 0 && $object->event && isset($object->event->id)) {
    // Check for open attempt to see if it's a preview attempt
    $openattempt = $DB->get_record_sql(
        "SELECT preview FROM {topomojo_attempts}
         WHERE topomojoid = :topomojoid
         AND userid = :userid
         AND eventid = :eventid
         AND state = :state
         LIMIT 1",
        [
            'topomojoid' => $topomojo->id,
            'userid' => $USER->id,
            'eventid' => $object->event->id,
            'state' => \mod_topomojo\topomojo_attempt::INPROGRESS
        ]
    );

    if ($openattempt) {
        $ispreview = $openattempt->preview;
        // Update URLs to include preview parameter
        $url = new moodle_url('/mod/topomojo/challenge.php', ['id' => $cm->id, 'preview' => $ispreview]);
        $returnurl = new moodle_url('/mod/topomojo/view.php', ['id' => $cm->id, 'preview' => $ispreview]);
    }
}

// Get active attempt for user
$activeattempt = $object->get_open_attempt($ispreview);
if ($activeattempt == true) {
    debugging("get_open_attempt returned attemptid " . $object->openAttempt->id, DEBUG_DEVELOPER);

    // If active attempt found but event not fetched yet, fetch it
    if (empty($object->event) && isset($object->openAttempt)) {
        $attemptdata = $object->openAttempt->get_attempt();
        if (!empty($attemptdata->eventid)) {
            try {
                $object->event = get_event($object->userauth, $attemptdata->eventid);
            } catch (Exception $e) {
                // Gamespace no longer exists (404, 400, etc) - clear the stale eventid
                debugging("Gamespace {$attemptdata->eventid} not found, clearing event", DEBUG_DEVELOPER);
                $object->openAttempt->eventid = null;
                $object->openAttempt->save();
                $object->event = null;
            }
        }
    }
} else if ($activeattempt == false) {
    debugging("get_open_attempt returned false - checking for exploration mode", DEBUG_DEVELOPER);

    // Check for exploration mode: FINISHED attempt with active gamespace
    $finished_attempts = $object->getall_attempts('closed', false, $ispreview);
    debugging("Found " . count($finished_attempts) . " finished attempts", DEBUG_DEVELOPER);

    if (!empty($finished_attempts)) {
        // Get most recent finished attempt
        $latest_finished = reset($finished_attempts);
        $attempt_data = $latest_finished->get_attempt(); // Get the raw DB record
        debugging("Checking finished attempt {$attempt_data->id}, eventid: " . ($attempt_data->eventid ?? 'null'), DEBUG_DEVELOPER);

        // Check if it has an active gamespace
        if (!empty($attempt_data->eventid)) {
            try {
                $event = get_event($object->userauth, $attempt_data->eventid);
                debugging("Got event, isActive: " . ($event->isActive ? 'true' : 'false'), DEBUG_DEVELOPER);

                if ($event && $event->isActive) {
                    debugging("Found exploration mode: finished attempt {$attempt_data->id} with active gamespace", DEBUG_DEVELOPER);
                    $object->openAttempt = $latest_finished;
                    $object->event = $event;
                    $object->exploration_mode = true;
                    $activeattempt = true; // Set to true so we render the quiz
                }
            } catch (Exception $e) {
                debugging("Exception checking gamespace {$attempt_data->eventid}: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
        } else {
            debugging("Latest finished attempt has no eventid", DEBUG_DEVELOPER);
        }
    }

    // If still no attempt (not exploration mode), redirect students
    if (!$activeattempt && !has_capability('mod/topomojo:manage', $context)) {
        redirect($returnurl);
    }
}

// Handle start/stop form action
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['start'])) {
    debugging("start request received", DEBUG_DEVELOPER);

    // Check not started already
    if (!$object->event) {
        // Check for conflicting gamespace from another activity sharing this workspace
        $conflict = check_conflicting_gamespace($topomojo->id, $topomojo->workspaceid);
        if ($conflict) {
            $conflicturl = new moodle_url('/mod/topomojo/view.php', ['id' => $conflict->cmid]);
            $conflictdata = (object)[
                'activityname' => $conflict->activityname,
                'activityurl' => $conflicturl->out()
            ];
            echo $OUTPUT->header();
            echo $OUTPUT->notification(
                get_string('conflicting_gamespace', 'mod_topomojo', $conflictdata),
                \core\output\notification::NOTIFY_ERROR
            );
            echo html_writer::link($conflicturl, get_string('conflicting_gamespace_link', 'mod_topomojo', $conflictdata), ['class' => 'btn btn-secondary']);
            echo $OUTPUT->footer();
            exit;
        }

        // TODO check for open attempt and check for status of its event
        $object->event = start_event($object->userauth, $object->topomojo->workspaceid, $object->topomojo);
        if ($object->event) {
            debugging("new event created " . $object->event->id, DEBUG_DEVELOPER);
            $activeattempt = $object->init_attempt($ispreview);
            debugging("init_attempt returned $activeattempt", DEBUG_DEVELOPER);
            if (!$activeattempt) {
                debugging("init_attempt failed");
                throw new moodle_exception('init_attempt failed');
            }
            topomojo_start($cm, $context, $topomojo);
        } else {
            debugging("start_event failed", DEBUG_DEVELOPER);
            throw new moodle_exception("start_event failed");
        }
        debugging("new event created with variant " . $object->event->variant, DEBUG_DEVELOPER);
        // Contact topomojo and pull the correct answers for this attempt
        $object->get_question_manager()->update_answers($object->openAttempt->get_quba(), $object->openAttempt->eventid);
    } else {
        debugging("event has already been started", DEBUG_DEVELOPER);
    }
} else if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['stop'])) {
    debugging("stop request received - finishing attempt", DEBUG_DEVELOPER);
    if ($object->event) {
        if ($object->event->isActive) {
            if (!$activeattempt) {
                debugging('no attempt to close', DEBUG_DEVELOPER);
                throw new moodle_exception('no attempt to close');
            }

            // Process and close the attempt
            // (Questions are already graded via Check buttons in interactive mode)
            $object->openAttempt->close_attempt();
            $grader = new \mod_topomojo\utils\grade($object);
            $grader->process_attempt($object->openAttempt);

            // Only destroy gamespace if endlab checkbox is enabled
            if ($object->topomojo->endlab) {
                debugging("endlab=1, destroying gamespace", DEBUG_DEVELOPER);
                stop_event($object->userauth, $object->event->id);
                topomojo_end($cm, $context, $topomojo);
            } else {
                debugging("endlab=0, keeping gamespace for exploration", DEBUG_DEVELOPER);
            }

            // Redirect to review page
            $viewattempturl = new moodle_url(
                '/mod/topomojo/viewattempt.php',
                ['a' => $object->openAttempt->id, 'action' => 'view']
            );
            redirect($viewattempturl);
        }
    }
}

// If there's an active attempt but no event, the gamespace was destroyed/timed out
// The attempt should stay open - user can relaunch or continue working
if ((!$object->event) && ($activeattempt)) {
    debugging("active attempt with no event - gamespace may have timed out", DEBUG_DEVELOPER);
}

$grader = new \mod_topomojo\utils\grade($object);
$gradepass = $grader->get_grade_item_passing_grade();
debugging("grade to pass is $gradepass", DEBUG_DEVELOPER);

// Show grade only if a grade is set
if ((int)$object->topomojo->grade > 0) {
    $showgrade = true;
} else {
    $showgrade = false;
}

$renderer = $object->renderer;
echo $renderer->header();

// Show preview mode warning if this is a preview attempt
if ($ispreview == 1) {
    $previewmsg = get_string('previewmode', 'mod_topomojo') . ': ' . get_string('previewmodewarning', 'mod_topomojo');
    echo $OUTPUT->notification($previewmsg, \core\output\notification::NOTIFY_INFO);
}

$action = optional_param('action', '', PARAM_ALPHA);

switch ($action) {
    case "submitquiz":
        debugging("submitquiz request received - Check button clicked", DEBUG_DEVELOPER);
        if ($activeattempt) {
            // Process the submission through question engine (handles Check button grading)
            $quba = $object->openAttempt->get_quba();
            $quba->process_all_actions(time());

            // Save the changes
            question_engine::save_questions_usage_by_activity($quba);
            $object->openAttempt->save();

            // Reload the challenge page to show feedback
            $challengeurl = new moodle_url('/mod/topomojo/challenge.php', ['id' => $cm->id]);
            redirect($challengeurl);
        }
        break;

    case "finishattempt":
        debugging("finishattempt request received - Submit Quiz clicked", DEBUG_DEVELOPER);
        require_sesskey();

        if ($activeattempt) {
            // Close the attempt (finish all questions and calculate final grade)
            $object->openAttempt->close_attempt();

            // Process grading
            $grader = new \mod_topomojo\utils\grade($object);
            $grader->process_attempt($object->openAttempt);

            debugging("Attempt {$object->openAttempt->id} closed, final grade recorded", DEBUG_DEVELOPER);

            // Handle gamespace based on endlab setting
            if ($object->topomojo->endlab && $object->event && $object->event->isActive) {
                // endlab=1: Destroy the gamespace
                debugging("endlab=1: destroying gamespace", DEBUG_DEVELOPER);
                stop_event($object->userauth, $object->event->id);
                topomojo_end($cm, $context, $topomojo);
            } else {
                debugging("endlab=0: keeping gamespace running (exploration mode)", DEBUG_DEVELOPER);
            }

            // Redirect to review page
            $reviewurl = new moodle_url('/mod/topomojo/viewattempt.php', ['a' => $object->openAttempt->id]);
            redirect($reviewurl);
        } else {
            throw new moodle_exception('noactiveattempt', 'mod_topomojo');
        }
        break;
    default:
        if ($object->openAttempt && $object->openAttempt->get_quba()) {
            if ($object->event && $object->event->id) {
                    $challenge = get_gamespace_challenge($object->userauth, $object->event->id);

                    // Get the full challenge structure to access variant text
                    $full_challenge = get_challenge($object->userauth, $object->topomojo->workspaceid);
                    $deployed_variant = $object->event->variant ?? null;

                    $combined_text = $challenge->text ?? '';

                    // Append variant-specific text if available
                    if ($full_challenge && $deployed_variant !== null && isset($full_challenge->variants[$deployed_variant]->text)) {
                        $variant_text = $full_challenge->variants[$deployed_variant]->text;
                        if (!empty($variant_text)) {
                            $combined_text .= "\n\n" . $variant_text;
                            debugging("Appended variant $deployed_variant text (" . strlen($variant_text) . " chars)", DEBUG_DEVELOPER);
                        }
                    }

                    if ($combined_text) {
                        debugging("Combined text length: " . strlen($combined_text) . " chars", DEBUG_DEVELOPER);
                        $renderer->render_challenge_instructions($combined_text);
                    }
            }

            // Check if this is a finished attempt in exploration mode
            $is_finished = $object->openAttempt && $object->openAttempt->state == 'finished';

            // TODO: Show current score for interactive mode (Check buttons)
            // Temporarily disabled due to lang string cache issue
            /*
            if (!$is_finished && $object->openAttempt && $object->topomojo->preferredbehaviour === 'interactive') {
                $quba = $object->openAttempt->get_quba();
                $total_mark = 0;
                $max_mark = 0;
                $has_graded_questions = false;

                foreach ($quba->get_slots() as $slot) {
                    $qa = $quba->get_question_attempt($slot);
                    $max_mark += $qa->get_max_mark();
                    $fraction = $qa->get_fraction();
                    if ($fraction !== null) {
                        $total_mark += $qa->get_mark();
                        $has_graded_questions = true;
                    }
                }

                if ($has_graded_questions && $max_mark > 0) {
                    $percentage = round(($total_mark / $max_mark) * 100);
                    echo html_writer::start_div('alert alert-info mt-3');
                    echo html_writer::tag('h5', 'Current Score');
                    echo html_writer::tag('p',
                        round($total_mark, 2) . ' out of ' . round($max_mark, 2) . ' (' . $percentage . '%)',
                        ['class' => 'font-weight-bold']);
                    echo html_writer::end_div();
                }
            }
            */

            // Show score and End Lab button for finished attempts with active gamespace
            if ($is_finished && $object->event && $object->event->isActive) {
                $current_score = $object->openAttempt->score;
                $max_score = (int)$object->topomojo->grade;

                // Display score prominently
                echo html_writer::start_div('alert alert-success mt-3');
                echo html_writer::tag('h3', get_string('your_final_score', 'topomojo'));
                if ($max_score > 0) {
                    $percentage = round(($current_score / $max_score) * 100);
                    echo html_writer::tag('p',
                        get_string('score_display', 'topomojo',
                            ['score' => round($current_score, 2), 'maxscore' => $max_score, 'percentage' => $percentage]),
                        ['class' => 'lead font-weight-bold']);
                }
                echo html_writer::tag('p', get_string('exploration_mode_notice', 'topomojo'));
                echo html_writer::end_div();

                // End Lab button (posts stop_confirmed=yes to destroy gamespace) - use Moodle standard button
                $endlaburl = new moodle_url('/mod/topomojo/view.php', [
                    'id' => $cm->id,
                    'stop_confirmed' => 'yes',
                    'sesskey' => sesskey()
                ]);
                echo $OUTPUT->single_button($endlaburl, get_string('endlab_button', 'topomojo'), 'post', ['class' => 'btn-danger']);
            }

            // Render quiz if questions exist
            if (!empty($object->topomojo->questionorder)) {
                $renderer->render_quiz($object->openAttempt, $pageurl, $id);
            } elseif (isset($challenge) && empty($challenge->text)) {
                // No challenge text and no questions - show informational message
                echo $OUTPUT->notification(get_string('nochallengequestions', 'topomojo'), \core\output\notification::NOTIFY_WARNING);
            }
        } else {
            // No active attempt - only show preview for instructors
            if (has_capability('mod/topomojo:manage', $context)) {
                // Instructor preview mode
                echo $OUTPUT->notification(
                    'Challenge Preview - Students will see this content after launching the lab',
                    \core\output\notification::NOTIFY_INFO
                );

                try {
                    $preview_text = '';
                    $has_content = false;

                    // Get challenge structure
                    $full_challenge = get_challenge($object->userauth, $object->topomojo->workspaceid);
                    debugging("Preview mode - challenge retrieved: " . ($full_challenge ? "yes" : "no"), DEBUG_DEVELOPER);

                    if ($full_challenge) {
                        // Show challenge markdown (shared across all variants)
                        if (!empty($full_challenge->text)) {
                            echo '<div class="card mb-3">';
                            echo '<div class="card-header bg-primary text-white"><strong>Challenge Markdown</strong></div>';
                            echo '<div class="card-body">';
                            $renderer->render_challenge_instructions($full_challenge->text, false);
                            echo '</div></div>';
                            $has_content = true;
                            debugging("Challenge text length: " . strlen($full_challenge->text), DEBUG_DEVELOPER);
                        }

                        // Show variant-specific texts
                        if (isset($full_challenge->variants)) {
                            debugging("Variants count: " . count($full_challenge->variants), DEBUG_DEVELOPER);

                            if (count($full_challenge->variants) > 0) {
                                $is_random = ($object->topomojo->variant == 0);
                                $variant_has_text = false;

                                // Check if any variant has text before showing the card
                                if ($is_random) {
                                    foreach ($full_challenge->variants as $variant) {
                                        if (!empty($variant->text)) {
                                            $variant_has_text = true;
                                            break;
                                        }
                                    }
                                } else {
                                    $variant_index = $object->topomojo->variant - 1;
                                    if (isset($full_challenge->variants[$variant_index]) && !empty($full_challenge->variants[$variant_index]->text)) {
                                        $variant_has_text = true;
                                    }
                                }

                                // Only show card if there's variant text to display
                                if ($variant_has_text) {
                                    echo '<div class="card mb-3">';
                                    if ($is_random) {
                                        echo '<div class="card-header bg-light"><strong>Variant-Specific Text</strong> (students see one variant based on random assignment)</div>';
                                    } else {
                                        echo '<div class="card-header bg-light"><strong>Variant-Specific Text</strong></div>';
                                    }
                                    echo '<div class="card-body">';

                                    if ($is_random) {
                                        // Show all variants for random mode
                                        foreach ($full_challenge->variants as $idx => $variant) {
                                            if (!empty($variant->text)) {
                                                $variant_num = $idx + 1;
                                                echo '<div class="border-bottom pb-3 mb-3">';
                                                echo '<h5 class="text-primary">Variant ' . $variant_num . '</h5>';
                                                $renderer->render_challenge_instructions($variant->text, false);
                                                echo '</div>';
                                                $has_content = true;
                                            }
                                        }
                                    } else {
                                        // Show only the configured variant
                                        $variant_index = $object->topomojo->variant - 1; // Convert to 0-based
                                        $configured_variant = $object->topomojo->variant;
                                        if (isset($full_challenge->variants[$variant_index]) && !empty($full_challenge->variants[$variant_index]->text)) {
                                            echo '<h5 class="text-primary">Variant ' . $configured_variant . '</h5>';
                                            $renderer->render_challenge_instructions($full_challenge->variants[$variant_index]->text, false);
                                            $has_content = true;
                                        }
                                    }

                                    echo '</div></div>';
                                }
                            }
                        }
                    }

                    if (!$has_content) {
                        debugging("No challenge text or variant text found", DEBUG_DEVELOPER);
                        echo $OUTPUT->notification(
                            'No challenge or variant markdown configured in TopoMojo.',
                            \core\output\notification::NOTIFY_INFO
                        );
                    }

                    // Add note about managing questions
                    echo $OUTPUT->notification(
                        'To manage graded questions for this activity, visit the Questions page.',
                        \core\output\notification::NOTIFY_INFO
                    );
                } catch (Exception $e) {
                    debugging("Failed to fetch challenge preview: " . $e->getMessage(), DEBUG_DEVELOPER);
                    $renderer->render_no_challenge();
                }
            } else {
                $renderer->render_no_challenge();
            }
        }
}
// Attempts may differ from events pulled from history on server

echo $renderer->footer();
