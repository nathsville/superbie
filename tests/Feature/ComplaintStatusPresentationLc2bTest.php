<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — LC-2B — UI, FILTER, TIMELINE, LEGACY STATUS.
 *
 * Regression suite for the LC-2B surface changes. It asserts the CONTRACT of the
 * ACTIVE lifecycle (4 active statuses / 3 transitions) as presented to users:
 *
 *   - TUGAS B — labels/badges stay readable for active AND legacy statuses;
 *   - TUGAS C — list filters separate the active workflow from the legacy
 *     (history) group, while legacy rows remain FINDABLE;
 *   - TUGAS D — status history / timeline renders legacy values (and unknown
 *     values) without a fatal error;
 *   - TUGAS E — the only transition controls offered are the allowed ones; no
 *     manual close; legacy / final-outcome statuses offer no transition; and the
 *     server-side guard remains the authority.
 *
 * The LC-2A enum/matrix and the frozen business decisions are NOT re-tested here
 * (see ComplaintStatusLifecycleTest / ComplaintClosureTest). These tests exercise
 * the PRESENTATION layer only.
 */
class ComplaintStatusPresentationLc2bTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private User $superAdmin;
    private User $admin;
    private User $citizen;

    private ComplaintCategory $category;
    private DinasUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR-LC2B', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan-lc2b',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->category->dinasUnits()->attach($this->unit->id);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
    }

    private static int $seq = 0;

    /**
     * A complaint visible to the Operator (routed to its Dinas/Unit and validated).
     */
    private function makeComplaint(ComplaintStatus $status, array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-LC2B-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan LC-2B ' . static::$seq,
            'description'    => 'Deskripsi laporan uji LC-2B.',
            'status'         => $status,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // A. RENDERING STATUS — active + legacy labels/badges
    // =========================================================================

    public function test_active_statuses_render_with_correct_labels_on_operator_detail(): void
    {
        $expectations = [
            [ComplaintStatus::Submitted, 'Diajukan'],
            [ComplaintStatus::InProgress, 'Sedang Diproses'],
            [ComplaintStatus::Resolved, 'Selesai'],
            [ComplaintStatus::Rejected, 'Ditolak'],
        ];

        foreach ($expectations as [$status, $label]) {
            $complaint = $this->makeComplaint($status);

            $this->actingAs($this->operator)
                ->get(route('operator.complaint.show', $complaint))
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_legacy_statuses_render_with_readable_labels_without_exception(): void
    {
        $expectations = [
            [ComplaintStatus::UnderReview, 'Sedang Ditinjau'],
            [ComplaintStatus::WaitingForInformation, 'Menunggu Informasi'],
            [ComplaintStatus::Closed, 'Ditutup'],
        ];

        foreach ($expectations as [$status, $label]) {
            $complaint = $this->makeComplaint($status);

            $this->actingAs($this->operator)
                ->get(route('operator.complaint.show', $complaint))
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_legacy_complaint_detail_is_openable_by_super_admin(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Closed);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('Ditutup');
    }

    public function test_status_history_renders_legacy_transitions_in_order(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Closed);

        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id, 'from_status' => null, 'to_status' => 'submitted',
            'changed_by' => $this->citizen->id, 'note' => 'Laporan dibuat.', 'created_at' => now()->subDays(2),
        ]);
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id, 'from_status' => 'submitted', 'to_status' => 'under_review',
            'changed_by' => $this->operator->id, 'note' => 'Ditinjau operator.', 'created_at' => now()->subDay(),
        ]);
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id, 'from_status' => 'under_review', 'to_status' => 'closed',
            'changed_by' => $this->operator->id, 'note' => 'Ditutup manual.', 'created_at' => now(),
        ]);

        $html = $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        // Legacy transition labels are rendered readably in the timeline.
        $this->assertStringContainsString('Sedang Ditinjau', $html);
        $this->assertStringContainsString('Ditutup', $html);
        $this->assertStringContainsString('Ditinjau operator.', $html);
    }

    public function test_unknown_history_status_value_is_rendered_safely(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);

        // A value the formatter does not know about (defensive: never a fatal).
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id, 'from_status' => 'submitted', 'to_status' => 'legacy_unknown_value',
            'changed_by' => $this->operator->id, 'note' => 'Nilai status tak dikenal.', 'created_at' => now(),
        ]);

        $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('legacy_unknown_value');
    }

    // =========================================================================
    // B. FILTER — active workflow only, listed directly (no group header)
    // =========================================================================

    /**
     * The filter dropdown offers ONLY the four active workflow statuses, listed
     * DIRECTLY under "Semua Status" with no group header. The former
     * "Workflow Aktif" / "Legacy (Histori)" optgroups were removed from the UI;
     * the legacy values remain accepted by the backend via the `status` query
     * string (see the legacy-filter test below).
     */
    public function test_operator_filter_offers_only_active_workflow_statuses(): void
    {
        $html = $this->actingAs($this->operator)
            ->get(route('operator.complaint.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Workflow Aktif', $html);
        $this->assertStringNotContainsString('Legacy (Histori)', $html);
        $this->assertStringNotContainsString('<optgroup', $html);

        // The four active workflow statuses are the only options offered.
        foreach (array_merge(ComplaintStatus::activeCases(), ComplaintStatus::finalOutcomeCases()) as $status) {
            $this->assertStringContainsString('value="' . $status->value . '"', $html);
        }

        // The history-only statuses are no longer presented as filter options.
        foreach (ComplaintStatus::legacyCases() as $status) {
            $this->assertStringNotContainsString('value="' . $status->value . '"', $html);
        }
    }

    public function test_super_admin_filter_offers_only_active_workflow_statuses(): void
    {
        $html = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Workflow Aktif', $html);
        $this->assertStringNotContainsString('Legacy (Histori)', $html);
        $this->assertStringNotContainsString('<optgroup', $html);

        foreach (array_merge(ComplaintStatus::activeCases(), ComplaintStatus::finalOutcomeCases()) as $status) {
            $this->assertStringContainsString('value="' . $status->value . '"', $html);
        }

        foreach (ComplaintStatus::legacyCases() as $status) {
            $this->assertStringNotContainsString('value="' . $status->value . '"', $html);
        }
    }

    public function test_admin_filter_offers_only_active_workflow_statuses(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.complaints'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Workflow Aktif', $html);
        $this->assertStringNotContainsString('Legacy (Histori)', $html);
        $this->assertStringNotContainsString('<optgroup', $html);

        foreach (array_merge(ComplaintStatus::activeCases(), ComplaintStatus::finalOutcomeCases()) as $status) {
            $this->assertStringContainsString('value="' . $status->value . '"', $html);
        }

        foreach (ComplaintStatus::legacyCases() as $status) {
            $this->assertStringNotContainsString('value="' . $status->value . '"', $html);
        }
    }

    public function test_operator_can_filter_by_legacy_status_value(): void
    {
        $closed = $this->makeComplaint(ComplaintStatus::Closed);
        $this->makeComplaint(ComplaintStatus::Submitted);

        $response = $this->actingAs($this->operator)
            ->get(route('operator.complaint.index', ['status' => 'closed']))
            ->assertOk();

        $response->assertSee($closed->reference_code);
    }

    public function test_operator_filter_ignores_unknown_status_value(): void
    {
        $this->makeComplaint(ComplaintStatus::Submitted);

        // An unknown value must be ignored (allowlist), never injected.
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.index', ['status' => 'not_a_status']))
            ->assertOk();
    }

    public function test_malformed_array_status_filter_is_handled_on_all_surfaces(): void
    {
        // A crafted `?status[]=x` must NEVER reach the enum constructor. All three
        // list surfaces must degrade gracefully (200), not 500.
        $this->makeComplaint(ComplaintStatus::Submitted);

        $this->actingAs($this->operator)
            ->get(route('operator.complaint.index', ['status' => ['bad']]))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['status' => ['bad']]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.complaints', ['status' => ['bad']]))
            ->assertOk();
    }

    public function test_super_admin_can_filter_by_legacy_status_value(): void
    {
        $closed = $this->makeComplaint(ComplaintStatus::Closed);
        $this->makeComplaint(ComplaintStatus::Submitted);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['status' => 'closed']))
            ->assertOk()
            ->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $closed->id);
    }

    public function test_operator_filter_still_respects_dinas_scope(): void
    {
        // A complaint routed to ANOTHER Dinas/Unit must never surface for this
        // Operator, even when the status filter value matches.
        $otherUnit = DinasUnit::create([
            'name' => 'Dinas Lain', 'code' => 'LAIN-LC2B', 'is_active' => true, 'sort_order' => 2,
        ]);
        $foreign = $this->makeComplaint(ComplaintStatus::Submitted, ['dinas_unit_id' => $otherUnit->id]);
        $own     = $this->makeComplaint(ComplaintStatus::Submitted);

        $response = $this->actingAs($this->operator)
            ->get(route('operator.complaint.index', ['status' => 'submitted']))
            ->assertOk();

        $response->assertSee($own->reference_code);
        $response->assertDontSee($foreign->reference_code);
    }

    // =========================================================================
    // C. TRANSITION CONTROL — only allowed transitions are offered
    // =========================================================================

    public function test_submitted_offers_only_in_progress_transition(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::Submitted);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Simpan Status', $html);
        $this->assertStringContainsString('value="in_progress"', $html);
        // No other transition target is offered from `submitted`.
        $this->assertStringNotContainsString('value="under_review"', $html);
        $this->assertStringNotContainsString('value="waiting_for_information"', $html);
        $this->assertStringNotContainsString('value="closed"', $html);
    }

    public function test_in_progress_offers_only_final_outcomes_to_super_admin(): void
    {
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="resolved"', $html);
        $this->assertStringContainsString('value="rejected"', $html);
        $this->assertStringNotContainsString('value="closed"', $html);
        $this->assertStringNotContainsString('value="under_review"', $html);
    }

    public function test_final_outcome_statuses_offer_no_transition(): void
    {
        foreach ([ComplaintStatus::Resolved, ComplaintStatus::Rejected] as $status) {
            $complaint = $this->makeComplaint($status);

            $html = $this->actingAs($this->superAdmin)
                ->get(route('operator.complaint.show', $complaint))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('Simpan Status', $html);
            $this->assertStringNotContainsString('name="status"', $html);
        }
    }

    public function test_legacy_statuses_offer_no_transition_control(): void
    {
        foreach (ComplaintStatus::legacyCases() as $status) {
            $complaint = $this->makeComplaint($status);

            $html = $this->actingAs($this->operator)
                ->get(route('operator.complaint.show', $complaint))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('Simpan Status', $html);
            $this->assertStringNotContainsString('Tutup Laporan', $html);
            $this->assertStringNotContainsString('name="status"', $html);
        }
    }

    public function test_no_manual_close_action_exists_in_any_view(): void
    {
        // `resolved` is the previous close entry-point; the manual-close form must
        // never be rendered again for any actor.
        $complaint = $this->makeComplaint(ComplaintStatus::Resolved);

        $html = $this->actingAs($this->superAdmin)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tutup Laporan', $html);
        $this->assertStringNotContainsString('name="status" value="closed"', $html);
    }

    public function test_server_side_guard_rejects_illegal_transition_even_when_posted_directly(): void
    {
        // `submitted → resolved` is NOT in the active matrix. Hiding the option is
        // convenience only — the server must reject the direct request.
        $complaint = $this->makeComplaint(ComplaintStatus::Submitted);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Upaya menyelesaikan tanpa transisi yang sah.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Submitted, $complaint->fresh()->status);
    }

    public function test_server_side_guard_rejects_transition_into_legacy_status(): void
    {
        // `in_progress → under_review` (legacy) is forbidden. The server must
        // reject it even though the value is a valid enum case.
        $complaint = $this->makeComplaint(ComplaintStatus::InProgress);

        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status' => 'under_review',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_server_side_guard_rejects_transition_out_of_legacy_status(): void
    {
        // `closed` has no outgoing transition. A direct request must be rejected.
        $complaint = $this->makeComplaint(ComplaintStatus::Closed);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status' => 'in_progress',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Closed, $complaint->fresh()->status);
    }

    // =========================================================================
    // D. NON-STATUS ACTIONS remain available on legacy statuses
    // =========================================================================

    public function test_internal_note_and_public_response_remain_available_on_legacy(): void
    {
        foreach (ComplaintStatus::legacyCases() as $status) {
            $complaint = $this->makeComplaint($status);

            // The forms are still rendered in the detail view for a legacy row.
            $html = $this->actingAs($this->operator)
                ->get(route('operator.complaint.show', $complaint))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Tambah Catatan Internal', $html);
            $this->assertStringContainsString('Tambah Respons Publik', $html);

            // And the endpoints still accept the non-status mutations.
            $this->actingAs($this->operator)
                ->post(route('operator.complaint.add-note', $complaint), [
                    'body' => 'Catatan internal pada status legacy.', 'visibility' => 'internal',
                ])
                ->assertSessionHas('success');
        }
    }

    // =========================================================================
    // E. REGRESSION — the enum helpers remain the single source of truth
    // =========================================================================

    public function test_active_filter_group_is_exactly_the_four_active_statuses(): void
    {
        // The active workflow = the two active lifecycle statuses + the two final
        // outcomes (the four user-facing "active" statuses). Legacy is excluded.
        $active = array_merge(ComplaintStatus::activeCases(), ComplaintStatus::finalOutcomeCases());

        $this->assertCount(4, $active);
        foreach (ComplaintStatus::legacyCases() as $legacy) {
            $this->assertNotContains($legacy, $active);
        }
    }
}
