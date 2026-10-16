<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — "/super-admin/laporan" audit remediation (H1–H6, M1–M5, L1, L4)
 * regression guard.
 *
 * Pins the fixes applied after the read-only audit of the Super Admin
 * "Semua Laporan" list so they cannot silently regress. The read-only contract
 * itself is enforced elsewhere (SuperAdminComplaintManagementTest) and is NOT
 * re-tested here.
 *
 *   H1  The paginator renders Indonesian copy — no raw `pagination.*` keys and
 *       no English "Showing … of … results".
 *   H2  The paginator carries no `dark:` utilities (the app is light-only).
 *   H4  Filter chips are escaped exactly once (no double-encoding).
 *   H5  The results table has column scopes + a caption.
 *   H6  Malformed array query params (e.g. `?q[]=x`) render 200, never 500.
 *   M1  No `text-[#94A3B8]` slate-400 text remains on the page.
 *   M2  Each "Buka" link exposes a unique accessible name.
 *   M3  Decorative SVGs are hidden from assistive technology.
 *   M4  Both search landmarks carry an accessible name.
 *   M5  An unknown `?status=` value never produces a misleading chip.
 *   L1  The controller no longer passes the unused `$statuses` variable.
 *   L4  The page exposes `<h2>` section headings (no h1 → table jump).
 */
class SuperAdminComplaintListAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function complaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-LAP-' . strtoupper(uniqid()),
            'category_id'    => ComplaintCategory::factory()->create()->id,
            'reporter_id'    => $this->superAdmin->id,
            'title'          => 'Laporan uji audit daftar',
            'description'    => 'Deskripsi laporan pengujian audit daftar laporan.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function html(array $query = []): string
    {
        return $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', $query))
            ->assertOk()
            ->getContent();
    }

    // ── H1 + H2 — Indonesian, light-only paginator ───────────────────────────

    public function test_paginator_renders_indonesian_copy_without_raw_keys(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->complaint();
        }

        $html = $this->html();

        // The custom published view is in use.
        $this->assertStringContainsString('Navigasi halaman', $html);
        $this->assertStringContainsString('Menampilkan', $html);

        // No raw translation keys and no English default copy.
        $this->assertStringNotContainsString('pagination.previous', $html);
        $this->assertStringNotContainsString('pagination.next', $html);
        $this->assertStringNotContainsString('Showing', $html);
        $this->assertStringNotContainsString(' results', $html);

        // Accessible pager semantics survive.
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('rel="next"', $html);
    }

    public function test_paginator_carries_no_dark_mode_utilities(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->complaint();
        }

        $html = $this->html();

        preg_match('/<nav role="navigation" aria-label="Navigasi halaman".*?<\/nav>/s', $html, $m);

        $this->assertNotEmpty($m, 'The custom paginator <nav> was not found.');
        $this->assertStringNotContainsString('dark:', $m[0]);
    }

    // ── H4 — chips are escaped exactly once ──────────────────────────────────

    public function test_filter_chip_is_not_double_encoded(): void
    {
        $this->complaint();

        $html = $this->html(['q' => "O'Brien"]);

        // Escaped ONCE: the raw apostrophe becomes &#039; — not &amp;#039;.
        $this->assertStringContainsString('O&#039;Brien', $html);
        $this->assertStringNotContainsString('&amp;#039;', $html);
    }

    // ── H5 — table scope + caption ───────────────────────────────────────────

    public function test_results_table_has_scopes_and_caption(): void
    {
        $this->complaint();

        $html = $this->html();

        $this->assertSame(8, substr_count($html, 'scope="col"'));
        $this->assertStringContainsString('<caption class="sr-only">', $html);
    }

    // ── H6 — malformed array params never 500 ────────────────────────────────

    public function test_malformed_array_query_params_render_ok(): void
    {
        $this->complaint();

        foreach (['q', 'category', 'dinas_unit', 'date_from', 'date_to', 'status', 'sort'] as $key) {
            $this->actingAs($this->superAdmin)
                ->get(route('super-admin.complaints.index', [$key => ['bad']]))
                ->assertOk();
        }
    }

    // ── M1 — contrast ────────────────────────────────────────────────────────

    public function test_page_has_no_low_contrast_slate_400_text(): void
    {
        $this->complaint();

        $html = $this->html();

        $this->assertStringNotContainsString('text-[#94A3B8]', $html);
    }

    // ── M2 — each "Buka" link has a unique accessible name ───────────────────

    public function test_each_buka_link_has_an_accessible_name(): void
    {
        $a = $this->complaint();
        $b = $this->complaint();

        $html = $this->html();

        $this->assertStringContainsString('aria-label="Buka laporan ' . $a->reference_code . '"', $html);
        $this->assertStringContainsString('aria-label="Buka laporan ' . $b->reference_code . '"', $html);
    }

    // ── M3 — decorative SVGs hidden ──────────────────────────────────────────

    public function test_decorative_svgs_are_hidden_from_assistive_tech(): void
    {
        $this->complaint();

        $html = $this->html();

        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => !str_contains($svg, 'aria-hidden'));

        $this->assertSame([], array_values($bare), 'Every <svg> on the page must be aria-hidden.');
    }

    // ── M4 — search landmarks are labelled ───────────────────────────────────

    public function test_search_landmarks_are_labelled(): void
    {
        $this->complaint();

        $html = $this->html();

        preg_match_all('/<form[^>]*role="search"[^>]*>/', $html, $m);

        $this->assertCount(2, $m[0]);
        foreach ($m[0] as $form) {
            $this->assertStringContainsString('aria-label=', $form);
        }
    }

    // ── M5 — unknown status never yields a chip ──────────────────────────────

    public function test_unknown_status_value_produces_no_misleading_chip(): void
    {
        $this->complaint();

        $html = $this->html(['status' => 'bogus']);

        // The filter is ignored (list intact) AND no chip claims it was applied.
        $this->assertStringNotContainsString('Status: bogus', $html);
        $this->assertStringNotContainsString('Status: ', $html);
    }

    // ── L1 — the unused $statuses variable is gone ───────────────────────────

    public function test_controller_no_longer_passes_unused_statuses_variable(): void
    {
        $this->complaint();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index'))
            ->assertOk()
            ->assertViewMissing('statuses');
    }

    // ── L4 — section headings exist ──────────────────────────────────────────

    public function test_page_exposes_h2_section_headings(): void
    {
        $this->complaint();

        $html = $this->html();

        $this->assertSame(2, substr_count($html, '<h2'));
    }
}
