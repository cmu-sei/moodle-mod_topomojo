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
 * Renderer outputting the quiz editing UI.
 *
 * @package mod_topomojo
 * @copyright 2016 Carnegie Mellon University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
Topomojo Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

namespace mod_topomojo\output;
defined('MOODLE_INTERNAL') || die();

use \mod_topomojo\traits\renderer_base;

class edit_renderer extends \plugin_renderer_base {

    use renderer_base;

    public $has_attempts;

    /**
     * Render the list questions view for the edit page
     *
     * @param array  $questions Array of questions
     */
    public function listquestions($topomojohasattempts, $questions, $questionbankview, $cm, $pagevars) {
        global $CFG;
	    $this->has_attempts = $topomojohasattempts;
	    echo \html_writer::start_div('row', array('id' => 'questionrow'));
        echo \html_writer::start_div('inline-block col-sm-6');
        echo \html_writer::tag('h2', get_string('questionlist', 'topomojo'));
        echo \html_writer::div('', 'topomojostatusbox topomojohiddenstatus', array('id' => 'editstatus'));
        if (count($questions) == 0) {
            echo \html_writer::tag('p', get_string('noquestions', 'topomojo'));
        }

        echo $this->show_questionlist($questions);

        echo \html_writer::end_div(); //inline-block-span6 questionlist

        if ($this->has_attempts) {
            echo \html_writer::start_div('inline-block span6');
            echo \html_writer::tag('h2', get_string('questionbank', 'question'));
            echo \html_writer::tag('p', get_string('cannoteditafterattempts', 'topomojo'));
            echo \html_writer::end_div();
        } else {
            echo \html_writer::start_div('inline-block col-sm-6');
            echo $questionbankview->display($pagevars, 'editq');
            echo \html_writer::end_div(); // inline-block-span6 questionbank

            echo \html_writer::end_div(); // questionrow

            // TODO update to amd
            $this->page->requires->js('/mod/topomojo/js/core.js');
            $this->page->requires->js('/mod/topomojo/js/sortable/sortable.min.js');
            // TODO update to amd
            $this->page->requires->js('/mod/topomojo/js/edit_quiz.js');

            // next set up a class to pass to js for js info
            $jsinfo = new \stdClass();
            $jsinfo->sesskey = sesskey();
            $jsinfo->siteroot = $CFG->wwwroot;
            $jsinfo->cmid = $cm->id;
            $jsinfo->topomojoid = $this->topomojo->topomojo->id;

            // print jsinfo to javascript
            echo \html_writer::start_tag('script', array('type' => 'text/javascript'));
            echo "topomojoinfo = " . json_encode($jsinfo);
            echo \html_writer::end_tag('script');

            $this->page->requires->strings_for_js(array(
                'success',
                'error'
            ), 'core');
        }
    }


    /**
     * Builds the question list from the questions passed in
     *
     * @param array $questions an array of \mod_topomojo\topomojo_question
     * @return string
     */
    protected function show_questionlist($questions) {

        $return = '<ol class="questionlist">';
        $questioncount = count($questions);
        $questionnum = 1;
        foreach ($questions as $question) {
            /** @var \mod_topomojo\topomojo_question $question */
            $return .= '<li data-questionid="' . $question->getId() . '">';
            $return .= $this->display_question_block($question, $questionnum, $questioncount);
            $return .= '</li>';
            $questionnum++;
        }
        $return .= '</ol>';

        return $return;
    }

