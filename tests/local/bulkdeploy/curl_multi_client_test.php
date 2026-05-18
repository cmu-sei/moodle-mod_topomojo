<?php
namespace mod_topomojo\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/fake_curl_multi_client.php');

/**
 * @covers \mod_topomojo\local\bulkdeploy\fake_curl_multi_client
 */
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
}
