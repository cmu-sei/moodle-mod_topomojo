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

/**
 * Behat step definitions for mod_topomojo.
 *
 * @package    mod_topomojo
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for mod_topomojo.
 *
 * Only page resolvers: the plugin's view and edit pages call the TopoMojo API, so the
 * features open the pages that render from Moodle's own data directly.
 *
 * @package    mod_topomojo
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_topomojo extends behat_base {
    /**
     * Convert page names to URLs for steps like 'When I am on the "mod_topomojo > [page name]" page'.
     *
     * Recognised page names are:
     * | Labs | The site-wide lab directory (labs.php). |
     *
     * @param string $page name of the page, with the component name removed.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'labs':
                return new moodle_url('/mod/topomojo/labs.php');

            default:
                throw new Exception('Unrecognised mod_topomojo page type "' . $page . '."');
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "mod_topomojo > [page type]" page'.
     *
     * Recognised page names are:
     * | pagetype | name meaning      | description                                          |
     * | Index    | Course identifier | The course's list of TopoMojo activities (index.php) |
     * | Manage   | Activity name     | The Manage Deployments page (manage.php)             |
     *
     * @param string $type identifies which type of page this is, e.g. 'Manage'.
     * @param string $identifier identifies the particular page, e.g. 'Test lab'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'index':
                $courseid = $this->get_course_id($identifier);
                if (!$courseid) {
                    throw new Exception('The specified course with identifier "' . $identifier . '" does not exist');
                }
                return new moodle_url('/mod/topomojo/index.php', ['id' => $courseid]);

            case 'manage':
                $cm = $this->get_cm_by_activity_name('topomojo', $identifier);
                return new moodle_url('/mod/topomojo/manage.php', ['id' => $cm->id]);

            default:
                throw new Exception('Unrecognised mod_topomojo page type "' . $type . '."');
        }
    }
}
