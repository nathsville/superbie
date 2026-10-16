<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Prompt 8 — P0 remediation regression guards.
 *
 * These tests protect the specific P0 defects fixed in Prompt 8 so they cannot
 * silently regress:
 *   P0-1  Password reset page must render (previously a missing view → HTTP 500).
 *   P0-2  Admin dashboard must show only real DB data or an honest empty state —
 *         never fabricated officers/districts/OPDs/percentages/trends.
 *   P0-3  No invented SLA may be computed or displayed (SLA is not an approved
 *         business rule).
 *
 * Assertions target rendered output / view data (not trivial `assertTrue(true)`).
 */
class P0RemediationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ComplaintCategory::create([
            'name'       => 'Infrastruktur Jalan',
            'slug'       => 'infrastruktur-jalan',
            'dinas_name' => 'Dinas PUPR',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function makeComplaint(User $reporter, string $status = 'submitted', array $overrides = []): Complaint
    {
        static $seq = 0;
        $seq++;

        return Complaint::create(array_merge([
            'reference_code'      => 'LPW-REG-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'tracking_secret_hash' => hash('sha256', 'secret-' . $seq),
            'category_id'         => $this->category->id,
            'reporter_id'         => $reporter->id,
            'title'               => 'Laporan regresi ' . $seq,
            'description'         => 'Deskripsi laporan regresi untuk pengujian.',
            'location_text'       => 'Jl. Bau Massepe',
            'status'              => $status,
            'submitted_at'        => now(),
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // P0-1 — Password reset view renders (no 500)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_reset_password_page_renders_with_token(): void
    {
        $response = $this->get('/reset-password/some-valid-looking-token');

        $response->assertOk();
        // The form must expose the token, email, password and confirmation fields.
        $response->assertSee('name="token"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        // CSRF protection present.
        $response->assertSee('name="_token"', false);
    }

    public function test_reset_password_page_prefills_email_from_query_string(): void
    {
        $response = $this->get('/reset-password/some-token?email=warga@superbie.local');

        $response->assertOk();
        $response->assertSee('warga@superbie.local', false);
    }

    public function test_password_reset_flow_updates_the_password(): void
    {
        $user = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
            'password'  => Hash::make('OldPassword123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // P0-2 — Admin dashboard shows real data / honest empty state, no fakes
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_dashboard_renders_real_counts_from_database(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, 'submitted');
        $this->makeComplaint($reporter, 'in_progress');
        $this->makeComplaint($reporter, 'resolved');

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard Admin');

        // Controller must pass the real, derived-from-DB metrics.
        $response->assertViewHas('totalLaporan', 3);
        $response->assertViewHas('laporanAktif', 2);   // submitted + in_progress
        $response->assertViewHas('laporanSelesai', 1); // resolved
    }

    public function test_admin_dashboard_shows_honest_empty_state_with_no_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 0);
        // Honest empty states (not fabricated content) for the retained cards.
        $response->assertSee('Belum ada data historis laporan.');
        $response->assertSee('Belum ada data kategori.');
    }

    public function test_admin_dashboard_does_not_contain_fabricated_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertOk();

        // Known fabricated strings from the pre-Prompt-8 dashboard.
        foreach ([
            'Drs. M. Sanusi',
            'Lowokwaru',
            'Klojen',
            'Kedungkandang',
            '4 OPD Utama',
            '2.740',
            'Net Backlog',
            'SHA-256',
            'Integritas Hash',
        ] as $fabricated) {
            $response->assertDontSee($fabricated, false);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // P0-3 — No invented SLA is claimed anywhere in the admin views
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_dashboard_claims_no_sla(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertDontSee('SLA', false);
        $response->assertDontSee('Terlambat', false);
    }

    public function test_admin_complaints_page_claims_no_sla(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, 'submitted');

        $response = $this->actingAs($admin)->get('/admin/laporan');

        $response->assertOk();
        $response->assertDontSee('SLA', false);
        $response->assertDontSee('Terlambat', false);
        // The neutral, factual column header replaces the old "SLA Status".
        $response->assertSee('Umur Laporan');
    }

    public function test_admin_complaints_kpi_has_no_sla_risk_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/laporan');

        $response->assertOk();

        $kpi = $response->viewData('kpi');
        $this->assertIsArray($kpi);
        $this->assertArrayNotHasKey('sla_risk', $kpi);
        // PHASE 6C — Decision 3: the KPI is now "Belum Ditentukan Dinas/Unit"
        // (routing destination missing), NOT the deprecated "belum ditugaskan".
        $this->assertArrayHasKey('belum_dinas_unit', $kpi);
        $this->assertArrayNotHasKey('belum_ditugaskan', $kpi);
    }

    // =========================================================================
    // Admin complaints table — readability / layout contract (visual hardening)
    // =========================================================================

    public function test_admin_complaints_table_has_readable_layout_hooks(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->makeComplaint($reporter, 'submitted');

        $html = $this->actingAs($admin)->get('/admin/laporan')->assertOk()->getContent();

        // Filter bar is a single tidy grid (not the old wrapping flex row).
        $this->assertStringContainsString('filter-grid', $html);

        // Row typography + spacing hooks (clearer secondary-text contrast).
        $this->assertStringContainsString('ml-ref', $html);
        $this->assertStringContainsString('ml-title', $html);
        $this->assertStringContainsString('ml-reporter', $html);

        // Aksi column is the clean ghost button (no literal "Detail >").
        $this->assertStringContainsString('btn-detail', $html);
        $this->assertStringNotContainsString('Detail', $html);
    }

    public function test_admin_complaints_action_is_not_a_dead_link(): void
    {
        // Admin is monitoring-only with NO detail route, so the Aksi affordance
        // must never be a hyperlink (no dead link / no false UI).
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $complaint = $this->makeComplaint($reporter, 'submitted');

        $html = $this->actingAs($admin)->get('/admin/laporan')->assertOk()->getContent();

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.complaints.show'));
        $this->assertStringNotContainsString('/admin/laporan/'.$complaint->id, $html);
    }

    public function test_admin_complaints_unassigned_filter_is_real(): void
    {
        // The Attention Center's "belum ditentukan Dinas/Unit" signal links to a
        // REAL filter — it must return only reports with no routing destination
        // (dinas_unit_id IS NULL) that are still active (no dead/fake filter).
        //
        // PHASE 6C — Decision 3: the filter semantics are Dinas/Unit-based, NOT
        // the deprecated `assigned_to` column.
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $unit     = \App\Models\DinasUnit::create([
            'name'       => 'Dinas PUPR Filter',
            'code'       => 'PUPR-FILTER',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $unrouted = $this->makeComplaint($reporter, 'submitted', ['dinas_unit_id' => null]);
        $routed   = $this->makeComplaint($reporter, 'submitted', ['dinas_unit_id' => $unit->id]);
        $resolved = $this->makeComplaint($reporter, 'resolved', ['dinas_unit_id' => null]);

        $response = $this->actingAs($admin)->get('/admin/laporan?assigned=unassigned');

        $response->assertOk();
        $response->assertSee($unrouted->reference_code);
        $response->assertDontSee($routed->reference_code);
        $response->assertDontSee($resolved->reference_code);

        // The active filter is preserved + reflected honestly.
        $response->assertSee('name="assigned"', false);
        $response->assertSee('value="unassigned"', false);
    }

    public function test_admin_complaints_category_filter_is_global(): void
    {
        // Admin is a GLOBAL monitoring role with NO Dinas/Unit (the User model
        // forces dinas_unit_id = NULL for non-operator roles). Its Kategori
        // dropdown therefore intentionally lists EVERY category — no per-unit
        // narrowing, unlike the Operator "Kelola Laporan" surface.
        $admin    = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reporter = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $unitA = \App\Models\DinasUnit::create([
            'name' => 'Dinas Global A', 'code' => 'GLOBAL-A', 'is_active' => true, 'sort_order' => 1,
        ]);
        $unitB = \App\Models\DinasUnit::create([
            'name' => 'Dinas Global B', 'code' => 'GLOBAL-B', 'is_active' => true, 'sort_order' => 2,
        ]);

        $catA = ComplaintCategory::create([
            'name' => 'Kategori Global A', 'slug' => 'kategori-global-a', 'is_active' => true, 'sort_order' => 1,
        ]);
        $catB = ComplaintCategory::create([
            'name' => 'Kategori Global B', 'slug' => 'kategori-global-b', 'is_active' => true, 'sort_order' => 2,
        ]);
        $catA->dinasUnits()->attach($unitA->id);
        $catB->dinasUnits()->attach($unitB->id);

        $this->makeComplaint($reporter, 'submitted', ['category_id' => $catA->id]);

        $response = $this->actingAs($admin)->get('/admin/laporan')->assertOk();

        $html = $response->getContent();
        preg_match('/<select id="filter-category".*?<\/select>/s', $html, $m);
        $dropdown = $m[0] ?? '';

        $this->assertStringContainsString('value="' . $catA->id . '"', $dropdown);
        $this->assertStringContainsString('value="' . $catB->id . '"', $dropdown);
    }

    public function test_admin_complaints_select_styles_do_not_emit_a_relative_url(): void
    {
        // Regression guard for the spurious `GET /admin/%EF%BF%BD` 404.
        //
        // The native <select> chevrons previously carried an inline
        // `style="...url(\"data:...\")"`. HTML does not treat `\"` as an
        // escape, so the attribute value was truncated at the first `"`,
        // leaving the CSS fragment `url(\` in the document. A trailing
        // backslash in CSS is an escape that yields U+FFFD, which the browser
        // then resolved as a RELATIVE url against `/admin/` -> `GET
        // /admin/%EF%BF%BD` -> 404.
        //
        // The chevron now lives in the page stylesheet (`.filter-select`), so
        // the rendered HTML must never contain a truncated `url(` + backslash.
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($admin)->get('/admin/laporan')->assertOk()->getContent();

        // The malformed, backslash-terminated CSS value must be gone.
        $this->assertStringNotContainsString('url(\\', $html);

        // Every filter <select> opts into the stylesheet-driven chevron.
        $this->assertSame(3, substr_count($html, 'filter-input filter-select'));

        // The chevron data-URI is declared once, in the page stylesheet.
        $this->assertStringContainsString('.filter-select', $html);

        // The page stylesheet must be pushed into <head> — never emitted before
        // <!DOCTYPE html>, which would force the document into quirks mode and
        // break the bento grid layout.
        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($html));
        $this->assertLessThan(
            strpos($html, '<style>'),
            strpos($html, '<!DOCTYPE html>'),
            'The page stylesheet must not render before the doctype.',
        );
    }
}