    /**
     * sets up what is displayed for each question on the edit quiz question listing
     *
     * @param \mod_topomojo\topomojo_question $question
     * @param int                                 $qnum The question number we're currently on
     * @param int                                 $qcount The total number of questions
     *
     * @return string
     */
    protected function display_question_block($question, $qnum, $qcount) {

        $return = '';

	    // TODO disable reordering if attepts exist
        $dragicon = new \pix_icon('i/dragdrop', 'dragdrop');
        $return .= \html_writer::div($this->output->render($dragicon), 'dragquestion');

        $return .= \html_writer::div(print_question_icon($question->getQuestion()), 'icon');

        $namehtml = \html_writer::start_tag('p');

        $namehtml .= $question->getQuestion()->name . '<br />';
        $namehtml .= get_string('points', 'topomojo') . ': ' . number_format($question->getPoints(), 2);
        $namehtml .= \html_writer::end_tag('p');

        $return .= \html_writer::div($namehtml, 'name');

        $controlHTML = '';

        $spacericon = new \pix_icon('spacer', 'space', null, array('class' => 'smallicon space'));
        $controlHTML .= \html_writer::start_tag('noscript');
        if ($qnum > 1) { // if we're on a later question than the first one add the move up control

            $moveupurl = clone($this->pageurl);
            $moveupurl->param('action', 'moveup');
            $moveupurl->param('questionid', $question->getId()); // add the topomojoqid so that the question manager handles the translation

            $alt = get_string('questionmoveup', 'mod_topomojo', $qnum);

            $upicon = new \pix_icon('t/up', $alt);
            $controlHTML .= \html_writer::link($moveupurl, $this->output->render($upicon));
        } else {
            $controlHTML .= $this->output->render($spacericon);
        }
        if ($qnum < $qcount) { // if we're not on the last question add the move down control

            $movedownurl = clone($this->pageurl);
            $movedownurl->param('action', 'movedown');
            $movedownurl->param('questionid', $question->getId());

            $alt = get_string('questionmovedown', 'mod_topomojo', $qnum);

            $downicon = new \pix_icon('t/down', $alt);
            $controlHTML .= \html_writer::link($movedownurl, $this->output->render($downicon));

        } else {
            $controlHTML .= $this->output->render($spacericon);
        }

        $controlHTML .= \html_writer::end_tag('noscript');

	    // do not allow edit or delete if attempts exist
	    if (!$this->has_attempts) {
            $editurl = clone($this->pageurl);
            $editurl->param('action', 'editquestion');
            $editurl->param('topomojoquestionid', $question->getId());
            $alt = get_string('questionedit', 'topomojo', $qnum);
            $deleteicon = new \pix_icon('t/edit', $alt);
            $controlHTML .= \html_writer::link($editurl, $this->output->render($deleteicon));
            $deleteurl = clone($this->pageurl);
            $deleteurl->param('action', 'deletequestion');
            $deleteurl->param('questionid', $question->getId());
            $alt = get_string('questiondelete', 'mod_topomojo', $qnum);
            $deleteicon = new \pix_icon('t/delete', $alt);
            $controlHTML .= \html_writer::link($deleteurl, $this->output->render($deleteicon));
        }
        $previewurl = \qbank_previewquestion\helper::question_preview_url($question->getQuestion()->id);
        $previewicon = new \pix_icon('t/preview', get_string('preview'));
        $options = ['height' => 800, 'width' => 900];
        $popup = new \popup_action('click', $previewurl, 'preview', $options);
        $actionlink = new \action_link($previewurl, '', $popup, array('target' => '_blank'), $previewicon);
        $controlHTML .= $this->output->render($actionlink);


        $return .= \html_writer::div($controlHTML, 'controls');

        return $return;

    }

    /**
     * renders the add question form
     *
     * @param moodleform $mform
     */
    public function addquestionform($mform) {

        echo $mform->display();

    }

    public function opensession(){

        echo \html_writer::tag('h3', get_string('editpage_opensession_error', 'topomojo'));

    }

    /**
     * Prints edit page header
     *
     */
    public function print_header() {
        $this->base_header('edit');
        echo $this->output->box_start('generalbox boxaligncenter topomojobox');
    }

    /**
     * Ends the edit page with the footer of Moodle
     *
     */
    public function footer() {

        echo $this->output->box_end();
        $this->base_footer();
    }


}
