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

/**
 * Unit tests for the instructor lab capacity estimate.
 *
 * @package    mod_topomojo
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/topomojo/locallib.php');

/**
 * Unit tests for the instructor lab capacity estimate.
 *
 * @package    mod_topomojo
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('topomojo_count_pending_launches')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\mod_topomojo_renderer::class, 'display_capacity')]
final class capacity_test extends \advanced_testcase {

    /**
     * Only students without a running regular attempt still need a launch.
     */
    public function test_count_pending_launches(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $topomojo = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);
        $context = \context_module::instance($topomojo->cmid);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_topomojo');

        $waiting = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $running = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $finished = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $generator->create_attempt($topomojo, $running);
        $generator->create_attempt($topomojo, $finished, ['state' => topomojo_attempt::FINISHED]);

        // An instructor's preview gamespace is not a student launch.
        $preview = $generator->create_attempt($topomojo, $teacher);
        $DB->set_field('topomojo_attempts', 'preview', 1, ['id' => $preview->id]);

        // A running attempt in another activity does not count against this one.
        $other = $this->getDataGenerator()->create_module('topomojo', ['course' => $course->id]);
        $generator->create_attempt($other, $waiting);

        // waiting and finished still need a launch; the suspended student cannot launch.
        $this->assertSame(2, topomojo_count_pending_launches($topomojo, $context));
    }

    /**
     * Each case maps a fit and the students still to launch to a notification level.
     *
     * @return array
     */
    public static function capacity_provider(): array {
        return [
            'enough' => [10, 4, 'memory', 'alert-success', 'Lab capacity: enough for everyone', 'hypervisor memory'],
            'short' => [3, 4, 'memory', 'alert-warning', 'Lab capacity: short', 'hypervisor memory'],
            'full' => [0, 4, 'pool', 'alert-danger', 'Lab capacity: full', 'resource pool'],
            'none waiting' => [2, 0, 'memory', 'alert-info', 'Lab capacity: room for more', 'hypervisor memory'],
            'none waiting, no room' => [0, 0, 'memory', 'alert-warning', 'Lab capacity: full', 'hypervisor memory'],
        ];
    }

    /**
     * The message carries the meaning in words, not only the colour.
     *
     * @param int $fits
     * @param int $pending
     * @param string $limitedby
     * @param string $class
     * @param string $text
     * @param string $limit
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('capacity_provider')]
    public function test_display_capacity(int $fits, int $pending, string $limitedby, string $class,
            string $text, string $limit): void {
        global $PAGE;
        $this->resetAfterTest();

        $PAGE->set_url('/mod/topomojo/view.php');
        // The general target renders html; under cli the default target prints plain text.
        $renderer = $PAGE->get_renderer('mod_topomojo', null, RENDERER_TARGET_GENERAL);

        ob_start();
        $renderer->display_capacity((object) [
            'fits' => $fits,
            'limitedBy' => $limitedby,
            'asOf' => '2026-10-16T18:45:00+00:00',
        ], $pending);
        $html = ob_get_clean();

        $this->assertStringContainsString($class, $html);
        $this->assertStringContainsString($text, $html);
        $this->assertStringContainsString($limit, $html);
        $this->assertStringContainsString('Estimate as of', $html);
    }

    /**
     * A workspace with no vms has no limit, so nothing is shown.
     */
    public function test_display_capacity_without_a_limit_shows_nothing(): void {
        global $PAGE;
        $this->resetAfterTest();

        $PAGE->set_url('/mod/topomojo/view.php');
        $renderer = $PAGE->get_renderer('mod_topomojo');

        ob_start();
        $renderer->display_capacity((object) ['fits' => null, 'limitedBy' => null], 3);

        $this->assertSame('', ob_get_clean());
    }
}
