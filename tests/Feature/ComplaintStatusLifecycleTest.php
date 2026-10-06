<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Prompt 3B — Official Status & Transition Matrix Tests
 *
 * Business rules frozen per Prompt 3B:
 *  - 7 official statuses
 *  - 11 allowed transitions
 *  - submitted → rejected: FORBIDDEN
 *  - waiting_for_information → rejected: FORBIDDEN
 *  - closed = terminal (no transitions)
 *  - No reopen
 *  - rejected: rejection_reason required + public_response required
 *  - resolved: public_response required
 *  - Authority: Operator YES, Super Admin YES, Admin NO, Masyarakat NO
 */
class ComplaintStatusLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private User $superAdmin;
    private User $admin;
    private User $citizen;
    private ComplaintCategory $category;
    private DinasUnit $unit;
    private Complaint $complaint;

    protected function setUp(): void
    {
        parent::setUp();

        // Prompt 15 — the Operator is scoped to a Dinas/Unit. The category used
        // by every complaint is mapped to that unit, so complaints with a NULL
        // destination are visible under the hybrid rule.
        $this->unit = DinasUnit::create([
            'name'       => 'Dinas Uji Lifecycle',
            'code'       => 'UJI-LIFE',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $this->operator = User::factory()->create([
            'role'      => 'operator',
            'is_active' => true,
            'dinas_unit_id' => $this->unit->id,
        ]);

        $this->superAdmin = User::factory()->create([
            'role'      => 'super_admin',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->citizen = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
        ]);

        $this->category = ComplaintCategory::create([
            'name'       => 'Infrastruktur',
            'slug'       => 'infrastruktur',
            'dinas_name' => 'Dinas PUPR',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $this->category->dinasUnits()->attach($this->unit->id);

        $this->complaint = $this->makeComplaint(ComplaintStatus::Submitted);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function makeComplaint(ComplaintStatus $status, ?User $reporter = null): Complaint
    {
        return Complaint::create([
            'reference_code' => 'LPW-TEST-' . uniqid(),
            'reporter_id'    => ($reporter ?? $this->citizen)->id,
            'category_id'    => $this->category->id,
            'status'         => $status,
            'title'          => 'Laporan Test ' . $status->value,
            'description'    => 'Deskripsi test lifecycle.',
            'submitted_at'   => now(),
        ]);
    }

    private function updateStatus(User $user, Complaint $complaint, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->patch(route('operator.complaint.update-status', $complaint), $data);
    }

    // =========================================================================
    // §26.1 — Official Status List
    // =========================================================================

    public function test_complaint_status_enum_has_exactly_7_cases(): void
    {
        $cases = ComplaintStatus::cases();
        $this->assertCount(7, $cases);
    }

    public function test_all_official_status_codes_exist(): void
    {
        $expected = [
            'submitted',
            'under_review',
            'in_progress',
            'waiting_for_information',
            'resolved',
            'rejected',
            'closed',
        ];

        $actual = array_map(fn($c) => $c->value, ComplaintStatus::cases());

        foreach ($expected as $code) {
            $this->assertContains($code, $actual, "Status code '{$code}' missing from enum.");
        }
        $this->assertSame(sort($expected), sort($actual) ?: true);
    }

    public function test_official_labels_are_correct(): void
    {
        $this->assertSame('Diajukan', ComplaintStatus::Submitted->label());
        $this->assertSame('Sedang Ditinjau', ComplaintStatus::UnderReview->label());
        $this->assertSame('Sedang Diproses', ComplaintStatus::InProgress->label());
        $this->assertSame('Menunggu Informasi', ComplaintStatus::WaitingForInformation->label());
        $this->assertSame('Selesai', ComplaintStatus::Resolved->label());
        $this->assertSame('Ditolak', ComplaintStatus::Rejected->label());
        $this->assertSame('Ditutup', ComplaintStatus::Closed->label());
    }

    // =========================================================================
    // §26.2 — Initial Status
    // =========================================================================

    public function test_new_complaint_initial_status_is_submitted(): void
    {
        $this->assertDatabaseHas('complaints', [
            'id'     => $this->complaint->id,
            'status' => 'submitted',
        ]);
    }

    public function test_submitted_status_label_is_diajukan(): void
    {
        $this->complaint->refresh();
        $this->assertSame('Diajukan', $this->complaint->status->label());
    }

    // =========================================================================
    // §26.3 — Allowed Transitions (all 11)
    // =========================================================================

    public function test_transition_submitted_to_under_review(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Submitted);
        $this->assertTrue(ComplaintStatus::Submitted->canTransitionTo(ComplaintStatus::UnderReview));

        $this->updateStatus($this->operator, $complaint, ['status' => 'under_review'])
            ->assertRedirect();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'under_review']);
    }

    public function test_transition_under_review_to_in_progress(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);
        $this->assertTrue(ComplaintStatus::UnderReview->canTransitionTo(ComplaintStatus::InProgress));

        $this->updateStatus($this->operator, $complaint, ['status' => 'in_progress'])
            ->assertRedirect();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'in_progress']);
    }

    public function test_transition_under_review_to_waiting_for_information(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);
        $this->assertTrue(ComplaintStatus::UnderReview->canTransitionTo(ComplaintStatus::WaitingForInformation));

        $this->updateStatus($this->operator, $complaint, ['status' => 'waiting_for_information'])
            ->assertRedirect();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'waiting_for_information']);
    }

    public function test_transition_under_review_to_rejected(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);
        $this->assertTrue(ComplaintStatus::UnderReview->canTransitionTo(ComplaintStatus::Rejected));

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => 'Laporan tidak lengkap dan tidak dapat diverifikasi.',
            'public_response_body' => 'Maaf, laporan Anda tidak dapat kami proses karena informasi yang diberikan tidak lengkap.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'rejected']);
    }

    public function test_transition_in_progress_to_waiting_for_information(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);
        $this->assertTrue(ComplaintStatus::InProgress->canTransitionTo(ComplaintStatus::WaitingForInformation));

        $this->updateStatus($this->operator, $complaint, ['status' => 'waiting_for_information'])
            ->assertRedirect();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'waiting_for_information']);
    }

    public function test_transition_in_progress_to_resolved(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);
        $this->assertTrue(ComplaintStatus::InProgress->canTransitionTo(ComplaintStatus::Resolved));

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'resolved',
            'public_response_body' => 'Masalah telah berhasil ditangani oleh tim terkait.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'resolved']);
    }

    public function test_transition_in_progress_to_rejected(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);
        $this->assertTrue(ComplaintStatus::InProgress->canTransitionTo(ComplaintStatus::Rejected));

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => 'Laporan ternyata bukan dalam yurisdiksi kami.',
            'public_response_body' => 'Maaf, laporan ini berada di luar kewenangan instansi kami.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'rejected']);
    }

    public function test_transition_waiting_for_information_to_in_progress(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::WaitingForInformation);
        $this->assertTrue(ComplaintStatus::WaitingForInformation->canTransitionTo(ComplaintStatus::InProgress));

        $this->updateStatus($this->operator, $complaint, ['status' => 'in_progress'])
            ->assertRedirect();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'in_progress']);
    }

    public function test_transition_waiting_for_information_to_resolved(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::WaitingForInformation);
        $this->assertTrue(ComplaintStatus::WaitingForInformation->canTransitionTo(ComplaintStatus::Resolved));

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'resolved',
            'public_response_body' => 'Informasi yang diterima mencukupi dan masalah telah diselesaikan.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'resolved']);
    }

    public function test_transition_resolved_to_closed(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Resolved);
        $this->assertTrue(ComplaintStatus::Resolved->canTransitionTo(ComplaintStatus::Closed));

        $this->updateStatus($this->operator, $complaint, ['status' => 'closed'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'closed']);
    }

    public function test_transition_rejected_to_closed(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Rejected);
        $this->assertTrue(ComplaintStatus::Rejected->canTransitionTo(ComplaintStatus::Closed));

        $this->updateStatus($this->operator, $complaint, ['status' => 'closed'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'closed']);
    }

    public function test_exactly_11_allowed_transitions_in_enum(): void
    {
        $count = 0;
        foreach (ComplaintStatus::cases() as $status) {
            $count += count($status->allowedTransitions());
        }
        $this->assertSame(11, $count, "Expected exactly 11 allowed transitions, got {$count}.");
    }

    // =========================================================================
    // §26.4 — Forbidden Transitions
    // =========================================================================

    public function test_submitted_to_rejected_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Submitted->canTransitionTo(ComplaintStatus::Rejected));

        $this->updateStatus($this->operator, $this->complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => 'test reason',
            'public_response_body' => 'test response',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'submitted']);
    }

    public function test_waiting_for_information_to_rejected_is_forbidden(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::WaitingForInformation);
        $this->assertFalse(ComplaintStatus::WaitingForInformation->canTransitionTo(ComplaintStatus::Rejected));

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => 'test reason',
            'public_response_body' => 'test response',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'waiting_for_information']);
    }

    public function test_submitted_to_in_progress_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Submitted->canTransitionTo(ComplaintStatus::InProgress));

        $this->updateStatus($this->operator, $this->complaint, ['status' => 'in_progress'])
            ->assertSessionHasErrors('status');
    }

    public function test_submitted_to_resolved_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Submitted->canTransitionTo(ComplaintStatus::Resolved));

        $this->updateStatus($this->operator, $this->complaint, [
            'status'               => 'resolved',
            'public_response_body' => 'test',
        ])->assertSessionHasErrors('status');
    }

    public function test_submitted_to_closed_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Submitted->canTransitionTo(ComplaintStatus::Closed));

        $this->updateStatus($this->operator, $this->complaint, ['status' => 'closed'])
            ->assertSessionHasErrors('status');
    }

    public function test_under_review_to_resolved_is_forbidden(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);
        $this->assertFalse(ComplaintStatus::UnderReview->canTransitionTo(ComplaintStatus::Resolved));

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'resolved',
            'public_response_body' => 'test',
        ])->assertSessionHasErrors('status');
    }

    public function test_under_review_to_closed_is_forbidden(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);
        $this->assertFalse(ComplaintStatus::UnderReview->canTransitionTo(ComplaintStatus::Closed));

        $this->updateStatus($this->operator, $complaint, ['status' => 'closed'])
            ->assertSessionHasErrors('status');
    }

    public function test_in_progress_to_closed_is_forbidden(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);
        $this->assertFalse(ComplaintStatus::InProgress->canTransitionTo(ComplaintStatus::Closed));

        $this->updateStatus($this->operator, $complaint, ['status' => 'closed'])
            ->assertSessionHasErrors('status');
    }

    public function test_arbitrary_string_status_is_rejected(): void
    {
        $this->updateStatus($this->operator, $this->complaint, ['status' => 'fake_invented_status'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'submitted']);
    }

    // =========================================================================
    // §26.5 — Terminal: closed has no transitions out
    // =========================================================================

    public function test_closed_to_submitted_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Closed->canTransitionTo(ComplaintStatus::Submitted));
    }

    public function test_closed_to_under_review_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Closed->canTransitionTo(ComplaintStatus::UnderReview));
    }

    public function test_closed_to_in_progress_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Closed->canTransitionTo(ComplaintStatus::InProgress));
    }

    public function test_closed_to_waiting_for_information_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Closed->canTransitionTo(ComplaintStatus::WaitingForInformation));
    }

    public function test_closed_to_resolved_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Closed->canTransitionTo(ComplaintStatus::Resolved));
    }

    public function test_closed_to_rejected_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Closed->canTransitionTo(ComplaintStatus::Rejected));
    }

    public function test_closed_has_empty_allowed_transitions(): void
    {
        $this->assertEmpty(ComplaintStatus::Closed->allowedTransitions());
    }

    public function test_closed_is_terminal_via_is_terminal_helper(): void
    {
        $this->assertTrue(ComplaintStatus::Closed->isTerminal());
    }

    public function test_non_closed_statuses_are_not_terminal(): void
    {
        foreach (ComplaintStatus::cases() as $status) {
            if ($status !== ComplaintStatus::Closed) {
                $this->assertFalse($status->isTerminal(), "{$status->value} should not be terminal.");
            }
        }
    }

    public function test_cannot_transition_from_closed_via_api(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Closed);
        $this->updateStatus($this->operator, $complaint, ['status' => 'submitted'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'closed']);
    }

    // =========================================================================
    // §26.6 — Reopen tests (all forbidden)
    // =========================================================================

    public function test_resolved_to_in_progress_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Resolved->canTransitionTo(ComplaintStatus::InProgress));
    }

    public function test_resolved_to_waiting_for_information_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Resolved->canTransitionTo(ComplaintStatus::WaitingForInformation));
    }

    public function test_resolved_to_rejected_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Resolved->canTransitionTo(ComplaintStatus::Rejected));
    }

    public function test_resolved_to_under_review_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Resolved->canTransitionTo(ComplaintStatus::UnderReview));
    }

    public function test_resolved_to_submitted_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Resolved->canTransitionTo(ComplaintStatus::Submitted));
    }

    public function test_rejected_to_in_progress_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Rejected->canTransitionTo(ComplaintStatus::InProgress));
    }

    public function test_rejected_to_resolved_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Rejected->canTransitionTo(ComplaintStatus::Resolved));
    }

    public function test_rejected_to_under_review_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Rejected->canTransitionTo(ComplaintStatus::UnderReview));
    }

    public function test_rejected_to_submitted_is_forbidden(): void
    {
        $this->assertFalse(ComplaintStatus::Rejected->canTransitionTo(ComplaintStatus::Submitted));
    }

    public function test_cannot_reopen_resolved_complaint_via_api(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Resolved);
        $this->updateStatus($this->operator, $complaint, ['status' => 'in_progress'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'resolved']);
    }

    public function test_cannot_reopen_rejected_complaint_via_api(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Rejected);
        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'in_progress',
            'public_response_body' => 'attempt',
        ])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'rejected']);
    }

    // =========================================================================
    // §26.7 — Authorization
    // =========================================================================

    public function test_masyarakat_cannot_update_status(): void
    {
        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'submitted']);
    }

    public function test_admin_cannot_update_status(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'submitted']);
    }

    public function test_operator_can_update_status(): void
    {
        $this->updateStatus($this->operator, $this->complaint, ['status' => 'under_review'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'under_review']);
    }

    public function test_super_admin_can_update_status(): void
    {
        $this->updateStatus($this->superAdmin, $this->complaint, ['status' => 'under_review'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'under_review']);
    }

    public function test_super_admin_must_follow_transition_matrix(): void
    {
        // Super Admin cannot bypass lifecycle either
        $this->updateStatus($this->superAdmin, $this->complaint, [
            'status'               => 'resolved',
            'public_response_body' => 'bypass attempt',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'submitted']);
    }

    public function test_guest_cannot_update_status(): void
    {
        $this->patch(route('operator.complaint.update-status', $this->complaint), [
            'status' => 'under_review',
        ])->assertRedirect(); // redirects to login

        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'submitted']);
    }

    // =========================================================================
    // §26.8 — Rejection Validation
    // =========================================================================

    public function test_rejected_without_reason_fails(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => '',
            'public_response_body' => 'Some public response.',
        ])->assertSessionHasErrors('rejection_reason');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'under_review']);
    }

    public function test_rejected_with_whitespace_only_reason_fails(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => '   ',
            'public_response_body' => 'Some public response.',
        ])->assertSessionHasErrors('rejection_reason');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'under_review']);
    }

    public function test_rejected_without_public_response_fails(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => 'Laporan tidak relevan.',
            'public_response_body' => '',
        ])->assertSessionHasErrors('public_response_body');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'under_review']);
    }

    public function test_rejected_with_valid_reason_and_public_response_succeeds(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => 'Laporan tidak memiliki cukup bukti pendukung.',
            'public_response_body' => 'Maaf, laporan Anda ditolak karena bukti yang dilampirkan tidak mencukupi.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'rejected']);
    }

    public function test_rejection_reason_is_stored_in_status_history(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);
        $reason = 'Ini adalah alasan penolakan resmi.';

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => $reason,
            'public_response_body' => 'Respons publik untuk penolakan.',
        ]);

        // History note should contain the rejection reason
        $history = ComplaintStatusHistory::where('complaint_id', $complaint->id)
            ->where('to_status', 'rejected')
            ->first();
        $this->assertNotNull($history);
        $this->assertStringContainsString($reason, $history->note);
    }

    // =========================================================================
    // §26.9 — Public Response Validation
    // =========================================================================

    public function test_resolved_without_public_response_fails(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'resolved',
            'public_response_body' => '',
        ])->assertSessionHasErrors('public_response_body');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'in_progress']);
    }

    public function test_resolved_with_whitespace_only_public_response_fails(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'resolved',
            'public_response_body' => '   ',
        ])->assertSessionHasErrors('public_response_body');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'in_progress']);
    }

    public function test_resolved_with_valid_public_response_succeeds(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);
        $publicResponse = 'Masalah yang Anda laporkan telah berhasil ditangani oleh Dinas PUPR Parepare.';

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'resolved',
            'public_response_body' => $publicResponse,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'resolved']);
    }

    public function test_public_response_is_saved_as_note_when_resolved(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);
        $publicBody = 'Laporan telah diselesaikan dan diverifikasi oleh tim lapangan.';

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'resolved',
            'public_response_body' => $publicBody,
        ]);

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $complaint->id,
            'visibility'   => 'public_response',
            'body'         => $publicBody,
        ]);
    }

    public function test_public_response_is_saved_as_note_when_rejected(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);
        $publicBody = 'Laporan Anda ditolak karena tidak sesuai ketentuan layanan.';

        $this->updateStatus($this->operator, $complaint, [
            'status'               => 'rejected',
            'rejection_reason'     => 'Tidak sesuai ketentuan.',
            'public_response_body' => $publicBody,
        ]);

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $complaint->id,
            'visibility'   => 'public_response',
            'body'         => $publicBody,
        ]);
    }

    public function test_transitions_not_requiring_public_response_do_not_force_it(): void
    {
        // under_review → in_progress: no public response required
        $complaint = $this->makeComplaint(ComplaintStatus::UnderReview);

        $this->updateStatus($this->operator, $complaint, [
            'status' => 'in_progress',
            // no public_response_body
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'in_progress']);
    }

    // =========================================================================
    // §26.10 — Citizen Visibility
    // =========================================================================

    public function test_citizen_can_see_public_response_for_rejected_complaint(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Rejected, $this->citizen);
        $publicBody = 'TANGGAPAN RESMI PENOLAKAN UNTUK PELAPOR';

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::PublicResponse,
            'body'         => $publicBody,
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee($publicBody);
    }

    public function test_citizen_cannot_see_internal_notes(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Submitted, $this->citizen);

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'INTERNAL SECRET NOTE — NOT FOR CITIZEN',
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertDontSee('INTERNAL SECRET NOTE — NOT FOR CITIZEN');
    }

    public function test_citizen_cannot_see_another_citizens_complaint(): void
    {
        $otherCitizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $otherComplaint = $this->makeComplaint(ComplaintStatus::Submitted, $otherCitizen);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $otherComplaint))
            ->assertForbidden();
    }

    public function test_rejection_reason_is_visible_to_citizen_via_public_response(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Rejected, $this->citizen);
        $publicBody = 'Alasan resmi: Laporan tidak memenuhi kriteria pengaduan yang berlaku.';

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::PublicResponse,
            'body'         => $publicBody,
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee($publicBody);
    }

    // =========================================================================
    // §26.11 — IDOR & Security
    // =========================================================================

    public function test_operator_cannot_update_status_of_another_operators_complaint_without_access(): void
    {
        // Prompt 15 — operators are scoped to their Dinas/Unit. This complaint's
        // category is mapped to the operator's unit (NULL destination), so it is
        // VISIBLE under the hybrid rule; the operator still CANNOT bypass the
        // lifecycle. IDOR on complaint_id is blocked for out-of-scope records.
        $otherComplaint = $this->makeComplaint(ComplaintStatus::Submitted);

        $this->updateStatus($this->operator, $otherComplaint, ['status' => 'under_review'])
            ->assertRedirect()
            ->assertSessionHas('success'); // In-scope complaint per Prompt 15 visibility rule

        // But CANNOT bypass lifecycle
        $this->updateStatus($this->operator, $otherComplaint, [
            'status'               => 'resolved',
            'public_response_body' => 'bypass',
        ])->assertSessionHasErrors('status'); // now under_review → resolved is invalid
    }

    public function test_citizen_cannot_reach_operator_status_mutation_endpoint(): void
    {
        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_reach_operator_status_mutation_endpoint(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // §Atomicity — Status + History + Audit
    // =========================================================================

    public function test_status_update_atomically_writes_history_and_audit(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Submitted);

        $this->updateStatus($this->operator, $complaint, ['status' => 'under_review']);

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'under_review']);
        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'from_status'  => 'submitted',
            'to_status'    => 'under_review',
            'changed_by'   => $this->operator->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id'   => $this->operator->id,
            'action'     => 'complaint.status_updated',
            'subject_id' => $complaint->id,
        ]);
    }

    public function test_failed_transition_does_not_create_history_record(): void
    {
        // submitted → resolved is forbidden
        $this->updateStatus($this->operator, $this->complaint, [
            'status'               => 'resolved',
            'public_response_body' => 'attempt',
        ]);

        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $this->complaint->id,
            'to_status'    => 'resolved',
        ]);
    }

    // =========================================================================
    // §Helper methods — requiresPublicResponse / requiresRejectionReason
    // =========================================================================

    public function test_resolved_requires_public_response(): void
    {
        $this->assertTrue(ComplaintStatus::Resolved->requiresPublicResponse());
    }

    public function test_rejected_requires_public_response(): void
    {
        $this->assertTrue(ComplaintStatus::Rejected->requiresPublicResponse());
    }

    public function test_other_statuses_do_not_require_public_response(): void
    {
        $noPublicResponse = [
            ComplaintStatus::Submitted,
            ComplaintStatus::UnderReview,
            ComplaintStatus::InProgress,
            ComplaintStatus::WaitingForInformation,
            ComplaintStatus::Closed,
        ];
        foreach ($noPublicResponse as $status) {
            $this->assertFalse($status->requiresPublicResponse(), "{$status->value} should not require public response.");
        }
    }

    public function test_rejected_requires_rejection_reason(): void
    {
        $this->assertTrue(ComplaintStatus::Rejected->requiresRejectionReason());
    }

    public function test_other_statuses_do_not_require_rejection_reason(): void
    {
        foreach (ComplaintStatus::cases() as $status) {
            if ($status !== ComplaintStatus::Rejected) {
                $this->assertFalse($status->requiresRejectionReason(), "{$status->value} should not require rejection reason.");
            }
        }
    }
}
