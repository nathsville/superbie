<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — "/super-admin/validasi" audit remediation (R1, V1–V13) regression
 * guard.
 *
 * Pins the fixes applied after the audit of the Super Admin initial-validation
 * surface. The validation WORKFLOW itself is covered by
 * InitialValidationWorkflowPhase2Test and is NOT re-tested here — this file
 * guards the presentation, escaping and accessibility fixes only.
 *
 *   R1  The shared paginator uses a NEUTRAL noun ("hasil"), so non-complaint
 *       lists (audit log, categories, Dinas/Units) no longer claim "laporan".
 *   V2  The queue table has column scopes + a caption.
 *   V3  Decorative SVGs are hidden from assistive technology.
 *   V4  Each "Validasi" link exposes a unique accessible name.
 *   V5  The index exposes an <h2> section heading.
 *   V6  The constant, redundant "Review State" column is gone.
 *   V8  The detail page has exactly ONE <h1> (the report title is an <h2>).
 *   V9  No `text-[#94A3B8]` slate-400 text remains on either page.
 *   V11 Crafted form input (quotes) is JS-safe — it cannot break out of the
 *       Alpine `x-data` object; malformed array input renders 200.
 */
class SuperAdminValidationAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function pendingComplaint(array $overrides = []): Complaint
    {
        return Complaint::factory()->create(array_merge([
            'category_id'   => ComplaintCategory::factory()->create()->id,
            'reporter_id'   => $this->superAdmin->id,
            'status'        => 'submitted',
            'review_state'  => 'pending_initial_validation',
            'submitted_at'  => now(),
        ], $overrides));
    }

    // ── R1 — shared paginator uses a neutral noun ────────────────────────────

    public function test_paginator_does_not_mislabel_non_complaint_lists(): void
    {
        // 35 audit rows → the audit-log page paginates (30/page).
        for ($i = 0; $i < 35; $i++) {
            \App\Models\AuditLog::create([
                'actor_id'     => $this->superAdmin->id,
                'action'       => 'user.updated',
                'subject_type' => 'user',
                'subject_id'   => $this->superAdmin->id,
            ]);
        }

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.audit.index'))
            ->assertOk()
            ->getContent();

        // Scope to the paginator block only (the sidebar legitimately mentions
        // "laporan" in its navigation labels).
        preg_match('/<nav role="navigation" aria-label="Navigasi halaman".*?<\/nav>/s', $html, $m);
        $this->assertNotEmpty($m, 'The paginator <nav> was not found.');

        $pager = $m[0];
        $this->assertStringContainsString('Menampilkan', $pager);
        $this->assertStringContainsString('dari', $pager);
        // The neutral noun — never "laporan" on an audit-log paginator.
        $this->assertStringContainsString('hasil', $pager);
        $this->assertStringNotContainsString('laporan', $pager);
    }

    // ── V2 + V5 + V6 — index table semantics + headings ──────────────────────

    public function test_queue_table_has_scopes_caption_and_section_heading(): void
    {
        $this->pendingComplaint();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.index'))
            ->assertOk()
            ->getContent();

        // V2 — 8 header cells scoped + a caption.
        $this->assertSame(8, substr_count($html, 'scope="col"'));
        $this->assertStringContainsString('<caption class="sr-only">', $html);

        // V5 — an <h2> section heading exists (no h1 → table jump).
        $this->assertGreaterThanOrEqual(1, substr_count($html, '<h2'));

        // V6 — the constant, redundant "Review State" column is removed.
        $this->assertStringNotContainsString('Review State', $html);
    }

    // ── V4 — each "Validasi" link has a unique accessible name ───────────────

    public function test_each_validasi_link_has_a_unique_accessible_name(): void
    {
        $a = $this->pendingComplaint();
        $b = $this->pendingComplaint();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="Validasi laporan ' . $a->reference_code . '"', $html);
        $this->assertStringContainsString('aria-label="Validasi laporan ' . $b->reference_code . '"', $html);
    }

    // ── V3 + V9 — decorative SVGs hidden, contrast ───────────────────────────

    public function test_index_has_no_bare_svgs_and_no_low_contrast_text(): void
    {
        $this->pendingComplaint();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => !str_contains($svg, 'aria-hidden'));
        $this->assertSame([], array_values($bare), 'Every <svg> must be aria-hidden.');

        $this->assertStringNotContainsString('text-[#94A3B8]', $html);
    }

    // ── V8 + V9 + V11 — detail page ──────────────────────────────────────────

    public function test_detail_page_has_a_single_h1_and_no_low_contrast_text(): void
    {
        $complaint = $this->pendingComplaint();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.show', $complaint))
            ->assertOk()
            ->getContent();

        // V8 — the page title is the ONLY <h1>; the report title is an <h2>.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h2 class="text-xl font-bold', $html);

        // V9 — contrast.
        $this->assertStringNotContainsString('text-[#94A3B8]', $html);

        // V3 — decorative SVGs hidden.
        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => !str_contains($svg, 'aria-hidden'));
        $this->assertSame([], array_values($bare));
    }

    /**
     * V11 — a crafted value that fails validation is re-flashed through `old()`
     * and re-injected into the Alpine `x-data` object. It MUST be JS-escaped
     * (Js::from) so a quote cannot break out of the string and inject script.
     */
    public function test_crafted_form_input_cannot_break_out_of_alpine_x_data(): void
    {
        $complaint = $this->pendingComplaint();

        $payload = "1'};window.__pwned=1;//";

        // Fails validation (category_id must be an integer) → old() re-flashes.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.validation.approve', $complaint), [
                'category_id'   => $payload,
                'dinas_unit_id' => $complaint->dinas_unit_id ?? 1,
            ]);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.show', $complaint))
            ->assertOk()
            ->getContent();

        // The raw apostrophe must NEVER appear unescaped inside the JS object.
        $this->assertStringNotContainsString("categoryId: '1';", $html);
        $this->assertStringNotContainsString("categoryId: '1'}", $html);
        $this->assertStringNotContainsString('};window.__pwned=1;//', $html);

        // Js::from() emits a \u0027-escaped JSON string.
        $this->assertStringContainsString('\\u0027', $html);
    }

    public function test_malformed_array_form_input_renders_without_error(): void
    {
        $complaint = $this->pendingComplaint();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.validation.approve', $complaint), [
                'category_id'   => ['bad'],
                'dinas_unit_id' => ['bad'],
            ]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.show', $complaint))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.validation.return', $complaint), ['reason' => ['bad']]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.show', $complaint))
            ->assertOk();
    }
}
