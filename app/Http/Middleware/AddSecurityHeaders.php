<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AddSecurityHeaders — narrow, non-breaking security headers.
 *
 * Owner decision O-8: keep the Windy.com embed and add a NARROW Content
 * Security Policy. Explicitly NOT used: `XFrameOptionsMode.ALLOWALL` or any
 * blanket `frame-ancestors *`.
 *
 * The CSP is deliberately MINIMAL and specifies ONLY:
 *   - frame-ancestors 'self'  → our pages cannot be framed by other origins
 *                               (clickjacking protection).
 *   - frame-src 'self' https://embed.windy.com
 *                             → the ONLY external frame we permit is the
 *                               Windy.com embed (the configured allow-list).
 *   - object-src 'none'       → no plugin/object embedding.
 *   - base-uri 'self'         → no <base> hijacking.
 *
 * It intentionally does NOT set `default-src`/`script-src`/`style-src`, so it
 * cannot break existing Vite assets, inline styles, Alpine, or fonts. A
 * restrictive script/style policy is out of scope for this slice and is
 * flagged as not-previously-verified (no CSP existed in this repo before).
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $frameSrc = (string) config('weather.embed.allowed_frame_src', 'https://embed.windy.com');

        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self'; frame-src 'self' {$frameSrc}; object-src 'none'; base-uri 'self'"
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Information-disclosure hardening: drop the PHP version banner. It is
        // emitted by the SAPI (`expose_php`) rather than by Symfony's header bag,
        // so it must also be cleared with `header_remove()`.
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
