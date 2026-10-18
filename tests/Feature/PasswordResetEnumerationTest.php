<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 20 — Password reset account-enumeration verification (§8).
 *
 * The forgot-password endpoint must NOT reveal whether an account exists:
 * the response for an existing email and a non-existing email must be
 * indistinguishable (same status, same flash message, no field errors).
 *
 * No password-reset flow redesign is performed in Prompt 20 (§8: "jangan
 * sekaligus melakukan redesign password-reset flow tanpa scope").
 */
class PasswordResetEnumerationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::factory()->create([
            'email'     => $email,
            'role'      => 'masyarakat',
            'is_active' => true,
            'password'  => Hash::make('CorrectPassword123!'),
        ]);
    }

    private function requestReset(string $email)
    {
        return $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $email]);
    }

    // =========================================================================
    // Forgot-password — enumeration-safe (PASS)
    // =========================================================================

    public function test_forgot_password_returns_generic_status_for_existing_email(): void
    {
        $user = $this->user('terdaftar@example.test');

        $response = $this->requestReset($user->email);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'Jika email terdaftar, instruksi reset password telah dikirimkan.');
        $response->assertSessionHasNoErrors();
    }

    public function test_forgot_password_returns_generic_status_for_unknown_email(): void
    {
        $response = $this->requestReset('tidak-ada@example.test');

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'Jika email terdaftar, instruksi reset password telah dikirimkan.');
        $response->assertSessionHasNoErrors();
    }

    public function test_forgot_password_response_is_indistinguishable_between_existing_and_unknown_email(): void
    {
        $existing = $this->user('ada@example.test');

        $forExisting = $this->requestReset($existing->email);
        $flashExisting = $forExisting->getSession()->get('status');
        $statusExisting = $forExisting->getStatusCode();

        $forUnknown = $this->requestReset('tidak-ada-sama-sekali@example.test');
        $flashUnknown = $forUnknown->getSession()->get('status');
        $statusUnknown = $forUnknown->getStatusCode();

        // Identical HTTP status, identical redirect target, identical flash text,
        // and neither leaks a field error.
        $this->assertSame($statusExisting, $statusUnknown);
        $this->assertSame($flashExisting, $flashUnknown);
        $forExisting->assertSessionHasNoErrors();
        $forUnknown->assertSessionHasNoErrors();
    }

    public function test_forgot_password_invalid_email_format_is_a_validation_error(): void
    {
        // Input validation is a separate concern from enumeration and is allowed
        // to respond with a 422-style field error.
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    // =========================================================================
    // RESET endpoint (`POST /reset-password`) — enumeration-safe contract
    //
    // Audit PROMPT_28 (S-01, decision 2026-10-18): Prompt 20 had PINNED the
    // framework behaviour (`passwords.user` for an unknown account vs
    // `passwords.token` for a bad token) as a known gap. The approved
    // contract now is: every failure that is not "reset succeeded" or
    // "throttled" returns ONE generic message — an unknown email and a bad
    // token are indistinguishable.
    // =========================================================================

    public function test_reset_endpoint_unknown_email_yields_the_generic_message(): void
    {
        $response = $this->from(route('password.reset', ['token' => 'dummy-token']))
            ->post(route('password.update'), [
                'token'                 => 'dummy-token',
                'email'                 => 'tidak-ada@example.test',
                'password'              => 'BrandNewPassword123!',
                'password_confirmation' => 'BrandNewPassword123!',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(
            'Tautan reset tidak valid atau sudah kedaluwarsa.',
            session('errors')->first('email')
        );
    }

    public function test_reset_endpoint_existing_email_with_bad_token_yields_the_same_generic_message(): void
    {
        $user = $this->user('ada@example.test');

        $response = $this->from(route('password.reset', ['token' => 'bad-token']))
            ->post(route('password.update'), [
                'token'                 => 'bad-token',
                'email'                 => $user->email,
                'password'              => 'BrandNewPassword123!',
                'password_confirmation' => 'BrandNewPassword123!',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(
            'Tautan reset tidak valid atau sudah kedaluwarsa.',
            session('errors')->first('email'),
            'Unknown email and invalid token must be indistinguishable.'
        );
    }
}
