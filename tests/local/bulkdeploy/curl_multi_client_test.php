<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/fake_curl_multi_client.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\local\bulkdeploy\fake_curl_multi_client::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_topomojo\local\bulkdeploy\curl_multi_client::class)]
final class curl_multi_client_test extends \advanced_testcase {
    public function test_fake_returns_queued_responses_in_order(): void {
        $fake = new fake_curl_multi_client();
        $fake->queue('POST', 'https://x/a', new curl_response(200, 0, '{"id":"1"}'));
        $fake->queue('POST', 'https://x/a', new curl_response(500, 0, 'boom'));

        $r1 = $fake->execute([['method' => 'POST', 'url' => 'https://x/a']])[0];
        $r2 = $fake->execute([['method' => 'POST', 'url' => 'https://x/a']])[0];

        $this->assertSame(200, $r1->httpcode);
        $this->assertSame('{"id":"1"}', $r1->body);
        $this->assertSame(500, $r2->httpcode);
    }

    public function test_fake_throws_when_no_response_queued(): void {
        $fake = new fake_curl_multi_client();
        $this->expectException(\RuntimeException::class);
        $fake->execute([['method' => 'GET', 'url' => 'https://x/missing']]);
    }

    /**
     * Every bulk-deploy request carries the API key or a system bearer token in its
     * headers, and this client drives libcurl directly, so none of Moodle's \curl
     * handling applies: it has to ask for verification itself, bound the connect as
     * well as the transfer, and refuse redirects - nothing would strip x-api-key from
     * one that crossed hosts.
     */
    public function test_requests_verify_the_certificate_and_refuse_redirects(): void {
        $options = curl_multi_client::request_options([
            'method' => 'POST',
            'url' => 'https://topomojo.example/api/gamespace',
            'headers' => ['x-api-key: secret'],
            'body' => '{}',
            'timeout' => 120,
        ]);

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(120, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(curl_multi_client::CONNECT_TIMEOUT_SECONDS, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertSame(['x-api-key: secret'], $options[CURLOPT_HTTPHEADER]);
        $this->assertTrue($options[CURLOPT_POST]);
        $this->assertSame('{}', $options[CURLOPT_POSTFIELDS]);
    }

    /**
     * A poll is a GET with the batch's own timeout, and must not turn into a POST.
     */
    public function test_a_get_request_carries_no_post_options(): void {
        $options = curl_multi_client::request_options([
            'url' => 'https://topomojo.example/api/gamespace/1',
        ]);

        $this->assertArrayNotHasKey(CURLOPT_POST, $options);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
        $this->assertArrayNotHasKey(CURLOPT_HTTPHEADER, $options);
        $this->assertSame(60, $options[CURLOPT_TIMEOUT]);
    }
}
