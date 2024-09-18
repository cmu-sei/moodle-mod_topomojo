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

defined('MOODLE_INTERNAL') || die();
use mod_topomojo\traits\renderer_base;

/**
 * Renderer for the Topomojo plugin in Moodle.
 *
 * This file contains the renderer class for the Topomojo plugin, which is responsible
 * for rendering the plugin's output in the Moodle environment. The class extends Moodle's
 * `plugin_renderer_base` and utilizes traits to provide additional functionality.
 *
 * @package   mod_topomojo
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_topomojo_renderer extends \plugin_renderer_base {

    use renderer_base;

//class mod_topomojo_renderer extends plugin_renderer_base {

    /**
     * Renders the detailed view of a Topomojo activity.
     *
     * This function prepares the data for rendering a detailed view of a Topomojo activity,
     * including its name, introduction text, duration, and an optional code. It then
     * renders this data using the `mod_topomojo/detail` template.
     *
     * @param object $topomojo The Topomojo object containing activity details.
     * @param int $duration The duration of the activity in seconds.
     * @param bool $code Optional. A flag to include a code in the view. Default is false.
     * @return void
     */
    public function display_detail($topomojo, $duration, $code = false) {
        $data = new stdClass();
        $data->name = $topomojo->name;
        $data->intro = $topomojo->intro;
        $data->code = $code;
        $data->durationtext = get_string('durationtext', 'mod_topomojo');
        $data->duration = $duration / 60;
        echo $this->render_from_template('mod_topomojo/detail', $data);
    }

    /**
     * Renders the start form for Topomojo activity with Markdown content.
     *
     * This function prepares and renders a start form for a Topomojo activity. It processes
     * the provided Markdown content, formats it, and replaces any placeholder URLs with
     * the base URL configured for Topomojo. The prepared data is then rendered using
     * the `mod_topomojo/startform` template.
     *
     * @param string $url The URL to be used for rendering.
     * @param string $workspace The workspace or context where the Topomojo activity is used.
     * @param string $markdown The Markdown content to be displayed in the start form.
     * @return void
     */
    public function display_startform($url, $workspace, $markdown) {
        $data = new stdClass();
        $data->url = $url;
        $data->workspace = $workspace;
        $options['trusted'] = true;
        $options['noclean'] = true;
        $options['nocache'] = true;

        $data->markdown = format_text($markdown, FORMAT_MARKDOWN, $options);
        $url = get_config('topomojo', 'topomojobaseurl');
        //$data->markdown = str_replace("src=\"/docs/", "src=\"" . $url . "docs/", $data->markdown, $i);
        //$data->markdown = str_replace("src=\"docs/", "src=\"" . $url . "docs/", $data->markdown, $i);
        $data->markdown = $this->clean_markdown($markdown);


        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_topomojo/startform', $data);
    }

    /**
     * Renders the stop form for Topomojo activity.
     *
     * This function prepares and renders a stop form for a Topomojo activity. It prepares
     * the data required for the stop form, which includes the URL and workspace, and then
     * renders the data using the `mod_topomojo/stopform` template.
     *
     * @param string $url The URL to be used for rendering.
     * @param string $workspace The workspace or context where the Topomojo activity is used.
     * @return void
     */
    public function display_stopform($url, $workspace) {
        $data = new stdClass();
        $data->url = $url;
        $data->workspace = $workspace;
        echo $this->render_from_template('mod_topomojo/stopform', $data);
    }

    /**
     * Renders the return form for Topomojo activity.
     *
     * This function prepares and renders a return form for a Topomojo activity. It includes
     * the URL, an identifier, and a localized return text message. The form is rendered
     * using the `mod_topomojo/returnform` template.
     *
     * @param string $url The URL to be used for rendering the return form.
     * @param int $id The identifier related to the Topomojo activity.
     * @return void
     */
    public function display_return_form($url, $id) {
        $data = new stdClass();
        $data->url = $url;
        $data->id = $id;
        $data->returntext = get_string('returntext', 'mod_topomojo');;
        echo $this->render_from_template('mod_topomojo/returnform', $data);
    }

    /**
     * Renders a link page for Topomojo activity.
     *
     * This function prepares and renders a link page for a Topomojo activity. It includes
     * the URL for the launch point and a localized link text. The page is rendered using
     * the `mod_topomojo/link` template.
     *
     * @param string $launchpointurl The URL to be used as the launch point for the Topomojo activity.
     * @return void
     */
    public function display_link_page($launchpointurl) {
        $data = new stdClass();
        $data->url = $launchpointurl;
        $data->playerlinktext = get_string('playerlinktext', 'mod_topomojo');
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_topomojo/link', $data);

    }

    /**
     * Renders challenge instructions using Markdown.
     *
     * This function takes Markdown-formatted challenge instructions, formats it, and
     * renders it using the `mod_topomojo/challenge` Mustache template. The Markdown text
     * is formatted with trusted content settings, and the resulting HTML is displayed.
     *
     * @param string $markdown The Markdown-formatted instructions to be rendered.
     * @return void
     */
    public function render_challenge_instructions($markdown) {
        $data = new stdClass();
        //$options['trusted'] = true;
        //$options['noclean'] = true;
        //$options['nocache'] = true;

        //$data->markdown = format_text($markdown, FORMAT_MARKDOWN, $options);
        $data->markdown = $this->clean_markdown($markdown);


        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_topomojo/challenge', $data);
    }

    /**
     * Renders an embedded page with a launch URL, Markdown content, and a VM list.
     *
     * This function prepares data for an embedded page by including a launch URL, formatted
     * Markdown content, and a list of virtual machines (VMs). The Markdown is formatted with
     * trusted content settings, and specific URLs within the Markdown are adjusted. The data
     * is then rendered using the `mod_topomojo/embed` Mustache template.
     *
     * @param string $launchpointurl The URL to be used for launching the embedded page.
     * @param string $markdown The Markdown-formatted content to be included on the page.
     * @param array $vmlist An array of virtual machines to be displayed on the page.
     * @return void
     */
    public function display_embed_page($launchpointurl, $markdown, $vmlist) {
        $data = new stdClass();
        $data->url = $launchpointurl;
        //$data->fullscreen = get_string('fullscreen', 'mod_topomojo');

        $data->vmlist = $vmlist;

        //$options['trusted'] = true;
        //$options['noclean'] = true;
        //$options['nocache'] = true;

        //$data->markdown = format_text($markdown, FORMAT_MARKDOWN, $options);
        //$url = get_config('topomojo', 'topomojobaseurl');
        //$data->markdown = str_replace("img src=\"/docs/", "img src=\"" . $url . "docs/", $data->markdown, $i);
        //$data->markdown = str_replace("img src=\"docs/", "img src=\"" . $url . "docs/", $data->markdown, $i);

        $data->markdown = $this->clean_markdown($markdown);

        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_topomojo/embed', $data);

    }

    /**
     * Renders the grade information for a user in relation to a topomojo activity.
     *
     * This function retrieves the grade for a specified user or the current user if none is specified,
     * formats the grade, and renders it using the `mod_topomojo/grade` Mustache template.
     * The grade is displayed along with the maximum possible grade for the topomojo activity.
     *
     * @param object $topomojo The topomojo settings object containing activity details.
     * @param int|null $user Optional. The user ID for whom the grade is to be displayed. Defaults to null,
     *                         in which case the current user's ID is used.
     * @return void
     */
    public function display_grade($topomojo, $user = null) {
        global $USER;

        if (is_null($user)) {
            $user = $USER->id;
        }
        $usergrades = \mod_topomojo\utils\grade::get_user_grade($topomojo, $user);
        // Should only be 1 grade, but we'll always get end just in case
        $usergrade = end($usergrades);
        $data = new stdClass();
        $data->overallgrade = get_string('overallgrade', 'topomojo');
        $data->grade = number_format($usergrade, 2);
        $data->maxgrade = $topomojo->grade;
        echo $this->render_from_template('mod_topomojo/grade', $data);
    }

    /**
     * Renders the score information for a specific topomojo attempt.
     *
     * This function retrieves the score for a given topomojo attempt from the database,
     * along with the maximum grade for the associated topomojo activity. It then formats
     * this data and renders it using the `mod_topomojo/score` Mustache template.
     *
     * @param int $attempt The ID of the topomojo attempt whose score is to be displayed.
     * @return void
     */
    public function display_score($attempt) {
        global $USER;
        global $DB;

        $rec = $DB->get_record("topomojo_attempts", ["id" => $attempt]);

        $data = new stdClass();
        $data->score = $rec->score;
        $data->maxgrade = $DB->get_field("topomojo", "grade", ['id' => $rec->topomojoid]);
        echo $this->render_from_template('mod_topomojo/score', $data);
    }

    /**
     * Renders a table of topomojo attempts with optional details and grading information.
     *
     * This function generates a table displaying details of topomojo attempts. The table
     * can include user information, event IDs, workspace details, start and finish times,
     * and scores based on the parameters provided. It then renders this table using the
     * `mod_topomojo/history` Mustache template.
     *
     * @param array $attempts An array of topomojo attempt records to display.
     * @param bool $showgrade Whether to include the score for each attempt in the table.
     * @param bool $showuser Whether to include user details in the table.
     * @param bool $showdetail Whether to include workspace details in the table.
     * @return void
     */
    public function display_attempts($attempts, $showgrade, $showuser = false, $showdetail = false) {
        global $DB;
        $data = new stdClass();
        $data->tableheaders = new stdClass();
        $data->tabledata = [];

        if ($showuser) {
            $data->tableheaders->username = get_string('username', 'mod_topomojo');
            $data->tableheaders->eventguid = get_string('eventid', 'mod_topomojo');
        }
        if ($showdetail) {
            $data->tableheaders->name = get_string('workspace', 'mod_topomojo');
        }
        $data->tableheaders->timestart = get_string('timestart', 'mod_topomojo');
        $data->tableheaders->timefinish = get_string('timefinish', 'mod_topomojo');

        if ($showgrade) {
            $data->tableheaders->score = get_string('score', 'mod_topomojo');
        }

        if ($attempts) {
            foreach ($attempts as $attempt) {
                $rowdata = new stdClass();
                if ($showuser) {
                    $user = $DB->get_record("user", ['id' => $attempt->userid]);
                    $rowdata->username = fullname($user);
                    if ($attempt->eventid) {
                        $rowdata->eventguid = $attempt->eventid;
                    } else {
                        $rowdata->eventguid = "-";
                    }
                }
                if ($showdetail) {
                    $topomojo = $DB->get_record("topomojo", ['id' => $attempt->topomojoid]);
                    $rowdata->name = $topomojo->name;
                    $rowdata->moduleurl = new moodle_url('/mod/topomojo/view.php', ["c" => $topomojo->id]);
                }
                $rowdata->timestart = userdate($attempt->timestart);
                if ($attempt->state == \mod_topomojo\topomojo_attempt::FINISHED) {
                    $rowdata->timefinish = userdate($attempt->timefinish);
                } else {
                    $rowdata->timefinish = null;
                }
                if ($showgrade) {
                    if ($attempt->score !== null) {
                        $rowdata->score = $attempt->score;
                        $rowdata->attempturl = new moodle_url('/mod/topomojo/viewattempt.php', ["a" => $attempt->id]);
                    } else {
                        $rowdata->score = "-";
                    }
                }
                $data->tabledata[] = $rowdata;
            }
        }
        echo $this->render_from_template('mod_topomojo/history', $data);
    }

    /**
     * Renders control elements for managing the topomojo event time.
     *
     * This function prepares and renders control elements such as start and end times
     * for a topomojo event. Optionally, if the `$extend` parameter is true, it also
     * includes an extension option in the controls. The rendered controls are displayed
     * using the `mod_topomojo/controls` Mustache template.
     *
     * @param int $starttime The start time of the topomojo event, in Unix timestamp format.
     * @param int $endtime The end time of the topomojo event, in Unix timestamp format.
     * @param bool $extend Optional. Whether to include an extension option in the controls.
     * @return void
     */
    public function display_controls($starttime, $endtime, $extend = false) {

        $data = new stdClass();
        $data->starttime = $starttime;
        $data->endtime = $endtime;
        if ($extend) {
            $data->extend = get_string('extendevent', 'mod_topomojo');
        }

        echo $this->render_from_template('mod_topomojo/controls', $data);
    }

    /**
     * Initialize the renderer with some variables
     *
     * @param \mod_topomojo\topomojo $topomojo
     * @param moodle_url                 $pageurl Always require the page url
     * @param array                      $pagevars (optional)
     */
    public function init($topomojo, $pageurl, $pagevars = []) {
        $this->pagevars = $pagevars;
        $this->pageurl = $pageurl;
        $this->topomojo = $topomojo;
    }

    /**
     * Renders the topomojo to the page
     *
     * @param \mod_topomojo\topomojo_attempt $attempt
     */
    public function render_quiz(\mod_topomojo\topomojo_attempt $attempt, $pageurl, $cmid) {

        $output = '';

            //$output .= html_writer::start_div();
        //$output .= $this->topomojo_intro();
        //$output .= html_writer::end_div();

        $output .= html_writer::start_div('', ['id' => 'topomojoview']);
        // Start the form.
        $output .= html_writer::start_tag('form',
                ['action' => new moodle_url($pageurl,
                ['id' => $cmid]), 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                ]);
        /*
        if ($this->topomojo->is_instructor()) {
            $instructions = get_string('instructortopomojoinst', 'topomojo');
        } else {
            $instructions = get_string('studenttopomojoinst', 'topomojo');
        }
        $loadingpix = $this->output->pix_icon('i/loading', 'loading...');
        $output .= html_writer::start_div('topomojoloading', array('id' => 'loadingbox'));
        $output .= html_writer::tag('p', get_string('loading', 'topomojo'), array('id' => 'loadingtext'));
        $output .= $loadingpix;
        $output .= html_writer::end_div();

            // topomojo instructions
        $output .= html_writer::start_div('topomojobox', array('id' => 'instructionsbox'));
            $output .= $instructions;
        $output .= html_writer::end_div();
        */

        foreach ($attempt->getSlots() as $slot) {
            // Render question form.
            $output .= $this->render_question_form($slot, $attempt);
        }

        $params = [
            'id' => $this->topomojo->getCM()->id,
            'a' => $attempt->id,
            'stop' => 'submittopomojo',
        ];

        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'slots',
        'value' => implode(',', $attempt->getSlots())]);

        $endurl = new moodle_url('/mod/topomojo/view.php', $params);
        //$output .= $this->output->single_button($endurl, 'Submit Quiz', 'get');
        $output .= $this->output->single_button($endurl, 'Submit Quiz');

        // Finish the form.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        $output .= html_writer::end_div();
        echo $output;
    }


    /**
     * Render a specific question in its own form so it can be submitted
     * independently of the rest of the questions
     *
     * @param int                                $slot the id of the question we're rendering
     * @param \mod_topomojo\topomojo_attempt $attempt
     *
     * @return string HTML fragment of the question
     */
    public function render_question_form($slot, $attempt) {

        $output = '';
        $output .= $attempt->render_question($slot);

        return $output;
    }

    /**
     * Render a specific attempt
     *
     * @param \mod_topomojo\topomojo_attempt $attempt
     */
    public function render_attempt($attempt) {

        $this->showMessage();

        $timenow = time();
        $timeopen = $this->topomojo->topomojo->timeopen;
        $timeclose = $this->topomojo->topomojo->timeclose;
        $timelimit = $this->topomojo->topomojo->duration;

        $state = $this->topomojo->get_openclose_state();

        if ($state == 'unopen') {
            echo html_writer::start_div('topomojobox');
            echo html_writer::tag('p', get_string('notopen', 'topomojo') . userdate($timeopen), ['id' => 'quiz_notavailable']);
            echo html_writer::end_tag('div');

        } else if ($state == 'closed') {
            echo html_writer::start_div('topomojobox');
            echo html_writer::tag('p', get_string('closed', 'topomojo'). userdate($timeclose), ['id' => 'quiz_notavailable']);
            echo html_writer::end_tag('div');
        }

        $reviewoptions = $this->topomojo->get_review_options();
        $canreviewattempt = $this->topomojo->canreviewattempt($reviewoptions, $state);
        $canreviewmarks = $this->topomojo->canreviewmarks($reviewoptions, $state);

        // Show overall grade
        if ($canreviewmarks && (!$this->topomojo->is_instructor())) {
            $this->display_grade($this->topomojo->topomojo);
        }

        if ($attempt && ($canreviewattempt || $this->topomojo->is_instructor())) {
            foreach ($attempt->getSlots() as $slot) {
                if ($this->topomojo->is_instructor()) {
                    echo $this->render_edit_review_question($slot, $attempt);
                } else {
                    echo $this->render_review_question($slot, $attempt);
                }
            }
        } else if ($attempt && !$canreviewattempt) {
            echo html_writer::tag('p', get_string('noreview', 'topomojo'), ['id' => 'review_notavailable']);
        }

        $this->render_return_button();
    }

    /**
     * Renders an individual question review
     *
     * This is the "edit" version that are for instructors/users who have the control capability
     *
     * @param int                                $slot
     * @param \mod_topomojo\topomojo_attempt $attempt
     *
     * @return string HTML fragment
     */
    public function render_edit_review_question($slot, $attempt) {

        $qnum = $attempt->get_question_number();
        $output = '';

        $output .= html_writer::start_div('topomojobox', ['id' => 'q' . $qnum . '_container']);

        $action = clone($this->pageurl);

        $output .= html_writer::start_tag('form',
            ['action'  => '', 'method' => 'post',
                  'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                  'id'      => 'q' . $qnum, 'class' => 'topomojo_question',
                  'name'    => 'q' . $qnum]);

        $output .= $attempt->render_question($slot, true, 'edit');

        $output .= html_writer::empty_tag('input', ['type'  => 'hidden', 'name' => 'slots',
                                                         'value' => $slot]);
        $output .= html_writer::empty_tag('input', ['type'  => 'hidden', 'name' => 'slot',
                                                         'value' => $slot]);
        $output .= html_writer::empty_tag('input', ['type'  => 'hidden', 'name' => 'action',
                                                         'value' => 'savecomment']);
        $output .= html_writer::empty_tag('input', ['type'  => 'hidden', 'name' => 'sesskey',
                                                         'value' => sesskey()]);

        $savebtn = html_writer::empty_tag('input', ['type'  => 'submit', 'name' => 'submit',
                                                         'value' => get_string('savequestion', 'topomojo'),
                                                         'class' => 'btn btn-secondary']);

        $mark = $attempt->get_slot_mark($slot);
        $maxmark = $attempt->get_slot_max_mark($slot);

        $output .= html_writer::start_tag('p');
        $output .= 'Marked ' . $mark . ' / ' . $maxmark;
        $output .= html_writer::end_tag('p');

        // only add save button if attempt is finished
        if ($attempt->getState() === 'finished') {
            $output .= html_writer::div($savebtn, 'save_row');
        }

        // Finish the form.
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_div();

        return $output;
    }
    /**
     * Render a review question with no editing capabilities.
     *
     * Reviewing will be based upon the after review options specified in module settings
     *
     * @param int                                $slot
     * @param \mod_topomojo\topomojo_attempt $attempt
     *
     * @return string HTML fragment for the question
     */
    public function render_review_question($slot, $attempt) {

        $qnum = $attempt->get_question_number();
        $when = $this->topomojo->get_openclose_state();

        $output = '';

        $output .= html_writer::start_div('topomojobox', ['id' => 'q' . $qnum . '_container']);

        $output .= $attempt->render_question($slot, true, $this->topomojo->get_review_options(), $when);

        $output .= html_writer::end_div();

        return $output;
    }

    /**
     * Renders a button that allows users to return to the topomojo view page.
     *
     * This function generates an HTML button that, when clicked, directs the user back to the
     * topomojo view page for the current course module. The button is created using Moodle's
     * `single_button` method and is labeled "Return".
     *
     * @return void
     */
    public function render_return_button() {
        $output = '';
            $params = [
                'id' => $this->topomojo->getCM()->id,
                //'action' => ''
            ];
            $starturl = new moodle_url('/mod/topomojo/view.php', $params);
            $output .= $this->output->single_button($starturl, 'Return', 'get');
            echo $output;
    }

    private function clean_markdown($markdown) {
        $cleanlines = array();
        $url = get_config('topomojo', 'topomojobaseurl');
        $lines = preg_split("/\r\n|\n|\r/", $markdown);

        //$markdown = "![page2-nestednetworkdiagram.jpg](/docs/6a4d6fa4-1147-47b9-ae76-7d914854f717/page2-nestednetworkdiagram.jpg)";
        // Match the pattern for markdown images
        $pattern = '/(!\[.*\]\()(.*\))/i';
        $replace = '${1}' . $url .'${2}';
        foreach ($lines as $line) {
            $clean = preg_replace($pattern, $replace, $line);
            array_push($cleanlines, $clean);
        }
        $markdown = implode("\n", $cleanlines);

        $options['nocache'] = true;
        $options['trusted'] = true;
        $options['noclean'] = true;
   
        $cleaned = format_text($markdown, FORMAT_MARKDOWN, $options);
        $url = get_config('topomojo', 'topomojobaseurl');

        // Add classes
        $cleaned = str_replace("img src=", "img class=\"img-fluid rounded\" src=", $cleaned);

        // Risky due to lack of verification of img...
        //$cleaned = str_replace("src=\"/docs/", "src=\"" . $url . "/docs/", $cleaned, $i);

        return $cleaned;
    }
}


