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
 * topomojo configuration form
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/topomojo/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_login();
require_once("$CFG->dirroot/mod/topomojo/lib.php");
require_once("$CFG->dirroot/tag/lib.php");

use mod_topomojo\topomojo;

/**
 * Class representing the form for the TopoMojo module in Moodle.
 *
 * This form is used to configure the settings for the TopoMojo module.
 * It extends the moodleform_mod class to inherit Moodle's standard
 * module form functionality.
 *
 * @package    mod_topomojo
 * @category   form
 */
class mod_topomojo_mod_form extends moodleform_mod {
    /**
     * Auth token or authentication status.
     *
     * @var string|null
     */
    private $auth;

    /**
     * Workspaces pulled from TopoMojo.
     *
     * @var array[]|null An array of associative arrays, where each array represents a workspace with various properties,
     * or null if not set.
     */
    private $workspaces;

    /** @var array options to be used with date_time_selector fields in the quiz. */
    public static $datefieldoptions = ['optional' => true];

    /**
     * Array to hold review fields.
     *
     * This variable is used to store the fields related to the review process.
     * It is initialized in the constructor.
     *
     * @var array An array of review fields.
     */
    protected static $reviewfields = [];

    /**
     * Stores feedback data for the module.
     *
     * This variable holds the feedback information related to the module.
     * It is typically an array containing feedback entries, where each entry
     * could be an associative array or object representing individual feedback.
     *
     * @var array An array of feedback data. Each element may contain feedback details.
     */
    protected $_feedbacks;

