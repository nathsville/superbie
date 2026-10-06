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
    // CHARACTERIZATION — reset endpoint (`POST /reset-password`)
    //
    // KNOWN GAP / DECISION REQUIRED (see Prompt 20 report, F-20-01):
    // Laravel's password broker distinguishes an unknown account
    // (`passwords.user`) from an invalid token (`passwords.token`), and the
    // controller surfaces that status via `withErrors`. This is NOT changed in
    // Prompt 20 because §8 forbids redesigning the reset flow without scope.
    // The assertions below PIN the current behaviour so a future approved
    // decision can be verified against a known baseline.
    // =========================================================================

    public function test_reset_endpoint_unknown_email_yields_the_invalid_user_message(): void
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
            __('passwords.user'),
            session('errors')->first('email'),
            'Characterization: unknown-account reset currently returns the passwords.user message.'
        );
    }

    public function test_reset_endpoint_existing_email_with_bad_token_yields_the_invalid_token_message(): void
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
            __('passwords.token'),
            session('errors')->first('email'),
            'Characterization: existing-account reset with a bad token returns the passwords.token message.'
        );
    }
}
