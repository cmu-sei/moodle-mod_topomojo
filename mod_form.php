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

/**
 * topomojo configuration form
 *
 * @package    mod_topomojo
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
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

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/topomojo/locallib.php');

class mod_topomojo_mod_form extends moodleform_mod {

    /** @var array options to be used with date_time_selector fields in the activity. */
    public static $datefieldoptions = array('optional' => true);

    private $auth;

    protected static $reviewfields = array(); // Initialised in the constructor.

    public function __construct($current, $section, $cm, $course) {
        self::$reviewfields = array(
            'attempt'          => array('theattempt', 'topomojo'),
            'correctness'      => array('whethercorrect', 'question'),
            'marks'            => array('marks', 'topomojo'),
            'specificfeedback' => array('specificfeedback', 'question'),
            'generalfeedback'  => array('generalfeedback', 'question'),
            'rightanswer'      => array('rightanswer', 'question'),
            'overallfeedback'  => array('reviewoverallfeedback', 'topomojo'),
            'manualcomment'    => array('manualcomment', 'topomojo')
        );
        parent::__construct($current, $section, $cm, $course);
    }


    function definition() {
        global $COURSE, $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $config = get_config('topomojo');

        // Adding the standard "intro" and "introformat" fields.
        //$this->standard_intro_elements();
        //TODO remove ability to edit the description and just show the select and dropdown
        //$mform->removeElement('introeditor');
        //TODO figure out why the description doesnt appear
        //$mform->removeElement('showdescription');


        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

	    if ($config->autocomplete < 2) {
	        #debugging("looking up list of workspaces", DEBUG_DEVELOPER);

            // pull list from topomojo
            $this->auth = setup();
            $this->workspaces = get_workspaces($this->auth);
            $labnames = array();
            $labs = [];
            foreach ($this->workspaces as $workspace) {
                array_push($labnames, $workspace->name);
                $labs[$workspace->id] = s($workspace->name);
            }
            array_unshift($labs, "");
            asort($labs);

            $options = array(
                'multiple' => false,
                //'noselectionstring' => get_string('selectname', 'topomojo'),
                'placeholder' => get_string('selectname', 'topomojo')
            );
            if ($config->autocomplete) {
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
        $mform->addRule('workspaceid', 'You must choose an option', 'minlength', '2', 'client'); //why is this client?

        $mform->setDefault('workspaceid', null);
        $mform->addHelpButton('workspaceid', 'workspace', 'topomojo');
/*
        $mform->addElement('checkbox', 'extendevent', get_string('extendeventsetting', 'topomojo'));
        $mform->addHelpButton('extendevent', 'extendeventsetting', 'topomojo');
*/
        //-------------------------------------------------------
        $mform->addElement('header', 'optionssection', get_string('appearance'));

        $options = array(get_string('displaylink', 'topomojo'), get_string('embedlab', 'topomojo'));
        $mform->addElement('select', 'embed', get_string('embed', 'topomojo'), $options);
        $mform->setDefault('embed', $config->embed);
        $mform->addHelpButton('embed', 'embed', 'topomojo');

        $options = array('', 'Countdown', 'Timer');
        $mform->addElement('select', 'clock', get_string('clock', 'topomojo'), $options);
        $mform->setDefault('clock', '');
        $mform->addHelpButton('clock', 'clock', 'topomojo');

        // Grade settings.
        $this->standard_grading_coursemodule_elements();

	    $mform->removeElement('grade');
        $currentgrade = 0;
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        }

        $mform->addElement('text', 'grade', get_string('grade', 'quiz'), $currentgrade);
        $mform->setType('grade', PARAM_INT);
        $mform->addHelpButton('grade', 'grade', 'topomojo');

        $mform->addElement('select', 'grademethod',
            get_string('grademethod', 'topomojo'),
            \mod_topomojo\utils\scaletypes::get_display_types());
        $mform->setType('grademethod', PARAM_INT);
        $mform->addHelpButton('grademethod', 'grademethod', 'topomojo');
        //$mform->hideIf('grademethod', 'grade', 'eq', '0');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'timing', get_string('timing', 'topomojo'));

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('eventopen', 'topomojo'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeopen', 'eventopen', 'topomojo');

        $mform->addElement('date_time_selector', 'timeclose', get_string('eventclose', 'topomojo'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeclose', 'eventclose', 'topomojo');

        //TODO pull duration from topomojo workspace
        // duration is referred to as timelimit in topomojo plugin
        // type duration gets stored in the db in seconds. renderer and locallib convert to minutes
        $mform->addElement('duration', 'duration', get_string('duration', 'topomojo'), "0");
        $mform->setType('duration', PARAM_INT);
        $mform->addHelpButton('duration', 'duration', 'topomojo');        
    
        $mform->addElement('checkbox', 'extendevent', get_string('extendeventsetting', 'topomojo'));
        $mform->addHelpButton('extendevent', 'extendeventsetting', 'topomojo');
    

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'interactionhdr', get_string('questionbehaviour', 'topomojo'));

        // Shuffle within questions.
        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shufflewithin', 'topomojo'));
        $mform->addHelpButton('shuffleanswers', 'shufflewithin', 'topomojo');
        $mform->setAdvanced('shuffleanswers', '');
        $mform->setDefault('shuffleanswers', '');

        // How questions behave (question behaviour).
        if (!empty($this->current->preferredbehaviour)) {
            $currentbehaviour = $this->current->preferredbehaviour;
        } else {
            $currentbehaviour = '';
        }
        $behaviours = question_engine::get_behaviour_options($currentbehaviour);
        $mform->addElement('select', 'preferredbehaviour',
                get_string('howquestionsbehave', 'question'), $behaviours);
        $mform->addHelpButton('preferredbehaviour', 'howquestionsbehave', 'question');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'reviewoptionshdr',
                get_string('reviewoptionsheading', 'topomojo'));
        $mform->addHelpButton('reviewoptionshdr', 'reviewoptionsheading', 'topomojo');
        // Review options.
//        $this->add_review_options_group($mform, $config, 'during',
//                question_display_options::DURING, true);
//        $this->add_review_options_group($mform, $config, 'immediately',
//                question_display_options::IMMEDIATELY_AFTER);
        $this->add_review_options_group($mform, $config, 'open',
                mod_topomojo\topomojo_display_options::LATER_WHILE_OPEN);
        $this->add_review_options_group($mform, $config, 'closed',
                mod_topomojo\topomojo_display_options::AFTER_CLOSE);


	    foreach (self::$reviewfields as $field => $notused) {
            $mform->disabledIf($field . 'closed', 'timeclose[enabled]');
        }



        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();

    }

    /**
     * Adapted from the  module's review options group function
     *
     * @param      $mform
     * @param      $whenname
     * @param bool $withhelp
     */
    protected function add_review_options_group($mform, $config, $whenname,
            $when, $withhelp = false) {
        global $OUTPUT;

        $group = array();
        foreach (self::$reviewfields as $field => $string) {
            list($identifier, $component) = $string;

            $label = get_string($identifier, $component);
            if ($withhelp) {
                $label .= ' ' . $OUTPUT->help_icon($identifier, $component);
            }

            $group[] = $mform->createElement('checkbox', $field . $whenname, '', $label);
        }
        $mform->addGroup($group, $whenname . 'optionsgrp',
                get_string('review' . $whenname, 'topomojo'), null, false);

        foreach (self::$reviewfields as $field => $notused) {
            $cfgfield = 'review' . $field;
            if ($config->$cfgfield & $when) {
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


    protected function preprocessing_review_settings(&$toform, $whenname, $when) {
        foreach (self::$reviewfields as $field => $notused) {
            $fieldname = 'review' . $field;
            if (array_key_exists($fieldname, $toform)) {
                $toform[$field . $whenname] = $toform[$fieldname] & $when;
            }
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] != 0 && $data['timeclose'] != 0 &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'quiz');
        }


        if (array_key_exists('completion', $data) && $data['completion'] == COMPLETION_TRACKING_AUTOMATIC) {
            $completionpass = isset($data['completionpass']) ? $data['completionpass'] : $this->current->completionpass;

            // Show an error if require passing grade was selected and the grade to pass was set to 0.
            if ($completionpass && (empty($data['gradepass']) || grade_floatval($data['gradepass']) == 0)) {
                if (isset($data['completionpass'])) {
                    $errors['completionpassgroup'] = get_string('gradetopassnotset', 'topomojo');
                } else {
                    $errors['gradepass'] = get_string('gradetopassmustbeset', 'topomojo');
                }
            }
        }
    }

    function data_preprocessing(&$data) {

        // Completion settings check.
        if (empty($toform['completionusegrade'])) {
            $toform['completionpass'] = 0; // Forced unchecked.
        }

    }

    function data_postprocessing($data) {
        if (!$data->workspaceid) {
            echo "return to settings page<br>";
            exit;
        }
        if (!$data->embed) {
            $data->embed = 0;
        }

        if (is_array($this->workspaces)) {
            $index = array_search($data->workspaceid, array_column($this->workspaces, 'id'), true);
            $data->name = $this->workspaces[$index]->name;
            $data->intro = $this->workspaces[$index]->description;
            // pull durationMinutes from topomojo
            if ($data->duration == 0) {
                $this->workspace = get_workspace($this->auth, $this->workspaces[$index]->id);
                $data->duration = $this->workspace->durationMinutes;
            }
            $this->workspace = get_workspace($this->auth, $this->workspaces[$index]->id);

            $data->introformat = FORMAT_MARKDOWN;

        } else {
            debugging('name of lab is unknown', DEBUG_DEVELOPER);
            $data->name = "Unknown lab";
            $data->intro = "No description available";
        }
        $data->introeditor['format'] = FORMAT_PLAIN;

        // TODO if grade method changed, update all grades
    }


}

