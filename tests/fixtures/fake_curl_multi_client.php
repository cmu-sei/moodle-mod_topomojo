<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * In-memory test double for curl_multi_client.
 *
 * Tests pre-program responses indexed by URL+method. Each call to execute()
 * pops the next queued response for each request, so tests can model
 * sequences (e.g. first poll returns isActive=false, second returns true).
 */
class fake_curl_multi_client extends curl_multi_client {
    /** @var array<string, curl_response[]> queue of responses keyed by "METHOD URL" */
    public array $queues = [];

    /** @var array<int, array> log of all requests executed, in order */
    public array $log = [];

    public function queue(string $method, string $url, curl_response $response): void {
        $key = $method . ' ' . $url;
        $this->queues[$key][] = $response;
    }

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
