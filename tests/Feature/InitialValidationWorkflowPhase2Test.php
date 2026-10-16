<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 2 — Super Admin initial validation workflow.
 *
 * Frozen business rules under test:
 *   A. Every NEW citizen complaint passes through Super Admin initial validation
 *      before an Operator can handle it. There is NO citizen → operator path.
 *   B. The review/validation state is SEPARATE from the complaint status (which
 *      stays at exactly 7 values). No new lifecycle status is introduced.
 *   C. Returning a complaint is NOT a rejection: the status stays `submitted`.
 *   D. The reporter may correct + resubmit the SAME complaint (no new complaint,
 *      no double count, daily-limit semantics preserved).
 *   E. Operators see ONLY complaints routed to their own Dinas/Unit that have
 *      passed validation (`validated_for_operator`) — or legacy (NULL) rows.
 *   F. The handling Operator is resolved AUTOMATICALLY from the Dinas/Unit.
 */
class InitialValidationWorkflowPhase2Test extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $operatorA;
    private User $operatorB;
    private User $citizen;
    private User $otherCitizen;

    private ComplaintCategory $categoryA;
    private ComplaintCategory $categoryB;
    private DinasUnit $unitA;
    private DinasUnit $unitB;
    private DinasUnit $unitNoOperator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->otherCitizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

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
        $this->unitNoOperator = DinasUnit::create([
            'name' => 'Dinas Tanpa Operator', 'code' => 'NO-OP', 'is_active' => true, 'sort_order' => 3,
        ]);

        $this->unitA->categories()->attach($this->categoryA->id);
        $this->unitNoOperator->categories()->attach($this->categoryA->id);
        $this->unitB->categories()->attach($this->categoryB->id);

        $this->operatorA = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);
        $this->operatorB = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitB->id,
        ]);
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-P2-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'title'          => 'Laporan uji validasi ' . static::$seq,
            'description'    => 'Deskripsi laporan uji validasi awal yang cukup panjang.',
            'status'         => ComplaintStatus::Submitted,
            'review_state'   => ComplaintReviewState::PendingInitialValidation,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // A. QUEUE
    // =========================================================================

    /** Number of notifications for a recipient (optionally of a given type). */
    private function countNotifications(User $user, ?string $type = null): int
    {
        $q = AppNotification::where('recipient_user_id', $user->id);
        if ($type !== null) {
            $q->where('type', $type);
        }

        return $q->count();
    }

    public function test_super_admin_sees_pending_complaints_in_validation_queue(): void
    {
        $pending = $this->makeComplaint(['title' => 'LAPORAN_MENUNGGU_VALIDASI']);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.validation.index'));

        $response->assertOk();
        $response->assertSee('LAPORAN_MENUNGGU_VALIDASI');
        $response->assertSee($pending->reference_code);
    }

    public function test_queue_excludes_validated_returned_and_legacy_complaints(): void
    {
        $this->makeComplaint(['title' => 'QUEUE_PENDING']);
        $this->makeComplaint([
            'title' => 'QUEUE_VALIDATED',
            'review_state' => ComplaintReviewState::ValidatedForOperator,
            'dinas_unit_id' => $this->unitA->id,
        ]);
        $this->makeComplaint(['title' => 'QUEUE_RETURNED', 'review_state' => ComplaintReviewState::ReturnedToCitizen]);
        $this->makeComplaint(['title' => 'QUEUE_LEGACY', 'review_state' => null]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.validation.index'));

        $response->assertOk();
        $response->assertSee('QUEUE_PENDING');
        $response->assertDontSee('QUEUE_VALIDATED');
        $response->assertDontSee('QUEUE_RETURNED');
        $response->assertDontSee('QUEUE_LEGACY');
    }

    public function test_queue_is_ordered_oldest_waiting_first(): void
    {
        $newer = $this->makeComplaint(['title' => 'QUEUE_NEWER', 'submitted_at' => now()]);
        $older = $this->makeComplaint(['title' => 'QUEUE_OLDER', 'submitted_at' => now()->subDays(3)]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.validation.index'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, $newer->reference_code),
            strpos($html, $older->reference_code),
            'The oldest waiting complaint must be listed first.'
        );
    }

    public function test_only_super_admin_can_access_the_validation_queue(): void
    {
        foreach ([$this->operatorA, $this->citizen] as $user) {
            // Server-side role middleware denies with 403 (never a silent redirect).
            $this->actingAs($user)->get(route('super-admin.validation.index'))->assertForbidden();
        }
    }

    public function test_super_admin_can_view_the_validation_detail_page(): void
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.validation.show', $complaint));

        $response->assertOk();
        $response->assertSee($complaint->reference_code);
        $response->assertSee('Setujui Validasi');
        $response->assertSee('Kembalikan ke Pelapor');
        $response->assertSee('Dinas PUPR');
    }

    public function test_validation_detail_is_not_available_for_non_pending_complaints(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
            'dinas_unit_id' => $this->unitA->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.show', $complaint))
            ->assertNotFound();
    }

    // =========================================================================
    // B. APPROVAL
    // =========================================================================

    public function test_approval_validates_and_routes_complaint(): void
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitA->id]
        );

        $response->assertRedirect(route('super-admin.validation.index'));

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertSame($this->categoryA->id, $fresh->category_id);
        // LC-2 — initial-validation approval now moves submitted → in_progress
        // (under_review is retired).
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertNull($fresh->assigned_to, 'PHASE 2 must NOT set assigned_to.');

        // Status history records the approved transition.
        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'from_status'  => ComplaintStatus::Submitted->value,
            'to_status'    => ComplaintStatus::InProgress->value,
        ]);
    }

    public function test_approval_may_correct_the_category(): void
    {
        $complaint = $this->makeComplaint(['category_id' => $this->categoryA->id]);

        $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryB->id, 'dinas_unit_id' => $this->unitB->id]
        )->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame($this->categoryB->id, $fresh->category_id);
        $this->assertSame($this->unitB->id, $fresh->dinas_unit_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.category_corrected', 'subject_type' => 'complaint', 'subject_id' => $complaint->id,
        ]);
    }

    public function test_approval_fails_when_dinas_is_not_mapped_to_the_final_category(): void
    {
        $complaint = $this->makeComplaint(['category_id' => $this->categoryA->id]);

        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitB->id]
        );

        $response->assertSessionHasErrors('dinas_unit_id');
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $complaint->fresh()->review_state);
        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    public function test_approval_fails_when_dinas_has_no_operator(): void
    {
        $complaint = $this->makeComplaint(['category_id' => $this->categoryA->id]);

        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitNoOperator->id]
        );

        $response->assertSessionHasErrors('dinas_unit_id');
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $complaint->fresh()->review_state);
    }

    public function test_approval_fails_when_dinas_is_inactive(): void
    {
        $inactive = DinasUnit::create(['name' => 'Dinas Nonaktif', 'code' => 'OFF', 'is_active' => false, 'sort_order' => 9]);
        $inactive->categories()->attach($this->categoryA->id);
        User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $inactive->id]);

        $complaint = $this->makeComplaint(['category_id' => $this->categoryA->id]);

        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $inactive->id]
        );

        $response->assertSessionHasErrors('dinas_unit_id');
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $complaint->fresh()->review_state);
    }

    public function test_approval_is_rejected_when_the_complaint_is_not_awaiting_validation(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::ValidatedForOperator,
            'dinas_unit_id' => $this->unitA->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitA->id]
        );

        $response->assertSessionHasErrors('validation');
    }

    // =========================================================================
    // B2. LC-2 — approval status change + notification contract
    // =========================================================================

    public function test_approval_emits_only_m2_and_never_a_duplicate_m8(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitA->id]
        )->assertSessionHasNoErrors();

        // M2 reaches the routed Operator and the reporter; never the actor.
        $this->assertSame(1, $this->countNotifications($this->operatorA, NotificationService::M2));
        $this->assertSame(1, $this->countNotifications($this->citizen, NotificationService::M2));
        $this->assertSame(0, $this->countNotifications($this->superAdmin, NotificationService::M2));

        // No M8 (status_in_progress) is emitted for the same event.
        $this->assertSame(0, $this->countNotifications($this->citizen, NotificationService::M8));
        $this->assertSame(0, $this->countNotifications($this->operatorA, NotificationService::M8));
        $this->assertSame(0, AppNotification::where('type', 'status_in_progress')->count());
    }

    public function test_approval_does_not_resurrect_a_legacy_status(): void
    {
        // A legacy-status row that (through bad data) still carries a pending
        // review_state must NOT be moved into the active flow: `in_progress` has no
        // inbound edge from a legacy status, so the status stays untouched.
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::UnderReview,
            'review_state' => ComplaintReviewState::PendingInitialValidation,
        ]);

        $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitA->id]
        )->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::UnderReview, $fresh->status);
        // The review state is still advanced (routing metadata is written), but the
        // legacy status is never converted.
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);
        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'to_status'    => ComplaintStatus::InProgress->value,
        ]);
    }

    // =========================================================================
    // C. OPERATOR RESOLUTION
    // =========================================================================

    public function test_operator_of_the_routed_dinas_sees_the_validated_complaint(): void
    {
        $complaint = $this->makeComplaint(['title' => 'ROUTED_TO_A']);

        $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitA->id]
        )->assertRedirect();

        $this->assertSame($this->operatorA->id, $complaint->fresh()->resolvedOperator()?->id);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('ROUTED_TO_A');

        // A different Dinas' operator must NOT see it.
        $this->actingAs($this->operatorB)
            ->get(route('operator.complaint.show', $complaint))
            ->assertNotFound();
    }

    public function test_operator_cannot_see_pending_or_returned_complaints(): void
    {
        $pending  = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $returned = $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'review_state'  => ComplaintReviewState::ReturnedToCitizen,
        ]);

        $this->actingAs($this->operatorA)->get(route('operator.complaint.show', $pending))->assertNotFound();
        $this->actingAs($this->operatorA)->get(route('operator.complaint.show', $returned))->assertNotFound();
    }

    public function test_legacy_complaint_without_review_state_remains_operator_visible(): void
    {
        $legacy = $this->makeComplaint([
            'review_state'  => null,
            'dinas_unit_id' => $this->unitA->id,
            'title'         => 'LEGACY_VISIBLE',
        ]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $legacy))
            ->assertOk()
            ->assertSee('LEGACY_VISIBLE');
    }

    // =========================================================================
    // D. RETURN TO CITIZEN
    // =========================================================================

    public function test_return_sets_review_state_and_reason_without_rejecting(): void
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.return', $complaint),
            ['reason' => 'Lokasi kejadian belum jelas, mohon lengkapi alamat dan foto.']
        );

        $response->assertRedirect(route('super-admin.validation.index'));

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintReviewState::ReturnedToCitizen, $fresh->review_state);
        $this->assertStringContainsString('Lokasi kejadian belum jelas', $fresh->review_return_reason);
        // A return is NOT a rejection.
        $this->assertSame(ComplaintStatus::Submitted, $fresh->status);
        $this->assertNotSame(ComplaintStatus::Rejected, $fresh->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.initial_validation_returned', 'subject_id' => $complaint->id,
        ]);
    }

    public function test_return_requires_a_reason(): void
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.return', $complaint),
            ['reason' => '']
        );

        $response->assertSessionHasErrors('reason');
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $complaint->fresh()->review_state);
    }

    public function test_citizen_sees_the_return_reason_and_correction_affordance(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'         => ComplaintReviewState::ReturnedToCitizen,
            'review_return_reason' => 'CATATAN_PERBAIKAN_UNIK',
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('CATATAN_PERBAIKAN_UNIK');
        $response->assertSee('Perbaiki Laporan');
    }

    // =========================================================================
    // E. CITIZEN CORRECTION + RESUBMISSION
    // =========================================================================

    public function test_citizen_can_open_the_correction_form_for_own_returned_complaint(): void
    {
        $complaint = $this->makeComplaint(['review_state' => ComplaintReviewState::ReturnedToCitizen]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.edit', $complaint));

        $response->assertOk();
        $response->assertSee('Kirim Ulang Laporan');
    }

    public function test_citizen_cannot_correct_another_citizens_complaint(): void
    {
        $complaint = $this->makeComplaint(['review_state' => ComplaintReviewState::ReturnedToCitizen]);

        $this->actingAs($this->otherCitizen)
            ->get(route('citizen.complaint.edit', $complaint))
            ->assertForbidden();

        $this->actingAs($this->otherCitizen)
            ->put(route('citizen.complaint.update', $complaint), $this->correctionPayload())
            ->assertForbidden();
    }

    public function test_citizen_cannot_correct_a_complaint_that_was_not_returned(): void
    {
        $complaint = $this->makeComplaint(['review_state' => ComplaintReviewState::PendingInitialValidation]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.edit', $complaint))
            ->assertForbidden();

        $this->actingAs($this->citizen)
            ->put(route('citizen.complaint.update', $complaint), $this->correctionPayload())
            ->assertForbidden();
    }

    public function test_resubmission_updates_the_same_complaint_and_returns_it_to_validation(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'         => ComplaintReviewState::ReturnedToCitizen,
            'review_return_reason' => 'Perbaiki judul.',
        ]);
        $countBefore = Complaint::count();

        $response = $this->actingAs($this->citizen)->put(
            route('citizen.complaint.update', $complaint),
            $this->correctionPayload(['title' => 'Judul yang sudah diperbaiki'])
        );

        $response->assertRedirect(route('citizen.complaint.show', $complaint));

        // SAME complaint — no new row, no double count.
        $this->assertSame($countBefore, Complaint::count());

        $fresh = $complaint->fresh();
        $this->assertSame('Judul yang sudah diperbaiki', $fresh->title);
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $fresh->review_state);
        $this->assertNull($fresh->review_return_reason);
        // Status is untouched by a resubmission.
        $this->assertSame(ComplaintStatus::Submitted, $fresh->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.citizen_resubmitted', 'subject_id' => $complaint->id,
        ]);

        // The resubmitted complaint is back in the Super Admin queue.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.index'))
            ->assertSee('Judul yang sudah diperbaiki');
    }

    public function test_resubmission_cannot_set_dinas_operator_or_review_state(): void
    {
        $complaint = $this->makeComplaint(['review_state' => ComplaintReviewState::ReturnedToCitizen]);

        $this->actingAs($this->citizen)->put(route('citizen.complaint.update', $complaint), array_merge(
            $this->correctionPayload(),
            [
                'dinas_unit_id' => $this->unitB->id,
                'assigned_to'   => $this->operatorB->id,
                'status'        => ComplaintStatus::Resolved->value,
                'review_state'  => ComplaintReviewState::ValidatedForOperator->value,
            ]
        ))->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertNull($fresh->dinas_unit_id);
        $this->assertNull($fresh->assigned_to);
        $this->assertSame(ComplaintStatus::Submitted, $fresh->status);
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $fresh->review_state);
    }

    public function test_resubmission_does_not_consume_the_daily_limit(): void
    {
        // Five complaints already created today (the daily maximum).
        for ($i = 0; $i < 5; $i++) {
            $this->makeComplaint();
        }
        $returned = $this->makeComplaint([
            'review_state' => ComplaintReviewState::ReturnedToCitizen,
            'submitted_at' => now()->subDay(),
        ]);

        $countBefore = Complaint::count();

        $this->actingAs($this->citizen)->put(
            route('citizen.complaint.update', $returned),
            $this->correctionPayload()
        )->assertRedirect();

        $this->assertSame($countBefore, Complaint::count());
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $returned->fresh()->review_state);
    }

    public function test_new_complaint_starts_in_initial_validation(): void
    {
        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->categoryA->id,
            'title'       => 'Laporan baru untuk validasi',
            'description' => 'Deskripsi laporan baru yang cukup panjang.',
            ...$this->locationPayload(),
        ]);

        $response->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->latest('id')->first();
        $this->assertNotNull($complaint);
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $complaint->review_state);
        $this->assertNull($complaint->dinas_unit_id);
    }

    // =========================================================================
    // F. AUTHORIZATION / AUDIT
    // =========================================================================

    public function test_only_super_admin_can_approve_or_return(): void
    {
        $complaint = $this->makeComplaint();

        foreach ([$this->operatorA, $this->citizen] as $user) {
            $this->actingAs($user)->post(route('super-admin.validation.approve', $complaint), [
                'category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitA->id,
            ])->assertForbidden();

            $this->actingAs($user)->post(route('super-admin.validation.return', $complaint), [
                'reason' => 'Alasan pengembalian uji coba yang panjang.',
            ])->assertForbidden();
        }

        // No state change occurred.
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $complaint->fresh()->review_state);
    }

    public function test_approval_writes_the_required_audit_events(): void
    {
        $complaint = $this->makeComplaint(['category_id' => $this->categoryA->id]);

        $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryB->id, 'dinas_unit_id' => $this->unitB->id]
        )->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.initial_validation_approved', 'subject_id' => $complaint->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.category_corrected', 'subject_id' => $complaint->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.dinas_assigned', 'subject_id' => $complaint->id]);

        $approved = AuditLog::where('action', 'complaint.initial_validation_approved')
            ->where('subject_id', $complaint->id)->first();

        $this->assertSame($this->superAdmin->id, $approved->actor_id);
        $this->assertArrayHasKey('old_review_state', $approved->metadata);
        $this->assertArrayHasKey('new_review_state', $approved->metadata);
        $this->assertArrayHasKey('resolved_operator_id', $approved->metadata);
    }

    // =========================================================================
    // G. ATTACHMENT + REVIEW DATA RETENTION
    // =========================================================================

    public function test_citizen_can_add_and_remove_attachments_on_resubmission(): void
    {
        Storage::fake('private');

        $complaint = $this->makeComplaint(['review_state' => ComplaintReviewState::ReturnedToCitizen]);
        $keep = \App\Models\ComplaintAttachment::create([
            'complaint_id' => $complaint->id, 'disk' => 'private', 'path' => 'complaints/x/keep.jpg',
            'original_name' => 'keep.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1000,
        ]);
        $remove = \App\Models\ComplaintAttachment::create([
            'complaint_id' => $complaint->id, 'disk' => 'private', 'path' => 'complaints/x/remove.jpg',
            'original_name' => 'remove.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1000,
        ]);

        $this->actingAs($this->citizen)->put(route('citizen.complaint.update', $complaint), array_merge(
            $this->correctionPayload(),
            [
                'remove_attachments' => [$remove->id],
                'attachments'        => [UploadedFile::fake()->image('baru.jpg', 100, 100)],
            ]
        ))->assertRedirect();

        $this->assertDatabaseHas('complaint_attachments', ['id' => $keep->id]);
        $this->assertDatabaseMissing('complaint_attachments', ['id' => $remove->id]);
        $this->assertSame(2, \App\Models\ComplaintAttachment::where('complaint_id', $complaint->id)->count());
    }

    public function test_review_data_is_deleted_with_the_complaint_by_retention(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'         => ComplaintReviewState::ReturnedToCitizen,
            'review_return_reason' => 'Data review untuk diuji retensi.',
            'submitted_at'         => now()->subYears(6),
        ]);

        $service = app(\App\Services\ComplaintRetentionService::class);
        $service->purge($complaint);

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }

    private function correctionPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->categoryA->id,
            'title'       => 'Judul laporan yang diperbaiki',
            'description' => 'Deskripsi laporan yang sudah diperbaiki oleh pelapor.',
        ], $this->locationPayload(), $overrides);
    }
}
