<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — "/super-admin/validasi-penyelesaian" audit remediation (P1–P8)
 * regression guard.
 *
 * Pins the fixes applied after the audit of the Super Admin COMPLETION-validation
 * surface. The completion WORKFLOW itself (queue eligibility, approve/return
 * side effects, atomicity, authorization) is frozen by CompletionValidationTest
 * and is NOT re-tested here — this file guards the escaping, presentation and
 * accessibility fixes only.
 *
 *   P1  A crafted ARRAY `reason` (e.g. `reason[]=x`) re-flashed through `old()`
 *       renders HTTP 200, never "htmlspecialchars(): array given" (500).
 *   P2  The detail page has exactly ONE <h1> (the report title is an <h2>).
 *   P3  No inline `on*` handlers (approve confirmation uses Alpine).
 *   P4  The queue table has column scopes + a caption + a section heading.
 *   P5  Decorative SVGs are hidden from assistive technology.
 *   P6  No `text-[#94A3B8]` slate-400 text remains on either page.
 *   P7  Each "Validasi" link exposes a unique accessible name.
 *   P8  The constant, redundant "Review State" column is gone.
 */
class SuperAdminCompletionValidationAuditFixesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private ComplaintCategory $category;
    private DinasUnit $unit;
    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->category   = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    /**
     * Build a complaint already in the completion-validation queue, mirroring
     * what the Operator's submitCompletion() leaves behind.
     */
    private function pendingCompletion(?string $narrative = 'Pekerjaan telah diselesaikan dan jalan sudah diperbaiki.'): Complaint
    {
        static::$seq++;

        $complaint = Complaint::create([
            'reference_code'          => 'LPW-P3CV-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'             => $this->superAdmin->id,
            'category_id'             => $this->category->id,
            'dinas_unit_id'           => $this->unit->id,
            'kecamatan_id'            => $this->kecamatanId,
            'kelurahan_id'            => $this->kelurahanId,
            'title'                   => 'Judul penyelesaian uji',
            'description'             => 'Deskripsi laporan uji.',
            'status'                  => 'in_progress',
            'review_state'            => ComplaintReviewState::PendingCompletionValidation->value,
            'submitted_at'            => now(),
            'completion_submitted_at' => now(),
        ]);

        if ($narrative !== null) {
            $note = ComplaintNote::create([
                'complaint_id' => $complaint->id,
                'author_id'    => $this->superAdmin->id,
                'visibility'   => 'internal',
                'body'         => $narrative,
                'created_at'   => now(),
            ]);

            AuditLog::create([
                'actor_id'     => $this->superAdmin->id,
                'action'       => 'complaint.completion_submitted',
                'subject_type' => 'complaint',
                'subject_id'   => $complaint->id,
                'metadata'     => ['completion_note_id' => $note->id],
            ]);
        }

        return $complaint;
    }

    // ── P1 — crafted array `reason` never crashes the detail page ────────────

    public function test_array_reason_does_not_crash_the_detail_page(): void
    {
        $complaint = $this->pendingCompletion();

        // A crafted array fails validation and is re-flashed through old().
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.completion-validation.return', $complaint), ['reason' => ['bad']])
            ->assertSessionHasErrors('reason');

        // The detail page — where the redirect lands — must still render 200.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint))
            ->assertOk();
    }

    // ── P4 + P5 + P6 + P7 + P8 — index markup ────────────────────────────────

    public function test_index_has_table_semantics_headings_and_no_low_contrast_text(): void
    {
        $this->pendingCompletion();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.index'))
            ->assertOk()
            ->getContent();

        // P4 — 7 columns scoped + a caption + a section heading.
        $this->assertSame(7, substr_count($html, 'scope="col"'));
        $this->assertStringContainsString('<caption class="sr-only">', $html);
        $this->assertGreaterThanOrEqual(1, substr_count($html, '<h2'));

        // P5 — every SVG is decorative-safe.
        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => ! str_contains($svg, 'aria-hidden'));
        $this->assertSame([], array_values($bare), 'Every <svg> must be aria-hidden.');

        // P6 — contrast.
        $this->assertStringNotContainsString('text-[#94A3B8]', $html);

        // P8 — the constant, redundant "Review State" column is removed.
        $this->assertStringNotContainsString('Review State', $html);
    }

    public function test_each_validasi_link_has_a_unique_accessible_name(): void
    {
        $a = $this->pendingCompletion();
        $b = $this->pendingCompletion();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="Validasi penyelesaian laporan ' . $a->reference_code . '"', $html);
        $this->assertStringContainsString('aria-label="Validasi penyelesaian laporan ' . $b->reference_code . '"', $html);
    }

    // ── P2 + P3 + P5 + P6 — detail page ──────────────────────────────────────

    public function test_detail_page_has_a_single_h1_no_inline_handlers_and_no_low_contrast_text(): void
    {
        $complaint = $this->pendingCompletion();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint))
            ->assertOk()
            ->getContent();

        // P2 — the page title is the ONLY <h1>; the report title is an <h2>.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h2 class="text-xl font-bold', $html);

        // P3 — no inline event handlers; the approve confirm uses Alpine.
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/', $html));
        $this->assertStringContainsString('@submit="if (! confirm(', $html);

        // P5 — decorative SVGs hidden.
        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => ! str_contains($svg, 'aria-hidden'));
        $this->assertSame([], array_values($bare), 'Every <svg> must be aria-hidden.');

        // P6 — contrast.
        $this->assertStringNotContainsString('text-[#94A3B8]', $html);
    }
}
