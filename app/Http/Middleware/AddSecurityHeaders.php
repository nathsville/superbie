<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * AddSecurityHeaders — security headers incl. a NARROW, real Content Security
 * Policy.
 *
 * Owner decision O-8 (extended 2026-10-18, audit PROMPT_28): keep the Windy.com
 * embed and ship the full policy — never `ALLOWALL` and never
 * `frame-ancestors *`. Directive by directive:
 *   - `default-src 'self'` — everything not listed below is same-origin.
 *   - `script-src 'self' 'unsafe-eval' 'nonce-…'` — the bundled Vite assets
 *     (self) plus a per-request nonce stamped on the three inline boot
 *     scripts. `'unsafe-eval'` is REQUIRED by Alpine's standard build (its
 *     expression evaluator compiles functions at runtime); migrating to
 *     `@alpinejs/csp` would be a repo-wide refactor and is NOT done here.
 *     Inline `on*=` handler attributes are NOT allowed — the single occurrence
 *     (an `onsubmit` confirm) became a delegated `data-confirm` listener.
 *   - `style-src 'self' 'unsafe-inline' https://fonts.googleapis.com` — inline
 *     style attributes / `<style>` blocks (landing editorial sheet) + Google
 *     Fonts.
 *   - `font-src 'self' https://fonts.gstatic.com`.
 *   - `img-src 'self' data: <tile origin>` — the Leaflet tile layer, derived
 *     from `config('map.tile.url')` (`{s}` → `*`); `data:` for the small
 *     inlined assets Vite emits.
 *   - `connect-src 'self' <nominatim origin?>` — the citizen-facing browser
 *     call is same-origin, plus the OPT-IN reverse-geocode endpoint when it is
 *     enabled (`config('map.reverse_geocode')`).
 *   - `frame-src 'self' <windy embed>` — the ONLY external frame.
 *   - `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`,
 *     `frame-ancestors 'self'` (clickjacking protection).
 *
 * The nonce is generated BEFORE the response renders so Blade can stamp it on
 * every inline `<script>`. Only the `local` environment additionally allows the
 * Vite dev-server origins (`npm run dev`); production/testing get the strict
 * policy.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
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

    /**
     * The full policy for one response (nonce-carrying).
     */
    private function contentSecurityPolicy(string $nonce): string
    {
        $frameSrc = (string) config('weather.embed.allowed_frame_src', 'https://embed.windy.com');

        $scriptSrc  = "'self' 'unsafe-eval' 'nonce-{$nonce}'";
        $styleSrc   = "'self' 'unsafe-inline' https://fonts.googleapis.com";
        $fontSrc    = "'self' https://fonts.gstatic.com";
        $imgSrc     = "'self' data: " . $this->tileOrigin();
        $connectSrc = "'self'";

        if ((bool) config('map.reverse_geocode.enabled', true)) {
            $origin = $this->originOf((string) config('map.reverse_geocode.url', 'https://nominatim.openstreetmap.org/reverse'));
            if ($origin !== '') {
                $connectSrc .= ' ' . $origin;
            }
        }

        // The Vite dev server (only ever used with `npm run dev` locally) serves
        // assets from its own origin and talks to the page over a websocket.
        if (app()->environment('local')) {
            $viteHttp = ' http://127.0.0.1:5173 http://localhost:5173';
            $viteWs   = ' ws://127.0.0.1:5173 ws://localhost:5173';
            $scriptSrc  .= $viteHttp;
            $styleSrc   .= $viteHttp;
            $connectSrc .= $viteHttp . $viteWs;
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "font-src {$fontSrc}",
            "img-src {$imgSrc}",
            "connect-src {$connectSrc}",
            "frame-src 'self' {$frameSrc}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    /**
     * The tile-layer origin — `https://{s}.tile.example/x/{z}` becomes
     * `https://*.tile.example` (the `{s}` subdomain placeholder).
     */
    private function tileOrigin(): string
    {
        $url    = (string) config('map.tile.url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'https');
        $host   = preg_replace('/\{s\}\./', '*.', (string) parse_url($url, PHP_URL_HOST));

        return ($host !== null && $host !== '')
            ? "{$scheme}://{$host}"
            : 'https://*.tile.openstreetmap.org';
    }

    /**
     * Scheme + host of an absolute URL ('' when it cannot be parsed).
     */
    private function originOf(string $url): string
    {
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'https');
        $host   = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' ? "{$scheme}://{$host}" : '';
    }
}
