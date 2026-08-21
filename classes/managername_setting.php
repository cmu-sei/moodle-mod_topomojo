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

defined('MOODLE_INTERNAL') || die();

/**
 * Manager-name setting that validates API-key authentication dependencies.
 *
 * @package   mod_topomojo
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_topomojo_managername_setting extends admin_setting_configtext {
    /**
     * Validates that API-key authentication has a configured manager name.
     *
     * @param string $data The submitted manager name.
     * @return true|string True when valid, otherwise an error message.
     */
    public function validate($data) {
        $validation = parent::validate($data);
        if ($validation !== true) {
            return $validation;
        }

        $enableapikey = optional_param(
            's_topomojo_enableapikey',
            get_config('topomojo', 'enableapikey'),
            PARAM_BOOL
        );
        $enablemanagername = optional_param(
            's_topomojo_enablemanagername',
            get_config('topomojo', 'enablemanagername'),
            PARAM_BOOL
        );

        return topomojo_validate_auth_configuration(
            (bool) $enableapikey,
            (bool) $enablemanagername,
            $data
        );
    }
}
