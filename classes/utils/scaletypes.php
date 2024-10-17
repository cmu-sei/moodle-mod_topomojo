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

namespace mod_topomojo\utils;

defined('MOODLE_INTERNAL') || die();

 /**
  * Class to define grade types for the module
  * Is used in multiple classes/functions
  *
  * @package     mod_topomojo
  * @copyright   2024 Carnegie Mellon University
  * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class scaletypes {

    /** Define grading scale types */
    /**
     * Scale type for the first attempt grade.
     *
     * @var int
     */
    const TOPOMOJO_FIRSTATTEMPT = 1;

     /**
     * Scale type for the last attempt grade.
     *
     * @var int
     */
    const TOPOMOJO_LASTATTEMPT = 2;

    /**
     * Scale type for the average of all attempts.
     *
     * @var int
     */
    const TOPOMOJO_ATTEMPTAVERAGE = 3;

    /**
     * Scale type for the highest grade from all attempts.
     *
     * @var int
     */
    const TOPOMOJO_HIGHESTATTEMPTGRADE = 4;


    /**
     * Return array of scale types keyed by the type name
     *
     * @return array
     */
    public static function get_types() {

        return [
            'firstattempt' => self::TOPOMOJO_FIRSTATTEMPT,
            'lastattempt'  => self::TOPOMOJO_LASTATTEMPT,
            'average'      => self::TOPOMOJO_ATTEMPTAVERAGE,
            'highestgrade' => self::TOPOMOJO_HIGHESTATTEMPTGRADE,
        ];
    }

    /**
     * Returns an array of scale types for display, i.e. a form
     * keyed by the values that each type is
     *
     * @return array
     */
    public static function get_display_types() {

        return [
            self::TOPOMOJO_FIRSTATTEMPT        => get_string('firstattempt', 'topomojo'),
            self::TOPOMOJO_LASTATTEMPT         => get_string('lastattempt', 'topomojo'),
            self::TOPOMOJO_ATTEMPTAVERAGE      => get_string('attemptaverage', 'topomojo'),
            self::TOPOMOJO_HIGHESTATTEMPTGRADE => get_string('highestattempt', 'topomojo'),
        ];
    }

}


