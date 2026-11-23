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
 * In-memory test double for the curl_multi_client class.
 *
 * @package    mod_topomojo
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * In-memory test double for curl_multi_client.
 *
 * Tests pre-program responses indexed by URL+method. Each call to execute()
 * pops the next queued response for each request, so tests can model
 * sequences (e.g. first poll returns isActive=false, second returns true).
 * @package mod_topomojo
 */
class fake_curl_multi_client extends curl_multi_client {
    /** @var array<string, curl_response[]> queue of responses keyed by "METHOD URL" */
    public array $queues = [];

    /** @var array<int, array> log of all requests executed, in order */
    public array $log = [];

    /**
     * Queues the response to return for a request.
     *
     * @param string $method The HTTP method.
     * @param string $url The URL.
     * @param curl_response $response The response to return.
     * @return void
     */
    public function queue(string $method, string $url, curl_response $response): void {
        $key = $method . ' ' . $url;
        $this->queues[$key][] = $response;
    }

    /**
     * Records the requests and returns the queued responses for them.
     *
     * @param array $requests The requests, as for curl_multi_client::execute().
     * @return curl_response[] The responses, with the same keys as the requests.
     */
    public function execute(array $requests): array {
        $results = [];
        foreach ($requests as $i => $req) {
            $this->log[] = $req;
            $key = ($req['method'] ?? 'GET') . ' ' . $req['url'];
            if (empty($this->queues[$key])) {
                throw new \RuntimeException("No response queued for $key");
            }
            $results[$i] = array_shift($this->queues[$key]);
        }
        return $results;
    }
}
