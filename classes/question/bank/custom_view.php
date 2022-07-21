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

namespace mod_topomojo\question\bank;

defined('MOODLE_INTERNAL') || die();

/**
 * Subclass of the question bank view class to change the way it works/looks
 *
 * @package     mod_topomojo
 * @copyright   2020 Carnegie Mellon University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
Group Quiz Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

class custom_view extends \core_question\local\bank\view {

    /** @var bool whether the topomojo this is used by has been attemptd. */
    protected $topomojohasattempts = false;

    /**
     * Let the question bank display know whether the topomojo has been attempted,
     * hence whether some bits of UI, like the add this question to the topomojo icon,
     * should be displayed.
     * @param bool $topomojohasattempts whether the topomojo has attempts.
     */
    public function set_topomojo_has_attempts($topomojohasattempts) {
        $this->topomojohasattempts = $topomojohasattempts;
        if ($this->topomojohasattempts) {
            unset($this->visiblecolumns['mod_topomojo\\question\\bank\\add_action_column']);
        }
    }



    protected function get_question_bank_plugins(): array {
        $questionbankclasscolumns = [];
        $corequestionbankcolumns = [
            'add_action_column',
            'checkbox_column',
            'question_type_column',
            'question_name_text_column',
            'preview_action_column'
        ];

        if (question_get_display_preference('qbshowtext', 0, PARAM_BOOL, new \moodle_url(''))) {
            $corequestionbankcolumns[] = 'question_text_row';
        }

        foreach ($corequestionbankcolumns as $fullname) {
            $shortname = $fullname;
            if (class_exists('mod_topomojo\\question\\bank\\' . $fullname)) {
                $fullname = 'mod_topomojo\\question\\bank\\' . $fullname;
                $questionbankclasscolumns[$shortname] = new $fullname($this);
            } else if (class_exists('core_question\\local\\bank\\' . $fullname)) {
                $fullname = 'core_question\\local\\bank\\' . $fullname;
                $questionbankclasscolumns[$shortname] = new $fullname($this);
            } else {
                $questionbankclasscolumns[$shortname] = '';
            }
        }
        $plugins = \core_component::get_plugin_list_with_class('qbank', 'plugin_feature', 'plugin_feature.php');
        foreach ($plugins as $componentname => $plugin) {
            $pluginentrypointobject = new $plugin();
            $plugincolumnobjects = $pluginentrypointobject->get_question_columns($this);
            // Don't need the plugins without column objects.
            if (empty($plugincolumnobjects)) {
                unset($plugins[$componentname]);
                continue;
            }
            foreach ($plugincolumnobjects as $columnobject) {
                $columnname = $columnobject->get_column_name();
                foreach ($corequestionbankcolumns as $key => $corequestionbankcolumn) {
                    if (!\core\plugininfo\qbank::is_plugin_enabled($componentname)) {
                        unset($questionbankclasscolumns[$columnname]);
                        continue;
                    }
                    // Check if it has custom preference selector to view/hide.
                    if ($columnobject->has_preference() && !$columnobject->get_preference()) {
                        continue;
                    }
                    if ($corequestionbankcolumn === $columnname) {
                        $questionbankclasscolumns[$columnname] = $columnobject;
                    }
                }
            }
        }

        // Mitigate the error in case of any regression.
        foreach ($questionbankclasscolumns as $shortname => $questionbankclasscolumn) {
            if (empty($questionbankclasscolumn)) {
                unset($questionbankclasscolumns[$shortname]);
            }
        }

        return $questionbankclasscolumns;
    }


    /**
     * Shows the question bank editing interface.
     *
     * The function also processes a number of actions:
     *
     * Actions affecting the question pool:
     * move           Moves a question to a different category
     * deleteselected Deletes the selected questions from the category
     * Other actions:
     * category      Chooses the category
     * displayoptions Sets display options
     */
