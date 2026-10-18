<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * SUPERBIE — audit PROMPT_28, O-8 — Content Security Policy (extended
 * 2026-10-18).
 *
 * The policy must be real (`default-src`), keep the Windy embed as the only
 * external frame, stamp every inline script with a per-request nonce that
 * matches the header, and never fall back to `ALLOWALL` / `frame-ancestors *`.
 */
class ContentSecurityPolicyTest extends TestCase
{
    private function csp(string $uri): string
    {
        $response = $this->get($uri);
        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, "CSP header missing on {$uri}");

        return $csp;
    }

    public function test_landing_ships_the_full_policy_without_blanket_allow(): void
    {
        $csp = $this->csp(route('home'));

        foreach ([
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval' 'nonce-",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "frame-src 'self' https://embed.windy.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $csp, "CSP must contain: {$fragment}");
        }

        $this->assertStringNotContainsString('ALLOWALL', $csp);
        $this->assertStringNotContainsString('frame-ancestors *', $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_inline_scripts_carry_the_same_nonce_as_the_header(): void
    {
        $response = $this->get(route('home'));
        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);

        $this->assertSame(
            1,
            preg_match("/script-src[^;]*'nonce-([A-Za-z0-9+\/=]+)'/", $csp, $matches),
            'The script-src directive must carry exactly one nonce source.'
        );

        $response->assertSee('nonce="' . $matches[1] . '"', false);
    }

    public function test_tile_origin_and_geocode_origin_follow_configuration(): void
    {
        $csp = $this->csp(route('home'));

        $tileUrl = (string) config('map.tile.url');
        $scheme  = (string) (parse_url($tileUrl, PHP_URL_SCHEME) ?: 'https');
        $host    = preg_replace('/\{s\}\./', '*.', (string) parse_url($tileUrl, PHP_URL_HOST));
        $this->assertStringContainsString("img-src 'self' data: {$scheme}://{$host}", $csp);

        if ((bool) config('map.reverse_geocode.enabled', true)) {
            $this->assertStringContainsString(
                'https://nominatim.openstreetmap.org',
                $csp,
                'The opt-in reverse-geocode origin must be allowed in connect-src.'
            );
        }
    }

    public function test_dev_server_origins_are_absent_outside_local(): void
    {
        $csp = $this->csp(route('home'));

        $this->assertStringNotContainsString('5173', $csp, 'Vite dev origins are local-only.');
    }

    public function test_policy_is_applied_to_every_web_response(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }
}
