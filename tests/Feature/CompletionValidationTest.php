<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 3 — Bagian 1C — Super Admin COMPLETION validation.
 *
 * FROZEN behaviour under test:
 *   - The completion queue lists ONLY `review_state = pending_completion_validation`,
 *     oldest submitted first (`completion_submitted_at ASC, id ASC`).
 *   - APPROVE performs the single EXISTING domain transition `in_progress →
 *     resolved` (no new status/transition), sets `review_state =
 *     validated_for_operator`, stamps `resolved_at`, KEEPS `completion_submitted_at`,
 *     clears `completion_return_reason`, writes the `in_progress → resolved`
 *     status history, PUBLISHES the previously-INTERNAL completion narrative as
 *     the citizen-facing public response, and writes `complaint.completion_validated`.
 *   - RETURN stores a MANDATORY reason (min 10 chars, trimmed) in
 *     `completion_return_reason`, keeps `status = in_progress`, leaves
 *     `resolved_at` NULL, writes NO resolved history, creates NO public response,
 *     clears `completion_submitted_at`, and writes `complaint.completion_returned`.
 *   - Approval is REJECTED when no publishable completion narrative exists — the
 *     public response is NEVER fabricated.
 *   - Only a Super Admin may approve/return; Admin (monitoring) and
 *     Operator/Masyarakat cannot; a guest is redirected to login.
 *   - Everything is atomic; a double decision, approve-after-return and
 *     return-after-approve can never both succeed.
 */
class CompletionValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $admin;
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

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair();
    }

    private static int $seq = 0;

    /**
     * Build a complaint already in the completion-validation queue, mirroring
     * EXACTLY what Bagian 1B-2's `submitCompletion()` leaves behind: status
     * `in_progress`, review_state `pending_completion_validation`,
     * `completion_submitted_at` stamped, an INTERNAL narrative note, and the
     * `complaint.completion_submitted` audit recording `completion_note_id`.
     */
    private function makePendingCompletion(array $overrides = [], ?string $narrative = 'Pekerjaan telah diselesaikan dan jalan sudah diperbaiki.'): Complaint
    {
        static::$seq++;

        $submittedAt = $overrides['completion_submitted_at'] ?? now();

        $complaint = Complaint::create(array_merge([
            'reference_code'          => 'LPW-P3C-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'             => $this->citizen->id,
            'category_id'             => $this->categoryA->id,
            'dinas_unit_id'           => $this->unitA->id,
            'kecamatan_id'            => $this->kecamatanId,
            'kelurahan_id'            => $this->kelurahanId,
            'title'                   => 'Laporan uji validasi penyelesaian ' . static::$seq,
            'description'             => 'Deskripsi laporan uji validasi penyelesaian.',
            'status'                  => ComplaintStatus::InProgress,
            'review_state'            => ComplaintReviewState::PendingCompletionValidation,
            'completion_submitted_at' => $submittedAt,
            'submitted_at'            => now(),
        ], $overrides));

        $noteId = null;
        if ($narrative !== null) {
            $note = ComplaintNote::create([
                'complaint_id' => $complaint->id,
                'author_id'    => $this->operatorA->id,
                'visibility'   => 'internal',
                'body'         => $narrative,
                'created_at'   => now(),
            ]);
            $noteId = $note->id;
        }

        AuditLog::create([
            'actor_id'     => $this->operatorA->id,
            'action'       => 'complaint.completion_submitted',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
            'ip_address'   => '127.0.0.1',
            'user_agent'   => 'test',
            'metadata'     => [
                'reference_code'         => $complaint->reference_code,
                'dinas_unit_id'          => $complaint->dinas_unit_id,
                'operator_id'            => $this->operatorA->id,
                'old_review_state'       => ComplaintReviewState::ValidatedForOperator->value,
                'new_review_state'       => ComplaintReviewState::PendingCompletionValidation->value,
                'status'                 => $complaint->status->value,
                'completion_submitted_at' => $complaint->completion_submitted_at?->toIso8601String(),
                'has_completion_note'    => $noteId !== null,
                'completion_note_id'     => $noteId,
            ],
        ]);

        return $complaint;
    }

    private function approveUrl(Complaint $c): string
    {
        return route('super-admin.completion-validation.approve', $c);
    }

    private function returnUrl(Complaint $c): string
    {
        return route('super-admin.completion-validation.return', $c);
    }

    // =========================================================================
    // 1–8. QUEUE
    // =========================================================================

    public function test_super_admin_sees_pending_completion_in_the_queue(): void
    {
        $complaint = $this->makePendingCompletion(['title' => 'LAPORAN_MENUNGGU_PENYELESAIAN']);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.completion-validation.index'));

        $response->assertOk();
        $response->assertSee('LAPORAN_MENUNGGU_PENYELESAIAN');
        $response->assertSee($complaint->reference_code);
    }

    public function test_queue_excludes_returned_validated_initial_and_legacy_rows(): void
    {
        $this->makePendingCompletion(['title' => 'QCP_PENDING']);
        $this->makePendingCompletion([
            'title' => 'QCP_RETURNED', 'review_state' => ComplaintReviewState::CompletionReturnedToOperator,
        ]);
        $this->makePendingCompletion([
            'title' => 'QCP_VALIDATED', 'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);
        $this->makePendingCompletion([
            'title' => 'QCP_INITIAL', 'review_state' => ComplaintReviewState::PendingInitialValidation,
        ]);
        $this->makePendingCompletion(['title' => 'QCP_LEGACY', 'review_state' => null]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.completion-validation.index'));

        $response->assertOk();
        $response->assertSee('QCP_PENDING');
        $response->assertDontSee('QCP_RETURNED');
        $response->assertDontSee('QCP_VALIDATED');
        $response->assertDontSee('QCP_INITIAL');
        $response->assertDontSee('QCP_LEGACY');
    }

    public function test_queue_is_ordered_oldest_submitted_first(): void
    {
        $newer = $this->makePendingCompletion(['title' => 'QCP_NEWER', 'completion_submitted_at' => now()]);
        $older = $this->makePendingCompletion(['title' => 'QCP_OLDER', 'completion_submitted_at' => now()->subDays(3)]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.completion-validation.index'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, $newer->reference_code),
            strpos($html, $older->reference_code),
            'The oldest submitted completion must be listed first.'
        );
    }

    // =========================================================================
    // N6B-1 (FROZEN, Option A) — queue eligibility requires BOTH
    // `review_state = pending_completion_validation` AND `status = in_progress`.
    // =========================================================================

    /** Fetch the completion-queue HTML as the Super Admin. */
    private function queueHtml(): string
    {
        return $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.index'))
            ->assertOk()
            ->getContent();
    }

    public function test_n6b1_positive_matrix_in_progress_plus_pending_completion_is_included(): void
    {
        $complaint = $this->makePendingCompletion([
            'title' => 'QCP_N6B1_POSITIVE',
        ]);

        $html = $this->queueHtml();

        $this->assertStringContainsString('QCP_N6B1_POSITIVE', $html);
        $this->assertStringContainsString($complaint->reference_code, $html);

        // Scope-level proof (single source of truth).
        $ids = Complaint::query()->pendingCompletionValidation()->pluck('id')->all();
        $this->assertSame([$complaint->id], $ids);
    }

    public function test_n6b1_negative_matrix_non_in_progress_statuses_are_excluded(): void
    {
        // A. resolved + pending_completion_validation (the frozen G7 stale state).
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_RESOLVED',
            'status' => ComplaintStatus::Resolved,
            'resolved_at' => now(),
        ]);
        // B. rejected + pending_completion_validation.
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_REJECTED',
            'status' => ComplaintStatus::Rejected,
        ]);
        // C. closed + pending_completion_validation.
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_CLOSED',
            'status' => ComplaintStatus::Closed,
        ]);

        $html = $this->queueHtml();

        $this->assertStringNotContainsString('QCP_N6B1_RESOLVED', $html);
        $this->assertStringNotContainsString('QCP_N6B1_REJECTED', $html);
        $this->assertStringNotContainsString('QCP_N6B1_CLOSED', $html);

        $this->assertSame(0, Complaint::query()->pendingCompletionValidation()->count());
    }

    public function test_n6b1_negative_matrix_other_review_states_are_excluded(): void
    {
        // D. in_progress + completion_returned_to_operator.
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_RETURNED',
            'review_state' => ComplaintReviewState::CompletionReturnedToOperator,
        ]);
        // E. in_progress + validated_for_operator.
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_VALIDATED',
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);
        // F. in_progress + pending_initial_validation.
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_INITIAL',
            'review_state' => ComplaintReviewState::PendingInitialValidation,
        ]);
        // G. in_progress + returned_to_citizen.
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_CITIZEN',
            'review_state' => ComplaintReviewState::ReturnedToCitizen,
        ]);
        // H. legacy NULL review_state.
        $this->makePendingCompletion([
            'title' => 'QCP_N6B1_LEGACY',
            'review_state' => null,
        ]);

        $html = $this->queueHtml();

        foreach ([
            'QCP_N6B1_RETURNED',
            'QCP_N6B1_VALIDATED',
            'QCP_N6B1_INITIAL',
            'QCP_N6B1_CITIZEN',
            'QCP_N6B1_LEGACY',
        ] as $marker) {
            $this->assertStringNotContainsString($marker, $html);
        }

        $this->assertSame(0, Complaint::query()->pendingCompletionValidation()->count());
    }

    public function test_n6b1_queue_filter_never_mutates_review_state(): void
    {
        // A stale `resolved + pending_completion_validation` row must be EXCLUDED
        // from the queue WITHOUT the filter rewriting `review_state`.
        $complaint = $this->makePendingCompletion([
            'status' => ComplaintStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $this->queueHtml();

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $fresh->status);
        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $fresh->review_state,
            'The queue filter is a read rule and must NEVER rewrite review_state.'
        );
    }

    public function test_n6b1_g7_direct_resolve_removes_the_complaint_from_the_completion_queue(): void
    {
        // Frozen G7: Super Admin direct resolve via the SHARED status endpoint.
        // The complaint starts in the completion queue (in_progress + pending).
        $complaint = $this->makePendingCompletion(['title' => 'QCP_N6B1_G7']);

        $this->assertSame(
            [$complaint->id],
            Complaint::query()->pendingCompletionValidation()->pluck('id')->all()
        );

        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Diselesaikan langsung oleh Super Admin (G7).',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();

        // 1 + 2 — G7 changes ONLY the status; `review_state` is NOT rewritten.
        $this->assertSame(ComplaintStatus::Resolved, $fresh->status);
        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $fresh->review_state,
            'G7 must NOT rewrite review_state.'
        );
        $this->assertNotNull($fresh->resolved_at);

        // 3 + 4 — the stale row disappears from the queue (status != in_progress).
        $html = $this->queueHtml();
        $this->assertStringNotContainsString('QCP_N6B1_G7', $html);
        $this->assertSame(0, Complaint::query()->pendingCompletionValidation()->count());
    }

    public function test_n6b1_approve_is_rejected_after_g7_direct_resolve(): void
    {
        $complaint = $this->makePendingCompletion(['title' => 'QCP_N6B1_G7_APPROVE']);

        // G7 direct resolve leaves `resolved + pending_completion_validation`.
        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Diselesaikan langsung oleh Super Admin (G7).',
            ])
            ->assertSessionHasNoErrors();

        $m7CitizenBefore  = \App\Models\AppNotification::where('recipient_user_id', $this->citizen->id)
            ->where('type', \App\Services\NotificationService::M7)->count();
        $m7OperatorBefore = \App\Models\AppNotification::where('recipient_user_id', $this->operatorA->id)
            ->where('type', \App\Services\NotificationService::M7)->count();

        // Attempting to APPROVE the now-resolved complaint must be rejected by the
        // server-side status guard (queue filtering is NOT the boundary).
        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $fresh->status);
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $fresh->review_state);

        // No duplicate side effects from the rejected approval.
        $this->assertSame(
            $m7CitizenBefore,
            \App\Models\AppNotification::where('recipient_user_id', $this->citizen->id)
                ->where('type', \App\Services\NotificationService::M7)->count()
        );
        $this->assertSame(
            $m7OperatorBefore,
            \App\Models\AppNotification::where('recipient_user_id', $this->operatorA->id)
                ->where('type', \App\Services\NotificationService::M7)->count()
        );
        // Exactly one resolved status history (from G7), none from the rejected approve.
        $this->assertSame(1, ComplaintStatusHistory::where('complaint_id', $complaint->id)
            ->where('to_status', 'resolved')->count());
    }

    public function test_only_super_admin_can_access_the_completion_queue(): void
    {
        foreach ([$this->operatorA, $this->admin, $this->citizen] as $user) {
            $this->actingAs($user)
                ->get(route('super-admin.completion-validation.index'))
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_the_completion_queue(): void
    {
        $this->get(route('super-admin.completion-validation.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_the_completion_detail_page(): void
    {
        $complaint = $this->makePendingCompletion();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint));

        $response->assertOk();
        $response->assertSee($complaint->reference_code);
        $response->assertSee('Setujui &amp; Selesaikan', false);
        $response->assertSee('Kembalikan ke Operator');
    }

    public function test_completion_detail_is_not_available_for_non_pending_complaints(): void
    {
        $complaint = $this->makePendingCompletion([
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint))
            ->assertNotFound();
    }

    public function test_completion_detail_shows_the_operator_internal_narrative(): void
    {
        $complaint = $this->makePendingCompletion([], 'NARASI_INTERNAL_OPERATOR_XYZ');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint))
            ->assertOk()
            ->assertSee('NARASI_INTERNAL_OPERATOR_XYZ');
    }

    // =========================================================================
    // 9–12. DETAIL AUTHORIZATION
    // =========================================================================

    public function test_only_super_admin_can_view_completion_detail(): void
    {
        $complaint = $this->makePendingCompletion();

        foreach ([$this->operatorA, $this->admin, $this->citizen] as $user) {
            $this->actingAs($user)
                ->get(route('super-admin.completion-validation.show', $complaint))
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_completion_detail(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->get(route('super-admin.completion-validation.show', $complaint))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // 13–24. RETURN
    // =========================================================================

    public function test_return_requires_a_reason(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $complaint->fresh()->review_state
        );
    }

    public function test_return_reason_must_be_at_least_ten_characters(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'pendek'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $complaint->fresh()->review_state
        );
    }

    public function test_return_sets_review_state_and_internal_reason(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Narasi penyelesaian belum lengkap, mohon perbaiki.'])
            ->assertRedirect(route('super-admin.completion-validation.index'))
            ->assertSessionHas('success');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintReviewState::CompletionReturnedToOperator, $fresh->review_state);
        $this->assertSame('Narasi penyelesaian belum lengkap, mohon perbaiki.', $fresh->completion_return_reason);
    }

    public function test_return_stores_reason_in_completion_return_reason_not_review_return_reason(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki bukti penyelesaian terlebih dahulu.']);

        $fresh = $complaint->fresh();
        $this->assertSame('Perbaiki bukti penyelesaian terlebih dahulu.', $fresh->completion_return_reason);
        $this->assertNull($fresh->review_return_reason);
    }

    public function test_return_trims_the_reason_server_side(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => '   Alasan dengan spasi di ujung   ']);

        $this->assertSame('Alasan dengan spasi di ujung', $complaint->fresh()->completion_return_reason);
    }

    public function test_return_keeps_status_in_progress(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_return_does_not_set_resolved_at(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        $this->assertNull($complaint->fresh()->resolved_at);
    }

    public function test_return_clears_completion_submitted_at(): void
    {
        $complaint = $this->makePendingCompletion();
        $this->assertNotNull($complaint->completion_submitted_at);

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    public function test_return_writes_no_resolved_status_history(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'to_status'    => 'resolved',
        ]);
        $this->assertSame(0, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());
    }

    public function test_return_creates_no_public_response(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')
            ->count());
    }

    public function test_return_does_not_change_category_dinas_or_assigned_to(): void
    {
        $complaint = $this->makePendingCompletion([
            'assigned_to' => null,
        ]);

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        $fresh = $complaint->fresh();
        $this->assertSame($this->categoryA->id, $fresh->category_id);
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertNull($fresh->assigned_to);
    }

    public function test_return_writes_completion_returned_audit(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        $audit = AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_returned')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->superAdmin->id, $audit->actor_id);
        $this->assertSame('complaint', $audit->subject_type);
        $this->assertSame(
            ComplaintReviewState::CompletionReturnedToOperator->value,
            $audit->metadata['new_review_state']
        );
        $this->assertSame('in_progress', $audit->metadata['status_unchanged']);
    }

    public function test_return_requires_the_complaint_to_be_pending_completion(): void
    {
        $complaint = $this->makePendingCompletion([
            'review_state' => ComplaintReviewState::ValidatedForOperator,
        ]);

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.'])
            ->assertSessionHasErrors('validation');

        $this->assertSame(
            ComplaintReviewState::ValidatedForOperator,
            $complaint->fresh()->review_state
        );
    }

    // =========================================================================
    // 25–40. APPROVAL
    // =========================================================================

    public function test_approval_sets_status_resolved_via_existing_transition(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertRedirect(route('super-admin.completion-validation.index'))
            ->assertSessionHas('success');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $fresh->status);
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);
    }

    public function test_approval_stamps_resolved_at(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $fresh = $complaint->fresh();
        $this->assertNotNull($fresh->resolved_at);
        $this->assertTrue($fresh->resolved_at->lessThanOrEqualTo(now()->addSecond()));
    }

    public function test_approval_keeps_completion_submitted_at(): void
    {
        $complaint = $this->makePendingCompletion();
        $submittedAt = $complaint->completion_submitted_at;

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $fresh = $complaint->fresh();
        $this->assertNotNull($fresh->completion_submitted_at);
        $this->assertTrue($fresh->completion_submitted_at->equalTo($submittedAt));
    }

    public function test_approval_clears_completion_return_reason(): void
    {
        $complaint = $this->makePendingCompletion([
            'completion_return_reason' => 'Sisa alasan pengembalian sebelumnya.',
        ]);

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $this->assertNull($complaint->fresh()->completion_return_reason);
    }

    public function test_approval_writes_resolved_status_history(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'from_status'  => 'in_progress',
            'to_status'    => 'resolved',
            'changed_by'   => $this->superAdmin->id,
        ]);
    }

    public function test_approval_publishes_public_response_from_internal_narrative(): void
    {
        $complaint = $this->makePendingCompletion([], 'Narasi penyelesaian untuk dipublikasikan.');

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $complaint->id,
            'visibility'   => 'public_response',
            'body'         => 'Narasi penyelesaian untuk dipublikasikan.',
        ]);
    }

    public function test_approval_keeps_the_internal_narrative_note(): void
    {
        $complaint = $this->makePendingCompletion([], 'Narasi internal yang tetap tersimpan.');

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $complaint->id,
            'visibility'   => 'internal',
            'body'         => 'Narasi internal yang tetap tersimpan.',
        ]);
    }

    public function test_approval_makes_the_public_response_visible_to_the_citizen(): void
    {
        $complaint = $this->makePendingCompletion([], 'PUBLIC_PAYLOAD_VISIBLE_XYZ');

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('PUBLIC_PAYLOAD_VISIBLE_XYZ');
    }

    public function test_approval_does_not_write_deprecated_public_updated_at(): void
    {
        // PHASE 6C — Decision 7: `complaints.public_updated_at` is DEPRECATED and
        // no longer written by any active workflow (there is no public tracking).
        // The completion-approval path must NOT set it.
        $complaint = $this->makePendingCompletion();

        $this->assertNull($complaint->fresh()->public_updated_at);

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $this->assertNull(
            $complaint->fresh()->public_updated_at,
            'Completion approval must NOT write the deprecated public_updated_at.'
        );
    }

    public function test_approval_writes_completion_validated_audit(): void
    {
        $complaint = $this->makePendingCompletion([], 'Narasi penyelesaian untuk audit.');

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $audit = AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_validated')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->superAdmin->id, $audit->actor_id);
        $this->assertSame('complaint', $audit->subject_type);
        $this->assertSame('resolved', $audit->metadata['to_status']);
        $this->assertSame(
            ComplaintReviewState::ValidatedForOperator->value,
            $audit->metadata['new_review_state']
        );
        $this->assertTrue($audit->metadata['has_public_response']);
        $this->assertArrayHasKey('public_response_note_id', $audit->metadata);
    }

    public function test_approval_does_not_change_category_dinas_or_assigned_to(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $fresh = $complaint->fresh();
        $this->assertSame($this->categoryA->id, $fresh->category_id);
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertNull($fresh->assigned_to);
    }

    public function test_approval_requires_the_complaint_to_be_pending_completion(): void
    {
        $complaint = $this->makePendingCompletion([
            'review_state' => ComplaintReviewState::CompletionReturnedToOperator,
        ]);

        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintReviewState::CompletionReturnedToOperator, $fresh->review_state);
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
    }

    public function test_approval_is_idempotent_safe_on_an_already_resolved_complaint(): void
    {
        // A resolved complaint is no longer in the queue; the second decision
        // must be rejected with no further side effects.
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint))->assertSessionHas('success');

        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertSessionHasErrors('validation');

        $this->assertSame(1, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());
        $this->assertSame(1, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')->count());
    }

    // =========================================================================
    // 41–44. PUBLIC-RESPONSE SAFETY (never fabricate)
    // =========================================================================

    public function test_approval_is_rejected_when_no_completion_narrative_exists(): void
    {
        // Submitted WITHOUT a narrative → no internal note, completion_note_id NULL.
        $complaint = $this->makePendingCompletion([], null);

        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $fresh->review_state);
        $this->assertNull($fresh->resolved_at);
    }

    public function test_rejected_approval_never_fabricates_a_public_response(): void
    {
        $complaint = $this->makePendingCompletion([], null);

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));

        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')->count());
        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'to_status'    => 'resolved',
        ]);
    }

    public function test_approval_is_rejected_when_the_recorded_note_was_deleted(): void
    {
        $complaint = $this->makePendingCompletion([], 'Narasi yang akan dihapus.');

        // Simulate the referenced note having vanished (e.g. retention anomaly).
        ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'internal')
            ->delete();

        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertSessionHasErrors('validation');

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_approval_is_rejected_when_the_narrative_is_blank(): void
    {
        $complaint = $this->makePendingCompletion([], '   ');

        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertSessionHasErrors('validation');

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_detail_page_disables_approval_when_narrative_missing(): void
    {
        $complaint = $this->makePendingCompletion([], null);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint));

        $response->assertOk();
        $response->assertSee('Narasi penyelesaian internal tidak tersedia.');
        $response->assertDontSee(route('super-admin.completion-validation.approve', $complaint));
    }

    // =========================================================================
    // 45–49. AUTHORIZATION (approve / return)
    // =========================================================================

    public function test_only_super_admin_can_approve_or_return(): void
    {
        foreach ([$this->operatorA, $this->admin, $this->citizen] as $user) {
            $complaint = $this->makePendingCompletion();

            $this->actingAs($user)->post($this->approveUrl($complaint))->assertForbidden();
            $this->actingAs($user)
                ->post($this->returnUrl($complaint), ['reason' => 'Alasan pengembalian uji coba.'])
                ->assertForbidden();

            $this->assertSame(
                ComplaintReviewState::PendingCompletionValidation,
                $complaint->fresh()->review_state
            );
        }
    }

    public function test_guest_cannot_approve_or_return(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->post($this->approveUrl($complaint))->assertRedirect(route('login'));
        $this->post($this->returnUrl($complaint), ['reason' => 'Alasan pengembalian uji coba.'])
            ->assertRedirect(route('login'));
    }

    public function test_admin_monitoring_cannot_reach_completion_surfaces(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->admin)->get(route('super-admin.completion-validation.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('super-admin.completion-validation.show', $complaint))->assertForbidden();
        $this->actingAs($this->admin)->post($this->approveUrl($complaint))->assertForbidden();
        $this->actingAs($this->admin)
            ->post($this->returnUrl($complaint), ['reason' => 'Alasan pengembalian uji coba.'])
            ->assertForbidden();
    }

    // =========================================================================
    // 50–54. CONCURRENCY / DOUBLE DECISIONS
    // =========================================================================

    public function test_double_approval_only_succeeds_once(): void
    {
        $complaint = $this->makePendingCompletion([], 'Narasi untuk uji double approval.');

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint))->assertSessionHas('success');
        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint))->assertSessionHasErrors('validation');

        $this->assertSame(1, ComplaintStatusHistory::where('complaint_id', $complaint->id)
            ->where('to_status', 'resolved')->count());
        $this->assertSame(1, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')->count());
        $this->assertSame(1, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_validated')->count());
    }

    public function test_double_return_only_succeeds_once(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.'])
            ->assertSessionHas('success');

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki lagi narasi penyelesaian.'])
            ->assertSessionHasErrors('validation');

        $this->assertSame(1, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_returned')->count());
        $this->assertSame(
            'Perbaiki narasi penyelesaian Anda.',
            $complaint->fresh()->completion_return_reason
        );
    }

    public function test_approve_after_return_is_rejected(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.'])
            ->assertSessionHas('success');

        $this->actingAs($this->superAdmin)
            ->post($this->approveUrl($complaint))
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertSame(ComplaintReviewState::CompletionReturnedToOperator, $fresh->review_state);
        $this->assertNull($fresh->resolved_at);
    }

    public function test_return_after_approve_is_rejected(): void
    {
        $complaint = $this->makePendingCompletion([], 'Narasi untuk uji return-after-approve.');

        $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint))->assertSessionHas('success');

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Coba kembalikan setelah disetujui.'])
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $fresh->status);
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);
        $this->assertNull($fresh->completion_return_reason);
    }

    public function test_approval_rolls_back_atomically_on_audit_failure(): void
    {
        $complaint = $this->makePendingCompletion([], 'Narasi untuk uji rollback.');

        AuditLog::creating(function () {
            throw new \DomainException('forced audit failure');
        });

        $threw = false;
        try {
            $this->withoutExceptionHandling();
            $this->actingAs($this->superAdmin)->post($this->approveUrl($complaint));
        } catch (\DomainException $e) {
            $threw = true;
        } finally {
            AuditLog::flushEventListeners();
        }

        $this->assertTrue($threw, 'The forced audit failure must abort the request.');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $fresh->review_state);
        $this->assertNull($fresh->resolved_at);
        $this->assertSame(0, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());
        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')->count());
    }

    // =========================================================================
    // 55–60. REGRESSION / DOMAIN FROZEN
    // =========================================================================

    public function test_complaint_status_enum_is_still_seven_cases(): void
    {
        $this->assertCount(7, ComplaintStatus::cases());
    }

    public function test_review_state_enum_is_still_five_cases(): void
    {
        $this->assertCount(5, ComplaintReviewState::cases());
    }

    public function test_completion_routes_are_registered_and_distinct_from_initial_validation(): void
    {
        $this->assertTrue(Route::has('super-admin.completion-validation.index'));
        $this->assertTrue(Route::has('super-admin.completion-validation.show'));
        $this->assertTrue(Route::has('super-admin.completion-validation.approve'));
        $this->assertTrue(Route::has('super-admin.completion-validation.return'));

        // The PHASE 2 initial-validation surface is untouched.
        $this->assertTrue(Route::has('super-admin.validation.index'));
        $this->assertTrue(Route::has('super-admin.validation.approve'));
        $this->assertTrue(Route::has('super-admin.validation.return'));
    }

    public function test_no_new_completion_migration_was_added(): void
    {
        // Bagian 1C reuses the existing columns — no new migration file.
        $this->assertSame(1, count(glob(database_path('migrations/*add_completion_validation*'))));
    }

    public function test_approval_uses_the_existing_in_progress_to_resolved_transition(): void
    {
        // The transition is an ALLOWED one in the frozen matrix — proof no new
        // transition was introduced for this feature.
        $this->assertTrue(ComplaintStatus::InProgress->canTransitionTo(ComplaintStatus::Resolved));
    }

    public function test_no_completion_status_value_exists(): void
    {
        foreach (ComplaintStatus::cases() as $case) {
            $this->assertStringNotContainsString('completion', $case->value);
        }
    }

    public function test_returned_completion_reappears_in_the_operator_workflow(): void
    {
        $complaint = $this->makePendingCompletion();

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.']);

        // The Operator keeps seeing its own returned work and may resubmit.
        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Ajukan Penyelesaian');

        $this->assertTrue($complaint->fresh()->isVisibleToOperator($this->operatorA));
    }

    public function test_completion_data_is_deleted_with_the_complaint_by_retention(): void
    {
        $complaint = $this->makePendingCompletion([
            'submitted_at' => now()->subYears(6),
        ]);

        $service = app(\App\Services\ComplaintRetentionService::class);
        $service->purge($complaint);

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }

    public function test_initial_validation_queue_is_not_contaminated_by_completion_rows(): void
    {
        // A pending-completion complaint must NOT appear in the initial queue.
        $this->makePendingCompletion(['title' => 'COMPLETION_NOT_INITIAL']);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.index'))
            ->assertOk()
            ->assertDontSee('COMPLETION_NOT_INITIAL');
    }

    // =========================================================================
    // N6E — N6D-1 FIX: Return requires BOTH `status = in_progress` AND
    // `review_state = pending_completion_validation`. A stale non-`in_progress`
    // row must be REJECTED (no mutation, no audit, no M6).
    // =========================================================================

    private function m6CountFor(User $user, Complaint $complaint): int
    {
        return \App\Models\AppNotification::where('recipient_user_id', $user->id)
            ->where('complaint_id', $complaint->id)
            ->where('type', \App\Services\NotificationService::M6)
            ->count();
    }

    public function test_n6e_return_is_rejected_after_g7_direct_resolve(): void
    {
        $complaint = $this->makePendingCompletion(['title' => 'QCP_N6E_G7_RETURN']);

        // G7 direct resolve: in_progress + pending_completion_validation →
        // resolved + pending_completion_validation (review_state NOT rewritten).
        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Diselesaikan langsung oleh Super Admin (G7).',
            ])
            ->assertSessionHasNoErrors();

        $before = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $before->status);
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $before->review_state);
        $submittedAtBefore = $before->completion_submitted_at;

        // The stale row is NOT actionable → Return must be rejected server-side.
        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Coba kembalikan setelah direct resolve G7.'])
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $fresh->status, 'Status must stay resolved.');
        $this->assertSame(
            ComplaintReviewState::PendingCompletionValidation,
            $fresh->review_state,
            'review_state must NOT be rewritten by a rejected return.'
        );
        $this->assertNull($fresh->completion_return_reason, 'No return reason may be written.');
        $this->assertNotNull($fresh->completion_submitted_at, 'completion_submitted_at must NOT be cleared.');
        $this->assertEquals($submittedAtBefore, $fresh->completion_submitted_at);

        // No audit, no status history, no M6.
        $this->assertSame(0, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_returned')->count());
        $this->assertSame(1, ComplaintStatusHistory::where('complaint_id', $complaint->id)
            ->where('to_status', 'resolved')->count());
        $this->assertSame(0, ComplaintStatusHistory::where('complaint_id', $complaint->id)
            ->where('to_status', '!=', 'resolved')->count());
        $this->assertSame(0, $this->m6CountFor($this->operatorA, $complaint));
    }

    public function test_n6e_return_is_rejected_on_a_rejected_pending_row(): void
    {
        $complaint = $this->makePendingCompletion([
            'title'  => 'QCP_N6E_REJECTED',
            'status' => ComplaintStatus::Rejected,
        ]);

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Kembalikan laporan rejected.'])
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Rejected, $fresh->status);
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $fresh->review_state);
        $this->assertNull($fresh->completion_return_reason);
        $this->assertNotNull($fresh->completion_submitted_at);
        $this->assertSame(0, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_returned')->count());
        $this->assertSame(0, $this->m6CountFor($this->operatorA, $complaint));
    }

    public function test_n6e_return_is_rejected_on_a_closed_pending_row(): void
    {
        $complaint = $this->makePendingCompletion([
            'title'  => 'QCP_N6E_CLOSED',
            'status' => ComplaintStatus::Closed,
        ]);

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Kembalikan laporan closed.'])
            ->assertSessionHasErrors('validation');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Closed, $fresh->status);
        $this->assertSame(ComplaintReviewState::PendingCompletionValidation, $fresh->review_state);
        $this->assertNull($fresh->completion_return_reason);
        $this->assertNotNull($fresh->completion_submitted_at);
        $this->assertSame(0, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_returned')->count());
        $this->assertSame(0, $this->m6CountFor($this->operatorA, $complaint));
    }

    public function test_n6e_valid_return_still_succeeds_and_emits_exactly_one_m6(): void
    {
        $complaint = $this->makePendingCompletion(['title' => 'QCP_N6E_VALID']);

        $this->actingAs($this->superAdmin)
            ->post($this->returnUrl($complaint), ['reason' => 'Perbaiki narasi penyelesaian Anda.'])
            ->assertRedirect(route('super-admin.completion-validation.index'))
            ->assertSessionHas('success');

        $fresh = $complaint->fresh();
        $this->assertSame(ComplaintStatus::InProgress, $fresh->status);
        $this->assertSame(ComplaintReviewState::CompletionReturnedToOperator, $fresh->review_state);
        $this->assertSame('Perbaiki narasi penyelesaian Anda.', $fresh->completion_return_reason);
        $this->assertNull($fresh->completion_submitted_at);
        $this->assertSame(1, AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.completion_returned')->count());
        $this->assertSame(1, $this->m6CountFor($this->operatorA, $complaint));
    }

    // =========================================================================
    // N6E — N6D-2 FIX: the completion DETAIL page is READ-ONLY for a stale
    // non-actionable row (status != in_progress), while remaining reachable so
    // the M5 deep-link never 404s.
    // =========================================================================

    public function test_n6e_detail_page_is_read_only_for_a_g7_stale_row(): void
    {
        $complaint = $this->makePendingCompletion(['title' => 'QCP_N6E_DETAIL_STALE']);

        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Diselesaikan langsung oleh Super Admin (G7).',
            ])
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint));

        // Reachable (the M5 deep-link must never 404) but NOT actionable.
        $response->assertOk();
        $response->assertSee($complaint->reference_code);
        $response->assertSee('Tidak Lagi Dapat Divalidasi');

        // No active Approve / Return form is exposed.
        $response->assertDontSee(route('super-admin.completion-validation.approve', $complaint));
        $response->assertDontSee(route('super-admin.completion-validation.return', $complaint));
        $response->assertDontSee('Kembalikan ke Operator');
    }

    public function test_n6e_detail_page_remains_actionable_for_the_valid_row(): void
    {
        $complaint = $this->makePendingCompletion();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.completion-validation.show', $complaint));

        $response->assertOk();
        $response->assertSee(route('super-admin.completion-validation.approve', $complaint));
        $response->assertSee(route('super-admin.completion-validation.return', $complaint));
        $response->assertDontSee('Tidak Lagi Dapat Divalidasi');
    }
}
