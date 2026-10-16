<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintRating;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 3 — Bagian 2 — Written review ("ulasan tertulis") attached to
 * the citizen satisfaction RATING.
 *
 * FROZEN behaviour under test:
 *   - The review is OPTIONAL (nullable). A rating with no review is valid.
 *   - The review rides on the SAME immutable, create-once `complaint_ratings`
 *     row: it inherits owner-only + `resolved`-only eligibility and can never be
 *     created for another user or another complaint, nor forged via the request.
 *   - The review text is validated server-side (max 5000, string) and rendered
 *     through Blade escaping (no HTML is trusted).
 *   - The review is visible to the reporter (own detail page) and to staff
 *     (Operator / Super Admin detail, READ-ONLY). It is NEVER placed in the
 *     aggregate-only rating analytics and NEVER copied into audit metadata.
 *   - MODERATION is NOT implemented (prd.md §4 TODO). The text is stored as-is.
 *   - All existing dashboards and the frozen rating behaviour keep working.
 */
class ComplaintReviewTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $citizenOwner;
    private User $citizenOther;
    private User $operator;
    private User $admin;
    private User $superAdmin;

    private ComplaintCategory $category;
    private DinasUnit $unit;

    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizenOwner = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->citizenOther = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->admin        = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->superAdmin   = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unit->categories()->attach($this->category->id);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = [], ?User $owner = null): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-REV-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => ($owner ?? $this->citizenOwner)->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji ulasan ' . static::$seq,
            'description'    => 'Deskripsi laporan uji ulasan tertulis.',
            'status'         => ComplaintStatus::Resolved,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'resolved_at'    => now(),
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function rateUrl(Complaint $complaint): string
    {
        return route('citizen.rating.store', $complaint);
    }

    // =========================================================================
    // SCHEMA / MODEL
    // =========================================================================

    public function test_review_text_column_is_nullable_text(): void
    {
        $this->assertTrue(Schema::hasTable('complaint_ratings'));

        $column = collect(Schema::getColumns('complaint_ratings'))->firstWhere('name', 'review_text');

        $this->assertNotNull($column, 'review_text column must exist');
        $this->assertSame('text', $column['type']);
        $this->assertTrue((bool) $column['nullable'], 'review_text must be nullable');
    }

    public function test_has_review_helper(): void
    {
        $complaint = $this->makeComplaint();

        $withReview = ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 5,
            'review_text'  => 'Pelayanan cepat dan memuaskan.',
        ]);

        $this->assertTrue($withReview->hasReview());

        $withoutReview = ComplaintRating::create([
            'complaint_id' => $this->makeComplaint()->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 4,
            'review_text'  => null,
        ]);
        $this->assertFalse($withoutReview->hasReview());

        $blank = ComplaintRating::create([
            'complaint_id' => $this->makeComplaint()->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 3,
            'review_text'  => '   ',
        ]);
        $this->assertFalse($blank->hasReview());
    }

    // =========================================================================
    // OWNER + ELIGIBILITY (same as the rating)
    // =========================================================================

    public function test_eligible_owner_can_submit_rating_with_review(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), [
                'rating'      => 5,
                'review_text' => 'Terima kasih, jalan sudah diperbaiki dengan baik.',
            ])
            ->assertRedirect(route('citizen.complaint.show', $complaint))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_ratings', [
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 5,
            'review_text'  => 'Terima kasih, jalan sudah diperbaiki dengan baik.',
        ]);
    }

    public function test_review_is_optional(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 4])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_ratings', [
            'complaint_id' => $complaint->id,
            'rating'       => 4,
            'review_text'  => null,
        ]);
    }

    public function test_blank_review_is_stored_as_null(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 4, 'review_text' => '   '])
            ->assertSessionHasNoErrors();

        $rating = ComplaintRating::where('complaint_id', $complaint->id)->firstOrFail();
        $this->assertNull($rating->review_text);
        $this->assertFalse($rating->hasReview());
    }

    public function test_another_citizen_cannot_review(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOther)
            ->post($this->rateUrl($complaint), ['rating' => 4, 'review_text' => 'Palsu'])
            ->assertForbidden();

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_cannot_review_non_resolved_complaint(): void
    {
        $complaint = $this->makeComplaint([
            'status'      => ComplaintStatus::InProgress,
            'resolved_at' => null,
        ]);

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'Belum selesai'])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_duplicate_rating_with_review_is_still_rejected(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 4, 'review_text' => 'Ulasan pertama']);

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'Ulasan kedua'])
            ->assertSessionHasErrors('rating');

        $this->assertSame(1, ComplaintRating::where('complaint_id', $complaint->id)->count());
        $this->assertDatabaseHas('complaint_ratings', [
            'complaint_id' => $complaint->id,
            'rating'       => 4,
            'review_text'  => 'Ulasan pertama',
        ]);
    }

    public function test_cannot_forge_ownership_via_request_body(): void
    {
        $complaint      = $this->makeComplaint();
        $otherComplaint = $this->makeComplaint([], $this->citizenOther);

        // Owner submits to their OWN complaint but tries to forge complaint_id /
        // user_id in the body. Ownership is derived from route model binding +
        // `complaints.reporter_id`, so the forged values are ignored.
        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), [
                'rating'       => 5,
                'review_text'  => 'Ulasan sah',
                'complaint_id' => $otherComplaint->id,
                'user_id'      => $this->citizenOther->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_ratings', [
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
        ]);
        $this->assertDatabaseMissing('complaint_ratings', [
            'complaint_id' => $otherComplaint->id,
        ]);
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function test_review_text_longer_than_5000_is_rejected(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), [
                'rating'      => 5,
                'review_text' => str_repeat('a', 5001),
            ])
            ->assertSessionHasErrors('review_text');

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_review_text_exactly_5000_is_accepted(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), [
                'rating'      => 5,
                'review_text' => str_repeat('a', 5000),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_non_string_review_is_rejected(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), [
                'rating'      => 5,
                'review_text' => ['not', 'a', 'string'],
            ])
            ->assertSessionHasErrors('review_text');

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_review_cannot_be_submitted_without_rating(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['review_text' => 'Tanpa rating'])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    // =========================================================================
    // IMMUTABILITY + AUDIT
    // =========================================================================

    public function test_review_does_not_change_complaint_state(): void
    {
        $complaint = $this->makeComplaint([
            'resolved_at' => now()->subDay(),
        ]);
        $before = $complaint->fresh();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'Ulasan'])
            ->assertSessionHasNoErrors();

        $after = $complaint->fresh();

        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->review_state, $after->review_state);
        $this->assertEquals($before->resolved_at, $after->resolved_at);
    }

    public function test_audit_records_has_review_boolean_but_never_the_text(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'Rahasia pelapor'])
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'complaint.rating_submitted')
            ->where('subject_id', $complaint->id)
            ->firstOrFail();

        $this->assertTrue($log->metadata['has_review']);
        $this->assertSame(5, $log->metadata['rating']);
        $this->assertStringNotContainsString('Rahasia pelapor', json_encode($log->metadata));
    }

    public function test_audit_records_has_review_false_when_no_text(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 3])
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'complaint.rating_submitted')
            ->where('subject_id', $complaint->id)
            ->firstOrFail();

        $this->assertFalse($log->metadata['has_review']);
    }

    // =========================================================================
    // VISIBILITY — reporter
    // =========================================================================

    public function test_owner_sees_own_review_read_only_on_detail(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'Ulasan saya yang terlihat']);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Ulasan Anda')
            ->assertSee('Ulasan saya yang terlihat')
            ->assertDontSee('Kirim Rating');
    }

    public function test_review_only_appears_on_the_right_complaint(): void
    {
        $reviewed = $this->makeComplaint();
        $other    = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($reviewed), ['rating' => 5, 'review_text' => 'HANYA-UNTUK-LAPORAN-A']);

        // The reviewed complaint shows the text.
        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $reviewed))
            ->assertOk()
            ->assertSee('HANYA-UNTUK-LAPORAN-A');

        // The OTHER (unreviewed) complaint must not leak it.
        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $other))
            ->assertOk()
            ->assertDontSee('HANYA-UNTUK-LAPORAN-A');
    }

    public function test_owner_without_review_sees_no_review_block(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 4]);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Rating Anda')
            ->assertDontSee('Ulasan Anda');
    }

    public function test_review_text_is_html_escaped_on_detail(): void
    {
        $complaint = $this->makeComplaint();

        $payload = '<script>alert(1)</script>';

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => $payload]);

        $response = $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        // Raw script tag must NOT be emitted; the escaped form is shown instead.
        $response->assertDontSee($payload, false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    // =========================================================================
    // VISIBILITY — staff (Operator + Super Admin), READ-ONLY
    // =========================================================================

    public function test_operator_sees_review_read_only(): void
    {
        $complaint = $this->makeComplaint();
        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'Ulasan untuk operator']);

        $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Rating Kepuasan Pelapor')
            ->assertSee('Ulasan untuk operator')
            ->assertDontSee('Kirim Rating');
    }

    public function test_super_admin_sees_review_read_only(): void
    {
        $complaint = $this->makeComplaint();
        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 4, 'review_text' => 'Ulasan untuk super admin']);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Rating Kepuasan Pelapor')
            ->assertSee('Ulasan untuk super admin');
    }

    public function test_admin_has_no_complaint_detail_route_for_reviews(): void
    {
        $complaint = $this->makeComplaint();
        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 4, 'review_text' => 'Ulasan admin']);

        // Admin is monitoring-only: there is no per-complaint detail route, so the
        // review is not exposed on any Admin surface.
        $this->actingAs($this->admin)
            ->get('/admin/laporan/' . $complaint->id)
            ->assertNotFound();
    }

    // =========================================================================
    // NO LEAK — internal notes stay hidden from the reporter
    // =========================================================================

    public function test_internal_notes_are_not_shown_on_citizen_detail(): void
    {
        $complaint = $this->makeComplaint();

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'CATATAN-INTERNAL-RAHASIA',
            'created_at'   => now(),
        ]);

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'Ulasan saya']);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Ulasan saya')
            ->assertDontSee('CATATAN-INTERNAL-RAHASIA');
    }

    // =========================================================================
    // REGRESSION — dashboards + frozen rating behaviour still work
    // =========================================================================

    public function test_dashboards_still_render(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dashboard'))
            ->assertOk();

        $this->actingAs($this->operator)
            ->get(route('operator.dashboard'))
            ->assertOk();
    }

    public function test_rating_still_works_without_any_review_field(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 2])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_ratings', [
            'complaint_id' => $complaint->id,
            'rating'       => 2,
            'review_text'  => null,
        ]);
    }

    public function test_analytics_remains_aggregate_only_no_review_text(): void
    {
        $complaint = $this->makeComplaint();
        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5, 'review_text' => 'JANGAN-MUNCUL-DI-ANALITIK']);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('JANGAN-MUNCUL-DI-ANALITIK');
    }
}
