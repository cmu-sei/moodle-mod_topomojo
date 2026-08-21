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
 * Unit tests for locallib.php functions.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');
require_once($CFG->libdir . '/adminlib.php');

/**
 * Unit tests for topomojo locallib functions.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::setup
 */
class locallib_test extends \advanced_testcase {

    /**
     * Test the API-key and external-manager configuration matrix.
     *
     * @dataProvider auth_configuration_provider
     * @param bool $enableapikey
     * @param bool $enablemanagername
     * @param bool $valid
     */
    public function test_validate_auth_configuration(
        bool $enableapikey,
        bool $enablemanagername,
        bool $valid
    ) {
        $result = topomojo_validate_auth_configuration($enableapikey, $enablemanagername);

        if ($valid) {
            $this->assertTrue($result);
        } else {
            $this->assertSame(
                get_string('configapikeymanagerrequired', 'topomojo'),
                $result
            );
        }
    }

    /**
     * Provides authentication configuration combinations.
     *
     * @return array
     */
    public static function auth_configuration_provider(): array {
        return [
            'API key off, manager off' => [false, false, true],
            'API key off, manager on' => [false, true, true],
            'API key on, manager on' => [true, true, true],
            'API key on, manager off' => [true, false, false],
        ];
    }

    /**
     * Test that an invalid legacy configuration has a clear runtime error.
     */
    public function test_invalid_auth_configuration_has_clear_runtime_error() {
        $this->resetAfterTest();
        set_config('enableapikey', 1, 'topomojo');
        set_config('enablemanagername', 0, 'topomojo');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('configapikeymanagerrequired', 'topomojo'));

