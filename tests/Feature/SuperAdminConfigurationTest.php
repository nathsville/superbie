<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use App\Services\AppSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 11 — Super Admin Configuration Management.
 *
 * Covers §21 test requirements:
 *   - Authorization: Super Admin only; Masyarakat/Operator/Admin → 403; guest → login.
 *   - Read: config page loads with real values; honest empty/default state.
 *   - Update: valid value updates; invalid value rejected; arbitrary key rejected.
 *   - Security: no .env mutation, no arbitrary key creation, IDOR blocked.
 *   - Audit: successful update audited; failed/rejected update not audited; no secrets.
 *   - Runtime: the changed value is actually consumed by complaint submission.
 */
class SuperAdminConfigurationTest extends TestCase
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

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    public function test_super_admin_can_access_configuration(): void
    {
        $this->actingAs($this->superAdmin)->get(route('super-admin.config.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.config.edit', 'daily_report_limit'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('super-admin.config.index'))->assertRedirect(route('login'));
        $this->get(route('super-admin.config.edit', 'daily_report_limit'))->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_access_or_update_configuration(): void
    {
        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.config.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.config.edit', 'daily_report_limit'))->assertForbidden();
            $this->actingAs($user)
                ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => 9])
                ->assertForbidden();
        }

        // No override was written by the forbidden attempts.
        $this->assertDatabaseMissing('app_settings', ['key' => 'daily_report_limit']);
    }

    public function test_idor_unknown_setting_key_is_not_found(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.config.edit', 'app_key'))
            ->assertNotFound();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.config.edit', 'some_arbitrary_key'))
            ->assertNotFound();
    }

    // =========================================================================
    // READ
    // =========================================================================

    public function test_index_shows_real_managed_setting_and_default(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.config.index'));

        $response->assertOk();
        $response->assertSee('daily_report_limit');
        $response->assertSee('Batas Laporan Harian');
        $response->assertViewHas('settings', function ($settings) {
            $row = collect($settings)->firstWhere('key', 'daily_report_limit');

            return $row !== null
                && $row['default'] === 5          // real default from config/business_rules.php
                && $row['effective'] === 5        // nothing overridden yet
                && $row['is_overridden'] === false;
        });
    }

    public function test_index_reflects_existing_override(): void
    {
        AppSetting::set('daily_report_limit', 3);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.config.index'));

        $response->assertViewHas('settings', function ($settings) {
            $row = collect($settings)->firstWhere('key', 'daily_report_limit');

            return $row['is_overridden'] === true
                && $row['effective'] === 3
                && $row['override'] === '3';
        });
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function test_super_admin_can_update_setting(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => 7]);

        $response->assertRedirect(route('super-admin.config.index'));
        $this->assertDatabaseHas('app_settings', ['key' => 'daily_report_limit', 'value' => '7']);
    }

    public function test_invalid_value_is_rejected(): void
    {
        foreach ([0, -1, 'abc', ''] as $bad) {
            $response = $this->actingAs($this->superAdmin)
                ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => $bad]);

            $response->assertSessionHasErrors('value');
        }

        $this->assertDatabaseMissing('app_settings', ['key' => 'daily_report_limit']);
    }

    public function test_arbitrary_unknown_key_is_rejected_on_update(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->put(route('super-admin.config.update', 'arbitrary_key'), ['value' => 5]);

        // Either 404 (unknown key never reaches the form) or validation error —
        // in both cases nothing is persisted.
        $this->assertContains($response->getStatusCode(), [302, 404]);
        $this->assertDatabaseMissing('app_settings', ['key' => 'arbitrary_key']);
    }

    // =========================================================================
    // SECURITY
    // =========================================================================

    public function test_no_env_file_is_written(): void
    {
        $envPath = base_path('.env');
        $before = file_exists($envPath) ? file_get_contents($envPath) : null;

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => 4]);

        $after = file_exists($envPath) ? file_get_contents($envPath) : null;

        $this->assertSame($before, $after);
    }

    public function test_service_rejects_unmanaged_key(): void
    {
        $service = app(AppSettingService::class);

        $this->assertTrue($service->isManaged('daily_report_limit'));
        $this->assertFalse($service->isManaged('app_key'));

        $this->expectException(\InvalidArgumentException::class);
        $service->set('app_key', 123);
    }

    public function test_managed_keys_are_restricted(): void
    {
        $this->assertSame(['daily_report_limit'], app(AppSettingService::class)->managedKeys());
    }

    public function test_no_delete_route_exists_for_configuration(): void
    {
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => str_contains($route->uri(), 'super-admin/config')
                    && in_array('DELETE', $route->methods(), true)
            )
        );
    }

    // =========================================================================
    // AUDIT
    // =========================================================================

    public function test_successful_update_is_audited(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => 6])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'app_setting.updated',
            'subject_type' => 'app_setting',
            'actor_id'     => $this->superAdmin->id,
        ]);

        $log = AuditLog::where('action', 'app_setting.updated')->firstOrFail();
        $this->assertSame('daily_report_limit', $log->metadata['key']);
        $this->assertSame(6, $log->metadata['new_value']);
        $this->assertSame(5, $log->metadata['old_value']);
    }

    public function test_failed_update_does_not_create_success_audit(): void
    {
        // Invalid value → rejected by validation → no audit.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => -5])
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'app_setting.updated']);
    }

    public function test_forbidden_update_does_not_create_audit(): void
    {
        $this->actingAs($this->admin)
            ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => 6])
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'app_setting.updated']);
    }

    public function test_audit_metadata_contains_no_secrets(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => 8])
            ->assertRedirect();

        $log = AuditLog::where('action', 'app_setting.updated')->firstOrFail();
        $payload = json_encode($log->metadata);

        foreach (['password', 'token', 'secret', 'api_key', 'credential'] as $needle) {
            $this->assertStringNotContainsString($needle, strtolower($payload));
        }
    }

    // =========================================================================
    // RUNTIME EFFECT — the changed value is actually consumed
    // =========================================================================

    public function test_updated_setting_is_actually_consumed_by_complaint_submission(): void
    {
        $category = ComplaintCategory::create([
            'name' => 'Infrastruktur', 'slug' => 'infrastruktur', 'is_active' => true, 'sort_order' => 1,
        ]);

        // Set the daily limit to 1 via the admin UI.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.config.update', 'daily_report_limit'), ['value' => 1])
            ->assertRedirect();

        // First complaint today is accepted.
        Complaint::create([
            'reference_code' => 'LPW-CFG-0001',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $category->id,
            'title'          => 'Laporan pertama',
            'description'    => 'Deskripsi laporan pertama.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ]);

        // Second complaint today is rejected by the consumer using the new limit.
        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $category->id,
            'title'       => 'Laporan kedua',
            'description'    => 'Deskripsi laporan kedua.',
        ]);

        $response->assertSessionHasErrors('daily_limit');
    }

    public function test_fallback_to_default_when_no_override(): void
    {
        $service = app(AppSettingService::class);

        $this->assertNull($service->storedValue('daily_report_limit'));
        $this->assertSame(5, $service->effectiveValue('daily_report_limit'));
        $this->assertSame(5, $service->defaultValue('daily_report_limit'));
    }
}
