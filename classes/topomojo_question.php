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

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * A question object
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

class topomojo_question {

    /** @var int $id The question id */
    protected $id;

    /** @var float $points The number of points for the question */
    protected $points;

    /** @var object $question the question object from the question bank questions */
    protected $question;

    /** @var int $slot The quba slot that this question belongs to during page runtime
     *                  This is used during getting questions for the quizdata callback
     */
    protected $slot;


    /**
     * Construct the question
     *
     * @param int    $topomojoqid
     * @param float  $points
     * @param object $question
     */
    public function __construct($topomojoqid, $points, $question) {
        $this->id = $topomojoqid;
        $this->points = $points;
        $this->question = $question;
    }

    /**
     * not used function until we only support 5.4 and higher
     */
    public function JsonSerialize() {
        // to make sue of the is function on json_encode, this class also needs to implement JsonSerializable

        // TODO: This will be supported if Moodle moves to only supporting php 5.4 and higher

    }

    /**
     * Returns the topomojo id
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Returns the number of points for the question
     *
     * @return float
     */
    public function getPoints() {
        return $this->points;
    }

    /**
     * Returns the standard class question object from the question table
     *
     * @return \stdClass
     */
    public function getQuestion() {
        return $this->question;
    }

    /**
     * Sets the slot number
     *
     * @param int $slot
     */
    public function set_slot($slot) {
        $this->slot = $slot;
    }

    /**
     * returns the current slot number
     *
     * @return int
     */
    public function get_slot() {
        return $this->slot;
    }


}

