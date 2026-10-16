<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PHASE 2R — Operator immutability of routing metadata.
 *
 * FROZEN business rule under test:
 *   An Operator MUST NOT be able to (1) change a complaint's category,
 *   (2) change its Dinas/Unit destination, or (3) assign/reassign an Operator.
 *   These three fields are decided ONLY by the Super Admin initial-validation
 *   surface and are resolved automatically from `complaints.dinas_unit_id`.
 *
 * Enforcement is SERVER-SIDE and STRUCTURAL: the former operator endpoints were
 * REMOVED entirely (no route → 404 for every actor), so there is no code path
 * through which an Operator could mutate `category_id`, `dinas_unit_id` or
 * `assigned_to`. UI controls were removed too, but the guarantee never depends
 * on the UI.
 *
 * Covers §12 of the PHASE 2R remediation prompt:
 *   A. Operator category mutation blocked
 *   B. Operator Dinas mutation blocked
 *   C. Operator assignment mutation blocked
 *   D. UI controls absent
 *   E. Super Admin still works
 *   F. Citizen returned workflow still works
 *   G. Operator legitimate actions still work
 *   H. IDOR (another Dinas' complaint)
 *   I. Historical data preserved
 */
class OperatorImmutabilityPhase2RTest extends TestCase
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

    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin  = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->citizen     = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
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
            'reference_code' => 'LPW-P2R-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => $this->unitA->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji imutabilitas ' . static::$seq,
            'description'    => 'Deskripsi laporan uji imutabilitas operator.',
            'status'         => ComplaintStatus::Submitted,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // STRUCTURAL — the three endpoints no longer exist
    // =========================================================================

    public function test_operator_routing_mutation_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('operator.complaint.update-category'), 'category route must be gone');
        $this->assertFalse(Route::has('operator.complaint.update-destination'), 'destination route must be gone');
        $this->assertFalse(Route::has('operator.complaint.assign'), 'assignment route must be gone');
    }

    public function test_legitimate_operator_routes_still_exist(): void
    {
        $this->assertTrue(Route::has('operator.complaint.index'));
        $this->assertTrue(Route::has('operator.complaint.show'));
        $this->assertTrue(Route::has('operator.complaint.update-location'));
        $this->assertTrue(Route::has('operator.complaint.update-status'));
        $this->assertTrue(Route::has('operator.complaint.add-note'));
    }

    // =========================================================================
    // A. Operator CATEGORY mutation blocked
    // =========================================================================

    public function test_a_operator_cannot_change_category(): void
    {
        $complaint = $this->makeComplaint(['category_id' => $this->categoryA->id]);
        $before = $complaint->category_id;

        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/kategori', ['category_id' => $this->categoryB->id])
            ->assertNotFound();

        $this->assertSame($before, $complaint->fresh()->category_id);
        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => 'complaint', 'subject_id' => $complaint->id, 'action' => 'complaint.category_updated',
        ]);
    }

    public function test_a_operator_category_attempt_does_not_create_false_success_audit(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/kategori', ['category_id' => $this->categoryB->id])
            ->assertNotFound();

        $this->assertSame(
            0,
            AuditLog::where('subject_id', $complaint->id)
                ->whereIn('action', ['complaint.category_updated', 'complaint.category_corrected'])
                ->count()
        );
    }

    // =========================================================================
    // B. Operator DINAS/UNIT mutation blocked
    // =========================================================================

    public function test_b_operator_cannot_change_dinas_unit(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $before = $complaint->dinas_unit_id;

        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/tujuan', ['dinas_unit_id' => $this->unitB->id])
            ->assertNotFound();

        $this->assertSame($before, $complaint->fresh()->dinas_unit_id);
        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => 'complaint', 'subject_id' => $complaint->id, 'action' => 'complaint.destination_updated',
        ]);
    }

    public function test_b_operator_cannot_clear_dinas_unit(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/tujuan', ['dinas_unit_id' => ''])
            ->assertNotFound();

        $this->assertSame($this->unitA->id, $complaint->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // C. Operator ASSIGNMENT mutation blocked
    // =========================================================================

    public function test_c_operator_cannot_assign_operator(): void
    {
        $complaint = $this->makeComplaint(['assigned_to' => null]);

        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/tugaskan', ['assigned_to' => $this->operatorB->id])
            ->assertNotFound();

        $this->assertNull($complaint->fresh()->assigned_to);
        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => 'complaint', 'subject_id' => $complaint->id, 'action' => 'complaint.assigned',
        ]);
    }

    public function test_c_operator_cannot_reassign_or_unassign(): void
    {
        $complaint = $this->makeComplaint(['assigned_to' => $this->operatorA->id]);

        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/tugaskan', ['assigned_to' => $this->operatorB->id])
            ->assertNotFound();

        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/tugaskan', ['assigned_to' => null])
            ->assertNotFound();

        $this->assertSame($this->operatorA->id, $complaint->fresh()->assigned_to);
    }

    // =========================================================================
    // D. UI controls absent
    // =========================================================================

    public function test_d_operator_detail_has_no_routing_mutation_controls(): void
    {
        $complaint = $this->makeComplaint();

        $html = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        // No category / destination / assignment inputs.
        $this->assertStringNotContainsString('name="category_id"', $html);
        $this->assertStringNotContainsString('name="dinas_unit_id"', $html);
        $this->assertStringNotContainsString('name="assigned_to"', $html);

        // No action affordances.
        foreach (['Ubah Kategori', 'Simpan Kategori', 'Simpan Tujuan', 'Tugaskan Operator', 'Simpan Penugasan'] as $label) {
            $this->assertStringNotContainsString($label, $html);
        }

        // No dead links to the removed endpoints.
        $this->assertStringNotContainsString('/kategori"', $html);
        $this->assertStringNotContainsString('/tujuan"', $html);
        $this->assertStringNotContainsString('/tugaskan"', $html);
    }

    public function test_d_operator_detail_shows_routing_info_read_only(): void
    {
        $complaint = $this->makeComplaint();

        $html = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        // Read-only information is present instead of controls.
        $this->assertStringContainsString('Kategori &amp; Tujuan', $html);
        $this->assertStringContainsString('Hanya baca', $html);
        $this->assertStringContainsString('Operator Dinas/Unit', $html);
        $this->assertStringContainsString('Dinas PUPR', $html);
        $this->assertStringContainsString($this->operatorA->name, $html);
    }

    // =========================================================================
    // E. Super Admin still works (category correction + Dinas routing)
    // =========================================================================

    public function test_e_super_admin_can_correct_category_and_route(): void
    {
        $complaint = $this->makeComplaint([
            'category_id'   => $this->categoryA->id,
            'dinas_unit_id' => null,
            'review_state'  => ComplaintReviewState::PendingInitialValidation,
        ]);

        $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryB->id, 'dinas_unit_id' => $this->unitB->id]
        )->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame($this->categoryB->id, $fresh->category_id);
        $this->assertSame($this->unitB->id, $fresh->dinas_unit_id);
        $this->assertSame(ComplaintReviewState::ValidatedForOperator, $fresh->review_state);

        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.category_corrected', 'subject_id' => $complaint->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.dinas_assigned', 'subject_id' => $complaint->id]);
    }

    public function test_e_super_admin_validation_queue_still_reachable(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.validation.index'))
            ->assertOk();
    }

    public function test_e_automatic_operator_resolution_still_works(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => null,
            'review_state'  => ComplaintReviewState::PendingInitialValidation,
        ]);

        $this->actingAs($this->superAdmin)->post(
            route('super-admin.validation.approve', $complaint),
            ['category_id' => $this->categoryA->id, 'dinas_unit_id' => $this->unitA->id]
        )->assertRedirect();

        // Resolved dynamically from the Dinas/Unit — never via assigned_to.
        $fresh = $complaint->fresh();
        $this->assertSame($this->operatorA->id, $fresh->resolvedOperator()?->id);
        $this->assertNull($fresh->assigned_to);
    }

    // =========================================================================
    // F. Citizen returned workflow still works
    // =========================================================================

    public function test_f_citizen_can_correct_and_resubmit_returned_complaint(): void
    {
        $complaint = $this->makeComplaint([
            'category_id'          => $this->categoryA->id,
            'review_state'         => ComplaintReviewState::ReturnedToCitizen,
            'review_return_reason' => 'Perbaiki kategori dan lokasi.',
        ]);

        // The reporter CAN change the category while it is returned to them.
        $this->actingAs($this->citizen)->put(route('citizen.complaint.update', $complaint), [
            'category_id'   => $this->categoryB->id,
            'title'         => 'Judul yang sudah diperbaiki',
            'description'   => 'Deskripsi laporan yang sudah diperbaiki oleh pelapor.',
            'kecamatan_id'  => $this->kecamatanId,
            'kelurahan_id'  => $this->kelurahanId,
        ])->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame($this->categoryB->id, $fresh->category_id);
        $this->assertSame(ComplaintReviewState::PendingInitialValidation, $fresh->review_state);
        $this->assertSame(ComplaintStatus::Submitted, $fresh->status);
    }

    public function test_f_citizen_still_cannot_set_routing_on_resubmission(): void
    {
        $complaint = $this->makeComplaint(['review_state' => ComplaintReviewState::ReturnedToCitizen]);

        $this->actingAs($this->citizen)->put(route('citizen.complaint.update', $complaint), [
            'category_id'   => $this->categoryA->id,
            'title'         => 'Judul yang sudah diperbaiki',
            'description'   => 'Deskripsi laporan yang sudah diperbaiki oleh pelapor.',
            'kecamatan_id'  => $this->kecamatanId,
            'kelurahan_id'  => $this->kelurahanId,
            // Tampering attempts — ignored (not citizen-owned).
            'dinas_unit_id' => $this->unitB->id,
            'assigned_to'   => $this->operatorB->id,
        ])->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id); // unchanged
        $this->assertNull($fresh->assigned_to);
    }

    // =========================================================================
    // G. Operator legitimate actions still work
    // =========================================================================

    public function test_g_operator_can_view_permitted_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk();
    }

    public function test_g_operator_can_add_internal_note(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->post(route('operator.complaint.add-note', $complaint), [
            'body' => 'Catatan internal operator.', 'visibility' => 'internal',
        ])->assertRedirect(route('operator.complaint.show', $complaint));

        $this->assertDatabaseHas('complaint_notes', ['complaint_id' => $complaint->id, 'visibility' => 'internal']);
    }

    public function test_g_operator_can_provide_public_response(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)->post(route('operator.complaint.add-note', $complaint), [
            'body' => 'Respons publik untuk pelapor.', 'visibility' => 'public_response',
        ])->assertRedirect(route('operator.complaint.show', $complaint));

        $this->assertDatabaseHas('complaint_notes', ['complaint_id' => $complaint->id, 'visibility' => 'public_response']);
    }

    public function test_g_operator_can_update_status(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Submitted]);

        $this->actingAs($this->operatorA)->patch(route('operator.complaint.update-status', $complaint), [
            'status' => ComplaintStatus::InProgress->value,
        ])->assertRedirect(route('operator.complaint.show', $complaint));

        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_g_operator_can_edit_geographic_location(): void
    {
        $complaint = $this->makeComplaint();
        [$kecamatanId, $kelurahanId] = $this->makeLocationPair('Kecamatan Baru', 'Kelurahan Baru');

        $this->actingAs($this->operatorA)->patch(route('operator.complaint.update-location', $complaint), [
            'kecamatan_id' => $kecamatanId,
            'kelurahan_id' => $kelurahanId,
        ])->assertRedirect(route('operator.complaint.show', $complaint));

        $fresh = $complaint->fresh();
        $this->assertSame($kecamatanId, $fresh->kecamatan_id);
        $this->assertSame($kelurahanId, $fresh->kelurahan_id);
        // Editing the geographic location NEVER changes the Dinas/Unit destination.
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
    }

    // =========================================================================
    // H. IDOR — another Dinas' complaint
    // =========================================================================

    public function test_h_operator_cannot_mutate_another_dinas_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'category_id' => $this->categoryB->id]);

        // The mutation routes are gone (404 for everyone) — never reachable.
        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/kategori', ['category_id' => $this->categoryA->id])
            ->assertNotFound();
        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/tujuan', ['dinas_unit_id' => $this->unitA->id])
            ->assertNotFound();
        $this->actingAs($this->operatorA)
            ->patch('/operator/laporan/' . $complaint->id . '/tugaskan', ['assigned_to' => $this->operatorA->id])
            ->assertNotFound();

        $fresh = $complaint->fresh();
        $this->assertSame($this->categoryB->id, $fresh->category_id);
        $this->assertSame($this->unitB->id, $fresh->dinas_unit_id);
        $this->assertNull($fresh->assigned_to);
    }

    public function test_h_operator_cannot_reach_another_dinas_complaint_detail(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'category_id' => $this->categoryB->id]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertNotFound();
    }

    public function test_h_operator_cannot_act_on_another_dinas_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'category_id' => $this->categoryB->id]);

        // Legitimate actions are still scoped server-side (404, not 403).
        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-status', $complaint), ['status' => ComplaintStatus::UnderReview->value])
            ->assertNotFound();
        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId, 'kelurahan_id' => $this->kelurahanId,
            ])
            ->assertNotFound();
        $this->actingAs($this->operatorA)
            ->post(route('operator.complaint.add-note', $complaint), ['body' => 'IDOR', 'visibility' => 'internal'])
            ->assertNotFound();

        $this->assertSame(ComplaintStatus::Submitted, $complaint->fresh()->status);
        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)->count());
    }

    // =========================================================================
    // I. Historical data preserved
    // =========================================================================

    public function test_i_historical_assigned_to_and_dinas_unit_are_untouched(): void
    {
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->unitA->id,
            'assigned_to'   => $this->operatorA->id,
        ]);

        // Reading the detail page must not mutate historical values.
        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk();

        $fresh = $complaint->fresh();
        $this->assertSame($this->unitA->id, $fresh->dinas_unit_id);
        $this->assertSame($this->operatorA->id, $fresh->assigned_to);
    }

    public function test_i_legacy_complaint_with_null_review_state_remains_operator_visible(): void
    {
        $legacy = $this->makeComplaint([
            'review_state'  => null,
            'dinas_unit_id' => $this->unitA->id,
            'title'         => 'LEGACY_P2R_VISIBLE',
        ]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $legacy))
            ->assertOk()
            ->assertSee('LEGACY_P2R_VISIBLE');
    }

    public function test_i_remediation_does_not_bulk_modify_existing_complaints(): void
    {
        // A spread of historical shapes must survive the remediation untouched.
        $a = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id, 'assigned_to' => $this->operatorA->id]);
        $b = $this->makeComplaint(['dinas_unit_id' => null, 'assigned_to' => null]);
        $c = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'assigned_to' => $this->operatorB->id, 'category_id' => $this->categoryB->id]);

        $snapshot = Complaint::orderBy('id')->get(['id', 'category_id', 'dinas_unit_id', 'assigned_to'])->toArray();

        // Exercise the operator surface (read-only) then re-check.
        $this->actingAs($this->operatorA)->get(route('operator.complaint.index'))->assertOk();
        $this->actingAs($this->operatorA)->get(route('operator.complaint.show', $a))->assertOk();

        $this->assertSame($snapshot, Complaint::orderBy('id')->get(['id', 'category_id', 'dinas_unit_id', 'assigned_to'])->toArray());

        $this->assertSame($this->unitA->id, $a->fresh()->dinas_unit_id);
        $this->assertSame($this->operatorA->id, $a->fresh()->assigned_to);
        $this->assertNull($b->fresh()->dinas_unit_id);
        $this->assertSame($this->unitB->id, $c->fresh()->dinas_unit_id);
        $this->assertSame($this->operatorB->id, $c->fresh()->assigned_to);
    }
}
