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
        $this->makeComplaint($reporter, 'under_review');
        $this->makeComplaint($reporter, 'resolved');

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard Admin');

        // Controller must pass the real, derived-from-DB metrics.
        $response->assertViewHas('totalLaporan', 3);
        $response->assertViewHas('laporanAktif', 2);   // submitted + under_review
        $response->assertViewHas('laporanSelesai', 1); // resolved
    }

    public function test_admin_dashboard_shows_honest_empty_state_with_no_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 0);
        // Honest empty states (not fabricated content).
        $response->assertSee('Belum ada laporan masuk.');
        $response->assertSee('Belum ada laporan yang perlu perhatian.');
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
        $this->assertArrayHasKey('belum_ditugaskan', $kpi);
    }
}
