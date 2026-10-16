<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintRating;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\ComplaintRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 3 — Bagian 2 — Citizen satisfaction RATING (1–5).
 *
 * FROZEN behaviour under test:
 *   - Only the OWNER (`complaints.reporter_id`) may rate, ONLY while the
 *     complaint status is `resolved`. `closed` and every other status are NOT
 *     eligible. Eligibility is derived from `ComplaintStatus` ONLY (never
 *     `review_state`).
 *   - One complaint = at most ONE rating; the `complaint_id` UNIQUE index is the
 *     primary defense. A duplicate is rejected without overwriting the first
 *     rating and without a second audit.
 *   - Submitting a rating NEVER mutates `status`, `review_state`, `resolved_at`
 *     or the completion columns; it creates NO public response.
 *   - A successful submission writes `complaint.rating_submitted`; a failed one
 *     writes NO successful audit.
 *   - Ratings are deleted with their complaint by the existing retention purge.
 */
class ComplaintRatingTest extends TestCase
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
            'reference_code' => 'LPW-P3R-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => ($owner ?? $this->citizenOwner)->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji rating ' . static::$seq,
            'description'    => 'Deskripsi laporan uji rating kepuasan.',
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
    // DATABASE (1–5)
    // =========================================================================

    public function test_1_complaint_ratings_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('complaint_ratings'));
        $this->assertDatabaseHas('migrations', [
            'migration' => '2025_01_07_100017_create_complaint_ratings_table',
        ]);
    }

    public function test_2_complaint_foreign_key_exists(): void
    {
        $fk = collect(Schema::getForeignKeys('complaint_ratings'))
            ->firstWhere('columns', ['complaint_id']);

        $this->assertNotNull($fk, 'complaint_id FK must exist');
        $this->assertSame('complaints', $fk['foreign_table']);
        $this->assertSame('restrict', $fk['on_delete']);
    }

    public function test_3_user_foreign_key_exists(): void
    {
        $fk = collect(Schema::getForeignKeys('complaint_ratings'))
            ->firstWhere('columns', ['user_id']);

        $this->assertNotNull($fk, 'user_id FK must exist');
        $this->assertSame('users', $fk['foreign_table']);
        $this->assertSame('restrict', $fk['on_delete']);
    }

    public function test_4_complaint_id_is_unique(): void
    {
        $unique = collect(Schema::getIndexes('complaint_ratings'))
            ->firstWhere(fn ($i) => ($i['unique'] ?? false) && $i['columns'] === ['complaint_id']);

        $this->assertNotNull($unique, 'complaint_id UNIQUE index must exist');
    }

    public function test_5_rating_column_is_a_small_integer(): void
    {
        $column = collect(Schema::getColumns('complaint_ratings'))->firstWhere('name', 'rating');

        $this->assertNotNull($column);
        $this->assertSame('tinyint unsigned', $column['type']);
    }

    // =========================================================================
    // VALID VALUES (6–10)
    // =========================================================================

    public function test_6_to_10_each_rating_1_through_5_is_accepted(): void
    {
        foreach ([1, 2, 3, 4, 5] as $score) {
            $complaint = $this->makeComplaint();

            $this->actingAs($this->citizenOwner)
                ->post($this->rateUrl($complaint), ['rating' => $score])
                ->assertRedirect(route('citizen.complaint.show', $complaint))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('complaint_ratings', [
                'complaint_id' => $complaint->id,
                'user_id'      => $this->citizenOwner->id,
                'rating'       => $score,
            ]);
        }
    }

    // =========================================================================
    // INVALID VALUES (11–16)
    // =========================================================================

    public function test_11_to_15_invalid_ratings_are_rejected(): void
    {
        $invalid = [
            'zero'     => 0,
            'six'      => 6,
            'negative' => -1,
            'decimal'  => '4.5',
            'string'   => 'lima',
        ];

        foreach ($invalid as $label => $score) {
            $complaint = $this->makeComplaint();

            $this->actingAs($this->citizenOwner)
                ->post($this->rateUrl($complaint), ['rating' => $score])
                ->assertSessionHasErrors('rating');

            $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
            $this->assertDatabaseMissing('audit_logs', [
                'action'     => 'complaint.rating_submitted',
                'subject_id' => $complaint->id,
            ]);
        }
    }

    public function test_16_missing_rating_is_rejected(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), [])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    // =========================================================================
    // OWNERSHIP (17–22)
    // =========================================================================

    public function test_17_owner_can_rate_own_complaint(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5])
            ->assertRedirect(route('citizen.complaint.show', $complaint));

        $this->assertDatabaseHas('complaint_ratings', [
            'complaint_id' => $complaint->id,
            'rating'       => 5,
        ]);
    }

    public function test_18_another_citizen_cannot_rate(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOther)
            ->post($this->rateUrl($complaint), ['rating' => 4])
            ->assertForbidden();

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_19_operator_cannot_rate(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->post($this->rateUrl($complaint), ['rating' => 4])
            ->assertForbidden();

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_20_admin_cannot_rate(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin)
            ->post($this->rateUrl($complaint), ['rating' => 4])
            ->assertForbidden();

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_21_super_admin_cannot_rate(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)
            ->post($this->rateUrl($complaint), ['rating' => 4])
            ->assertForbidden();

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_22_guest_cannot_rate(): void
    {
        $complaint = $this->makeComplaint();

        $this->post($this->rateUrl($complaint), ['rating' => 4])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    // =========================================================================
    // STATUS ELIGIBILITY (23–29)
    // =========================================================================

    public function test_23_resolved_can_be_rated(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Resolved]);

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 3])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_24_to_29_only_resolved_is_eligible(): void
    {
        $ineligible = [
            ComplaintStatus::Submitted,
            ComplaintStatus::UnderReview,
            ComplaintStatus::InProgress,
            ComplaintStatus::WaitingForInformation,
            ComplaintStatus::Rejected,
            ComplaintStatus::Closed,
        ];

        foreach ($ineligible as $status) {
            $complaint = $this->makeComplaint(['status' => $status, 'resolved_at' => null]);

            $this->actingAs($this->citizenOwner)
                ->post($this->rateUrl($complaint), ['rating' => 5])
                ->assertSessionHasErrors('rating');

            $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
            $this->assertDatabaseMissing('audit_logs', [
                'action'     => 'complaint.rating_submitted',
                'subject_id' => $complaint->id,
            ]);
        }
    }

    // =========================================================================
    // INVARIANTS (30–33)
    // =========================================================================

    public function test_30_to_33_rating_does_not_change_complaint_state(): void
    {
        $complaint = $this->makeComplaint([
            'status'                  => ComplaintStatus::Resolved,
            'review_state'            => ComplaintReviewState::ValidatedForOperator,
            'resolved_at'             => now()->subDay(),
            'completion_submitted_at' => now()->subDay(),
            'completion_return_reason' => null,
        ]);

        $before = $complaint->fresh();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5])
            ->assertSessionHasNoErrors();

        $after = $complaint->fresh();

        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->review_state, $after->review_state);
        $this->assertEquals($before->resolved_at, $after->resolved_at);
        $this->assertEquals($before->completion_submitted_at, $after->completion_submitted_at);
        $this->assertSame($before->completion_return_reason, $after->completion_return_reason);
    }

    public function test_33b_rating_creates_no_public_response_or_status_history(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5]);

        $this->assertDatabaseCount('complaint_notes', 0);
        $this->assertDatabaseCount('complaint_status_histories', 0);
    }

    // =========================================================================
    // DUPLICATE (34–36)
    // =========================================================================

    public function test_34_second_rating_is_rejected(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 4]);

        $this->actingAs($this->citizenOwner)
            ->post($this->rateUrl($complaint), ['rating' => 5])
            ->assertSessionHasErrors('rating');
    }

    public function test_35_duplicate_does_not_overwrite_first_rating(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 4]);
        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 5]);

        $this->assertDatabaseHas('complaint_ratings', [
            'complaint_id' => $complaint->id,
            'rating'       => 4,
        ]);
        $this->assertSame(1, ComplaintRating::where('complaint_id', $complaint->id)->count());
    }

    public function test_36_duplicate_does_not_create_second_audit(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 4]);
        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 5]);

        $this->assertSame(
            1,
            AuditLog::where('action', 'complaint.rating_submitted')
                ->where('subject_id', $complaint->id)
                ->count()
        );
    }

    public function test_36b_database_unique_index_is_the_primary_duplicate_defense(): void
    {
        // Proves the DB UNIQUE(complaint_id) — NOT application validation — is
        // the real guard: a direct second INSERT (bypassing the controller) is
        // rejected by MySQL itself. This is what makes a concurrent double
        // submission safe.
        $complaint = $this->makeComplaint();

        ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 4,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 5,
        ]);
    }

    // =========================================================================
    // AUDIT (37–40)
    // =========================================================================

    public function test_37_successful_rating_creates_audit_event(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 5]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id'     => $this->citizenOwner->id,
            'action'       => 'complaint.rating_submitted',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
        ]);
    }

    public function test_38_audit_metadata_contains_rating_and_complaint_id(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 5]);

        $log = AuditLog::where('action', 'complaint.rating_submitted')
            ->where('subject_id', $complaint->id)
            ->firstOrFail();

        $this->assertSame(5, $log->metadata['rating']);
        $this->assertSame($complaint->id, $log->metadata['complaint_id']);
        $this->assertSame($complaint->reference_code, $log->metadata['reference_code']);
        $this->assertSame($this->citizenOwner->id, $log->metadata['reporter_id']);
        $this->assertSame('resolved', $log->metadata['complaint_status']);
    }

    public function test_39_failed_submission_creates_no_success_audit(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 5]);

        $this->assertDatabaseMissing('audit_logs', [
            'action'     => 'complaint.rating_submitted',
            'subject_id' => $complaint->id,
        ]);
    }

    // =========================================================================
    // UI (41–45)
    // =========================================================================

    public function test_41_rating_form_shown_for_resolved_owner(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Resolved]);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Berikan Rating Kepuasan')
            ->assertSee('1 bintang')
            ->assertSee('2 bintang')
            ->assertSee('3 bintang')
            ->assertSee('4 bintang')
            ->assertSee('5 bintang');
    }

    public function test_42_rating_form_hidden_for_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee('Berikan Rating Kepuasan');
    }

    public function test_43_rating_form_hidden_after_submission(): void
    {
        $complaint = $this->makeComplaint();
        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 4]);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee('Berikan Rating Kepuasan')
            ->assertSee('Rating Anda');
    }

    public function test_44_existing_rating_is_displayed_read_only(): void
    {
        $complaint = $this->makeComplaint();
        $this->actingAs($this->citizenOwner)->post($this->rateUrl($complaint), ['rating' => 4]);

        $response = $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Rating Anda');
        // The read-only display renders 4 filled + 1 empty star.
        $response->assertSee('★★★★☆');
        // No submit form remains.
        $response->assertDontSee('Kirim Rating');
    }

    public function test_45_five_accessible_rating_options(): void
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('role="radiogroup"', false);
        foreach (range(1, 5) as $i) {
            $response->assertSee('aria-label="' . $i . ' bintang"', false);
            $response->assertSee('value="' . $i . '"', false);
        }
    }

    public function test_45b_rating_form_hidden_for_non_resolved_statuses(): void
    {
        foreach ([ComplaintStatus::Submitted, ComplaintStatus::InProgress, ComplaintStatus::Rejected] as $status) {
            $complaint = $this->makeComplaint(['status' => $status, 'resolved_at' => null]);

            $this->actingAs($this->citizenOwner)
                ->get(route('citizen.complaint.show', $complaint))
                ->assertOk()
                ->assertDontSee('Berikan Rating Kepuasan');
        }
    }

    // =========================================================================
    // RETENTION (46)
    // =========================================================================

    public function test_46_rating_is_deleted_with_complaint_during_purge(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Resolved,
            'submitted_at' => now()->subYears(6),
        ]);

        $rating = ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 5,
        ]);

        $this->artisan('complaints:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
        $this->assertDatabaseMissing('complaint_ratings', ['id' => $rating->id]);
    }

    // =========================================================================
    // MODEL / DOMAIN (relationships + single source of truth)
    // =========================================================================

    public function test_model_relationships_are_wired(): void
    {
        $complaint = $this->makeComplaint();
        $rating = ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 5,
        ]);

        $this->assertTrue($complaint->rating->is($rating));
        $this->assertTrue($rating->complaint->is($complaint));
        $this->assertTrue($rating->user->is($this->citizenOwner));
        $this->assertTrue($this->citizenOwner->complaintRatings->contains($rating));
        $this->assertIsInt($rating->fresh()->rating);
    }

    public function test_can_be_rated_reads_status_only(): void
    {
        $this->assertTrue(
            $this->makeComplaint(['status' => ComplaintStatus::Resolved])->canBeRated()
        );

        // `validated_for_operator` review state alone must NOT make it eligible.
        $inProgress = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);
        $this->assertFalse($inProgress->canBeRated());

        $this->assertFalse($this->makeComplaint(['status' => ComplaintStatus::Closed])->canBeRated());
    }
}