        topomojo_require_valid_auth_configuration();
    }

    /**
     * Test that the settings checkbox rejects enabling the API key without a manager user.
     */
    public function test_setting_rejects_apikey_without_manager() {
        $this->resetAfterTest();
        set_config('enableapikey', 0, 'topomojo');
        set_config('enablemanagername', 0, 'topomojo');

        $setting = new \mod_topomojo_auth_checkbox_setting(
            'topomojo/enableapikey',
            'API key',
            '',
            0
        );

        $result = $setting->write_setting(1);

        $this->assertSame(get_string('configapikeymanagerrequired', 'topomojo'), $result);
        $this->assertEmpty(get_config('topomojo', 'enableapikey'));
    }

    /**
     * Test that the settings checkbox accepts the API key when the manager user is enabled.
     */
    public function test_setting_accepts_apikey_with_manager() {
        $this->resetAfterTest();
        set_config('enableapikey', 0, 'topomojo');
        set_config('enablemanagername', 1, 'topomojo');

        $setting = new \mod_topomojo_auth_checkbox_setting(
            'topomojo/enableapikey',
            'API key',
            '',
            0
        );

        $this->assertSame('', $setting->write_setting(1));
        $this->assertEquals(1, get_config('topomojo', 'enableapikey'));
    }

    /**
     * Test that disabling the manager user is rejected while the API key is enabled.
     */
    public function test_setting_rejects_disabling_manager_with_apikey() {
        $this->resetAfterTest();
        set_config('enableapikey', 1, 'topomojo');
        set_config('enablemanagername', 1, 'topomojo');

        $setting = new \mod_topomojo_auth_checkbox_setting(
            'topomojo/enablemanagername',
            'Manager user',
            '',
            0
        );

        $result = $setting->write_setting(0);

        $this->assertSame(get_string('configapikeymanagerrequired', 'topomojo'), $result);
        $this->assertEquals(1, get_config('topomojo', 'enablemanagername'));
    }

    /**
     * Test that the manager user can be disabled when the API key is disabled.
     */
    public function test_setting_allows_disabling_manager_without_apikey() {
        $this->resetAfterTest();
        set_config('enableapikey', 0, 'topomojo');
        set_config('enablemanagername', 1, 'topomojo');

        $setting = new \mod_topomojo_auth_checkbox_setting(
            'topomojo/enablemanagername',
            'Manager user',
            '',
            0
        );

        $this->assertSame('', $setting->write_setting(0));
        $this->assertEmpty(get_config('topomojo', 'enablemanagername'));
    }

    /**
     * Test that gamespace-limit lookup rejects an invalid legacy configuration clearly.
     */
    public function test_get_gamespace_limit_rejects_invalid_auth_configuration() {
        $this->resetAfterTest();
        set_config('enableapikey', 1, 'topomojo');
        set_config('enablemanagername', 0, 'topomojo');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('configapikeymanagerrequired', 'topomojo'));

        get_gamespace_limit(new \curl());
    }

    /**
     * Test setup function with API key enabled and set.
     */
    public function test_setup_with_apikey() {
        $this->resetAfterTest();

        // Set config for API key.
        set_config('enableapikey', 1, 'topomojo');
        set_config('apikey', 'test-api-key-12345', 'topomojo');

        $client = setup();

        $this->assertInstanceOf(\curl::class, $client);
    }

    /**
     * Test setup function with API key enabled but not set.
     */
    public function test_setup_with_apikey_not_set() {
        $this->resetAfterTest();

        // Enable API key but don't set it.
        set_config('enableapikey', 1, 'topomojo');
        set_config('apikey', '', 'topomojo');

        $client = setup();

        $this->assertNull($client);
    }

    /**
     * Test setup function with API key disabled and no OAuth.
     */
    public function test_setup_without_apikey_or_oauth() {
        $this->resetAfterTest();

        // Disable API key and OAuth.
        set_config('enableapikey', 0, 'topomojo');
        set_config('enableoauth', 0, 'topomojo');

        $client = setup();

        $this->assertNull($client);
    }

    /**
     * Test setup function with OAuth enabled but no issuer ID.
     */
    public function test_setup_with_oauth_no_issuer() {
        $this->resetAfterTest();

        // Disable API key, enable OAuth but don't set issuer.
        set_config('enableapikey', 0, 'topomojo');
        set_config('enableoauth', 1, 'topomojo');
        set_config('issuerid', '', 'topomojo');

        $client = setup();

        $this->assertNull($client);
    }

    /**
     * Test setup function configuration priority (API key over OAuth).
     */
    public function test_setup_apikey_priority() {
        $this->resetAfterTest();

        // Set both API key and OAuth, API key should take priority.
        set_config('enableapikey', 1, 'topomojo');
        set_config('apikey', 'test-api-key', 'topomojo');
        set_config('enableoauth', 1, 'topomojo');
        set_config('issuerid', '123', 'topomojo');

        $client = setup();

        // Should return curl client because API key is set and has priority.
        $this->assertInstanceOf(\curl::class, $client);
    }

    /**
     * Test that setup returns correct client type.
     */
    public function test_setup_returns_curl_client() {
        $this->resetAfterTest();

        set_config('enableapikey', 1, 'topomojo');
        set_config('apikey', 'valid-api-key', 'topomojo');

        $client = setup();

        $this->assertNotNull($client);
        $this->assertInstanceOf(\curl::class, $client);
    }

    /**
     * Test invitation URL generation strips existing launchpoint parameters.
     */
    public function test_build_topomojo_invite_url_strips_existing_parameters() {
        $url = build_topomojo_invite_url(
            'https://topomojo.example/?t=ticket&g=gamespace',
            'invite-code'
        );

        $this->assertSame('https://topomojo.example/?c=invite-code', $url);
    }

    /**
     * Test invitation URL generation preserves a deployment path.
     */
    public function test_build_topomojo_invite_url_preserves_launchpoint_path() {
        $url = build_topomojo_invite_url(
            'https://topomojo.example/lp/?t=ticket&g=gamespace',
            'invite code'
        );

        $this->assertSame('https://topomojo.example/lp/?c=invite+code', $url);
    }

    /**
     * Test lookup returns the stored launchpoint URL for a gamespace.
     */
    public function test_get_topomojo_gamespace_launchpoint_url() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');

        $generator->create_attempt($topomojo, $user, [
            'eventid' => 'gamespace-id',
            'launchpointurl' => 'https://topomojo.example/?t=ticket',
        ]);

        $this->assertSame(
            'https://topomojo.example/?t=ticket',
            get_topomojo_gamespace_launchpoint_url('gamespace-id')
        );
    }

    /**
     * Test lookup returns null when no attempt exists for a gamespace.
     */
    public function test_get_topomojo_gamespace_launchpoint_url_returns_null() {
        $this->resetAfterTest();

        $this->assertNull(get_topomojo_gamespace_launchpoint_url('missing-gamespace'));
    }

    /**
     * Test invitation URL generation falls back to the configured base URL.
     */
    public function test_build_topomojo_invite_url_falls_back_to_configured_base_url() {
        $this->resetAfterTest();
        set_config('topomojobaseurl', 'https://topomojo.example', 'topomojo');

        $this->assertSame(
            'https://topomojo.example/lp/?c=invite-code',
            build_topomojo_invite_url('', 'invite-code')
        );
    }
}
