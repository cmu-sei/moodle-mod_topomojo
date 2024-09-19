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

/*
Group Quiz Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md)
Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

 /**
  * Subclass of the question bank view class to change the way it works/looks
  *
  * @package     mod_topomojo
  * @copyright   2020 Carnegie Mellon University
  * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

    /**
     * Retrieves and configures the available question bank plugins and columns.
     *
     * This function initializes the core question bank columns, checks for additional
     * columns from the Topomojo plugin and other question bank plugins, and includes them
     * if they are enabled and meet the necessary criteria. It returns an array of configured
     * question bank columns.
     *
     * @return array An array of question bank column objects, indexed by their short names.
     */
    protected function get_question_bank_plugins(): array {
        $questionbankclasscolumns = [];
        $corequestionbankcolumns = [
            'add_action_column',
            'checkbox_column',
            'question_type_column',
            'question_name_text_column',
            'delete_action_column',
            'preview_action_column',
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
    public function render($pagevars, $tabname): string {
        ob_start();
        $this->display();
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
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

    /**
     * Displays a form for creating a new question in the given category.
     *
     * This function renders the interface for creating a new question, including a button
     * to initiate the question creation process if the user has the necessary permissions.
     * It overrides the base class method to call a custom version of `create_new_question_button`.
     *
     * @param \stdClass $category The question category where the new question will be created.
     * @param bool $canadd Whether the user has the permission to add new questions.
     * @throws \coding_exception If an error occurs during the creation of the form.
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
        echo $OUTPUT->single_button($url, $caption, 'get', ['disabled' => $disabled, 'title' => $tooltip]);

        if (!$choiceformprinted) {
            echo '<div id="qtypechoicecontainer">';
            echo print_choose_qtype_to_add_form([], $enabledtypes);
            echo "</div>\n";
            $choiceformprinted = true;
        }
    }

    /**
     * Displays the bottom controls for the question bank interface.
     *
     * This function renders a section at the bottom of the question bank interface with
     * controls for interacting with selected questions. It includes a button to add selected
     * questions to the lab, but this button is only displayed if the user has the necessary
     * capability. The button is initially disabled.
     *
     * @param \context $catcontext The context of the category where the question bank is displayed.
     *
     * @return void
     */
    protected function display_bottom_controls(\context $catcontext): void {
        $cmoptions = new \stdClass();
        $cmoptions->hasattempts = !empty($this->topomojohasattempts);

        $canuseall = has_capability('moodle/question:useall', $catcontext);

        echo \html_writer::start_tag('div', ['class' => 'pt-2']);
        if ($canuseall) {
            // Add selected questions to the lab.
            $params = [
                'type' => 'submit',
                'name' => 'addquestionlist',
                'class' => 'btn btn-primary',
                'value' => get_string('addselectedquestionstotopomojo', 'topomojo'),
                'data-action' => 'toggle',
                'data-togglegroup' => 'qbank',
                'data-toggle' => 'action',
                'disabled' => true,
            ];
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
    public function preview_question_url() {
        return topomojo_question_preview_url($this->topomojo, $question);
    }

}
