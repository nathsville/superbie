<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 9 — Super Admin User Management.
 *
 * Covers §40–§42 test requirements:
 *   - Authorization: Super Admin only; Admin/Operator/Masyarakat → 403; guest → login.
 *   - Index: real DB data, server-side search (name/email), role filter,
 *     active/inactive filter, pagination.
 *   - Create: valid user, hashed password, plaintext never exposed, duplicate
 *     email rejected, invalid role / `petugas` rejected, audit logged.
 *   - Update: edit, duplicate email rejected, invalid role rejected, audit logged.
 *   - Activation: activate/deactivate + audit.
 *   - Last active super admin protection (incl. direct/hand-crafted requests).
 *   - Audit security: no secrets (password/token) in audit payload; failed
 *     authorization is never recorded as a successful mutation.
 *   - IDOR: direct requests from other roles are rejected (not just hidden nav).
 */
class SuperAdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;
    private DinasUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Prompt 15 — an Operator MUST belong to exactly one Dinas/Unit.
        $this->unit = DinasUnit::create([
            'name' => 'Dinas Uji UserMgmt', 'code' => 'UJI-UM',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->operator   = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
    }

    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Pengguna Baru',
            'email'                 => 'pengguna.baru@superbie.local',
            'password'              => 'RahasiaKuat123!',
            'password_confirmation' => 'RahasiaKuat123!',
            'role'                  => 'operator',
            'is_active'             => '1',
            'dinas_unit_id'         => $this->unit->id,
        ], $overrides);
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    public function test_super_admin_can_access_user_management(): void
    {
        $this->actingAs($this->superAdmin)->get(route('super-admin.users.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.users.create'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.users.edit', $this->citizen))->assertOk();
    }

    public function test_non_super_admin_cannot_access_user_management(): void
    {
        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.users.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.users.create'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.users.edit', $this->citizen))->assertForbidden();
            $this->actingAs($user)->post(route('super-admin.users.store'), $this->validCreatePayload())->assertForbidden();
            $this->actingAs($user)->put(route('super-admin.users.update', $this->citizen), [
                'name' => 'Hacked', 'email' => 'hacked@x.local', 'role' => 'admin', 'is_active' => '1',
            ])->assertForbidden();
            $this->actingAs($user)->patch(route('super-admin.users.toggle', $this->citizen))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('super-admin.users.index'))->assertRedirect(route('login'));
        $this->get(route('super-admin.users.create'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // IDOR — direct requests, not navigation visibility
    // =========================================================================

    public function test_idor_direct_requests_are_rejected_for_other_roles(): void
    {
        $target = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)
                ->get("/super-admin/users/{$target->id}/edit")
                ->assertForbidden();
            $this->actingAs($user)
                ->patch("/super-admin/users/{$target->id}/active")
                ->assertForbidden();
        }

        // Target unchanged.
        $this->assertTrue($target->fresh()->is_active);
    }

    // =========================================================================
    // INDEX — real data, search, filters, pagination
    // =========================================================================

    public function test_index_lists_real_database_users(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.users.index'));

        $response->assertOk();
        $response->assertSee($this->superAdmin->email);
        $response->assertSee($this->admin->email);
        $response->assertSee($this->operator->email);
        $response->assertSee($this->citizen->email);
    }

    public function test_search_by_name_works(): void
    {
        User::factory()->create(['name' => 'Zulkifli Petualang', 'role' => 'masyarakat']);
        User::factory()->create(['name' => 'Nama Lain', 'role' => 'masyarakat']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['search' => 'Zulkifli']));

        $response->assertOk();
        $response->assertSee('Zulkifli Petualang');
        $response->assertDontSee('Nama Lain');
    }

    public function test_search_by_email_works(): void
    {
        User::factory()->create(['email' => 'khusus.pencarian@superbie.local', 'role' => 'masyarakat']);
        User::factory()->create(['email' => 'lain.lagi@superbie.local', 'role' => 'masyarakat']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['search' => 'khusus.pencarian']));

        $response->assertOk();
        $response->assertSee('khusus.pencarian@superbie.local');
        $response->assertDontSee('lain.lagi@superbie.local');
    }

    public function test_filter_by_role_works(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['role' => 'admin']));

        $response->assertOk();
        $response->assertViewHas('users', function ($users) {
            return $users->every(fn ($u) => $u->role === 'admin');
        });
    }

    public function test_filter_by_status_works(): void
    {
        $inactive = User::factory()->create(['role' => 'masyarakat', 'is_active' => false]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['status' => 'inactive']));

        $response->assertOk();
        $response->assertViewHas('users', function ($users) {
            return $users->every(fn ($u) => $u->is_active === false);
        });

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['status' => 'active']));
        $response->assertViewHas('users', function ($users) {
            return $users->every(fn ($u) => $u->is_active === true);
        });
    }

    public function test_pagination_works_when_dataset_exceeds_page_size(): void
    {
        User::factory()->count(25)->create(['role' => 'masyarakat']);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.users.index'));

        $response->assertOk();
        // 20 per page → 29+ users total means more than one page.
        $response->assertViewHas('users', function ($users) {
            return $users->perPage() === 20 && $users->total() > 20 && $users->count() === 20;
        });
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function test_super_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), $this->validCreatePayload());

        $response->assertRedirect(route('super-admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'pengguna.baru@superbie.local',
            'role'  => 'operator',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'subject_type' => 'user',
        ]);
    }

    public function test_created_password_is_hashed_and_plaintext_not_exposed(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), $this->validCreatePayload([
                'password' => 'PlaintextRahasia123!',
                'password_confirmation' => 'PlaintextRahasia123!',
            ]))
            ->assertRedirect();

        $user = User::where('email', 'pengguna.baru@superbie.local')->firstOrFail();

        // Stored as a hash, not plaintext.
        $this->assertNotSame('PlaintextRahasia123!', $user->password);
        $this->assertTrue(Hash::check('PlaintextRahasia123!', $user->password));

        // Plaintext never appears on the index page.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index'))
            ->assertDontSee('PlaintextRahasia123!');
    }

    public function test_duplicate_email_is_rejected_on_create(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.users.store'),
            $this->validCreatePayload(['email' => $this->admin->email])
        );

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', $this->admin->email)->count());
    }

    public function test_invalid_role_is_rejected_on_create(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.users.store'),
            $this->validCreatePayload(['role' => 'unknown'])
        );

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'pengguna.baru@superbie.local']);
    }

    public function test_petugas_role_is_rejected_on_create(): void
    {
        foreach (['petugas', 'Petugas'] as $petugas) {
            $response = $this->actingAs($this->superAdmin)->post(
                route('super-admin.users.store'),
                $this->validCreatePayload(['email' => 'petugas' . strtolower($petugas) . '@x.local', 'role' => $petugas])
            );

            $response->assertSessionHasErrors('role');
        }

        $this->assertDatabaseMissing('users', ['role' => 'petugas']);
        $this->assertDatabaseMissing('users', ['role' => 'Petugas']);
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.users.store'),
            $this->validCreatePayload(['password_confirmation' => 'Berbeda123!'])
        );

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'pengguna.baru@superbie.local']);
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function test_super_admin_can_update_user(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $this->citizen),
            [
                'name'  => 'Nama Diperbarui',
                'email' => 'diperbarui@superbie.local',
                'role'  => 'masyarakat',
                'is_active' => '1',
            ]
        );

        $response->assertRedirect(route('super-admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $this->citizen->id,
            'name' => 'Nama Diperbarui',
            'email' => 'diperbarui@superbie.local',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'subject_type' => 'user']);
    }

    public function test_duplicate_email_is_rejected_on_update(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $this->citizen),
            [
                'name'  => $this->citizen->name,
                'email' => $this->admin->email, // already used
                'role'  => 'masyarakat',
                'is_active' => '1',
            ]
        );

        $response->assertSessionHasErrors('email');
        $this->assertSame($this->citizen->email, $this->citizen->fresh()->email);
    }

    public function test_invalid_role_is_rejected_on_update(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $this->citizen),
            [
                'name'  => $this->citizen->name,
                'email' => $this->citizen->email,
                'role'  => 'petugas',
                'is_active' => '1',
            ]
        );

        $response->assertSessionHasErrors('role');
        $this->assertSame('masyarakat', $this->citizen->fresh()->role);
    }

    public function test_role_change_is_audited(): void
    {
        $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $this->citizen),
            [
                'name'  => $this->citizen->name,
                'email' => $this->citizen->email,
                'role'  => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->unit->id,
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'subject_type' => 'user']);
        $this->assertSame('operator', $this->citizen->fresh()->role);
    }

    public function test_update_does_not_change_password(): void
    {
        $originalHash = $this->citizen->password;

        $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $this->citizen),
            [
                'name'  => 'Nama Saja',
                'email' => $this->citizen->email,
                'role'  => 'masyarakat',
                'is_active' => '1',
                'password' => 'AttemptedNewPassword123!',
                'password_confirmation' => 'AttemptedNewPassword123!',
            ]
        )->assertRedirect();

        // Password is not an editable field through this flow.
        $this->assertSame($originalHash, $this->citizen->fresh()->password);
    }

    // =========================================================================
    // ACTIVATION
    // =========================================================================

    public function test_super_admin_can_deactivate_and_activate_user(): void
    {
        // Target is a plain operator — deactivation is allowed.
        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.users.toggle', $this->operator))
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertFalse($this->operator->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'subject_type' => 'user']);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.users.toggle', $this->operator))
            ->assertRedirect();

        $this->assertTrue($this->operator->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activated', 'subject_type' => 'user']);
    }

    public function test_deactivated_user_cannot_use_authenticated_actions(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.users.toggle', $this->operator))
            ->assertRedirect();

        // The `active` middleware must log the (now inactive) user out.
        $this->actingAs($this->operator->fresh())
            ->get('/operator/dashboard')
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // LAST ACTIVE SUPER ADMIN PROTECTION
    // =========================================================================

    public function test_cannot_deactivate_last_active_super_admin(): void
    {
        // Only one active super admin exists: $this->superAdmin.
        $response = $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.users.toggle', $this->superAdmin));

        $response->assertSessionHasErrors('user');
        $this->assertTrue($this->superAdmin->fresh()->is_active);
    }

    public function test_cannot_downgrade_last_active_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $this->superAdmin),
            [
                'name'  => $this->superAdmin->name,
                'email' => $this->superAdmin->email,
                'role'  => 'admin', // downgrade
                'is_active' => '1',
            ]
        );

        $response->assertSessionHasErrors('role');
        $this->assertSame('super_admin', $this->superAdmin->fresh()->role);
    }

    public function test_cannot_deactivate_last_active_super_admin_via_edit_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $this->superAdmin),
            [
                'name'  => $this->superAdmin->name,
                'email' => $this->superAdmin->email,
                'role'  => 'super_admin',
                'is_active' => '0', // attempt to deactivate self
            ]
        );

        $response->assertSessionHasErrors('role');
        $this->assertTrue($this->superAdmin->fresh()->is_active);
    }

    public function test_can_deactivate_super_admin_when_another_active_one_exists(): void
    {
        $secondSuperAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.users.toggle', $secondSuperAdmin))
            ->assertRedirect(route('super-admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($secondSuperAdmin->fresh()->is_active);
        // The acting super admin is still active.
        $this->assertTrue($this->superAdmin->fresh()->is_active);
    }

    public function test_can_downgrade_super_admin_when_another_active_one_exists(): void
    {
        $secondSuperAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($this->superAdmin)->put(
            route('super-admin.users.update', $secondSuperAdmin),
            [
                'name'  => $secondSuperAdmin->name,
                'email' => $secondSuperAdmin->email,
                'role'  => 'admin',
                'is_active' => '1',
            ]
        )->assertRedirect(route('super-admin.users.index'))->assertSessionHasNoErrors();

        $this->assertSame('admin', $secondSuperAdmin->fresh()->role);
    }

    public function test_protection_works_on_direct_hand_crafted_request(): void
    {
        // A hand-crafted request bypassing the UI (no disabled button involved).
        $response = $this->actingAs($this->superAdmin)->patch(
            "/super-admin/users/{$this->superAdmin->id}/active"
        );

        $response->assertSessionHasErrors('user');
        $this->assertTrue($this->superAdmin->fresh()->is_active);
        // No successful deactivation audit entry was written.
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.deactivated']);
    }

    // =========================================================================
    // AUDIT SECURITY
    // =========================================================================

    public function test_password_and_token_are_never_written_to_audit_payload(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), $this->validCreatePayload([
            'password' => 'SangatRahasia999!',
            'password_confirmation' => 'SangatRahasia999!',
            // Extra fields a malicious client might try to smuggle in:
            'token' => 'reset-token-should-never-be-logged',
            'remember_token' => 'remember-should-never-be-logged',
        ]))->assertRedirect();

        $log = AuditLog::where('action', 'user.created')->firstOrFail();
        $payload = json_encode($log->metadata);

        $this->assertStringNotContainsString('SangatRahasia999!', $payload);
        $this->assertStringNotContainsString('reset-token-should-never-be-logged', $payload);
        $this->assertStringNotContainsString('remember-should-never-be-logged', $payload);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertArrayNotHasKey('token', $log->metadata);
    }

    public function test_failed_authorization_is_not_recorded_as_successful_mutation(): void
    {
        // An Admin attempts to create a user — rejected, and NOT audited as success.
        $this->actingAs($this->admin)
            ->post(route('super-admin.users.store'), $this->validCreatePayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'pengguna.baru@superbie.local']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.created']);
    }

    public function test_no_hard_delete_route_exists_for_users(): void
    {
        // User accounts are deactivated, never hard-deleted (Prompt 9 §22).
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => str_contains($route->uri(), 'super-admin/users')
                    && in_array('DELETE', $route->methods(), true)
            )
        );
    }
}
