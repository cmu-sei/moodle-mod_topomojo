<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Result of one HTTP request executed by curl_multi_client.
 */
class curl_response {
    public int $httpcode;
    public int $errno;
    public string $body;

    public function __construct(int $httpcode, int $errno, string $body) {
        $this->httpcode = $httpcode;
        $this->errno = $errno;
        $this->body = $body;
    }
}

/**
 * Concurrent HTTP client wrapping the curl_multi_* PHP extension.
 *
 * Test seam — production code uses this concrete class; tests substitute
 * fake_curl_multi_client.
 */
class curl_multi_client {

    /**
     * Execute a list of requests concurrently and return their results in
     * the same order.
     *
     * @param array $requests array of associative arrays:
     *   [
     *     'method'  => 'GET' | 'POST',
     *     'url'     => string,
     *     'headers' => array<string> (e.g. ['Content-Type: application/json']),
     *     'body'    => ?string,
     *     'timeout' => int (seconds),
     *   ]
     * @return curl_response[] one per request, in the same order.
     */
    public function execute(array $requests): array {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($requests as $i => $req) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $req['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int) ($req['timeout'] ?? 60));
            if (!empty($req['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $req['headers']);
            }
            if (($req['method'] ?? 'GET') === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) ($req['body'] ?? ''));
            }
            curl_multi_add_handle($multi, $ch);
            $handles[$i] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0);

        $results = [];
        foreach ($handles as $i => $ch) {
            $body = (string) curl_multi_getcontent($ch);
            $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = (int) curl_errno($ch);
            $results[$i] = new curl_response($httpcode, $errno, $body);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        return $results;
    }
}
