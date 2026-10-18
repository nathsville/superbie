<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — audit PROMPT_28, S-01 — guest endpoint throttling + generic
 * identity messages (decision 2026-10-18, recorded in `prd.md` F-004/F-005).
 *
 * `POST /register`, `POST /forgot-password` and `POST /reset-password` are
 * limited to 5 requests per minute per IP; the 6th request is a 429. The
 * uniqueness messages for email/NIK/nomor HP are identical and generic so the
 * registration form is not an account-enumeration oracle.
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_UNIQUE_MESSAGE =
        'Email, NIK, atau nomor HP sudah terdaftar. Masuk jika ini akun Anda.';

    // =========================================================================
    // Throttle — 5/minute/IP on every guest POST
    // =========================================================================

    public function test_register_posts_are_throttled_after_five_attempts(): void
    {
        // Invalid payloads: each attempt is a normal 302 validation bounce
        // (no auto-login), so only the throttle counter advances.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), [])->assertStatus(302);
        }

        $this->post(route('register.store'), [])->assertStatus(429);

        // The GET form itself is never throttled.
        $this->get(route('register'))->assertOk();
    }

    public function test_forgot_password_posts_are_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'tidak-ada@example.test'])
                ->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'tidak-ada@example.test'])
            ->assertStatus(429);

        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_password_posts_are_throttled_after_five_attempts(): void
    {
        $payload = [
            'token'                 => 'dummy-token',
            'email'                 => 'tidak-ada@example.test',
            'password'              => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.update'), $payload)->assertStatus(302);
        }

        $this->post(route('password.update'), $payload)->assertStatus(429);
    }

    // =========================================================================
    // Generic uniqueness messages (anti-enumeration)
    // =========================================================================

    public function test_registration_uniqueness_message_is_generic_for_every_identity_field(): void
    {
        User::factory()->create([
            'email'        => 'terdaftar@example.test',
            'nik'          => '7373000000001234',
            'phone_number' => '081200000123',
        ]);

        $base = [
            'name'                  => 'Warga',
            'address'               => 'Alamat uji',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ];

        // One submission per colliding field — every response must carry the
        // SAME generic message (never "NIK sudah terdaftar." etc.).
        $cases = [
            'email'        => ['email' => 'terdaftar@example.test', 'nik' => '7373000000009991', 'phone_number' => '081200000991'],
            'nik'          => ['email' => 'fresh-nik@example.test', 'nik' => '7373000000001234', 'phone_number' => '081200000992'],
            'phone_number' => ['email' => 'fresh-phone@example.test', 'nik' => '7373000000009993', 'phone_number' => '081200000123'],
        ];

        foreach ($cases as $field => $identity) {
            $response = $this->post(route('register.store'), $base + $identity);

            $response->assertSessionHasErrors($field);
            $this->assertSame(
                self::GENERIC_UNIQUE_MESSAGE,
                session('errors')->first($field),
                "Field {$field} must use the shared generic uniqueness message."
            );
        }
    }
}
