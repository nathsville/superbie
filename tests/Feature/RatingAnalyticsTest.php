<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintRating;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\DashboardCacheService;
use App\Services\RatingAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 4 — Bagian 2 — RATING ANALYTICS (D-4.1 .. D-4.15).
 *
 * FROZEN behaviour under test:
 *   - Viewers: Admin (global), Super Admin (global), Operator (own Dinas only),
 *     Masyarakat / Guest DENIED. Authorization is server-side.
 *   - Metrics: total, average, distribution 1–5, resolved-without-rating,
 *     per Dinas/Unit, per category, monthly trend, satisfaction rate.
 *   - Satisfaction = COUNT(rating IN 4,5) / COUNT(all ratings) × 100.
 *   - resolved_without_rating = status=resolved with NO rating row (period basis
 *     `complaints.resolved_at`); NOT negative, NOT in the satisfaction denominator.
 *   - Period presets: 6m / 12m / year / custom — basis `complaint_ratings.created_at`.
 *   - A rating stays counted after the complaint becomes `closed`.
 *   - AGGREGATE ONLY: no reporter identity in the payload or the response.
 *   - Empty state instead of a fabricated 0% satisfaction.
 *   - Rating submission invalidates the rating-analytics cache.
 */
class RatingAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $citizen;
    private User $admin;
    private User $superAdmin;
    private User $operatorA;
    private User $operatorB;
    private User $operatorNoUnit;

    private DinasUnit $unitA;
    private DinasUnit $unitB;

    private ComplaintCategory $categoryA;
    private ComplaintCategory $categoryB;

    private int $kecamatanA;
    private int $kelurahanA;
    private int $kecamatanB;
    private int $kelurahanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen    = User::factory()->create([
            'role' => 'masyarakat', 'is_active' => true,
            'name' => 'Pelapor Rahasia', 'email' => 'rahasia@example.test',
            'nik' => '7371000000000001', 'phone_number' => '081200000001',
            'address' => 'Jalan Rahasia Nomor 1',
        ]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->unitA = DinasUnit::create(['name' => 'Dinas A', 'code' => 'DA', 'is_active' => true, 'sort_order' => 1]);
        $this->unitB = DinasUnit::create(['name' => 'Dinas B', 'code' => 'DB', 'is_active' => true, 'sort_order' => 2]);

        $this->categoryA = ComplaintCategory::create(['name' => 'Kategori A', 'slug' => 'kategori-a', 'is_active' => true, 'sort_order' => 1]);
        $this->categoryB = ComplaintCategory::create(['name' => 'Kategori B', 'slug' => 'kategori-b', 'is_active' => true, 'sort_order' => 2]);

        $this->unitA->categories()->attach($this->categoryA->id);
        $this->unitB->categories()->attach($this->categoryB->id);

        $this->operatorA      = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id]);
        $this->operatorB      = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitB->id]);
        $this->operatorNoUnit = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null]);

        [$this->kecamatanA, $this->kelurahanA] = $this->makeLocationPair('Kecamatan A', 'Kelurahan A1');
        [$this->kecamatanB, $this->kelurahanB] = $this->makeLocationPair('Kecamatan B', 'Kelurahan B1');
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-P4-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => $this->unitA->id,
            'kecamatan_id'   => $this->kecamatanA,
            'kelurahan_id'   => $this->kelurahanA,
            'title'          => 'Laporan uji rating analytics ' . static::$seq,
            'description'    => 'Deskripsi laporan uji rating analytics.',
            'status'         => ComplaintStatus::Resolved,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'resolved_at'    => now(),
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function rate(Complaint $complaint, int $score, ?Carbon $at = null): ComplaintRating
    {
        $rating = ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $complaint->reporter_id,
            'rating'       => $score,
        ]);

        if ($at !== null) {
            DB::table('complaint_ratings')->where('id', $rating->id)->update([
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $rating->refresh();
        }

        return $rating;
    }

    private function service(): RatingAnalyticsService
    {
        return app(RatingAnalyticsService::class);
    }

    // =========================================================================
    // D-4.1 — VIEWERS / AUTHORIZATION (1–6)
    // =========================================================================

    public function test_admin_sees_global_rating_analytics(): void
    {
        $this->makeComplaint();
        $this->rate($this->makeComplaint(), 5);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Analitik Rating Kepuasan');
    }

    public function test_super_admin_sees_global_rating_analytics(): void
    {
        $this->rate($this->makeComplaint(), 4);

        $this->actingAs($this->superAdmin)
            ->get('/super-admin/dashboard')
            ->assertOk()
            ->assertSee('Analitik Rating Kepuasan');
    }

    public function test_operator_sees_own_dinas_rating_analytics(): void
    {
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id]), 5);

        $this->actingAs($this->operatorA)
            ->get('/operator/dashboard')
            ->assertOk()
            ->assertSee('Analitik Rating Kepuasan');
    }

    public function test_operator_cannot_see_another_dinas_analytics(): void
    {
        // Ratings in BOTH units.
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id]), 5);
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitB->id]), 1);

        // Server-side scope wins even when the operator forges another unit.
        $payload = $this->service()->getRatingAnalytics($this->operatorA, [
            'dinas_unit_id' => $this->unitB->id,
        ], true);

        $this->assertSame($this->unitA->id, $payload['scope']['dinasUnitId']);
        $this->assertSame(1, $payload['totalRatings']);
        $this->assertSame(5.0, $payload['averageRating']);

        // The rendered page never leaks the other unit.
        $this->actingAs($this->operatorA)
            ->get('/operator/dashboard?dinas_unit_id=' . $this->unitB->id)
            ->assertOk()
            ->assertDontSee('Dinas B');
    }

    public function test_masyarakat_cannot_access_rating_analytics(): void
    {
        $this->actingAs($this->citizen)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->citizen)->get('/super-admin/dashboard')->assertForbidden();
        $this->actingAs($this->citizen)->get('/operator/dashboard')->assertForbidden();

        // The citizen surface renders NO rating analytics section at all.
        $this->actingAs($this->citizen)
            ->get('/laporan/dashboard')
            ->assertOk()
            ->assertDontSee('Analitik Rating Kepuasan');
    }

    public function test_guest_cannot_access_rating_analytics(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/super-admin/dashboard')->assertRedirect('/login');
        $this->get('/operator/dashboard')->assertRedirect('/login');
    }

    // =========================================================================
    // D-4.2 / D-4.3 / D-4.4 — METRICS (7–10)
    // =========================================================================

    public function test_total_rating_is_correct(): void
    {
        $this->rate($this->makeComplaint(), 5);
        $this->rate($this->makeComplaint(), 4);
        $this->rate($this->makeComplaint(), 2);

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $this->assertSame(3, $payload['totalRatings']);
    }

    public function test_average_rating_is_correct(): void
    {
        foreach ([5, 4, 3, 1] as $score) {
            $this->rate($this->makeComplaint(), $score);
        }

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        // (5 + 4 + 3 + 1) / 4 = 3.25
        $this->assertSame(3.25, $payload['averageRating']);
    }

    public function test_distribution_1_to_5_is_correct(): void
    {
        foreach ([1, 3, 3, 4, 5, 5] as $score) {
            $this->rate($this->makeComplaint(), $score);
        }

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $this->assertSame([1 => 1, 2 => 0, 3 => 2, 4 => 1, 5 => 2], $payload['distribution']);
    }

    public function test_satisfaction_rate_counts_only_ratings_4_and_5(): void
    {
        foreach ([5, 4, 3, 1] as $score) {
            $this->rate($this->makeComplaint(), $score);
        }

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        // 2 satisfied (5,4) / 4 total × 100 = 50.0
        $this->assertSame(50.0, $payload['satisfactionRate']);
        $this->assertSame(2, $payload['satisfiedCount']);
    }

    // =========================================================================
    // D-4.5 — RESOLVED WITHOUT RATING (11)
    // =========================================================================

    public function test_resolved_without_rating_is_counted_separately(): void
    {
        // 2 resolved complaints with NO rating.
        $this->makeComplaint();
        $this->makeComplaint();

        // 1 resolved complaint WITH a rating.
        $this->rate($this->makeComplaint(), 5);

        // A rejected complaint must NOT count as "resolved without rating".
        $this->makeComplaint(['status' => ComplaintStatus::Rejected, 'resolved_at' => null]);

        // A closed complaint must NOT count either (status is not `resolved`).
        $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $this->assertSame(2, $payload['resolvedWithoutRating']);
        // Unrated complaints NEVER enter the satisfaction denominator.
        $this->assertSame(1, $payload['totalRatings']);
        $this->assertSame(100.0, $payload['satisfactionRate']);
    }

    // ─── D-4.5 (OPTION A, FINAL) — current-state coverage, period-independent ──

    /**
     * TEST 1 — a resolved complaint without a rating that lies OUTSIDE the rating
     * period is STILL counted (the metric is current-state, not period-bound).
     */
    public function test_d45_resolved_without_rating_ignores_the_rating_period(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        // Resolved long ago, never rated → outside the 6m rating window.
        $this->makeComplaint(['resolved_at' => Carbon::parse('2020-01-01 09:00:00')]);

        $payload = $this->service()->getRatingAnalytics($this->admin, ['period' => '6m'], true);

        $this->assertSame(1, $payload['resolvedWithoutRating']);
    }

    /**
     * TEST 2 — switching the rating period 6m → 12m must NOT change
     * resolved_without_rating (rating metrics may change).
     */
    public function test_d45_resolved_without_rating_is_stable_across_periods(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        // Rating metrics: one inside 6m, one inside 12m only.
        $this->rate($this->makeComplaint(), 5, Carbon::parse('2026-06-10 10:00:00'));
        $this->rate($this->makeComplaint(), 4, Carbon::parse('2025-09-01 10:00:00'));

        // Coverage: two resolved complaints without a rating (outside any period).
        $this->makeComplaint(['resolved_at' => Carbon::parse('2021-05-05 09:00:00')]);
        $this->makeComplaint(['resolved_at' => Carbon::parse('2022-05-05 09:00:00')]);

        $service = $this->service();

        $six  = $service->getRatingAnalytics($this->admin, ['period' => '6m'], true);
        $twelve = $service->getRatingAnalytics($this->admin, ['period' => '12m'], true);

        $this->assertSame(2, $six['resolvedWithoutRating']);
        $this->assertSame(2, $twelve['resolvedWithoutRating']);
        // Rating metrics DO change with the period (sanity: the filter is real).
        $this->assertSame(1, $six['totalRatings']);
        $this->assertSame(2, $twelve['totalRatings']);
    }

    /**
     * TEST 3 — giving a rating to a resolved complaint moves it OUT of
     * resolved_without_rating and INTO the rating count.
     */
    public function test_d45_rating_a_resolved_complaint_reduces_the_coverage_count(): void
    {
        $complaint = $this->makeComplaint();

        $service = $this->service();

        $before = $service->getRatingAnalytics($this->admin, [], true);
        $this->assertSame(1, $before['resolvedWithoutRating']);
        $this->assertSame(0, $before['totalRatings']);

        $this->rate($complaint, 5);

        $after = $service->getRatingAnalytics($this->admin, [], true);
        $this->assertSame(0, $after['resolvedWithoutRating']);
        $this->assertSame(1, $after['totalRatings']);
    }

    /**
     * TEST 4 — the CATEGORY filter applies to resolved_without_rating.
     */
    public function test_d45_resolved_without_rating_respects_the_category_filter(): void
    {
        $this->makeComplaint(['category_id' => $this->categoryA->id]);
        $this->makeComplaint(['category_id' => $this->categoryB->id]);

        $payload = $this->service()->getRatingAnalytics($this->admin, ['category_id' => $this->categoryA->id], true);

        $this->assertSame(1, $payload['resolvedWithoutRating']);
    }

    /**
     * TEST 5 — the KECAMATAN filter applies to resolved_without_rating.
     */
    public function test_d45_resolved_without_rating_respects_the_kecamatan_filter(): void
    {
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA, 'kelurahan_id' => $this->kelurahanA]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanB, 'kelurahan_id' => $this->kelurahanB]);

        $payload = $this->service()->getRatingAnalytics($this->admin, ['kecamatan_id' => $this->kecamatanB], true);

        $this->assertSame(1, $payload['resolvedWithoutRating']);
    }

    /**
     * TEST 6 — the KELURAHAN filter applies and the Kecamatan/Kelurahan pair
     * invariant is still validated server-side.
     */
    public function test_d45_resolved_without_rating_respects_the_kelurahan_filter(): void
    {
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA, 'kelurahan_id' => $this->kelurahanA]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanB, 'kelurahan_id' => $this->kelurahanB]);

        $service = $this->service();

        $valid = $service->getRatingAnalytics($this->admin, ['kelurahan_id' => $this->kelurahanA], true);
        $this->assertSame(1, $valid['resolvedWithoutRating']);

        // An invalid pair is dropped (never trusted) — filter effectively off.
        $forged = $service->getRatingAnalytics($this->admin, [
            'kecamatan_id' => $this->kecamatanB,
            'kelurahan_id' => $this->kelurahanA, // does not belong to Kecamatan B
        ], true);
        $this->assertNull($forged['filters']['kelurahan_id']);
        $this->assertSame(1, $forged['resolvedWithoutRating']); // Kecamatan B only
    }

    /**
     * TEST 7 — Operator: resolved_without_rating only counts the Operator's own
     * Dinas/Unit.
     */
    public function test_d45_operator_coverage_is_scoped_to_own_dinas(): void
    {
        $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $payload = $this->service()->getRatingAnalytics($this->operatorA, [], true);

        $this->assertSame(2, $payload['resolvedWithoutRating']);
    }

    /**
     * TEST 8 — a forged dinas_unit_id cannot expand an Operator's coverage scope.
     */
    public function test_d45_forged_dinas_cannot_expand_operator_coverage(): void
    {
        $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);
        $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $payload = $this->service()->getRatingAnalytics($this->operatorA, [
            'dinas_unit_id' => $this->unitB->id, // forged
        ], true);

        $this->assertSame($this->unitA->id, $payload['scope']['dinasUnitId']);
        $this->assertSame(1, $payload['resolvedWithoutRating']);
    }

    /**
     * TEST 9 — a CLOSED complaint without a rating is NOT counted as
     * resolved_without_rating (frozen D-3.4 + D-4.5; no closed_without_rating).
     */
    public function test_d45_closed_complaint_without_rating_is_not_counted(): void
    {
        $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $this->assertSame(0, $payload['resolvedWithoutRating']);
    }

    /**
     * TEST 10 — a REJECTED complaint without a rating is NOT counted.
     */
    public function test_d45_rejected_complaint_without_rating_is_not_counted(): void
    {
        $this->makeComplaint(['status' => ComplaintStatus::Rejected, 'resolved_at' => null]);

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $this->assertSame(0, $payload['resolvedWithoutRating']);
    }

    // =========================================================================
    // D-4.11 — CLOSED COMPLAINTS STILL COUNTED (12)
    // =========================================================================

    public function test_rating_of_a_closed_complaint_is_still_counted(): void
    {
        $closed = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        // The rating was validly given while `resolved`, then the complaint was
        // closed. It must remain in the analytics.
        $this->rate($closed, 5);

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $this->assertSame(1, $payload['totalRatings']);
        $this->assertSame(5.0, $payload['averageRating']);
    }

    // =========================================================================
    // D-4.10 / D-4.6 — TIME BASIS + PERIOD PRESETS (13–17)
    // =========================================================================

    public function test_period_uses_rating_created_at_not_complaint_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        // Complaint submitted long ago, resolved long ago — but rated NOW.
        $complaint = $this->makeComplaint([
            'submitted_at' => Carbon::parse('2025-01-10 09:00:00'),
            'resolved_at'  => Carbon::parse('2025-02-10 09:00:00'),
        ]);

        $this->rate($complaint, 5, Carbon::parse('2026-06-10 10:00:00'));

        $payload = $this->service()->getRatingAnalytics($this->admin, ['period' => '6m'], true);

        // Counted in the 6-month window because the RATING is in June 2026.
        $this->assertSame(1, $payload['totalRatings']);
    }

    public function test_period_presets_6m_12m_year_and_custom(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $this->rate($this->makeComplaint(), 5, Carbon::parse('2026-06-10 10:00:00')); // 6m
        $this->rate($this->makeComplaint(), 4, Carbon::parse('2026-02-01 10:00:00')); // 6m
        $this->rate($this->makeComplaint(), 3, Carbon::parse('2025-09-01 10:00:00')); // 12m only
        $this->rate($this->makeComplaint(), 2, Carbon::parse('2025-03-01 10:00:00')); // outside 12m

        $service = $this->service();

        $this->assertSame(2, $service->getRatingAnalytics($this->admin, ['period' => '6m'], true)['totalRatings']);
        $this->assertSame(3, $service->getRatingAnalytics($this->admin, ['period' => '12m'], true)['totalRatings']);
        $this->assertSame(2, $service->getRatingAnalytics($this->admin, ['period' => 'year'], true)['totalRatings']);

        $custom = $service->getRatingAnalytics($this->admin, [
            'period'     => 'custom',
            'start_date' => '2025-08-01',
            'end_date'   => '2025-10-01',
        ], true);
        $this->assertSame(1, $custom['totalRatings']);
    }

    public function test_custom_period_rejects_invalid_range_and_falls_back(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $this->rate($this->makeComplaint(), 5, Carbon::parse('2026-06-10 10:00:00'));

        // start > end → invalid → safe default (6m), never an unbounded query.
        $payload = $this->service()->getRatingAnalytics($this->admin, [
            'period'     => 'custom',
            'start_date' => '2026-06-01',
            'end_date'   => '2026-01-01',
        ], true);

        $this->assertSame('6m', $payload['period']['key']);
        $this->assertSame(1, $payload['totalRatings']);
    }

    // =========================================================================
    // D-4.7 — FILTERS (18–22)
    // =========================================================================

    public function test_category_filter(): void
    {
        $this->rate($this->makeComplaint(['category_id' => $this->categoryA->id]), 5);
        $this->rate($this->makeComplaint(['category_id' => $this->categoryB->id]), 2);

        $payload = $this->service()->getRatingAnalytics($this->admin, ['category_id' => $this->categoryA->id], true);

        $this->assertSame(1, $payload['totalRatings']);
        $this->assertSame(5.0, $payload['averageRating']);
    }

    public function test_dinas_filter(): void
    {
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id]), 5);
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitB->id]), 1);

        $payload = $this->service()->getRatingAnalytics($this->admin, ['dinas_unit_id' => $this->unitB->id], true);

        $this->assertSame(1, $payload['totalRatings']);
        $this->assertSame(1.0, $payload['averageRating']);
    }

    public function test_kecamatan_and_kelurahan_filters(): void
    {
        $this->rate($this->makeComplaint(['kecamatan_id' => $this->kecamatanA, 'kelurahan_id' => $this->kelurahanA]), 5);
        $this->rate($this->makeComplaint(['kecamatan_id' => $this->kecamatanB, 'kelurahan_id' => $this->kelurahanB]), 3);

        $byKecamatan = $this->service()->getRatingAnalytics($this->admin, ['kecamatan_id' => $this->kecamatanB], true);
        $this->assertSame(1, $byKecamatan['totalRatings']);
        $this->assertSame(3.0, $byKecamatan['averageRating']);

        $byKelurahan = $this->service()->getRatingAnalytics($this->admin, ['kelurahan_id' => $this->kelurahanA], true);
        $this->assertSame(1, $byKelurahan['totalRatings']);
        $this->assertSame(5.0, $byKelurahan['averageRating']);
    }

    public function test_kelurahan_is_bounded_by_selected_kecamatan(): void
    {
        // Kelurahan A1 belongs to Kecamatan A. Pairing it with Kecamatan B must
        // be rejected server-side (the filter is dropped, never trusted).
        $this->rate($this->makeComplaint(['kecamatan_id' => $this->kecamatanA, 'kelurahan_id' => $this->kelurahanA]), 5);

        $payload = $this->service()->getRatingAnalytics($this->admin, [
            'kecamatan_id' => $this->kecamatanB,
            'kelurahan_id' => $this->kelurahanA, // invalid pair
        ], true);

        $this->assertNull($payload['filters']['kelurahan_id']);
    }

    public function test_combined_filters(): void
    {
        $this->rate($this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'category_id'   => $this->categoryA->id,
            'kecamatan_id'  => $this->kecamatanA,
            'kelurahan_id'  => $this->kelurahanA,
        ]), 5);

        $this->rate($this->makeComplaint([
            'dinas_unit_id' => $this->unitB->id,
            'category_id'   => $this->categoryB->id,
            'kecamatan_id'  => $this->kecamatanB,
            'kelurahan_id'  => $this->kelurahanB,
        ]), 1);

        $payload = $this->service()->getRatingAnalytics($this->admin, [
            'dinas_unit_id' => $this->unitA->id,
            'category_id'   => $this->categoryA->id,
            'kecamatan_id'  => $this->kecamatanA,
            'kelurahan_id'  => $this->kelurahanA,
        ], true);

        $this->assertSame(1, $payload['totalRatings']);
        $this->assertSame(5.0, $payload['averageRating']);
    }

    // =========================================================================
    // D-4.9 — OPERATOR CANNOT ESCAPE DINAS SCOPE (23)
    // =========================================================================

    public function test_operator_cannot_escape_dinas_scope(): void
    {
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id]), 5);
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitB->id]), 5);

        $service = $this->service();

        // Forging ANY dinas param still yields the operator's own unit only.
        foreach ([$this->unitB->id, '', 999999] as $forged) {
            $payload = $service->getRatingAnalytics($this->operatorA, ['dinas_unit_id' => $forged], true);
            $this->assertSame(1, $payload['totalRatings']);
            $this->assertSame($this->unitA->id, $payload['scope']['dinasUnitId']);
        }

        // An operator with NO unit is denied (no global fallback).
        $denied = $service->getRatingAnalytics($this->operatorNoUnit, [], true);
        $this->assertFalse($denied['hasData']);
        $this->assertSame(0, $denied['totalRatings']);
        $this->assertSame([], $denied['byDinas']);
    }

    public function test_operator_payload_contains_only_own_dinas(): void
    {
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id]), 5);
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitB->id]), 5);

        $payload = $this->service()->getRatingAnalytics($this->operatorA, [], true);

        $this->assertCount(1, $payload['byDinas']);
        $this->assertSame('Dinas A', $payload['byDinas'][0]['name']);

        // The option list exposes ONLY the operator's own unit, as a plain
        // array (cache-safe payload — never an Eloquent Collection).
        $this->assertCount(1, $payload['options']['dinasUnits']);
        $this->assertSame($this->unitA->id, $payload['options']['dinasUnits'][0]['id']);
    }

    // =========================================================================
    // D-4.8 — PRIVACY (24)
    // =========================================================================

    public function test_payload_exposes_no_individual_reporter_data(): void
    {
        $this->rate($this->makeComplaint(), 5);

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);
        $json    = json_encode($payload);

        // Aggregate-only payload — no reporter identity, no per-rating records.
        $this->assertStringNotContainsString('Pelapor Rahasia', $json);
        $this->assertStringNotContainsString('rahasia@example.test', $json);
        $this->assertStringNotContainsString('081200000001', $json);
        $this->assertArrayNotHasKey('ratings', $payload);
        $this->assertArrayNotHasKey('reporter', $payload);
    }

    public function test_dashboard_response_exposes_no_reporter_identity(): void
    {
        $this->rate($this->makeComplaint(), 5);

        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('Pelapor Rahasia', $html);
        $this->assertStringNotContainsString('rahasia@example.test', $html);
        $this->assertStringNotContainsString('081200000001', $html);
        // No individual-rating drill-down / export control.
        $this->assertStringNotContainsString('Export', $html);
        $this->assertStringNotContainsString('Unduh CSV', $html);
    }

    // =========================================================================
    // D-4.13 — EMPTY STATE (25)
    // =========================================================================

    public function test_empty_state_when_no_rating_exists(): void
    {
        // Resolved complaints exist but none is rated.
        $this->makeComplaint();
        $this->makeComplaint();

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $this->assertFalse($payload['hasData']);
        $this->assertNull($payload['satisfactionRate']); // never a fabricated 0%
        $this->assertNull($payload['averageRating']);
        $this->assertSame(0, $payload['totalRatings']);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Belum ada rating pada periode/filter yang dipilih.');
    }

    // =========================================================================
    // D-4.15 — CACHE INVALIDATION (26)
    // =========================================================================

    public function test_rating_submission_invalidates_the_rating_cache(): void
    {
        $service = $this->service();

        // 1) Prime the cache with the (empty) analytics.
        $before = $service->getRatingAnalytics($this->admin, []);
        $this->assertSame(0, $before['totalRatings']);

        $keyBefore = $service->cacheKeyFor($this->admin, []);
        $this->assertTrue(Cache::has($keyBefore), 'rating analytics must be cached');

        // 2) Create a rating (the parent complaint row is NOT touched).
        $this->rate($this->makeComplaint(), 5);

        // 3) The cache version was bumped → the key changed (old entry is dead).
        $keyAfter = $service->cacheKeyFor($this->admin, []);
        $this->assertNotSame($keyBefore, $keyAfter, 'rating submission must invalidate the cache');

        // 4) The next read returns FRESH data (not the stale cached payload).
        $after = $service->getRatingAnalytics($this->admin, []);
        $this->assertSame(1, $after['totalRatings']);
        $this->assertSame(5.0, $after['averageRating']);
    }

    public function test_rating_observer_bumps_the_cache_version(): void
    {
        $versionBefore = (int) Cache::get(DashboardCacheService::RATING_CACHE_VERSION_KEY, 1);

        $this->rate($this->makeComplaint(), 4);

        // The rating cache version is bumped the moment a rating is created.
        $this->assertGreaterThan($versionBefore, (int) Cache::get(DashboardCacheService::RATING_CACHE_VERSION_KEY, 1));
    }

    public function test_rating_submitted_via_citizen_endpoint_refreshes_analytics(): void
    {
        $complaint = $this->makeComplaint();

        $service = $this->service();

        // 1) Prime the cached analytics (stale: no rating yet).
        $this->assertSame(0, $service->getRatingAnalytics($this->admin, [])['totalRatings']);

        // 2) Submit through the REAL citizen endpoint (never touches the complaint row).
        $this->actingAs($this->citizen)
            ->post(route('citizen.rating.store', $complaint), ['rating' => 5])
            ->assertRedirect(route('citizen.complaint.show', $complaint));

        // 3) The next analytics read is FRESH — the observer invalidated the cache.
        $after = $service->getRatingAnalytics($this->admin, []);
        $this->assertSame(1, $after['totalRatings']);
        $this->assertSame(5.0, $after['averageRating']);
    }

    // =========================================================================
    // D-4.15 — CACHE ROUND-TRIP (database store) — REGRESSION GUARD
    // =========================================================================

    /**
     * The `options` filter lists MUST survive a `database` cache round-trip.
     *
     * The production store is `database` (`.env`), whose `unserialize()` runs
     * with `allowed_classes => false` (`config/cache.php` — `serializable_classes`).
     * Caching an Eloquent model / Collection therefore returns
     * `__PHP_Incomplete_Class` on a cache HIT, and the view then reads a property
     * on a string — the exact cause of the operator-dashboard 500
     * ("Attempt to read property \"id\" on string"). The `array` store used by
     * the rest of the suite never serializes, so it could not catch this; this
     * test forces the database store to exercise the real round-trip.
     */
    public function test_options_payload_survives_a_database_cache_round_trip(): void
    {
        config(['cache.default' => 'database']);
        Cache::flush();

        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id]), 5);

        $service = $this->service();

        // Cold read: computes and WRITES the payload to the database cache.
        $cold = $service->getRatingAnalytics($this->operatorA, []);
        $key  = $service->cacheKeyFor($this->operatorA, []);
        $this->assertTrue(Cache::has($key), 'analytics must be cached in the database store');

        // Warm read: the cache HIT path that used to corrupt the payload.
        $warm = $service->getRatingAnalytics($this->operatorA, []);

        foreach (['dinasUnits', 'categories', 'kecamatan', 'kelurahan'] as $list) {
            $this->assertIsArray($warm['options'][$list], "options.{$list} must be a plain array after a cache round-trip");
            $this->assertNotInstanceOf(\__PHP_Incomplete_Class::class, $warm['options'][$list]);

            foreach ($warm['options'][$list] as $row) {
                $this->assertIsArray($row, "each options.{$list} entry must be a plain array");
                $this->assertArrayHasKey('id', $row);
                $this->assertArrayHasKey('name', $row);
                $this->assertIsInt($row['id']);
                $this->assertIsString($row['name']);
            }
        }

        // The warmed payload is byte-for-byte the freshly-computed one.
        $this->assertEquals($cold['options'], $warm['options']);
        $this->assertSame(1, $warm['totalRatings']);
    }

    /**
     * The operator dashboard renders on BOTH a cold and a WARM (cache HIT)
     * database cache — the second request is the one that produced the 500.
     */
    public function test_operator_dashboard_renders_on_a_warm_database_cache(): void
    {
        config(['cache.default' => 'database']);
        Cache::flush();

        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id]), 5);

        // Cold request primes the database cache; the second is a cache HIT.
        $this->actingAs($this->operatorA)->get('/operator/dashboard')->assertOk();
        $this->actingAs($this->operatorA)
            ->get('/operator/dashboard')
            ->assertOk()
            ->assertSee('Analitik Rating Kepuasan')
            ->assertSee($this->categoryA->name);
    }

    // =========================================================================
    // S — QUERY / PERFORMANCE — NO N+1 (27)
    // =========================================================================

    public function test_rating_analytics_does_not_produce_n_plus_one_queries(): void
    {
        // Small dataset.
        foreach ([5, 4, 3] as $score) {
            $this->rate($this->makeComplaint(), $score);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service()->getRatingAnalytics($this->admin, [], true);
        $small = count(DB::getQueryLog());

        // Much larger dataset (more categories, units, ratings).
        for ($i = 0; $i < 8; $i++) {
            $category = ComplaintCategory::create(['name' => 'Kategori Ekstra ' . $i, 'slug' => 'kategori-ekstra-' . $i, 'is_active' => true, 'sort_order' => 10 + $i]);
            $unit     = DinasUnit::create(['name' => 'Dinas Ekstra ' . $i, 'code' => 'DE' . $i, 'is_active' => true, 'sort_order' => 10 + $i]);
            $this->rate($this->makeComplaint(['category_id' => $category->id, 'dinas_unit_id' => $unit->id]), 5);
        }

        DB::flushQueryLog();
        $this->service()->getRatingAnalytics($this->admin, [], true);
        $large = count(DB::getQueryLog());

        DB::disableQueryLog();

        // The query count is CONSTANT regardless of how many categories/units
        // exist — a per-row loop would have made `$large` scale with the data.
        $this->assertSame($small, $large, 'rating analytics must not issue per-row queries');
        $this->assertLessThanOrEqual(16, $small);
    }

    // =========================================================================
    // D-4.2 — PER DINAS / PER CATEGORY / TREND (supporting metrics)
    // =========================================================================

    public function test_per_dinas_and_per_category_breakdowns(): void
    {
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id, 'category_id' => $this->categoryA->id]), 5);
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitA->id, 'category_id' => $this->categoryA->id]), 3);
        $this->rate($this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'category_id' => $this->categoryB->id]), 1);

        $payload = $this->service()->getRatingAnalytics($this->admin, [], true);

        $byDinas = collect($payload['byDinas'])->keyBy('name');
        $this->assertSame(2, $byDinas['Dinas A']['count']);
        $this->assertSame(4.0, $byDinas['Dinas A']['average']);
        $this->assertSame(50.0, $byDinas['Dinas A']['satisfactionRate']);
        $this->assertSame(1, $byDinas['Dinas B']['count']);

        $byCategory = collect($payload['byCategory'])->keyBy('name');
        $this->assertSame(2, $byCategory['Kategori A']['count']);
        $this->assertSame(1, $byCategory['Kategori B']['count']);
    }

    public function test_monthly_trend_zero_fills_without_data(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $this->rate($this->makeComplaint(), 5, Carbon::parse('2026-06-10 10:00:00'));
        $this->rate($this->makeComplaint(), 3, Carbon::parse('2026-06-11 10:00:00'));

        $payload = $this->service()->getRatingAnalytics($this->admin, ['period' => '6m'], true);
        $trend   = collect($payload['trend'])->keyBy('ym');

        // 6 calendar months, oldest first.
        $this->assertCount(6, $payload['trend']);
        $this->assertSame(2, $trend['2026-06']['count']);
        $this->assertSame(4.0, $trend['2026-06']['average']);

        // A month with no rating is NULL (no data), never "0 star".
        $this->assertNull($trend['2026-01']['average']);
        $this->assertSame(0, $trend['2026-01']['count']);
    }

    // =========================================================================
    // REGRESSION — existing dashboards still render (28–30)
    // =========================================================================

    public function test_existing_admin_dashboard_still_renders(): void
    {
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertSee('Dashboard Admin');
    }

    public function test_existing_super_admin_dashboard_still_renders(): void
    {
        $this->actingAs($this->superAdmin)->get('/super-admin/dashboard')->assertOk()->assertSee('Command Center — Super Admin');
    }

    public function test_existing_operator_dashboard_still_renders(): void
    {
        $this->actingAs($this->operatorA)->get('/operator/dashboard')->assertOk()->assertSee('Command Center — Operator');
    }

    // =========================================================================
    // D-4.14 — NO EXPORT
    // =========================================================================

    public function test_no_export_endpoint_exists(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => str_contains($uri, 'rating') || str_contains($uri, 'export') || str_contains($uri, 'analytics'));

        // The ONLY rating-related route is the frozen citizen rating submission.
        $this->assertSame(
            ['laporan/{complaint}/rating'],
            $routes->values()->all(),
        );
    }
}