    /**
     * Constructor for the class.
     *
     * Initializes the class with the provided parameters, setting up necessary
     * values or dependencies required by the class. This may include context,
     * course modules, and course information.
     *
     * @param object $current The current context or state relevant to the class.
     * @param int $section The ID or index of the section associated with the class.
     * @param stdClass $cm The course module object related to the class.
     * @param stdClass $course The course object or details related to the class.
     */
    public function __construct($current, $section, $cm, $course) {
        self::$reviewfields = array(
            'attempt'          => ['theattempt', 'topomojo'],          // Field for attempt details
            'correctness'      => ['whethercorrect', 'question'],      // Field for correctness of the answer
            'marks'            => ['marks', 'topomojo'],               // Field for marks obtained
            'specificfeedback' => ['specificfeedback', 'question'],    // Specific feedback related to the question
            'generalfeedback'  => ['generalfeedback', 'question'],     // General feedback for the question
            'rightanswer'      => ['rightanswer', 'question'],         // The correct answer
            'overallfeedback'  => ['reviewoverallfeedback', 'topomojo'], // Overall feedback for the review
            'manualcomment'    => ['manualcomment', 'topomojo']        // Manual comments by the reviewer
        );
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Defines the form elements for this module.
     *
     * This method sets up the form elements used for configuring the module, including
     * workspace selection, grading options, timing, and interaction settings. It configures
     * the form based on the current module's settings and options available.
     *
     * @return void
     */
    public function definition() {
        global $COURSE, $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $topomojoconfig = get_config('topomojo');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();
        // TODO remove ability to edit the description and just show the select and dropdown
        // $mform->removeElement('introeditor');
        $mform->addElement('header', 'general', get_string('general', 'form'));

        if ($topomojoconfig->autocomplete < 2) {
            // Pull list from topomojo.
            $this->auth = setup();
            $this->workspaces = get_workspaces($this->auth);
            $labnames = [];
            $labs = [];
            $tagimport = get_config('topomojo', 'tagimport');
            $tagcreate = get_config('topomojo', 'tagcreate');

            foreach ($this->workspaces as $workspace) {
                array_push($labnames, $workspace->name);
                $labs[$workspace->id] = s($workspace->name);

                if (!empty($workspace->tags) && $tagimport) {
                    $tagsarray = [];

                    $tagsarray = $workspace->tags;

                    // Check if $workspace->tags is a string
                    if (str_contains($tagsarray, '-')) {
                        $tagsarray = explode(' ', $workspace->tags);
                        $tagsarray = str_replace('-', ' ', $tagsarray);
                        $tagsarray = array_map('ucwords', $tagsarray);
                    } else {
                        if (!is_array($tagsarray)) {
                            $tagsarray = explode(', ', $tagsarray);
                        } else {
                            $tagsarray = $workspace->tags;;
                        }
                    }

                    $alltagsarray[$workspace->id] = $tagsarray;

                    $flattenedtagsarray = [];
                    foreach ($alltagsarray as $tags) {
                        if (is_array($tags)) {
                            $flattenedtagsarray = array_merge($flattenedtagsarray, $tags);
                        }
                    }

                    if (!empty($flattenedtagsarray) && $tagcreate) {
                        // Split the string into an array by spaces
                        $collectionid = get_config('topomojo', 'tagcollection');
                        // Add tag to moodle if missing
                        \core_tag_tag::create_if_missing($collectionid, $flattenedtagsarray, true);
                    }
                }

            }
            array_unshift($labs, "");
            asort($labs);

            $options = [
                'multiple' => false,
                // 'noselectionstring' => get_string('selectname', 'topomojo'),
                'placeholder' => get_string('selectname', 'topomojo'),
            ];
            if ($topomojoconfig->autocomplete) {
                $mform->addElement('autocomplete', 'workspaceid', get_string('workspace', 'topomojo'), $labs, $options);
            } else {
                $mform->addElement('select', 'workspaceid', get_string('workspace', 'topomojo'), $labs);
            }
        } else {
                debugging('need to manually select id', DEBUG_DEVELOPER);
                $mform->addElement('text', 'workspaceid', get_string('workspace', 'topomojo'));
            $mform->setType('workspaceid', PARAM_ALPHANUMEXT);
        }

        $mform->addRule('workspaceid', null, 'required', null, 'client');
        $mform->addRule('workspaceid', 'You must choose an option', 'minlength', '2', 'client'); // Why is this client?

        $mform->setDefault('workspaceid', null);
        $mform->addHelpButton('workspaceid', 'workspace', 'topomojo');

        $mform->addElement('text', 'variant', get_string('variant', 'topomojo'));
        $mform->setType('variant', PARAM_INT);
        $mform->setDefault('variant', '1');
        $mform->addHelpButton('variant', 'variant', 'topomojo');

        $mform->addElement('header', 'optionssection', get_string('appearance'));

        $options = [get_string('displaylink', 'topomojo'), get_string('embedlab', 'topomojo')];
        $mform->addElement('select', 'embed', get_string('embed', 'topomojo'), $options);
        $mform->setDefault('embed', $topomojoconfig->embed);
        $mform->addHelpButton('embed', 'embed', 'topomojo');

        $options = ['Hidden', 'Countdown', 'Timer'];
        $mform->addElement('select', 'clock', get_string('clock', 'topomojo'), $options);
        $mform->setDefault('clock', 1); // Set default to the index of 'Countdown' in $options array
        $mform->addHelpButton('clock', 'clock', 'topomojo');

        // Grade settings.
        $this->standard_grading_coursemodule_elements();

        $mform->removeElement('grade');
        $currentgrade = 0;
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        }

        $mform->addElement('text', 'grade', get_string('grade', 'topomojo'), $currentgrade);
        $mform->setType('grade', PARAM_INT);
        $mform->addHelpButton('grade', 'grade', 'topomojo');

        // Number of attempts.
        $maxattempts = get_config('topomojo', 'maxattempts');
        $mform->addElement('text', 'attempts', get_string('attemptsallowed', 'topomojo'));
        $mform->setType('attempts', PARAM_ALPHANUMEXT);
        $mform->setDefault('attempts', $maxattempts);
        $mform->addHelpButton('attempts', 'attemptsallowed', 'topomojo');

        // Grading method.
        $mform->addElement('select', 'grademethod',
                get_string('grademethod', 'topomojo'),
                \mod_topomojo\utils\scaletypes::get_display_types());
        $mform->setType('grademethod', PARAM_INT);
        $mform->addHelpButton('grademethod', 'grademethod', 'topomojo');
        // $mform->hideIf('grademethod', 'grade', 'eq', '0');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'timing', get_string('timing', 'topomojo'));

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('eventopen', 'topomojo'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeopen', 'eventopen', 'topomojo');

        $mform->addElement('date_time_selector', 'timeclose', get_string('eventclose', 'topomojo'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeclose', 'eventclose', 'topomojo');

        // if the duration is set to 0 here it will be pulled from topomojo workspace during form processing
        // type duration gets stored in the db in seconds. renderer and locallib convert to minutes
        $mform->addElement('duration', 'duration', get_string('duration', 'topomojo'), "0");
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', '3600');
        $mform->addHelpButton('duration', 'duration', 'topomojo');

        $mform->addElement('checkbox', 'extendevent', get_string('extendevent', 'topomojo'));
        $mform->addHelpButton('extendevent', 'extendevent', 'topomojo');

        $mform->addElement('header', 'interactionhdr', get_string('questionbehaviour', 'topomojo'));

        $mform->addElement('checkbox', 'importchallenge', get_string('importchallenge', 'topomojo'));
        $mform->addHelpButton('importchallenge', 'importchallenge', 'topomojo');

        // TODO this affects the submissions option
        $mform->addElement('checkbox', 'endlab', get_string('endlab', 'topomojo'));
        $mform->addHelpButton('endlab', 'endlab', 'topomojo');

        // Number of challenge submissions.
        // TODO this is affected by the endlab option
        //  if endlab is true, set submissions to 1 and disable
        $submissionoptions = ['0' => get_string('unlimited')];
        for ($i = 1; $i <= $maxattempts; $i++) {
            $submissionoptions[$i] = $i;
        }

        $mform->addElement('select', 'submissions', get_string('submissionsallowed', 'topomojo'),
                $submissionoptions);
        $mform->addHelpButton('submissions', 'submissionsallowed', 'topomojo');
        $mform->disabledIf('submissions', 'endlab', 'checked');

        // Shuffle within questions.
        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shufflewithin', 'topomojo'));
        $mform->addHelpButton('shuffleanswers', 'shufflewithin', 'topomojo');
        $mform->setAdvanced('shuffleanswers', '');
        $mform->setDefault('shuffleanswers', '');

	// TODO if we have mutiple tries, should this be set to interactive with multiple tries?
        // How questions behave (question behaviour).
        if (!empty($this->current->preferredbehaviour)) {
            $currentbehaviour = $this->current->preferredbehaviour;
        } else {
            $currentbehaviour = 'deferredfeedback';
        }
        $behaviours = question_engine::get_behaviour_options($currentbehaviour);
        // Filter to keep only 'deferredfeedback' behavior in the options.
        $filtered_behaviours = array_filter($behaviours, function($behaviour) {
            return $behaviour == 'deferredfeedback';
        });

        // Replace the behaviors with only deferredfeedback.
        $behaviours = !empty($filtered_behaviours) ? $filtered_behaviours : ['deferredfeedback' => 'Deferred feedback'];

        $mform->addElement('select', 'preferredbehaviour',
                get_string('howquestionsbehave', 'question'), $behaviours);
        $mform->addHelpButton('preferredbehaviour', 'howquestionsbehave', 'question');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'reviewoptionshdr',
                get_string('reviewoptionsheading', 'topomojo'));
        $mform->addHelpButton('reviewoptionshdr', 'reviewoptionsheading', 'topomojo');
        // Review options.
        $this->add_review_options_group($mform, $topomojoconfig, 'during',
                mod_topomojo_display_options::DURING, true);
        $this->add_review_options_group($mform, $topomojoconfig, 'immediately',
                mod_topomojo_display_options::IMMEDIATELY_AFTER);
        $this->add_review_options_group($mform, $topomojoconfig, 'open',
                mod_topomojo_display_options::LATER_WHILE_OPEN);
        $this->add_review_options_group($mform, $topomojoconfig, 'closed',
                mod_topomojo_display_options::AFTER_CLOSE);

        foreach (self::$reviewfields as $field => $notused) {
            $mform->disabledIf($field . 'closed', 'timeclose[enabled]');
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Adapted from the quiz module's review options group function
     *
     * @param      $mform
     * @param      $whenname
     * @param bool $withhelp
     */
    protected function add_review_options_group($mform, $topomojoconfig, $whenname,
            $when, $withhelp = false) {
        global $OUTPUT;

        $group = [];
        foreach (self::$reviewfields as $field => $string) {
            list($identifier, $component) = $string;

            $label = get_string($identifier, $component);
            if ($withhelp) {
                // TODO this displays a placeholder not an acutal help message
                $label .= ' ' . $OUTPUT->help_icon($identifier, $component);
            }

            $group[] = $mform->createElement('checkbox', $field . $whenname, '', $label);
        }
        $mform->addGroup($group, $whenname . 'optionsgrp',
                get_string('review' . $whenname, 'topomojo'), null, false);

        foreach (self::$reviewfields as $field => $notused) {
            $cfgfield = 'review' . $field;
            if ($topomojoconfig->$cfgfield & $when) {
                $mform->setDefault($field . $whenname, 1);
            } else {
                $mform->setDefault($field . $whenname, 0);
            }
        }
        if ($whenname != 'during') {
            $mform->disabledIf('correctness' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('specificfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('generalfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('rightanswer' . $whenname, 'attempt' . $whenname);
        }
    }

    /**
     * Preprocesses the review settings for the form.
     *
     * This method modifies or sets up the review settings of the form based on
     * certain conditions or configurations. It is used to ensure that the review
     * options are properly initialized and configured before the form is displayed.
     *
     * @param array $data The form data that will be processed.
     * @param array $form The form object that contains the settings.
     *
     * @return void
     */
    protected function preprocessing_review_settings(&$toform, $whenname, $when) {
        foreach (self::$reviewfields as $field => $notused) {
            $fieldname = 'review' . $field;
            if (array_key_exists($fieldname, $toform)) {
                $toform[$field . $whenname] = $toform[$fieldname] & $when;
            }
        }
    }

    /**
     * Preprocesses the data before it is used to populate the form.
     *
     * This method modifies the form data before it is displayed to the user.
     * It typically prepares the data by adding or adjusting values to ensure
     * the form is presented with the correct initial state.
     *
     * @param array $toform Reference to the form data that will be processed.
     *
     * @return void
     */
    public function data_preprocessing(&$toform) {
        if (isset($toform['grade'])) {
            // Convert to a real number, so we don't get 0.0000.
            $toform['grade'] = $toform['grade'] + 0;
        }

        if (is_array($this->_feedbacks) && count($this->_feedbacks)) {
            $key = 0;
            foreach ($this->_feedbacks as $feedback) {
                $draftid = file_get_submitted_draft_itemid('feedbacktext['.$key.']');
                $toform['feedbacktext['.$key.']']['text'] = file_prepare_draft_area(
                    $draftid,               // Draftid.
                    $this->context->id,     // Context.
                    'mod_topomojo',             // Component.
                    'feedback',             // Filarea.
                    !empty($feedback->id) ? (int) $feedback->id : null, // Itemid.
                    null,
                    $feedback->feedbacktext // Text.
                );
                $toform['feedbacktext['.$key.']']['format'] = $feedback->feedbacktextformat;
                $toform['feedbacktext['.$key.']']['itemid'] = $draftid;

                if ($toform['grade'] == 0) {
                    // When a quiz is un-graded, there can only be one lot of
                    // feedback. If the quiz previously had a maximum grade and
                    // several lots of feedback, we must now avoid putting text
                    // into input boxes that are disabled, but which the
                    // validation will insist are blank.
                    break;
                }

                if ($feedback->mingrade > 0) {
                    $toform['feedbackboundaries['.$key.']'] =
                            round(100.0 * $feedback->mingrade / $toform['grade'], 6) . '%';
                }
                $key++;
            }
        }
        /*
        if (isset($toform['timelimit'])) {
            $toform['timelimitenable'] = $toform['timelimit'] > 0;
        }
        */
        $this->preprocessing_review_settings($toform, 'during',
                mod_topomojo_display_options::DURING);
        $this->preprocessing_review_settings($toform, 'immediately',
                mod_topomojo_display_options::IMMEDIATELY_AFTER);
        $this->preprocessing_review_settings($toform, 'open',
                mod_topomojo_display_options::LATER_WHILE_OPEN);
        $this->preprocessing_review_settings($toform, 'closed',
                mod_topomojo_display_options::AFTER_CLOSE);
        $toform['attemptduring'] = true;
        $toform['overallfeedbackduring'] = false;

        // Completion settings check.
        if (empty($toform['completionusegrade'])) {
            $toform['completionpass'] = 0; // Forced unchecked.
        }

    }

    /**
     * Validates the form data.
     *
     * This method checks the validity of the form data provided by the user.
     * It performs any necessary validation to ensure the data meets the required
     * criteria before the form can be processed or saved.
     *
     * @param array $data The form data to validate.
     * @param array $files The files associated with the form submission.
     *
     * @return array An array of validation errors, if any. An empty array if validation passes.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] != 0 && $data['timeclose'] != 0 &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'quiz');
        }
        if (!empty($data['completionminattempts'])) {
            if ($data['attempts'] > 0 && $data['completionminattempts'] > $data['attempts']) {
                $errors['completionminattemptsgroup'] = get_string('completionminattemptserror', 'topomojo');
            }
        }
        // If CBM is involved, don't show the warning for grade to pass being larger than the maximum grade.
        if (($data['preferredbehaviour'] == 'deferredcbm') || ($data['preferredbehaviour'] == 'immediatecbm')) {
            unset($errors['gradepass']);
        }
        return $errors;

    }

    /**
     * Post-processes the data after form submission.
     *
     * This method is used to handle any additional processing or modifications
     * required after the form data has been submitted and validated. It can be
     * used to adjust or clean up the data before it is saved or used elsewhere.
     *
     * @param array $data The processed form data to be handled.
     *
     * @return void
     */
    public function data_postprocessing($data) {
        $usetopomojointro = false;

        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionminattemptsenabled) || !$autocompletion) {
                $data->completionminattempts = 0;
            }
        }

        if (!$data->workspaceid) {
            throw new moodle_exception("no workspace id is set");
        }
        if (!$data->embed) {
            $data->embed = 0;
        }

        $selectedworkspace = null;
        if (is_array($this->workspaces)) {
            $selectedworkspace = array_search($data->workspaceid, array_column($this->workspaces, 'id'), true);
            $data->name = $this->workspaces[$selectedworkspace]->name;
            // TODO make a setting to determine whether we pull this from topomojo or set it in moodle
            if ($usetopomojointro) {
                $description = $this->workspaces[$selectedworkspace]->description;
                $markdowncutline = "<!-- cut -->";
                $parts = preg_split($markdowncutline, $description);
                $data->intro = $parts[0];
                $data->introformat = FORMAT_MARKDOWN;
            }
            // pull durationMinutes from topomojo workspace if set to 0 by the teacher
            if ($data->duration == 0) {
                $data->duration = $this->workspaces[$selectedworkspace]->durationMinutes;
            }
            // Check that variant is valid
            $challenge = get_challenge($this->auth, $this->workspaces[$selectedworkspace]->id);
            if ($challenge) {
                $variants = count($challenge->variants);
            } else {
                $variants = 1;
            }

            if ($data->extendevent && $data->clock != 1) {
                throw new moodle_exception('The "Extend Lab" option requires the "Clock" setting to be set to "Countdown.');
            }

            if ($data->variant == 0) {
                throw new moodle_exception("random variants are not suppored.");
            } else if ($data->variant > $variants) {
                throw new moodle_exception("lab does not have variant number " . $data->variant);
            }

        } else {
            debugging('name of lab is unknown', DEBUG_DEVELOPER);
            $data->name = "Unknown lab";
            if ($usetopomojointro) {
                $data->intro = "No description available";
                $data->introeditor['format'] = FORMAT_PLAIN;
            }
        }

        // Handle tags
        $tagimport = get_config('topomojo', 'tagimport');
        $tagmap = get_config('topomojo', 'tagmap');

        if ($tagimport) {
            $newtags = $this->workspaces[$selectedworkspace]->tags;
            if ($newtags && $tagmap) {
                // Process tags
                $words = explode(' ', $newtags);
                $newtags = str_replace('-', ' ', $words);
                $data->tags = array_merge($data->tags ?? [], $newtags);
            }
        }
    }

    /**
     * Adds all the standard elements to a form to edit the settings for an activity module.
     */
    protected function standard_coursemodule_elements() {
        global $COURSE, $CFG, $DB, $OUTPUT;
        $mform =& $this->_form;

        $this->_outcomesused = false;
        if ($this->_features->outcomes) {
            if ($outcomes = grade_outcome::fetch_all_available($COURSE->id)) {
                $this->_outcomesused = true;
                $mform->addElement('header', 'modoutcomes', get_string('outcomes', 'grades'));
                foreach ($outcomes as $outcome) {
                    $mform->addElement('advcheckbox', 'outcome_'.$outcome->id, $outcome->get_name());
                }
            }
        }

        if ($this->_features->rating) {
            $this->add_rating_settings($mform, 0);
        }

        $mform->addElement('header', 'modstandardelshdr', get_string('modstandardels', 'form'));

        $section = get_fast_modinfo($COURSE)->get_section_info($this->_section);
        $allowstealth =
            !empty($CFG->allowstealth) &&
            $this->courseformat->allow_stealth_module_visibility($this->_cm, $section) &&
            !$this->_features->hasnoview;
        if ($allowstealth && $section->visible) {
            $modvisiblelabel = 'modvisiblewithstealth';
        } else if ($section->visible) {
            $modvisiblelabel = 'modvisible';
        } else {
            $modvisiblelabel = 'modvisiblehiddensection';
        }
        $mform->addElement('modvisible', 'visible', get_string($modvisiblelabel), null,
                ['allowstealth' => $allowstealth, 'sectionvisible' => $section->visible, 'cm' => $this->_cm]);
        $mform->addHelpButton('visible', $modvisiblelabel);
        if ($this->_features->idnumber) {
            $mform->addElement('text', 'cmidnumber', get_string('idnumbermod'));
            $mform->setType('cmidnumber', PARAM_RAW);
            $mform->addHelpButton('cmidnumber', 'idnumbermod');
        }

        if (has_capability('moodle/course:setforcedlanguage', $this->get_context())) {
            $languages = ['' => get_string('forceno')];
            $languages += get_string_manager()->get_list_of_translations();

            $mform->addElement('select', 'lang', get_string('forcelanguage'), $languages);
        }

        if ($CFG->downloadcoursecontentallowed) {
                $choices = [
                    DOWNLOAD_COURSE_CONTENT_DISABLED => get_string('no'),
                    DOWNLOAD_COURSE_CONTENT_ENABLED => get_string('yes'),
                ];
                $mform->addElement('select', 'downloadcontent', get_string('downloadcontent', 'course'), $choices);
                $downloadcontentdefault = $this->_cm->downloadcontent ?? DOWNLOAD_COURSE_CONTENT_ENABLED;
                $mform->addHelpButton('downloadcontent', 'downloadcontent', 'course');
                if (has_capability('moodle/course:configuredownloadcontent', $this->get_context())) {
                    $mform->setDefault('downloadcontent', $downloadcontentdefault);
                } else {
                    $mform->hardFreeze('downloadcontent');
                    $mform->setConstant('downloadcontent', $downloadcontentdefault);
                }
        }

        if ($this->_features->groups) {
            $options = [NOGROUPS       => get_string('groupsnone'),
                             SEPARATEGROUPS => get_string('groupsseparate'),
                             VISIBLEGROUPS  => get_string('groupsvisible')];
            $mform->addElement('select', 'groupmode', get_string('groupmode', 'group'), $options, NOGROUPS);
            $mform->addHelpButton('groupmode', 'groupmode', 'group');
        }

        if ($this->_features->groupings) {
            // Groupings selector - used to select grouping for groups in activity.
            $options = [];
            if ($groupings = $DB->get_records('groupings', ['courseid' => $COURSE->id])) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = format_string($grouping->name);
                }
            }
            core_collator::asort($options);
            $options = [0 => get_string('none')] + $options;
            $mform->addElement('select', 'groupingid', get_string('grouping', 'group'), $options);
            $mform->addHelpButton('groupingid', 'grouping', 'group');
        }
        if (!empty($CFG->enableavailability)) {
            // Add special button to end of previous section if groups/groupings
            // are enabled.

            $availabilityplugins = \core\plugininfo\availability::get_enabled_plugins();
            $groupavailability = $this->_features->groups && array_key_exists('group', $availabilityplugins);
            $groupingavailability = $this->_features->groupings && array_key_exists('grouping', $availabilityplugins);

            if ($groupavailability || $groupingavailability) {
                // When creating the button, we need to set type=button to prevent it behaving as a submit.
                $mform->addElement('static', 'restrictgroupbutton', '',
                    html_writer::tag('button', get_string('restrictbygroup', 'availability'), [
                        'id' => 'restrictbygroup',
                        'type' => 'button',
                        'disabled' => 'disabled',
                        'class' => 'btn btn-secondary',
                        'data-groupavailability' => $groupavailability,
                        'data-groupingavailability' => $groupingavailability,
                    ])
                );
            }

            // Availability field. This is just a textarea; the user interface
            // interaction is all implemented in JavaScript.
            $mform->addElement('header', 'availabilityconditionsheader',
                    get_string('restrictaccess', 'availability'));
            // Note: This field cannot be named 'availability' because that
            // conflicts with fields in existing modules (such as assign).
            // So it uses a long name that will not conflict.
            $mform->addElement('textarea', 'availabilityconditionsjson',
                    get_string('accessrestrictions', 'availability'),
                    ['class' => 'd-none']
            );
            // Availability loading indicator.
            $loadingcontainer = $OUTPUT->container(
                $OUTPUT->render_from_template('core/loading', []),
                'd-flex justify-content-center py-5 icon-size-5',
                'availabilityconditions-loading'
            );
            $mform->addElement('html', $loadingcontainer);

            // The _cm variable may not be a proper cm_info, so get one from modinfo.
            if ($this->_cm) {
                $modinfo = get_fast_modinfo($COURSE);
                $cm = $modinfo->get_cm($this->_cm->id);
            } else {
                $cm = null;
            }
            \core_availability\frontend::include_all_javascript($COURSE, $cm);
        }

        // Conditional activities: completion tracking section
        if (!isset($completion)) {
            $completion = new completion_info($COURSE);
        }

        // Add the completion tracking elements to the form.
        if ($completion->is_enabled()) {
            $mform->addElement('header', 'activitycompletionheader', get_string('activitycompletion', 'completion'));
            $this->add_completion_elements(null, false, false, false, $this->_course->id);
        }

        // TODO id topomojo is setting them, then display a message here instead
        // Populate module tags.
        if (core_tag_tag::is_enabled('core', 'course_modules')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
            $mform->addElement('tags', 'tags', get_string('tags'), ['itemtype' => 'course_modules', 'component' => 'core']);
            if ($this->_cm) {
                $tags = core_tag_tag::get_item_tags_array('core', 'course_modules', $this->_cm->id);
                $mform->setDefault('tags', $tags);
            }
        }

        $this->standard_hidden_coursemodule_elements();

        $this->plugin_extend_coursemodule_standard_elements();
    }
}

