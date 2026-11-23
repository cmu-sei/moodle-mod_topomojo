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
 * Builds the payload used to start a gamespace in TopoMojo.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the JSON payload used to POST a new gamespace to TopoMojo.
 *
 * Mirrors the logic in locallib.php's start_event() but accepts an explicit
 * $user object so single-user and bulk-deploy callers can both share one
 * implementation.
 * @package mod_topomojo
 */
class payload_builder {
    /**
     * Builds the gamespace request for one user.
     *
     * @param string $workspaceid The TopoMojo workspace ID.
     * @param \stdClass $topomojo The activity record.
     * @param \stdClass $user The user to start the gamespace for.
     * @return \stdClass The request body.
     */
    public static function build(string $workspaceid, \stdClass $topomojo, \stdClass $user): \stdClass {
        $payload = new \stdClass();
        $payload->resourceId = $workspaceid;
        $payload->startGamespace = true;
        $payload->allowPreview = false;
        $payload->allowReset = false;
        $payload->maxAttempts = (int) $topomojo->submissions;
        $payload->maxMinutes = (int) ($topomojo->duration / 60);
        $payload->points = (int) $topomojo->grade;

        // Pass variant directly - TopoMojo handles variant=0 as random.
        $payload->variant = (int) $topomojo->variant;

        $email = (string) ($user->email ?? '');
        $atpos = strpos($email, '@');
        $subjectid = $atpos === false ? $email : substr($email, 0, $atpos);

        $player = new \stdClass();
        $player->subjectId = $subjectid;
        $player->subjectName = (string) ($user->username ?? '');

        $payload->players = [$player];
        return $payload;
    }
}