//    public function display($tabname, $page, $perpage, $cat,
//                            $recurse, $showhidden, $showquestiontext, $tagids = array()) {
    public function display($pagevars, $tabname): void {
//      global $PAGE, $OUTPUT;
        global $OUTPUT;
        $page = $pagevars['qpage'];
        $perpage = $pagevars['qperpage'];
        $cat = $pagevars['cat'];
        $recurse = $pagevars['recurse'];
        $showhidden = $pagevars['showhidden'];
        $showquestiontext = $pagevars['qbshowtext'];
        $tagids = [];
        if (!empty($pagevars['qtagids'])) {
            $tagids = $pagevars['qtagids'];
        }



        $editcontexts = $this->contexts->having_one_edit_tab_cap($tabname);
        // Category selection form.
        echo $OUTPUT->heading(get_string('questionbank', 'question'), 2);

        array_unshift($this->searchconditions, new \core_question\bank\search\hidden_condition(!$showhidden));
        array_unshift($this->searchconditions, new \core_question\bank\search\category_condition(
            $cat, $recurse, $editcontexts, $this->baseurl, $this->course));
        //array_unshift($this->searchconditions, new topomojo_disabled_condition());
        $this->display_options_form($showquestiontext, '/mod/topomojo/edit.php');

        // Continues with list of questions.
        /*
        $this->display_question_list($this->contexts->having_one_edit_tab_cap($tabname),
            $this->baseurl, $cat, $this->cm,
            null, $page, $perpage, $showhidden, $showquestiontext,
            $this->contexts->having_cap('moodle/question:add'));
        */

        $params['category'] = $cat;
        $pageurl = new \moodle_url('/mod/topomojo/edit.php', $params);



        $this->display_question_list($pageurl, $cat, $recurse, $page,
                $perpage, $this->contexts->having_cap('moodle/question:add'));
    }


    /**
     * generate a url so that when clicked the question will be added
     *
     * @param int $questionid
     *
     * @return \moodle_url Moodle url to add the question
     */
    public function add_to_topomojo_url($questionid) {

        global $CFG;
        $params = $this->baseurl->params();
        $params['questionid'] = $questionid;
        $params['action'] = 'addquestion';
        $params['sesskey'] = sesskey();

        return new \moodle_url('/mod/topomojo/edit.php', $params);

    }

    /*
     * This has been taken from the base class to allow us to call our own version of
     * create_new_question_button.
     *
     * @param $category
     * @param $canadd
     * @throws \coding_exception
     */
    protected function create_new_question_form($category, $canadd): void {
        global $CFG;
        echo '<div class="createnewquestion">';
        if ($canadd) {
            $this->create_new_question_button($category->id, $this->editquestionurl->params(),
                get_string('createnewquestion', 'question'));
        } else {
            print_string('nopermissionadd', 'question');
        }
        echo '</div>';
    }

    /**
     * Print a button for creating a new question. This will open question/addquestion.php,
     * which in turn goes to question/question.php before getting back to $params['returnurl']
     * (by default the question bank screen).
     *
     * This has been taken from question/editlib.php and adapted to allow us to use the $allowedqtypes
     * param on print_choose_qtype_to_add_form
     *
     * @param int $categoryid The id of the category that the new question should be added to.
     * @param array $params Other paramters to add to the URL. You need either $params['cmid'] or
     *      $params['courseid'], and you should probably set $params['returnurl']
     * @param string $caption the text to display on the button.
     * @param string $tooltip a tooltip to add to the button (optional).
     * @param bool $disabled if true, the button will be disabled.
     */
    private function create_new_question_button($categoryid, $params, $caption, $tooltip = '', $disabled = false) {
        global $CFG, $PAGE, $OUTPUT;
        static $choiceformprinted = false;

        $config = get_config('topomojo');
        if (property_exists($config, 'enabledqtypes')) {
            $enabledtypes = explode(',', $config->enabledqtypes);
        } else {
            $enabledtypes = null;
        }
        $params['category'] = $categoryid;
        $url = new \moodle_url('/question/addquestion.php', $params);
        echo $OUTPUT->single_button($url, $caption, 'get', array('disabled'=>$disabled, 'title'=>$tooltip));

        if (!$choiceformprinted) {
            echo '<div id="qtypechoicecontainer">';
            echo print_choose_qtype_to_add_form(array(), $enabledtypes);
            echo "</div>\n";
            $choiceformprinted = true;
        }
    }

    protected function display_bottom_controls(\context $catcontext): void {
        $cmoptions = new \stdClass();
        $cmoptions->hasattempts = !empty($this->topomojohasattempts);

        $canuseall = has_capability('moodle/question:useall', $catcontext);

        echo \html_writer::start_tag('div', ['class' => 'pt-2']);
        if ($canuseall) {
            // Add selected questions to the lab.
            $params = array(
                'type' => 'submit',
                'name' => 'addquestionlist',
                'class' => 'btn btn-primary',
                'value' => get_string('addselectedquestionstotopomojo', 'topomojo'),
                'data-action' => 'toggle',
                'data-togglegroup' => 'qbank',
                'data-toggle' => 'action',
                'disabled' => true,
            );
            echo \html_writer::empty_tag('input', $params);
        }
        echo \html_writer::end_tag('div');
    }
    /**
     * Question preview url.
     *
     * @param \stdClass $question
     * @return \moodle_url
     */
    public function preview_question_url($question) {
        return topomojo_question_preview_url($this->topomojo, $question);
    }

}
