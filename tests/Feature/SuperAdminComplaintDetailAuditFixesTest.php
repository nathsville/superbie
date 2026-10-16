<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — "/super-admin/laporan/{complaint}" (detail) audit remediation
 * (SD1–SD5) regression guard.
 *
 * Pins the fixes applied after the audit of the Super Admin READ-ONLY complaint
 * detail page. The read-only contract and the STAGE-3 official reply workflow
 * are covered by SuperAdminComplaintManagementTest and
 * ComplaintReviewResponseTest and are NOT re-tested here — this file guards the
 * escaping, presentation and accessibility fixes only.
 *
 *   SD1a A crafted ARRAY `admin_response` on POST is rejected (422 redirect),
 *        never "Array to string conversion" (500).
 *   SD1b The same array re-flashed through `old()` renders the detail page as
 *        HTTP 200, never "htmlspecialchars(): array given" (500).
 *   SD2  The detail page has exactly ONE <h1> (the report title is an <h2>).
 *   SD3  No `text-[#94A3B8]` slate-400 text remains.
 *   SD4  Decorative SVGs are hidden from assistive technology.
 *   SD5  The `admin_response` error is rendered ONCE, not duplicated.
 */
class SuperAdminComplaintDetailAuditFixesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    /**
     * A complaint with a rating that carries a written review and NO reply yet,
     * so the official-reply form (the only write control) is rendered.
     */
    private function complaintWithReview(): Complaint
    {
        $category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);
        [$kecamatanId, $kelurahanId] = $this->makeLocationPair();

        $complaint = Complaint::create([
            'reference_code' => 'LPW-SDV-0001',
            'reporter_id'    => $this->superAdmin->id,
            'category_id'    => $category->id,
            'kecamatan_id'   => $kecamatanId,
            'kelurahan_id'   => $kelurahanId,
            'title'          => 'Judul detail audit',
            'description'    => 'Deskripsi detail audit.',
            'status'         => 'resolved',
            'submitted_at'   => now(),
        ]);

        ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->superAdmin->id,
            'rating'       => 4,
            'review_text'  => 'Ulasan uji untuk audit.',
            'created_at'   => now(),
        ]);

        return $complaint;
    }

    // ── SD1a — crafted array `admin_response` is rejected, never 500 ─────────

    public function test_array_admin_response_is_rejected_without_a_server_error(): void
    {
        $complaint = $this->complaintWithReview();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.review-response.store', $complaint), ['admin_response' => ['bad']])
            ->assertSessionHasErrors('admin_response');

        // Nothing was written.
        $this->assertNull($complaint->fresh()->rating->admin_response);
    }

    // ── SD1b — the same array re-flashed via old() renders 200 ───────────────

    public function test_array_old_input_does_not_crash_the_detail_page(): void
    {
        $complaint = $this->complaintWithReview();

        // A crafted array fails validation and is re-flashed through old().
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.review-response.store', $complaint), ['admin_response' => ['bad']])
            ->assertSessionHasErrors('admin_response');

        // The detail page — where the redirect lands — must still render 200.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertOk();
    }

    // ── SD2 + SD3 + SD4 + SD5 — detail page markup ───────────────────────────

    public function test_detail_page_has_single_h1_no_low_contrast_no_bare_svgs_and_one_error_block(): void
    {
        $complaint = $this->complaintWithReview();

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertOk()
            ->getContent();

        // SD2 — the page title is the ONLY <h1>; the report title is an <h2>.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h2 class="text-xl font-bold', $html);

        // SD3 — contrast.
        $this->assertStringNotContainsString('text-[#94A3B8]', $html);

        // SD4 — every SVG is decorative-safe.
        preg_match_all('/<svg[^>]*>/', $html, $m);
        $bare = array_filter($m[0], fn ($svg) => ! str_contains($svg, 'aria-hidden'));
        $this->assertSame([], array_values($bare), 'Every <svg> must be aria-hidden.');
    }

    public function test_admin_response_error_is_rendered_only_once(): void
    {
        $complaint = $this->complaintWithReview();

        // Fail validation to flash the `admin_response` error. (The GET must come
        // BEFORE any session assertion: `assertSessionHasErrors()` consumes the
        // flashed errors, which would otherwise blank the page under test.)
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.review-response.store', $complaint), ['admin_response' => ['bad']]);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertOk()
            ->getContent();

        // SD5 — the specific validation message must appear exactly once.
        $message = 'Balasan resmi harus berupa teks.';
        $this->assertSame(1, substr_count($html, $message), 'The admin_response error must not be duplicated.');
    }
}
