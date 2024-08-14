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

/**
 * A column type for the add this question to the topomojo action.
 *
 * @package    mod_topomojo
 * @category   question
 * @copyright  2009 Tim Hunt
 * @author     2021 Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_action_column extends \core_question\local\bank\question_action_base {

    /** @var string caches a lang string used repeatedly. */
    protected $stradd;

    /**
     * Initializes the component with additional strings specific to the Topomojo plugin.
     *
     * This function overrides the parent `init` method to include additional
     * initialization for the Topomojo plugin. It sets up a localized string
     * used within the plugin.
     *
     * @return void
     */
    public function init(): void {
        parent::init();
        $this->stradd = get_string('addtotopomojo', 'topomojo');
    }

    /**
     * Retrieves the name identifier for the Topomojo action.
     *
     * This function returns the name identifier 'addtotopomojoaction',
     * which is used to identify this specific action within the Topomojo plugin.
     *
     * @return string The name identifier of the action.
     */
    public function get_name() {
        return 'addtotopomojoaction';
    }

    /**
     * Displays the content for a given question with specific row classes.
     *
     * This function checks if the user has the capability to use the given question.
     * If the user has the necessary capability, an icon is printed which allows the
     * user to add the question to Topomojo.
     *
     * @param stdClass $question The question object containing the question's data.
     * @param string $rowclasses A string of CSS classes to apply to the row.
     * @return void
     */
    protected function display_content($question, $rowclasses) {
        if (!question_has_capability_on($question, 'use')) {
            return;
        }
        $this->print_icon('t/add', $this->stradd, $this->qbank->add_to_topomojo_url($question->id));
    }
}
