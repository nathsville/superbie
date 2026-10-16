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
 * SUPERBIE — PHASE 3 — Bagian 1B-2 — Operator SUBMIT COMPLETION.
 *
 * FROZEN behaviour under test:
 *   - An Operator SUBMITS the finished work for Super Admin validation. This is
 *     NOT a status change: the status STAYS `in_progress`; `resolved_at` is NOT
 *     set; no `resolved` status history is written; NO citizen-visible public
 *     response is created.
 *   - `review_state` moves to `pending_completion_validation` and
 *     `completion_submitted_at` is stamped (server time). A resubmission clears
 *     `completion_return_reason`; `review_return_reason` is left untouched.
 *   - An Operator can NO LONGER set `resolved` directly through the legacy
 *     status endpoint (server-side enforcement, not UI-only).
 *   - Everything is atomic; a double submission is rejected with no duplicate
 *     audit.
 *
 * Super Admin approval/return is a LATER part and is NOT exercised here.
 */
class CompletionValidationSubmitTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $operatorA;
    private User $operatorB;
    private User $operatorNoUnit;
    private User $admin;
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
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
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
        $this->operatorNoUnit = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null,
        ]);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-P3B2-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => $this->unitA->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji submit completion ' . static::$seq,
            'description'    => 'Deskripsi laporan uji completion submission.',
            'status'         => ComplaintStatus::InProgress,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function submitUrl(Complaint $complaint): string
    {
        return route('operator.complaint.submit-completion', $complaint);
    }

    // =========================================================================
    // 1–3. HAPPY PATH
    // =========================================================================

    public function test_operator_can_submit_completion_for_own_dinas(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint))
            ->assertRedirect(route('operator.complaint.show', $complaint))
            ->assertSessionHas('success');

        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $complaint->fresh()->review_state
        );
    }

    public function test_submit_sets_review_state_to_pending_completion_validation(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint));

        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $complaint->fresh()->review_state
        );
    }

    public function test_submit_stamps_completion_submitted_at(): void
    {
        $complaint = $this->makeComplaint();
        $this->assertNull($complaint->completion_submitted_at);

        $before = now()->subSecond();
        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint));

        $fresh = $complaint->fresh();
        $this->assertNotNull($fresh->completion_submitted_at);
        $this->assertTrue($fresh->completion_submitted_at->greaterThanOrEqualTo($before));
        $this->assertTrue($fresh->completion_submitted_at->lessThanOrEqualTo(now()->addSecond()));
    }

    // =========================================================================
    // 4–7. STATUS IS UNTOUCHED / NO RESOLUTION SIDE EFFECTS
    // =========================================================================

    public function test_submit_keeps_status_in_progress(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint));

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_submit_does_not_set_resolved_at(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint));

        $this->assertNull($complaint->fresh()->resolved_at);
    }

    public function test_submit_does_not_create_resolved_status_history(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint));

        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'to_status'    => 'resolved',
        ]);
    }

    public function test_submit_does_not_create_public_completion_response(): void
    {
        $complaint = $this->makeComplaint();

        // Even with a narrative, it must NOT be published to the reporter.
        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint), [
            'completion_note' => 'Narasi penyelesaian internal yang belum boleh publik.',
        ]);

        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')
            ->count());
    }

    public function test_completion_narrative_is_stored_as_internal_note_only(): void
    {
        $complaint = $this->makeComplaint();
        $narrative = 'Narasi penyelesaian internal yang tidak terlihat pelapor.';

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint), [
            'completion_note' => $narrative,
        ]);

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operatorA->id,
            'visibility'   => 'internal',
            'body'         => $narrative,
        ]);
    }

    public function test_citizen_cannot_see_completion_narrative(): void
    {
        $complaint = $this->makeComplaint();
        $narrative = 'RAHASIA-INTERNAL-COMPLETION-XYZ';

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint), [
            'completion_note' => $narrative,
        ]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee($narrative);
    }

    // =========================================================================
    // 8. AUDIT
    // =========================================================================

    public function test_submit_writes_completion_submitted_audit(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint));

        $audit = AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_submitted')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->operatorA->id, $audit->actor_id);
        $this->assertSame('complaint', $audit->subject_type);
        $this->assertSame($this->unitA->id, $audit->metadata['dinas_unit_id']);
        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation->value,
            $audit->metadata['new_review_state']
        );
        $this->assertSame('in_progress', $audit->metadata['status']);
    }

    // =========================================================================
    // 9. DOUBLE SUBMISSION
    // =========================================================================

    public function test_double_submission_is_rejected(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint))
            ->assertSessionHas('success');

        $firstSubmittedAt = $complaint->fresh()->completion_submitted_at;

        // Second attempt must be rejected server-side with NO state change.
        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint))
            ->assertSessionHasErrors('completion');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $fresh->review_state);
        $this->assertTrue($fresh->completion_submitted_at->equalTo($firstSubmittedAt));

        // Exactly ONE audit entry — no duplicate.
        $this->assertSame(1, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_submitted')
            ->count());
    }

    // =========================================================================
    // 10–11. RESUBMISSION FIELDS
    // =========================================================================

    public function test_resubmission_clears_completion_return_reason(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'             => ComplaintReviewState::CompletionReturnedToOperator,
            'completion_return_reason' => 'Perbaiki bukti penyelesaian terlebih dahulu.',
        ]);

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint))
            ->assertSessionHas('success');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $fresh->review_state);
        $this->assertNull($fresh->completion_return_reason);
        $this->assertNotNull($fresh->completion_submitted_at);
    }

    public function test_resubmission_does_not_touch_review_return_reason(): void
    {
        // review_return_reason is the PHASE 2 return-to-CITIZEN message and must
        // never be mutated by a completion submission.
        $complaint = $this->makeComplaint([
            'review_state'         => ComplaintReviewState::ValidatedForOperator,
            'review_return_reason' => 'PESAN_RETURN_TO_CITIZEN_TIDAK_BOLEH_BERUBAH',
        ]);

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint));

        $this->assertSame(
            'PESAN_RETURN_TO_CITIZEN_TIDAK_BOLEH_BERUBAH',
            $complaint->fresh()->review_return_reason
        );
    }

    // =========================================================================
    // 12–15. AUTHORIZATION / IDOR
    // =========================================================================

    public function test_operator_a_cannot_submit_another_dinas_complaint(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unitB->id,
            'category_id'   => $this->categoryB->id,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint))
            ->assertNotFound();

        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $complaint->fresh()->review_state);
        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    public function test_operator_without_unit_cannot_submit(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorNoUnit)
            ->patch($this->submitUrl($complaint))
            ->assertNotFound();

        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $complaint->fresh()->review_state);
    }

    public function test_citizen_cannot_submit(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizen)
            ->patch($this->submitUrl($complaint))
            ->assertForbidden();

        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $complaint->fresh()->review_state);
    }

    public function test_admin_cannot_submit(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin)
            ->patch($this->submitUrl($complaint))
            ->assertForbidden();

        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $complaint->fresh()->review_state);
    }

    public function test_super_admin_cannot_use_the_operator_submit_endpoint(): void
    {
        // The Operator endpoint is NOT Super Admin's path — Super Admin validates
        // through a separate (later) surface.
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)
            ->patch($this->submitUrl($complaint))
            ->assertForbidden();

        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $complaint->fresh()->review_state);
    }

    public function test_n6b4_inactive_operator_cannot_submit_completion(): void
    {
        // N6B-4 — endpoint-level proof that an INACTIVE Operator is blocked on the
        // submit-completion route by the global `active` middleware (redirected to
        // login and logged out), with NO state mutation.
        $complaint = $this->makeComplaint();

        $this->operatorA->update(['is_active' => false]);

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint), [
                'completion_note' => 'Upaya pengajuan oleh operator nonaktif.',
            ])
            ->assertRedirect(route('login'));

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);
        $this->assertNull($fresh->completion_submitted_at);
        $this->assertSame(0, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_submitted')->count());
    }

    // =========================================================================
    // 16–17. UPDATE-STATUS AUTHORITY
    // =========================================================================

    public function test_operator_cannot_directly_resolve_via_status_endpoint(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::InProgress]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Upaya menyelesaikan langsung tanpa validasi.',
            ])
            ->assertSessionHasErrors('status');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertNull($fresh->resolved_at);
        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'to_status'    => 'resolved',
        ]);
        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')->count());
    }

    public function test_operator_cannot_resolve_from_waiting_for_information_either(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::WaitingForInformation]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Upaya menyelesaikan langsung.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::WaitingForInformation, $complaint->fresh()->status);
    }

    public function test_operator_can_still_make_other_valid_transitions(): void
    {
        // LC-2 — the active edges an Operator may use are submitted → in_progress
        // and in_progress → rejected. Use submitted → in_progress here.
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Submitted]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status' => 'in_progress',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_super_admin_retains_resolution_authority(): void
    {
        // Authority moved, the MATRIX did not: Super Admin can still resolve.
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::InProgress]);

        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Diselesaikan melalui otoritas Super Admin.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
    }

    // =========================================================================
    // 18–23. STATE ELIGIBILITY
    // =========================================================================

    public function test_completion_returned_to_operator_can_be_resubmitted(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::CompletionReturnedToOperator,
        ]);

        $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint))
            ->assertSessionHas('success');

        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $complaint->fresh()->review_state
        );
    }

    public function test_pending_initial_validation_cannot_be_submitted(): void
    {
        // Such a complaint has NOT passed the initial gate, so it is invisible to
        // the Operator (404) — the completion gate is unreachable even earlier.
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::PendingInitialValidation,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint))
            ->assertNotFound();

        $this->assertSame(
            ComplaintReviewState::PendingInitialValidation,
            $complaint->fresh()->review_state
        );
        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    public function test_returned_to_citizen_cannot_be_submitted(): void
    {
        // Returned-to-citizen complaints are also invisible to the Operator (404).
        $complaint = $this->makeComplaint([
            'review_state' => ComplaintReviewState::ReturnedToCitizen,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint))
            ->assertNotFound();

        $this->assertSame(
            ComplaintReviewState::ReturnedToCitizen,
            $complaint->fresh()->review_state
        );
        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    public function test_resolved_cannot_be_submitted(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Resolved,
            'resolved_at'  => now(),
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint))
            ->assertSessionHasErrors('completion');

        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $complaint->fresh()->review_state);
        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    public function test_closed_cannot_be_submitted(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Closed,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint))
            ->assertSessionHasErrors('completion');

        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    public function test_rejected_cannot_be_submitted(): void
    {
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Rejected,
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->submitUrl($complaint))
            ->assertSessionHasErrors('completion');

        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    // =========================================================================
    // 24. HISTORICAL LEGACY (NULL review_state)
    // =========================================================================

    public function test_legacy_null_review_state_is_not_mass_mutated(): void
    {
        $acted = $this->makeComplaint([
            'status'       => ComplaintStatus::InProgress,
            'review_state' => null,
        ]);
        $untouchedA = $this->makeComplaint(['status' => ComplaintStatus::InProgress, 'review_state' => null]);
        $untouchedB = $this->makeComplaint(['status' => ComplaintStatus::InProgress, 'review_state' => null]);

        $this->actingAs($this->operatorA)->patch($this->submitUrl($acted))
            ->assertSessionHas('success');

        // Only the acted-on row changed; the others stay NULL (no backfill).
        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $acted->fresh()->review_state
        );
        $this->assertNull($untouchedA->fresh()->review_state);
        $this->assertNull($untouchedB->fresh()->review_state);
    }

    // =========================================================================
    // 25. ATOMICITY / ROLLBACK
    // =========================================================================

    public function test_transaction_rollback_leaves_no_half_completion_state(): void
    {
        $complaint = $this->makeComplaint();

        // Force the audit insert to fail AFTER the review-state write, so the
        // whole transaction must roll back atomically.
        AuditLog::creating(function () {
            throw new \DomainException('forced audit failure');
        });

        $threw = false;
        try {
            $this->withoutExceptionHandling();
            $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint), [
                'completion_note' => 'Narasi penyelesaian.',
            ]);
        } catch (\DomainException $e) {
            $threw = true;
        } finally {
            AuditLog::flushEventListeners();
        }

        $this->assertTrue($threw, 'The forced audit failure must abort the request.');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);
        $this->assertNull($fresh->completion_submitted_at);
        $this->assertNull($fresh->completion_return_reason);
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);

        // Neither the internal note nor the audit survived the rollback.
        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)->count());
        $this->assertDatabaseMissing('audit_logs', [
            'subject_id' => $complaint->id,
            'action'     => 'complaint.completion_submitted',
        ]);
    }

    public function test_n6b2_notification_failure_rolls_back_the_whole_submit_transaction(): void
    {
        // N6B-2 — a NON-duplicate notification failure must abort the business
        // action and roll back EVERY mutation (N4 contract). We force the failure
        // at the model boundary used by NotificationService::deliver(): the
        // `AppNotification::creating` hook throws a non-duplicate exception, which
        // `deliver()` does NOT swallow (it only swallows duplicate-key
        // QueryExceptions), so it must propagate and roll the transaction back.
        $complaint = $this->makeComplaint();

        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $complaint->review_state);

        AppNotification::creating(function () {
            throw new \RuntimeException('forced non-duplicate notification failure');
        });

        $threw = false;
        try {
            $this->withoutExceptionHandling();
            $this->actingAs($this->operatorA)->patch($this->submitUrl($complaint), [
                'completion_note' => 'Narasi penyelesaian untuk uji rollback notifikasi.',
            ]);
        } catch (\RuntimeException $e) {
            $threw = true;
        } finally {
            AppNotification::flushEventListeners();
        }

        $this->assertTrue($threw, 'A non-duplicate notification failure must abort the request.');

        $fresh = $complaint->fresh();

        // 1 — review_state did NOT remain pending_completion_validation.
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);
        // 2 — completion_submitted_at is unchanged (null).
        $this->assertNull($fresh->completion_submitted_at);
        $this->assertNull($fresh->completion_return_reason);
        // 3 — the internal completion note was NOT persisted.
        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)->count());
        // 4 — the canonical completion audit was NOT persisted.
        $this->assertDatabaseMissing('audit_logs', [
            'subject_id' => $complaint->id,
            'action'     => 'complaint.completion_submitted',
        ]);
        // 5 — no notification was persisted.
        $this->assertSame(0, AppNotification::where('complaint_id', $complaint->id)->count());
        // Status was never touched by a submission.
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
    }

    // =========================================================================
    // UI CONVENIENCE (server remains source of truth)
    // =========================================================================

    public function test_operator_detail_shows_submit_action_when_eligible(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::InProgress]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Ajukan Penyelesaian');
    }

    public function test_operator_detail_hides_submit_action_when_pending(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'            => ComplaintReviewState::PendingCompletionValidation,
            'completion_submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint));

        $response->assertOk();
        $response->assertDontSee('name="completion_note"', false);
    }

    public function test_operator_detail_shows_submit_action_again_after_return(): void
    {
        $complaint = $this->makeComplaint([
            'review_state'             => ComplaintReviewState::CompletionReturnedToOperator,
            'completion_return_reason' => 'Perbaiki dulu.',
        ]);

        $response = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Ajukan Penyelesaian');
    }

    public function test_operator_status_dropdown_no_longer_offers_resolved(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::InProgress]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee('value="resolved"', false);
    }
}
