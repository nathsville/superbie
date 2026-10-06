<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 20 — Login rate-limit HTTP semantics (F-18-16, D-5).
 *
 * POLICY IS UNCHANGED (D-5). The login limiter keeps, exactly:
 *   - key        = `email|ip`   (Str::transliterate(Str::lower(email).'|'.ip))
 *   - threshold  = 5 attempts
 *   - window     = framework default decay (60 seconds)
 *   - behaviour  = hit on failure, clear on success, `Lockout` event
 *   - message    = "Terlalu banyak percobaan login. Coba lagi dalam :seconds detik."
 *
 * The ONLY Prompt 20 change: the exceeded state is an HTTP 429 (Too Many
 * Requests) instead of a 422 validation error, with a native `Retry-After`
 * header computed from `RateLimiter::availableIn()`.
 *
 * These tests prove the security invariant, not a re-designed policy.
 */
class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'CorrectPassword123!';

    private function user(string $email = 'warga@example.test'): User
    {
        return User::factory()->create([
            'email'     => $email,
            'role'      => 'masyarakat',
            'is_active' => true,
            'password'  => Hash::make(self::PASSWORD),
        ]);
    }

    /** The exact key the limiter must use (proves `email|ip`, never IP-only). */
    private function throttleKey(string $email, string $ip = '127.0.0.1'): string
    {
        return Str::transliterate(Str::lower($email) . '|' . $ip);
    }

    /** A single browser login POST. */
    private function attempt(string $email, string $password)
    {
        return $this->post(route('login.store'), [
            'email'    => $email,
            'password' => $password,
        ]);
    }

    /** Exhaust the limiter with N failed attempts. */
    private function exhaust(int $times, string $email): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->attempt($email, 'wrong-password');
        }
    }

    // =========================================================================
    // 1 — normal login still succeeds (no regression)
    // =========================================================================

    public function test_successful_login_redirects_to_dashboard(): void
    {
        $user = $this->user();

        $this->attempt($user->email, self::PASSWORD)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    // =========================================================================
    // 2 & 19 — invalid credentials remain a VALIDATION failure, NOT a 429
    // =========================================================================

    public function test_wrong_credentials_are_a_validation_error_not_a_rate_limit(): void
    {
        $user = $this->user();

        $this->attempt($user->email, 'wrong-password')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_missing_input_is_still_a_422_validation_failure(): void
    {
        // No email/password → validation, never the rate limiter.
        $response = $this->postJson(route('login.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    // =========================================================================
    // 3 & 4 — threshold stays 5 and the key stays `email|ip`
    // =========================================================================

    public function test_five_failed_attempts_are_allowed_then_sixth_is_rate_limited(): void
    {
        $user = $this->user();

        // The first 5 failed attempts are ordinary validation failures (422 JSON
        // so the status is deterministic and distinct from the 429).
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('login.store'), [
                'email'    => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // 6th → rate limited.
        $this->postJson(route('login.store'), [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        // The correct password is ALSO blocked while the window is open.
        $this->postJson(route('login.store'), [
            'email'    => $user->email,
            'password' => self::PASSWORD,
        ])->assertStatus(429);
    }

    public function test_limiter_uses_the_email_pipe_ip_key(): void
    {
        $user = $this->user();

        $this->exhaust(3, $user->email);

        // Directly prove the key format: `email|ip`.
        $this->assertSame(3, RateLimiter::attempts($this->throttleKey($user->email)));

        // An IP-only key must NOT exist (the scope is per account, not per IP).
        $this->assertSame(0, RateLimiter::attempts('127.0.0.1'));
    }

    // =========================================================================
    // 5 — the exceeded state is HTTP 429
    // =========================================================================

    public function test_rate_limited_browser_login_returns_429(): void
    {
        $user = $this->user();

        $this->exhaust(5, $user->email);

        $this->attempt($user->email, self::PASSWORD)->assertStatus(429);
        $this->assertGuest();
    }

    public function test_rate_limited_response_carries_native_retry_after_header(): void
    {
        $user = $this->user();

        $this->exhaust(5, $user->email);

        $response = $this->attempt($user->email, self::PASSWORD);

        $response->assertStatus(429);

        // Retry-After comes from RateLimiter::availableIn() — never hardcoded.
        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
        $this->assertLessThanOrEqual(60, (int) $retryAfter);
    }

    // =========================================================================
    // 15 — friendly message, no internal leakage in the rendered 429 view
    // =========================================================================

    public function test_rate_limited_browser_response_is_friendly_and_leaks_nothing(): void
    {
        $user = $this->user();

        $this->exhaust(5, $user->email);

        $response = $this->attempt($user->email, self::PASSWORD);

        $response->assertStatus(429);
        $response->assertSee('Terlalu banyak percobaan login', false);
        $response->assertDontSee('ThrottleRequestsException', false);
        $response->assertDontSee('Too Many Attempts', false);
        $response->assertDontSee($this->throttleKey($user->email), false);
        $response->assertDontSee('vendor' . DIRECTORY_SEPARATOR, false);
    }

    // =========================================================================
    // 16.5 — the window decays; login works again afterwards
    // =========================================================================

    public function test_login_succeeds_again_after_the_window_decays(): void
    {
        $user = $this->user();

        $this->exhaust(5, $user->email);
        $this->attempt($user->email, self::PASSWORD)->assertStatus(429);

        // Default RateLimiter decay is 60s; travel just past it (no sleep()).
        $this->travel(61)->seconds();

        $this->attempt($user->email, self::PASSWORD)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    // =========================================================================
    // hit-on-failure / clear-on-success behaviour is preserved
    // =========================================================================

    public function test_a_successful_login_clears_the_attempt_counter(): void
    {
        $user = $this->user();

        // 4 failures (still under the threshold).
        $this->exhaust(4, $user->email);
        $this->assertSame(4, RateLimiter::attempts($this->throttleKey($user->email)));

        // A correct login clears the counter...
        $this->attempt($user->email, self::PASSWORD)->assertRedirect(route('dashboard'));
        $this->assertSame(0, RateLimiter::attempts($this->throttleKey($user->email)));

        // ...so a fresh budget of 5 is available afterwards.
        $this->post(route('logout'));

        $this->exhaust(5, $user->email);
        $this->attempt($user->email, self::PASSWORD)->assertStatus(429);
    }

    public function test_lockout_event_is_dispatched_when_rate_limited(): void
    {
        $user = $this->user();

        $this->exhaust(5, $user->email);

        Event::fake([Lockout::class]);

        $this->attempt($user->email, self::PASSWORD);

        Event::assertDispatched(Lockout::class);
    }

    // =========================================================================
    // 16.7 — per-email isolation (same IP, different account)
    // =========================================================================

    public function test_rate_limit_is_isolated_per_email_account(): void
    {
        $locked = $this->user('locked@example.test');
        $other  = $this->user('other@example.test');

        $this->exhaust(5, $locked->email);
        $this->attempt($locked->email, self::PASSWORD)->assertStatus(429);

        // A different account on the SAME IP is unaffected → not 429.
        $this->attempt($other->email, self::PASSWORD)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($other);
    }

    // =========================================================================
    // 11 — JSON/AJAX requests also receive 429 (not a redirect)
    // =========================================================================

    public function test_json_login_rate_limit_returns_429_json(): void
    {
        $user = $this->user();

        $this->exhaust(5, $user->email);

        $response = $this->postJson(route('login.store'), [
            'email'    => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
    }

    // =========================================================================
    // 14 — the limiter must NOT spill onto unrelated endpoints
    // =========================================================================

    public function test_login_rate_limit_does_not_block_unrelated_endpoints(): void
    {
        $user = $this->user();

        $this->exhaust(5, $user->email);
        $this->attempt($user->email, self::PASSWORD)->assertStatus(429);

        // Read-only login page and the forgot-password flow keep working.
        $this->get(route('login'))->assertOk();
        $this->get(route('password.request'))->assertOk();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect();
    }
}
