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
 * A column type for the name followed by the start of the question text.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class question_name_text_column extends \qbank_viewquestionname\viewquestionname_column_helper {
    /**
     * Retrieves the name of the question bank column.
     *
     * This function returns a string that represents the name of the column in the
     * question bank. It is used to identify the column and differentiate it from other columns.
     *
     * @return string The name of the question bank column.
     */
    public function get_name(): string {
        return 'questionnametext';
    }

    /**
     * Displays the content of a question in the question bank.
     *
     * This method generates HTML to render the content of a given question. It includes:
     * - A `<div>` container for the content.
     * - An optional `<label>` element if a label is associated with the question.
     * - The question content formatted using `topomojo_question_tostring`.
     *
     * @param object $question The question object to display. It is expected to contain properties
     *                         such as `tags` used by `topomojo_question_tostring`.
     * @param string $rowclasses Additional CSS classes to apply to the row. This parameter is
     *                           not used in the current implementation but is included for
     *                           potential future use.
     *
     * @return void
     */
    protected function display_content($question, $rowclasses): void {
        echo \html_writer::start_tag('div');
        $labelfor = $this->label_for($question);
        if ($labelfor) {
            echo \html_writer::start_tag('label', ['for' => $labelfor]);
	}
	echo shorten_text(format_string($question->name), 100);
        if ($labelfor) {
            echo \html_writer::end_tag('label');
        }
        echo \html_writer::end_tag('div');
    }

    /**
     * Retrieves the required fields for the current context.
     *
     * This method extends the base implementation to include additional fields specific to
     * the current context. It appends fields related to question text and its format, as well
     * as an identifier number for the question bank entry.
     *
     * @return array An array of required field names. The fields include those from the parent
     *               class and additional fields: 'q.questiontext', 'q.questiontextformat', and
     *               'qbe.idnumber'.
     */
    public function get_required_fields(): array {
        $fields = parent::get_required_fields();
        $fields[] = 'q.questiontext';
        $fields[] = 'q.questiontextformat';
        $fields[] = 'qbe.idnumber';
        return $fields;
    }

    /**
     * Loads additional data for a given array of questions.
     *
     * This method extends the base implementation by also loading question tags.
     * It first invokes the parent method to handle the initial loading of additional data,
     * and then calls the parent method to load tags associated with the questions.
     *
     * @param array $questions An array of question objects for which additional data and tags
     *                         should be loaded.
     * @return void
     */
    public function load_additional_data(array $questions) {
        parent::load_additional_data($questions);
        parent::load_question_tags($questions);
    }
}
