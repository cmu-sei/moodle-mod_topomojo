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

namespace mod_topomojo\question;

use mod_topomojo\topomojo_attempt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/datalib.php');

/**
 * A {@see qubaid_condition} for finding all the question usages belonging to a particular topomojo.
 *
 * @package   mod_topomojo
 * @category  question
 * @copyright 2025 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaids_for_topomojo extends \qubaid_join {

    /**
     * Constructor.
     *
     * @param int $topomojoid The topomojo to search.
     * @param bool $includepreviews Whether to include preview attempts
     * @param bool $onlyfinished Whether to only include finished attempts or not
     */
    public function __construct(int $topomojoid, bool $includepreviews = true, bool $onlyfinished = false) {
        $where = 'topomojoa.topomojo = :topomojoatopomojo';
        $params = ['topomojoatopomojo' => $topomojoid];

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state = :statefinished';
            $params['statefinished'] = topomojo_attempt::FINISHED;
        }

        parent::__construct('{topomojo_attempts} topomojoa', 'topomojoa.questionusageid', $where, $params);
    }
}
