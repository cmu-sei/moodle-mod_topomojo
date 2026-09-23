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

    /** @var int Seconds to wait for TopoMojo to accept the connection. */
    const CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * cURL options for one request, so the security-relevant ones can be asserted.
     *
     * Every request here carries a credential - the API key or a system bearer token -
     * in its headers, and this client talks to libcurl directly rather than through
     * \curl, so none of Moodle's handling applies: verification is set explicitly rather
     * than left to the libcurl build, and redirects are not followed at all, because
     * nothing would strip x-api-key from one that crossed hosts.
     *
     * @param array $req one request from the list execute() was given.
     * @return array cURL option constant => value.
     */
    public static function request_options(array $req): array {
        $options = [
            CURLOPT_URL => $req['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ($req['timeout'] ?? 60),
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if (!empty($req['headers'])) {
            $options[CURLOPT_HTTPHEADER] = $req['headers'];
        }

        if (($req['method'] ?? 'GET') === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = (string) ($req['body'] ?? '');
        }

        return $options;
    }

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
            curl_setopt_array($ch, self::request_options($req));
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
