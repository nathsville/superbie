<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE N6A — F-1 (frozen).
 *
 * While an Operator's completion submission awaits Super Admin validation
 * (`review_state = pending_completion_validation`), the OPERATOR may NOT use
 * "Perbarui Status" (`operator.complaint.update-status`) at all. Once the Super
 * Admin RETURNS the submission (`review_state = completion_returned_to_operator`),
 * the normal status options come back.
 *
 * These tests lock the SERVER-SIDE guard (the UI is only a convenience):
 *   - no status change, no `resolved_at`, no status history, no success audit,
 *     no public response, no notification for a blocked Operator request;
 *   - `review_state` is never rewritten by the guard;
 *   - Super Admin behaviour (G7) is unchanged;
 *   - existing scope / direct-resolve / closed-terminal rules are intact.
 */
class OperatorStatusCompletionGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
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

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

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
            'reference_code' => 'LPW-N6A-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => $this->unitA->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji completion gate ' . static::$seq,
            'description'    => 'Deskripsi laporan uji F-1.',
            'status'         => ComplaintStatus::InProgress,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function updateStatusUrl(Complaint $complaint): string
    {
        return route('operator.complaint.update-status', $complaint);
    }

    private function statusHistoryCount(Complaint $complaint): int
    {
        return ComplaintStatusHistory::where('complaint_id', $complaint->id)->count();
    }

    private function statusAuditCount(Complaint $complaint): int
    {
        return AuditLog::where('subject_type', 'complaint')
            ->where('subject_id', $complaint->id)
            ->where('action', 'complaint.status_updated')
            ->count();
    }

    private function publicResponseCount(Complaint $complaint): int
    {
        return ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')
            ->count();
    }

    private function notificationCount(User $user): int
    {
        return AppNotification::where('recipient_user_id', $user->id)->count();
    }

    // =========================================================================
    // TEST 1–7 — Operator + pending_completion_validation → BLOCKED, no side effects
    // =========================================================================

    public function test_1_operator_is_blocked_from_update_status_while_pending_completion_validation(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'waiting_for_information'])
            ->assertRedirect(route('operator.complaint.show', $complaint))
            ->assertSessionHasErrors('status');

        // The friendly, non-sensitive message is used.
        $this->assertStringContainsString(
            'menunggu validasi penyelesaian',
            session('errors')->first('status')
        );
    }

    public function test_2_status_stays_in_progress_when_blocked(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'waiting_for_information']);

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_3_review_state_stays_pending_completion_validation_when_blocked(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'rejected', 'rejection_reason' => 'x', 'public_response_body' => 'y']);

        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $complaint->fresh()->review_state
        );
    }

    public function test_4_no_new_status_history_when_blocked(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $before = $this->statusHistoryCount($complaint);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'waiting_for_information']);

        $this->assertSame($before, $this->statusHistoryCount($complaint));
    }

    public function test_5_no_new_success_audit_when_blocked(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'waiting_for_information']);

        $this->assertSame(0, $this->statusAuditCount($complaint));
    }

    public function test_6_no_new_notification_when_blocked(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'waiting_for_information']);

        $this->assertSame(0, $this->notificationCount($this->citizen));
        $this->assertSame(0, $this->notificationCount($this->operatorA));
        $this->assertSame(0, $this->notificationCount($this->superAdmin));
    }

    public function test_7_no_new_public_response_when_blocked(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), [
                'status'               => 'rejected',
                'rejection_reason'     => 'Alasan penolakan.',
                'public_response_body' => 'Respons publik.',
            ]);

        $this->assertSame(0, $this->publicResponseCount($complaint));
    }

    public function test_operator_blocked_for_every_candidate_status_while_pending_completion(): void
    {
        // The complaint status is `in_progress`; every matrix-legal target for the
        // Operator (waiting_for_information, rejected, closed) must be blocked.
        foreach (['waiting_for_information', 'rejected', 'closed'] as $candidate) {
            $complaint = $this->makeComplaint([
                'review_state' => ComplaintReviewState::PendingCompletionValidation,
            ]);

            $this->actingAs($this->operatorA)
                ->patch($this->updateStatusUrl($complaint), [
                    'status'               => $candidate,
                    'rejection_reason'     => 'x',
                    'public_response_body' => 'y',
                ])
                ->assertSessionHasErrors('status');

            $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status, "candidate={$candidate}");
            $this->assertSame(0, $this->statusHistoryCount($complaint), "candidate={$candidate}");
            $this->assertSame(0, $this->statusAuditCount($complaint), "candidate={$candidate}");
        }
    }

    // =========================================================================
    // TEST 8–9 — completion_returned_to_operator → options restored
    // =========================================================================

    public function test_8_valid_transition_works_again_after_completion_returned(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::CompletionReturnedToOperator,
        ]);

        // LC-2 — the valid active transition from `in_progress` is → rejected.
        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), [
                'status'               => 'rejected',
                'rejection_reason'     => 'Bukan yurisdiksi kami.',
                'public_response_body' => 'Laporan ditolak setelah pengembalian penyelesaian.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ComplaintStatus::Rejected, $complaint->fresh()->status);
        $this->assertSame(1, $this->statusHistoryCount($complaint));
    }

    public function test_9_invalid_transition_still_rejected_after_completion_returned(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::CompletionReturnedToOperator,
        ]);

        // in_progress → submitted is NOT in the frozen matrix.
        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'submitted'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    // =========================================================================
    // TEST 10 — Super Admin G7 unchanged
    // =========================================================================

    public function test_10_super_admin_can_still_update_status_while_pending_completion(): void
    {
        // G7: the guard must NOT affect Super Admin. A Super Admin direct resolve
        // via this endpoint remains allowed (existing authority).
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->superAdmin)
            ->patch($this->updateStatusUrl($complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Diselesaikan oleh Super Admin.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
    }

    public function test_10b_super_admin_close_while_pending_completion_is_rejected_by_matrix(): void
    {
        // Super Admin is not blocked by the Operator guard; the matrix still
        // applies. LC-2 — the manual close edge is retired, so even a Super Admin
        // cannot close a `resolved` complaint.
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Resolved,
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $this->actingAs($this->superAdmin)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
    }

    // =========================================================================
    // TEST 11–14 — Existing rules intact
    // =========================================================================

    public function test_11_operator_from_another_dinas_is_still_denied(): void
    {
        $complaint = $this->makeComplaint([
            'category_id'   => $this->categoryB->id,
            'dinas_unit_id' => $this->unitB->id,
            'review_state'  => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'waiting_for_information'])
            ->assertNotFound();

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_12_operator_direct_resolve_is_still_denied(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Upaya resolve langsung.',
            ])
            ->assertSessionHasErrors('status');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertNull($fresh->resolved_at);
    }

    public function test_13_closed_is_still_terminal_for_operator(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Closed,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'in_progress'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Closed, $complaint->fresh()->status);
    }

    public function test_14_operator_cannot_resolve_a_resolved_complaint(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Resolved,
            'resolved_at'  => now(),
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        // resolved → in_progress is a forbidden reopen.
        $this->actingAs($this->operatorA)
            ->patch($this->updateStatusUrl($complaint), ['status' => 'in_progress'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
    }

    // =========================================================================
    // UI guard (convenience only; server is the boundary)
    // =========================================================================

    public function test_ui_does_not_offer_status_update_while_pending_completion(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
        ]);

        $html = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        // No status form / dropdown offered.
        $this->assertStringNotContainsString('name="status"', $html);
        $this->assertStringNotContainsString('Simpan Status', $html);
        // The informative message is shown instead.
        $this->assertStringContainsString('menunggu validasi penyelesaian', $html);
    }

    public function test_ui_offers_status_update_again_after_completion_returned(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::CompletionReturnedToOperator,
        ]);

        $html = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Simpan Status', $html);
        $this->assertStringContainsString('name="status"', $html);
    }
}
