<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Test double that records whether it was ever unserialized.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo;

defined('MOODLE_INTERNAL') || die();

/**
 * Sets a flag when PHP rebuilds an instance from serialized data.
 */
class observer_wakeup_probe {
    /** @var bool Whether __wakeup() ran. */
    public static bool $woken = false;

    /** @var string The text of the fake block config. */
    public string $text = '<a data-labofday="1" href="https://example.test/mod/topomojo/view.php?id=42">Lab</a>';

    /**
     * Records that the instance was rebuilt.
     */
    public function __wakeup(): void {
        self::$woken = true;
    }
}
