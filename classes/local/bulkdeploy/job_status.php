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
 * Status constants for a bulk deployment job.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Job status string constants for a bulk-deploy job.
 * @package mod_topomojo
 */
class job_status {
    /** @var string Waiting to start. */
    const QUEUED = 'queued';
    /** @var string Being processed. */
    const RUNNING = 'running';
    /** @var string Cancellation requested; the running batch is finishing. */
    const CANCELLING = 'cancelling';
    /** @var string Cancelled before it finished. */
    const CANCELLED = 'cancelled';
    /** @var string Finished. */
    const COMPLETED = 'completed';
    /** @var string Stopped by an error. */
    const FAILED = 'failed';

    /** @var string[] Statuses a job never leaves. */
    const TERMINAL = [self::CANCELLED, self::COMPLETED, self::FAILED];

    /**
     * Checks whether a status is final.
     *
     * @param string $status A job status.
     * @return bool True if the job will not change status again.
     */
    public static function is_terminal(string $status): bool {
        return in_array($status, self::TERMINAL, true);
    }
}
