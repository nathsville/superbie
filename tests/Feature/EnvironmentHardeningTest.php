<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 19 — Scopes A & E: source-controlled configuration hygiene.
 *
 * F-18-02: real DB credentials were committed in phpunit.xml.
 * F-18-22 / F-18-34: .env.example shipped misleading production defaults.
 *
 * These tests assert the repository's committed configuration files are safe.
 * They never contain a real secret — they only assert the ABSENCE of one.
 */
class EnvironmentHardeningTest extends TestCase
{
    public function test_phpunit_xml_does_not_commit_database_credentials(): void
    {
        $xml = file_get_contents(base_path('phpunit.xml'));

        // No credential env entries may be present at all.
        $this->assertStringNotContainsString('name="DB_USERNAME"', $xml);
        $this->assertStringNotContainsString('name="DB_PASSWORD"', $xml);

        // Non-secret coordinates are still allowed.
        $this->assertStringContainsString('name="DB_DATABASE"', $xml);
    }

    public function test_env_example_is_a_safe_template(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        // APP_DEBUG must not be advertised as enabled.
        $this->assertStringNotContainsString('APP_DEBUG=true', $example);
        $this->assertStringContainsString('APP_DEBUG=false', $example);

        // The authoritative timezone decision is reflected in the template.
        $this->assertStringContainsString('APP_TIMEZONE=Asia/Makassar', $example);

        // Locale / name must match the actual application, not Laravel defaults.
        $this->assertStringContainsString('APP_LOCALE=id', $example);
        $this->assertStringNotContainsString('APP_NAME=Laravel', $example);

        // Secure-cookie key must be present and configurable (defaults off for
        // local HTTP development; enabled per-environment for HTTPS production).
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=', $example);

        // APP_KEY must be empty in the template (never a committed key).
        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $example);
    }

    public function test_env_example_contains_no_real_secret_values(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        // DB_PASSWORD must be empty in the template.
        $this->assertMatchesRegularExpression('/^DB_PASSWORD=\s*$/m', $example);

        // APP_KEY must never carry a value in a committed template.
        $this->assertDoesNotMatchRegularExpression('/^APP_KEY=base64:/m', $example);
    }
}
