<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side defences sent with every page: a Content Security Policy that only lets the app
 * run its own scripts (so an injected script tag is inert), HTTPS-only, no framing by other
 * sites, no MIME sniffing and a minimal Referer and device-permission policy. The web server
 * sends some of these too; they are repeated here so they hold on any deployment.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('security.csp');
        $sendCsp = in_array($mode, ['enforce', 'report-only'], true) && ! Vite::isRunningHot();

        // The nonce must exist before the view renders, so the scripts it prints can carry it.
        if ($sendCsp) {
            Vite::useCspNonce();
        }

        $response = $next($request);
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $hsts = (int) config('security.hsts_max_age');

        if ($hsts > 0 && $request->isSecure() && ! app()->environment('local', 'testing')) {
            $response->headers->set('Strict-Transport-Security', "max-age={$hsts}; includeSubDomains");
        }

        if ($sendCsp) {
            $response->headers->set($mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', $this->policy((string) Vite::cspNonce()));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            // Only this app's own bundles, and the one inline script the server writes (the route table).
            "script-src 'self' 'nonce-{$nonce}'",
            // Components position themselves with inline style attributes; styles cannot run code.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            // Pages talk only to this app. The AI providers are called from the server, never the browser.
            "connect-src 'self'",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
