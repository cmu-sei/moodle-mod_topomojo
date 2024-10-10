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

namespace mod_topomojo\question\bank;

/**
 * A column type for the add this question to the topomojo action.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_action_column extends \core_question\local\bank\question_action_base {

    /** @var string caches a lang string used repeatedly. */
    protected $stradd;

    /**
     * Initializes the component with additional strings specific to the TopoMojo plugin.
     *
     * This function overrides the parent `init` method to include additional
     * initialization for the TopoMojo plugin. It sets up a localized string
     * used within the plugin.
     *
     * @return void
     */
    public function init(): void {
        parent::init();
        $this->stradd = get_string('addtotopomojo', 'topomojo');
    }

    /**
     * Retrieves the name identifier for the TopoMojo action.
     *
     * This function returns the name identifier 'addtotopomojoaction',
     * which is used to identify this specific action within the TopoMojo plugin.
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
     * user to add the question to TopoMojo.
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
