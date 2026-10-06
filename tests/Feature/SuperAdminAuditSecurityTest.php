<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 10 — Super Admin Audit & Security surface.
 *
 * Covers §20 test requirements:
 *   - Authorization: Super Admin only; Masyarakat/Operator/Admin → 403; guest → login.
 *   - Index: real DB rows, pagination, honest empty state.
 *   - Search: action / actor (name+email) / subject_type / subject_id.
 *   - Filter: action / subject / actor / date range.
 *   - Detail: Super Admin allowed, others denied, IDOR blocked.
 *   - Read-only: no DELETE/PUT/PATCH audit endpoint exists.
 *   - Secret protection: sensitive metadata keys are redacted at presentation.
 *   - Historical integrity: reading the audit surface never mutates records.
 */
class SuperAdminAuditSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->operator   = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
    }

    /**
     * AuditLog has $timestamps = false and only a `created_at` column, and
     * `created_at` is intentionally NOT mass-assignable, so it must be set via
     * forceFill() to persist an explicit timestamp in tests.
     */
    private function log(array $attributes = []): AuditLog
    {
        $log = new AuditLog();

        $log->forceFill(array_merge([
            'actor_id'     => $this->superAdmin->id,
            'action'       => 'user.created',
            'subject_type' => 'user',
            'subject_id'   => $this->citizen->id,
            'metadata'     => ['name' => 'Contoh', 'role' => 'masyarakat'],
            'ip_address'   => '127.0.0.1',
            'user_agent'   => 'PHPUnit',
            'created_at'   => now(),
        ], $attributes))->save();

        return $log;
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    public function test_super_admin_can_access_audit_surface(): void
    {
        $this->actingAs($this->superAdmin)->get(route('super-admin.audit.index'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('super-admin.audit.index'))->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_access_audit_surface(): void
    {
        $log = $this->log();

        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.audit.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.audit.show', $log))->assertForbidden();
        }
    }

    public function test_idor_direct_detail_request_is_rejected_for_other_roles(): void
    {
        $log = $this->log();

        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)
                ->get("/super-admin/audit/{$log->id}")
                ->assertForbidden();
        }
    }

    // =========================================================================
    // INDEX
    // =========================================================================

    public function test_index_lists_real_database_audit_rows(): void
    {
        $this->log(['action' => 'dinas_unit.created', 'subject_type' => 'dinas_unit']);
        $this->log(['action' => 'user.created', 'subject_type' => 'user']);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.audit.index'));

        $response->assertOk();
        $response->assertSee('dinas_unit.created');
        $response->assertSee('user.created');
        $response->assertViewHas('totalLogs', 2);
    }

    public function test_empty_state_is_shown_when_no_audit_rows(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.audit.index'));

        $response->assertOk();
        $response->assertSee('Tidak ada catatan audit yang ditemukan.');
    }

    public function test_pagination_works(): void
    {
        for ($i = 0; $i < 35; $i++) {
            $this->log(['action' => 'user.updated']);
        }

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.audit.index'));

        $response->assertOk();
        $response->assertViewHas('logs', function ($logs) {
            return $logs->perPage() === 30 && $logs->total() === 35 && $logs->count() === 30;
        });
    }

    // =========================================================================
    // SEARCH
    // =========================================================================

    public function test_search_by_action_works(): void
    {
        $this->log(['action' => 'user.deactivated']);
        $this->log(['action' => 'complaint_category.created', 'subject_type' => 'complaint_category']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['search' => 'deactivated']));

        $response->assertOk();
        // The filtered RESULT SET must contain only the matching row. (The filter
        // dropdown legitimately lists all distinct actions, so we assert on the
        // paginated collection, not on raw HTML.)
        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1 && $logs->first()->action === 'user.deactivated';
        });
    }

    public function test_search_by_actor_name_works(): void
    {
        $named = User::factory()->create(['name' => 'Zulkifli Auditor', 'role' => 'super_admin']);
        $this->log(['actor_id' => $named->id, 'action' => 'user.created']);
        $this->log(['action' => 'user.updated']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['search' => 'Zulkifli']));

        $response->assertOk();
        $response->assertViewHas('logs', function ($logs) use ($named) {
            return $logs->total() === 1 && (int) $logs->first()->actor_id === $named->id;
        });
    }

    public function test_search_by_subject_type_works(): void
    {
        $this->log(['action' => 'dinas_unit.updated', 'subject_type' => 'dinas_unit']);
        $this->log(['action' => 'user.created', 'subject_type' => 'user']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['search' => 'dinas_unit']));

        $response->assertOk();
        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1 && $logs->first()->subject_type === 'dinas_unit';
        });
    }

    public function test_search_by_subject_id_works(): void
    {
        $this->log(['action' => 'user.created', 'subject_type' => 'user', 'subject_id' => 987654]);
        $this->log(['action' => 'user.updated', 'subject_type' => 'user', 'subject_id' => 111111]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['search' => '987654']));

        $response->assertOk();
        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1 && (int) $logs->first()->subject_id === 987654;
        });
    }

    // =========================================================================
    // FILTER
    // =========================================================================

    public function test_filter_by_action_works(): void
    {
        $this->log(['action' => 'user.role_changed']);
        $this->log(['action' => 'user.created']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['action' => 'user.role_changed']));

        $response->assertOk();
        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1 && $logs->first()->action === 'user.role_changed';
        });
    }

    public function test_filter_by_subject_works(): void
    {
        $this->log(['action' => 'dinas_unit.created', 'subject_type' => 'dinas_unit']);
        $this->log(['action' => 'user.created', 'subject_type' => 'user']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['subject' => 'dinas_unit']));

        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1 && $logs->first()->subject_type === 'dinas_unit';
        });
    }

    public function test_filter_by_actor_works(): void
    {
        $other = User::factory()->create(['role' => 'super_admin']);
        $this->log(['actor_id' => $other->id]);
        $this->log(['actor_id' => $this->superAdmin->id]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['actor' => $other->id]));

        $response->assertViewHas('logs', function ($logs) use ($other) {
            return $logs->total() === 1 && (int) $logs->first()->actor_id === $other->id;
        });
    }

    public function test_filter_by_date_range_works(): void
    {
        $this->log(['action' => 'user.created', 'created_at' => now()->subDays(10)]);
        $this->log(['action' => 'user.updated', 'created_at' => now()->subDay()]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.audit.index', [
            'date_from' => now()->subDays(3)->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]));

        $response->assertViewHas('logs', function ($logs) {
            return $logs->total() === 1 && $logs->first()->action === 'user.updated';
        });
    }

    public function test_invalid_date_input_is_ignored_safely(): void
    {
        $this->log();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index', ['date_from' => 'not-a-date']));

        $response->assertOk();
        $response->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    // =========================================================================
    // DETAIL
    // =========================================================================

    public function test_super_admin_can_view_audit_detail(): void
    {
        $log = $this->log(['action' => 'user.role_changed', 'metadata' => ['old_role' => 'admin', 'new_role' => 'operator']]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.audit.show', $log));

        $response->assertOk();
        $response->assertSee('user.role_changed');
        $response->assertSee('admin');
        $response->assertSee('operator');
    }

    // =========================================================================
    // READ-ONLY / NO MUTATION
    // =========================================================================

    public function test_no_audit_mutation_routes_exist(): void
    {
        $mutating = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'super-admin/audit'))
            ->flatMap(fn ($route) => $route->methods())
            ->filter(fn ($method) => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true))
            ->values();

        $this->assertTrue($mutating->isEmpty(), 'Audit surface must expose no mutating routes: ' . $mutating->implode(', '));
    }

    // =========================================================================
    // SECRET PROTECTION
    // =========================================================================

    public function test_sensitive_metadata_is_redacted_on_index(): void
    {
        $this->log(['action' => 'user.created', 'metadata' => [
            'name'                  => 'Aman',
            'password'              => 'SuperRahasia123!',
            'remember_token'        => 'remember-secret-value',
            'api_key'               => 'key-should-not-leak',
        ]]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.audit.index'));

        $response->assertOk();
        $response->assertDontSee('SuperRahasia123!');
        $response->assertDontSee('remember-secret-value');
        $response->assertDontSee('key-should-not-leak');
        $response->assertSee(AuditLogService::REDACTED);
    }

    public function test_sensitive_metadata_is_redacted_on_detail(): void
    {
        $log = $this->log(['action' => 'user.created', 'metadata' => [
            'name'      => 'Aman',
            'password'  => 'SuperRahasia123!',
            'token'     => 'reset-token-value',
            'secret'    => 'app-secret-value',
        ]]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.audit.show', $log));

        $response->assertOk();
        $response->assertDontSee('SuperRahasia123!');
        $response->assertDontSee('reset-token-value');
        $response->assertDontSee('app-secret-value');
        $response->assertSee(AuditLogService::REDACTED);
        // Non-sensitive values remain visible.
        $response->assertSee('Aman');
    }

    public function test_redaction_service_masks_nested_and_case_insensitive_keys(): void
    {
        $service = app(AuditLogService::class);

        $safe = $service->redactMetadata([
            'Password'        => 'x',
            'nested'          => ['reset_token' => 'y', 'kept' => 'visible'],
            'safe_field'      => 'ok',
        ]);

        $this->assertSame(AuditLogService::REDACTED, $safe['Password']);
        $this->assertSame(AuditLogService::REDACTED, $safe['nested']['reset_token']);
        $this->assertSame('visible', $safe['nested']['kept']);
        $this->assertSame('ok', $safe['safe_field']);
    }

    // =========================================================================
    // HISTORICAL INTEGRITY
    // =========================================================================

    public function test_reading_audit_does_not_mutate_records(): void
    {
        $log = $this->log(['action' => 'user.created', 'metadata' => ['name' => 'Asli', 'role' => 'admin']]);
        $before = $log->fresh()->toArray();

        $this->actingAs($this->superAdmin)->get(route('super-admin.audit.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.audit.show', $log))->assertOk();

        $after = $log->fresh()->toArray();

        $this->assertSame($before, $after);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_redaction_does_not_persist_changes_to_stored_metadata(): void
    {
        $log = $this->log(['action' => 'user.created', 'metadata' => [
            'name'     => 'Aman',
            'password' => 'StoredPlaintext123!',
        ]]);

        $this->actingAs($this->superAdmin)->get(route('super-admin.audit.show', $log))->assertOk();

        // Stored value is untouched — redaction is presentation-only.
        $this->assertSame('StoredPlaintext123!', $log->fresh()->metadata['password']);
    }
}
