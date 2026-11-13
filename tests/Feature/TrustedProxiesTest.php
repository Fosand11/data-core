<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        // The trusted-proxy list is static; never let it leak into other tests.
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_forwarded_headers_are_ignored_by_default(): void
    {
        $this->assertFalse(config('app.trust_proxies'));

        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])->get('/health');

        $this->assertFalse(request()->isSecure());
        $this->assertNotSame('203.0.113.9', request()->ip());
    }

    public function test_forwarded_headers_are_believed_when_the_proxy_is_trusted(): void
    {
        config(['app.trust_proxies' => true]);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])->get('/health');

        $this->assertTrue(request()->isSecure());
        $this->assertSame('203.0.113.9', request()->ip());
    }
}
