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
 * Status constants for one user row in a bulk deployment job.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-user row status constants for a bulk-deploy job.
 * @package mod_topomojo
 */
class user_status {
    /** @var string Waiting for its turn. */
    const PENDING = 'pending';
    /** @var string Gamespace requested, waiting for it to become ready. */
    const LAUNCHED = 'launched';
    /** @var string Gamespace is ready. */
    const READY = 'ready';
    /** @var string Not launched, for example because the user has no email address. */
    const SKIPPED = 'skipped';
    /** @var string Launch failed or timed out. */
    const FAILED = 'failed';
    /** @var string Cancelled before it was launched. */
    const CANCELLED = 'cancelled';

    /** @var string[] Statuses a row never leaves. */
    const TERMINAL = [self::READY, self::SKIPPED, self::FAILED, self::CANCELLED];

    /**
     * Checks whether a status is final.
     *
     * @param string $status A user row status.
     * @return bool True if the row will not change status again.
     */
    public static function is_terminal(string $status): bool {
        return in_array($status, self::TERMINAL, true);
    }
}
