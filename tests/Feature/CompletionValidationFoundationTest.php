<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\ComplaintRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 3 — Bagian 1B-1 — Completion Validation FOUNDATION.
 *
 * Domain/schema only. This suite proves the DATA + DOMAIN foundation of the
 * Completion Validation Gate WITHOUT any route/controller/UI (which are later
 * parts). Frozen invariants asserted here:
 *   A. ComplaintReviewState has EXACTLY 5 states (+ NULL legacy); the 3 PHASE 2
 *      states are untouched.
 *   B. ComplaintStatus is unchanged (still exactly 7 cases) — no status added.
 *   C. The additive migration creates two NULLABLE columns and is reversible.
 *   D. `completion_submitted_at` is cast to datetime; `completion_return_reason`
 *      carries NO unnecessary cast.
 *   E. Scopes/helpers read `review_state` (never `status` alone).
 *   F. Operator visibility (single source of truth) admits the two completion
 *      states but still rejects the two pre-routing states, and Dinas isolation
 *      is preserved.
 *   G. Historical NULL review_state rows remain compatible.
 *   H. Retention deletes the new columns with the complaint (no new mechanism).
 */
class CompletionValidationFoundationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $operatorA;
    private User $operatorB;
    private User $citizen;

    private ComplaintCategory $categoryA;
    private ComplaintCategory $categoryB;
    private DinasUnit $unitA;
    private DinasUnit $unitB;

    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->categoryA = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->categoryB = ComplaintCategory::create([
            'name' => 'Kebersihan', 'slug' => 'kebersihan',
            'is_active' => true, 'sort_order' => 2,
        ]);

        $this->unitA = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unitB = DinasUnit::create([
            'name' => 'Dinas Lingkungan Hidup', 'code' => 'DLH', 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->unitA->categories()->attach($this->categoryA->id);
        $this->unitB->categories()->attach($this->categoryB->id);

        $this->operatorA = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);
        $this->operatorB = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitB->id,
        ]);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-P3-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => $this->unitA->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji completion ' . static::$seq,
            'description'    => 'Deskripsi laporan uji completion validation gate.',
            'status'         => ComplaintStatus::InProgress,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // A/B. ENUM SHAPE
    // =========================================================================

    public function test_review_state_enum_has_exactly_five_states(): void
    {
        $this->assertCount(5, ComplaintReviewState::cases());
    }

    public function test_expected_review_state_codes_exist(): void
    {
        $expected = [
            // PHASE 2 (unchanged).
            'pending_initial_validation',
            'returned_to_citizen',
            'validated_for_operator',
            // PHASE 3 (new).
            'pending_completion_validation',
            'completion_returned_to_operator',
        ];

        $actual = array_map(fn (ComplaintReviewState $c) => $c->value, ComplaintReviewState::cases());

        foreach ($expected as $code) {
            $this->assertContains($code, $actual, "Review state '{$code}' missing from enum.");
        }
        $this->assertSame($expected, $actual);
    }

    public function test_phase2_review_states_are_unchanged(): void
    {
        $this->assertSame('pending_initial_validation', ComplaintReviewState::PendingInitialValidation->value);
        $this->assertSame('returned_to_citizen', ComplaintReviewState::ReturnedToCitizen->value);
        $this->assertSame('validated_for_operator', ComplaintReviewState::ValidatedForOperator->value);
    }

    public function test_phase3_review_state_labels_are_correct(): void
    {
        $this->assertSame(
            'Menunggu Validasi Penyelesaian',
            ComplaintReviewState::PendingCompletionValidation->label()
        );
        $this->assertSame(
            'Penyelesaian Dikembalikan',
            ComplaintReviewState::CompletionReturnedToOperator->label()
        );
    }

    public function test_complaint_status_enum_is_unchanged_at_seven_cases(): void
    {
        // PHASE 3 must NOT add a lifecycle status.
        $this->assertCount(7, ComplaintStatus::cases());
    }

    // =========================================================================
    // C. MIGRATION (up / down)
    // =========================================================================

    public function test_migration_created_the_two_nullable_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('complaints', 'completion_submitted_at'));
        $this->assertTrue(Schema::hasColumn('complaints', 'completion_return_reason'));

        // The migration row is recorded (the migration ran).
        $this->assertDatabaseHas('migrations', [
            'migration' => '2025_01_06_100016_add_completion_validation_to_complaints_table',
        ]);
    }

    /**
     * Reversibility proof: run the ACTUAL migration's down() then up() and assert
     * the columns disappear and reappear. The migration is restored to `up`
     * within the same test, so the net schema is unchanged for other tests.
     */
    public function test_migration_is_reversible_down_then_up(): void
    {
        $migration = require database_path('migrations/2025_01_06_100016_add_completion_validation_to_complaints_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('complaints', 'completion_submitted_at'));
        $this->assertFalse(Schema::hasColumn('complaints', 'completion_return_reason'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('complaints', 'completion_submitted_at'));
        $this->assertTrue(Schema::hasColumn('complaints', 'completion_return_reason'));
    }

    // =========================================================================
    // D. NULLABILITY + CASTS
    // =========================================================================

    public function test_completion_columns_are_nullable_by_default(): void
    {
        $complaint = $this->makeComplaint();

        $fresh = $complaint->fresh();
        $this->assertNull($fresh->completion_submitted_at);
        $this->assertNull($fresh->completion_return_reason);
    }

    public function test_completion_submitted_at_is_cast_to_datetime(): void
    {
        $complaint = $this->makeComplaint(['completion_submitted_at' => now()]);

        $fresh = $complaint->fresh();
        $this->assertInstanceOf(Carbon::class, $fresh->completion_submitted_at);

        // The cast is declared explicitly.
        $this->assertArrayHasKey('completion_submitted_at', $fresh->getCasts());
        $this->assertSame('datetime', $fresh->getCasts()['completion_submitted_at']);
    }

    public function test_completion_return_reason_has_no_unnecessary_cast(): void
    {
        $complaint = $this->makeComplaint(['completion_return_reason' => 'Perbaiki bukti penyelesaian.']);

        $fresh = $complaint->fresh();

        // Plain TEXT string — not cast.
        $this->assertSame('Perbaiki bukti penyelesaian.', $fresh->completion_return_reason);
        $this->assertArrayNotHasKey('completion_return_reason', $fresh->getCasts());
    }

    // =========================================================================
    // E. SCOPES + HELPERS (review_state based)
    // =========================================================================

    public function test_pending_completion_scope_only_returns_pending_completion(): void
    {
        $pending = $this->makeComplaint(['review_state' => ComplaintReviewState::PendingCompletionValidation]);
        $this->makeComplaint(['review_state' => ComplaintReviewState::CompletionReturnedToOperator]);
        $this->makeComplaint(['review_state' => ComplaintReviewState::ValidatedForOperator]);
        $this->makeComplaint(['review_state' => ComplaintReviewState::PendingInitialValidation]);
        $this->makeComplaint(['review_state' => null]);

        $ids = Complaint::query()->pendingCompletionValidation()->pluck('id')->all();

        $this->assertSame([$pending->id], $ids);
    }

    public function test_completion_returned_scope_and_identifier_are_correct(): void
    {
        $returned = $this->makeComplaint(['review_state' => ComplaintReviewState::CompletionReturnedToOperator]);
        $this->makeComplaint(['review_state' => ComplaintReviewState::PendingCompletionValidation]);

        $ids = Complaint::query()->completionReturnedToOperator()->pluck('id')->all();
        $this->assertSame([$returned->id], $ids);

        $this->assertTrue($returned->fresh()->isCompletionReturnedToOperator());
        $this->assertFalse($returned->fresh()->isPendingCompletionValidation());
        $this->assertTrue($returned->fresh()->isCompletionPending());
    }

    public function test_pending_completion_helper_and_is_completion_pending(): void
    {
        $pending = $this->makeComplaint(['review_state' => ComplaintReviewState::PendingCompletionValidation]);

        $this->assertTrue($pending->fresh()->isPendingCompletionValidation());
        $this->assertFalse($pending->fresh()->isCompletionReturnedToOperator());
        $this->assertTrue($pending->fresh()->isCompletionPending());

        // A plain validated complaint is NOT in the completion gate.
        $plain = $this->makeComplaint(['review_state' => ComplaintReviewState::ValidatedForOperator]);
        $this->assertFalse($plain->fresh()->isCompletionPending());
    }

    public function test_helpers_read_review_state_not_status_alone(): void
    {
        // Same status (in_progress), different review_state → helpers must differ.
        $pending = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);
        $plain = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->assertSame(ComplaintStatus::InProgress, $pending->fresh()->status);
        $this->assertSame(ComplaintStatus::InProgress, $plain->fresh()->status);
        $this->assertTrue($pending->fresh()->isCompletionPending());
        $this->assertFalse($plain->fresh()->isCompletionPending());
    }

    // =========================================================================
    // F. OPERATOR VISIBILITY (single source of truth)
    // =========================================================================

    public function test_operator_visibility_accepts_pending_completion(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'review_state'  => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $ids = Complaint::query()->visibleToOperator($this->operatorA)->pluck('id')->all();
        $this->assertContains($complaint->id, $ids);
        $this->assertTrue($complaint->fresh()->isVisibleToOperator($this->operatorA));
    }

    public function test_operator_visibility_accepts_returned_completion(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'review_state'  => ComplaintReviewState::CompletionReturnedToOperator,
        ]);

        $ids = Complaint::query()->visibleToOperator($this->operatorA)->pluck('id')->all();
        $this->assertContains($complaint->id, $ids);
        $this->assertTrue($complaint->fresh()->isVisibleToOperator($this->operatorA));
    }

    public function test_operator_visibility_rejects_pending_initial_validation(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'review_state'  => ComplaintReviewState::PendingInitialValidation,
        ]);

        $ids = Complaint::query()->visibleToOperator($this->operatorA)->pluck('id')->all();
        $this->assertNotContains($complaint->id, $ids);
        $this->assertFalse($complaint->fresh()->isVisibleToOperator($this->operatorA));
    }

    public function test_operator_visibility_rejects_returned_to_citizen(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'review_state'  => ComplaintReviewState::ReturnedToCitizen,
        ]);

        $ids = Complaint::query()->visibleToOperator($this->operatorA)->pluck('id')->all();
        $this->assertNotContains($complaint->id, $ids);
        $this->assertFalse($complaint->fresh()->isVisibleToOperator($this->operatorA));
    }

    public function test_dinas_isolation_is_preserved_for_completion_states(): void
    {
        $mine = $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'review_state'  => ComplaintReviewState::PendingCompletionValidation,
        ]);
        $other = $this->makeComplaint([
            'dinas_unit_id' => $this->unitB->id,
            'review_state'  => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $idsA = Complaint::query()->visibleToOperator($this->operatorA)->pluck('id')->all();

        $this->assertContains($mine->id, $idsA);
        $this->assertNotContains($other->id, $idsA, 'Operator A must not see Dinas B completion.');
        $this->assertFalse($other->fresh()->isVisibleToOperator($this->operatorA));
    }

    public function test_operator_without_unit_still_sees_nothing(): void
    {
        $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'review_state'  => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $orphan = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null,
        ]);

        $this->assertSame([], Complaint::query()->visibleToOperator($orphan)->pluck('id')->all());
    }

    // =========================================================================
    // G. HISTORICAL DATA COMPATIBILITY
    // =========================================================================

    public function test_historical_null_review_state_remains_compatible_and_visible(): void
    {
        $legacy = $this->makeComplaint([
            'dinas_unit_id'           => $this->unitA->id,
            'review_state'            => null,
            'completion_submitted_at' => null,
            'completion_return_reason' => null,
        ]);

        $fresh = $legacy->fresh();

        // NULL review_state stays NULL (no mass backfill) and completion is empty.
        $this->assertNull($fresh->review_state);
        $this->assertNull($fresh->completion_submitted_at);
        $this->assertNull($fresh->completion_return_reason);

        // Legacy rows keep the historical operator workflow (visible).
        $this->assertTrue($fresh->isVisibleToOperator($this->operatorA));

        // And they are NOT part of the completion gate.
        $this->assertFalse($fresh->isCompletionPending());
    }

    public function test_legacy_rows_are_not_part_of_the_completion_queue(): void
    {
        $this->makeComplaint(['review_state' => null]);
        $this->makeComplaint(['review_state' => ComplaintReviewState::ValidatedForOperator]);

        $this->assertSame(0, Complaint::query()->pendingCompletionValidation()->count());
    }

    // =========================================================================
    // H. RETENTION COMPATIBILITY
    // =========================================================================

    public function test_completion_data_is_deleted_with_the_complaint_by_retention(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'             => ComplaintReviewState::CompletionReturnedToOperator,
            'completion_submitted_at'  => now(),
            'completion_return_reason' => 'Data completion untuk diuji retensi.',
            'submitted_at'             => now()->subYears(6),
        ]);

        $service = app(ComplaintRetentionService::class);
        $service->purge($complaint);

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }
}
