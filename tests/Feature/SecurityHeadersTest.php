<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_every_page_carries_the_browser_side_defences(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_the_content_security_policy_only_allows_the_apps_own_scripts_and_no_framing(): void
    {
        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=_-]{16,}'/", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-(inline|eval)'/", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp, 'the browser talks only to this app; the AI is called from the server');
        $this->assertStringNotContainsString('http', str_replace(["'self'"], '', $csp), 'no third-party origin is allowed');
    }

    public function test_the_nonce_in_the_policy_is_the_one_on_the_inline_script_and_changes_every_time(): void
    {
        $first = $this->get('/login');
        $second = $this->get('/login');

        preg_match("/'nonce-([^']+)'/", (string) $first->headers->get('Content-Security-Policy'), $nonce);
        preg_match("/'nonce-([^']+)'/", (string) $second->headers->get('Content-Security-Policy'), $other);

        $this->assertNotEmpty($nonce[1]);
        $this->assertNotSame($nonce[1], $other[1], 'a nonce that repeats can be guessed');
        $first->assertSee('nonce="'.$nonce[1].'"', false);
    }

    public function test_report_only_mode_reports_without_blocking_and_off_sends_nothing(): void
    {
        config(['security.csp' => 'report-only']);
        $report = $this->get('/login');
        $this->assertNull($report->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", (string) $report->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.csp' => 'off']);
        $off = $this->get('/login');
        $this->assertNull($off->headers->get('Content-Security-Policy'));
        $this->assertNull($off->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_strict_transport_security_is_sent_only_over_https_outside_local_and_testing(): void
    {
        config(['security.hsts_max_age' => 31536000]);

        $this->assertNull($this->get('/')->headers->get('Strict-Transport-Security'), 'never over plain http');

        $this->app['env'] = 'production';
        $secure = $this->get('https://localhost/')->headers->get('Strict-Transport-Security');
        $this->assertSame('max-age=31536000; includeSubDomains', $secure);

        config(['security.hsts_max_age' => 0]);
        $this->assertNull($this->get('https://localhost/')->headers->get('Strict-Transport-Security'));
    }

    public function test_the_page_asks_no_third_party_for_anything(): void
    {
        $html = (string) $this->get('/login')->getContent();

        $this->assertStringNotContainsString('bunny.net', $html);
        $this->assertStringNotContainsString('googleapis.com', $html);
        $this->assertStringNotContainsString('cdn.', $html);
    }
}
