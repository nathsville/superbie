<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 16 — Last Active Super Admin invariant + concurrency safety.
 *
 * Invariant (Prompt 9 §18–§21, hardened in Prompt 16):
 *   The system must ALWAYS keep at least one ACTIVE Super Admin.
 *
 * The check used to run OUTSIDE the mutation transaction (a TOCTOU window).
 * Prompt 16 moves the authoritative check INSIDE the transaction and locks the
 * active-Super-Admin rows (`SELECT ... FOR UPDATE`), so two concurrent removals
 * serialize and cannot both succeed.
 *
 * Cases A–E are deterministic invariant tests. Case F verifies the locking
 * mechanism is actually wired (FOR UPDATE emitted only when requested). Full
 * cross-connection concurrency cannot be reliably reproduced under
 * `RefreshDatabase`'s single-transaction isolation — see the note on Case F.
 */
class LastSuperAdminConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(bool $active = true): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => $active]);
    }

    private function activeSuperAdminCount(): int
    {
        return User::where('role', 'super_admin')->where('is_active', true)->count();
    }

    // ── Case A ────────────────────────────────────────────────────────────────
    public function test_case_a_deactivate_one_of_two_active_super_admins_succeeds(): void
    {
        $acting  = $this->makeSuperAdmin();
        $second  = $this->makeSuperAdmin();

        $this->actingAs($acting)
            ->patch(route('super-admin.users.toggle', $second))
            ->assertRedirect(route('super-admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($second->fresh()->is_active);
        $this->assertSame(1, $this->activeSuperAdminCount());
    }

    // ── Case B ────────────────────────────────────────────────────────────────
    public function test_case_b_deactivate_last_active_super_admin_is_rejected(): void
    {
        $only = $this->makeSuperAdmin();

        $this->actingAs($only)
            ->patch(route('super-admin.users.toggle', $only))
            ->assertSessionHasErrors('user');

        $this->assertTrue($only->fresh()->is_active);
        $this->assertSame(1, $this->activeSuperAdminCount());
    }

    // ── Case C ────────────────────────────────────────────────────────────────
    public function test_case_c_no_user_delete_flow_exists_to_remove_last_super_admin(): void
    {
        // The application exposes NO hard-delete of users (deactivation only),
        // so the "delete the last active super admin" vector does not exist.
        $this->assertFalse(Route::has('super-admin.users.destroy'));

        $hasDeleteUserRoute = collect(Route::getRoutes())->contains(function ($route) {
            return in_array('DELETE', $route->methods(), true)
                && str_contains($route->uri(), 'users');
        });
        $this->assertFalse($hasDeleteUserRoute, 'No DELETE route may target users.');

        // Even so, the invariant is upheld by deactivation protection.
        $only = $this->makeSuperAdmin();
        $this->assertTrue($only->fresh()->is_active);
    }

    // ── Case D ────────────────────────────────────────────────────────────────
    public function test_case_d_demote_one_of_two_active_super_admins_succeeds(): void
    {
        $acting = $this->makeSuperAdmin();
        $second = $this->makeSuperAdmin();

        $this->actingAs($acting)->put(
            route('super-admin.users.update', $second),
            [
                'name' => $second->name,
                'email' => $second->email,
                'role' => 'admin',
                'is_active' => '1',
            ]
        )->assertRedirect(route('super-admin.users.index'))->assertSessionHasNoErrors();

        $this->assertSame('admin', $second->fresh()->role);
        $this->assertSame(1, $this->activeSuperAdminCount());
    }

    // ── Case E ────────────────────────────────────────────────────────────────
    public function test_case_e_demote_last_active_super_admin_is_rejected(): void
    {
        $only = $this->makeSuperAdmin();

        $this->actingAs($only)->put(
            route('super-admin.users.update', $only),
            [
                'name' => $only->name,
                'email' => $only->email,
                'role' => 'admin', // demote away from super_admin
                'is_active' => '1',
            ]
        )->assertSessionHasErrors('role');

        $this->assertSame('super_admin', $only->fresh()->role);
        $this->assertSame(1, $this->activeSuperAdminCount());
    }

    // ── Case F — locking mechanism (as far as reliably testable) ──────────────
    /**
     * Proves the authoritative check issues a row lock (`SELECT ... FOR UPDATE`)
     * when `lock: true`, and does NOT when `lock: false`.
     *
     * Why not a true two-connection race test: `RefreshDatabase` wraps each test
     * in a single transaction on the default connection, so a second connection
     * cannot observe the fixture rows and a genuine blocking race cannot be
     * reproduced deterministically. Instead we assert the DB-level lock is
     * actually requested by the service — the mechanism that makes concurrent
     * removals serialize — and keep the invariant tests (A–E) deterministic.
     */
    public function test_case_f_authoritative_check_requests_a_row_lock(): void
    {
        $only = $this->makeSuperAdmin();
        $service = app(UserManagementService::class);

        // ── lock: true → a FOR UPDATE statement must be emitted ───────────────
        $lockedQueries = [];
        DB::listen(function ($query) use (&$lockedQueries) {
            $lockedQueries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
        });

        DB::transaction(function () use ($service, $only) {
            $result = $service->wouldRemoveLastActiveSuperAdmin($only, 'admin', true, lock: true);
            $this->assertTrue($result, 'The last active super admin must be protected.');
        });

        $this->assertNotEmpty($lockedQueries);
        $this->assertTrue(
            collect($lockedQueries)->contains(function (array $q) {
                return str_contains(strtolower($q['sql']), 'for update')
                    && str_contains($q['sql'], 'users')
                    && in_array('super_admin', $q['bindings'], true);
            }),
            'The locked check must issue a SELECT ... FOR UPDATE over active Super Admins.'
        );

        // ── lock: false (informational path) → no FOR UPDATE emitted ──────────
        $unlockedQueries = [];
        DB::listen(function ($query) use (&$unlockedQueries) {
            $unlockedQueries[] = $query->sql;
        });

        $service->wouldRemoveLastActiveSuperAdmin($only, 'admin', true, lock: false);

        $this->assertFalse(
            collect($unlockedQueries)->contains(
                fn (string $sql) => str_contains(strtolower($sql), 'for update')
            ),
            'The unlocked check must not take a row lock.'
        );
    }

    public function test_locked_check_does_not_lock_for_non_super_admin_targets(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $citizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $service = app(UserManagementService::class);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        DB::transaction(function () use ($service, $citizen) {
            // A non-super-admin target can never threaten the invariant.
            $result = $service->wouldRemoveLastActiveSuperAdmin($citizen, 'admin', false, lock: true);
            $this->assertFalse($result);
        });

        $this->assertFalse(
            collect($queries)->contains(
                fn (string $sql) => str_contains(strtolower($sql), 'for update')
            ),
            'Editing a non-super-admin must not take the Super Admin lock.'
        );

        // Sanity: the guard still holds for the real super admin.
        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    // ── Invariant is evaluated against live DB state (self-lockout) ───────────
    public function test_invariant_protects_self_lockout_via_edit_form(): void
    {
        $only = $this->makeSuperAdmin();

        $this->actingAs($only)->put(
            route('super-admin.users.update', $only),
            [
                'name' => $only->name,
                'email' => $only->email,
                'role' => 'super_admin',
                'is_active' => '0', // attempt to deactivate self via edit form
            ]
        )->assertSessionHasErrors('role');

        $this->assertTrue($only->fresh()->is_active);
        $this->assertSame(1, $this->activeSuperAdminCount());
    }

    public function test_invariant_holds_when_two_super_admins_are_removed_sequentially(): void
    {
        $a = $this->makeSuperAdmin();
        $b = $this->makeSuperAdmin();

        // First removal: allowed (one remains).
        $this->actingAs($a)
            ->patch(route('super-admin.users.toggle', $b))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->activeSuperAdminCount());

        // Second removal: rejected (would leave zero).
        $this->actingAs($a)
            ->patch(route('super-admin.users.toggle', $a))
            ->assertSessionHasErrors('user');
        $this->assertSame(1, $this->activeSuperAdminCount());
    }
}
