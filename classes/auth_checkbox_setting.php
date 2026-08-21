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

defined('MOODLE_INTERNAL') || die();

/**
 * Checkbox setting that validates the TopoMojo authentication setting pair.
 *
 * Moodle saves admin settings one at a time, so this setting reads the other
 * checkbox from the submitted form when validating the pair. This prevents an
 * invalid combination from being saved while still allowing both checkboxes
 * to be changed in one submission.
 *
 * @package   mod_topomojo
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_topomojo_auth_checkbox_setting extends admin_setting_configcheckbox {

    /**
     * Writes the setting after validating the submitted authentication pair.
     *
     * @param mixed $data The submitted checkbox value.
     * @return string Empty string on success, otherwise an error message.
     */
    public function write_setting($data) {
        $enableapikey = $this->get_submitted_checkbox_value(
            'enableapikey',
            $this->name === 'enableapikey' ? $data : get_config('topomojo', 'enableapikey')
        );
        $enablemanagername = $this->get_submitted_checkbox_value(
            'enablemanagername',
            $this->name === 'enablemanagername' ? $data : get_config('topomojo', 'enablemanagername')
        );

        $validation = topomojo_validate_auth_configuration($enableapikey, $enablemanagername);
        if ($validation !== true) {
            return $validation;
        }

        return parent::write_setting($data);
    }

    /**
     * Gets a checkbox value from the submitted admin settings form.
     *
     * @param string $name The short setting name.
     * @param mixed $default The fallback value when the setting was not submitted.
     * @return bool
     */
    private function get_submitted_checkbox_value($name, $default) {
        $submitted = optional_param('s_topomojo_' . $name, null, PARAM_BOOL);
        if ($submitted !== null) {
            return (bool) $submitted;
        }

        return (bool) $default;
    }
}
