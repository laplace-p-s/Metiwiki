<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * TRUSTED_PROXIES（config/trustedproxy.php）で指定したプロキシからのヘッダーだけを採用する
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * プロキシ経由で https として届いた、ログインが必要な画面へのリクエスト
     */
    private function requestViaProxy(string $proxyIp): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $proxyIp])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get(route('wiki.create', ['title' => '新規']));
    }

    /**
     * リダイレクト先のスキーム（ホスト名は APP_URL によって変わるので比べない）
     */
    private function redirectScheme(TestResponse $response): ?string
    {
        $response->assertRedirect();

        return parse_url((string) $response->headers->get('Location'), PHP_URL_SCHEME);
    }

    public function test_forwarded_headers_are_ignored_by_default()
    {
        config(['trustedproxy.proxies' => null]);

        $this->assertSame('http', $this->redirectScheme($this->requestViaProxy('10.0.0.1')));
    }

    public function test_forwarded_headers_from_listed_proxy_are_trusted()
    {
        config(['trustedproxy.proxies' => '192.168.0.0/16, 10.0.0.1']);

        $this->assertSame('https', $this->redirectScheme($this->requestViaProxy('10.0.0.1')));
        $this->assertSame('https', $this->redirectScheme($this->requestViaProxy('192.168.1.5')));
    }

    public function test_forwarded_headers_from_unlisted_address_are_ignored()
    {
        config(['trustedproxy.proxies' => '10.0.0.1']);

        $this->assertSame('http', $this->redirectScheme($this->requestViaProxy('10.0.0.2')));
    }

    public function test_wildcard_trusts_any_proxy()
    {
        config(['trustedproxy.proxies' => '*']);

        $this->assertSame('https', $this->redirectScheme($this->requestViaProxy('172.16.0.9')));
    }

    public function test_client_ip_is_taken_from_trusted_proxy()
    {
        config(['trustedproxy.proxies' => '10.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
            ->get('/-/up');

        $this->assertSame('203.0.113.7', request()->ip());
    }
}
