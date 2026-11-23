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
 * Result of one HTTP request executed by curl_multi_client.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Result of one HTTP request executed by curl_multi_client.
 * @package mod_topomojo
 */
class curl_response {
    /** @var int HTTP status code, 0 if no response was received. */
    public int $httpcode;
    /** @var int cURL error number, 0 if none. */
    public int $errno;
    /** @var string Response body. */
    public string $body;

    /**
     * Constructor.
     *
     * @param int $httpcode HTTP status code.
     * @param int $errno cURL error number.
     * @param string $body Response body.
     */
    public function __construct(int $httpcode, int $errno, string $body) {
        $this->httpcode = $httpcode;
        $this->errno = $errno;
        $this->body = $body;
    }
}
