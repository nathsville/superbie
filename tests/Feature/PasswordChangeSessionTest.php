<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 19 — Scope C: session invalidation on password change.
 *
 * F-18-06: changing a password must invalidate OTHER authenticated sessions so
 * a stolen/older session cannot outlive the credential rotation, while the
 * CURRENT session stays valid. Implemented with the framework's
 * AuthenticateSession middleware (`$middleware->authenticateSessions()`) plus
 * `Auth::logoutOtherDevices()` in ProfileController::update().
 *
 * Login policy (key/threshold/window) is UNCHANGED (D-5).
 */
class PasswordChangeSessionTest extends TestCase
{
    use RefreshDatabase;

    private function citizen(string $password = 'OldPassword123!'): User
    {
        return User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
            'password'  => Hash::make($password),
        ]);
    }

    private function login(User $user, string $password)
    {
        return $this->post(route('login.store'), [
            'email'    => $user->email,
            'password' => $password,
        ]);
    }

    public function test_auth_session_middleware_is_active_after_login(): void
    {
        $user = $this->citizen();

        $this->login($user, 'OldPassword123!')->assertRedirect(route('dashboard'));

        // The AuthenticateSession middleware records a password hash in the
        // session; its presence proves the middleware is registered and running.
        $keys = array_keys(session()->all());
        $hasPasswordHash = collect($keys)->contains(
            fn (string $k) => str_starts_with($k, 'password_hash_')
        );

        $this->assertTrue($hasPasswordHash, 'auth.session middleware is not active');
    }

    public function test_current_session_remains_authenticated_after_password_change(): void
    {
        $user = $this->citizen();

        $this->login($user, 'OldPassword123!');

        $response = $this->patch(route('citizen.profile.update'), [
            'name'                  => $user->name,
            'phone_number'          => $user->phone_number,
            'address'               => $user->address,
            'current_password'      => 'OldPassword123!',
            'password'              => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // The current session must survive the rotation.
        $this->get(route('citizen.dashboard'))->assertOk();

        // The credential actually rotated.
        $user->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
    }

    public function test_a_session_holding_a_stale_password_hash_is_invalidated(): void
    {
        $user = $this->citizen();

        // "Device A" logs in; the AuthenticateSession middleware stores this
        // session's password hash (HMAC form) under `password_hash_web`.
        $this->login($user, 'OldPassword123!');
        $staleSessionHash = session('password_hash_web');
        $this->assertNotNull($staleSessionHash);

        // The credential is rotated elsewhere (e.g. on another device, or by an
        // admin) — the stored session hash is now stale.
        $user->forceFill(['password' => Hash::make('RotatedPassword123!')])->save();

        // Ensure the guard re-resolves the user from the DB (a real "device A"
        // request is a separate process), so the comparison uses the NEW hash.
        $this->app['auth']->forgetGuards();

        $this->withSession(['password_hash_web' => $staleSessionHash])
            ->get(route('citizen.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_old_password_no_longer_authenticates_and_new_password_does(): void
    {
        $user = $this->citizen();

        // Rotate through the real profile endpoint.
        $this->login($user, 'OldPassword123!');
        $this->patch(route('citizen.profile.update'), [
            'name'                  => $user->name,
            'phone_number'          => $user->phone_number,
            'address'               => $user->address,
            'current_password'      => 'OldPassword123!',
            'password'              => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ])->assertRedirect();

        // Drop authentication, then attempt both credentials.
        $this->post(route('logout'));

        $this->login($user, 'OldPassword123!')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->login($user, 'BrandNewPassword123!')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_password_change_without_a_new_password_does_not_log_out_other_devices(): void
    {
        // Profile updates that do NOT change the password must not trigger a
        // session-rotation side effect (no spurious invalidation).
        $user = $this->citizen();
        $this->login($user, 'OldPassword123!');

        $this->patch(route('citizen.profile.update'), [
            'name'         => 'Nama Diperbarui',
            'phone_number' => $user->phone_number,
            'address'      => $user->address,
        ])->assertRedirect();

        $this->get(route('citizen.dashboard'))->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('OldPassword123!', $user->password));
        $this->assertSame('Nama Diperbarui', $user->name);
    }
}
