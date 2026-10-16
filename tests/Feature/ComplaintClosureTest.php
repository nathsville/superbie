<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintRating;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\ComplaintRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — LC-2 — LEGACY `closed` STATUS + RETIRED MANUAL CLOSE.
 *
 * LC-1/LC-1-R/LC-1-R2 (D4): the MANUAL CLOSE feature is RETIRED. `resolved` and
 * `rejected` are final business outcomes with NO outgoing transition, so
 * `resolved → closed` and `rejected → closed` are now FORBIDDEN for every actor
 * (Operator and Super Admin alike). The `closed` enum case is RETAINED for
 * history only: historical `closed` rows stay readable and are NEVER converted
 * or rewritten.
 *
 * Preserved from D-3.4: a legacy `closed` complaint is locked for STATUS
 * TRANSITION only — NOT for every mutation. Internal note / public response /
 * location update remain allowed; category / Dinas-Unit / assignment /
 * completion / reopen remain blocked.
 */
class ComplaintClosureTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $operatorA;
    private User $operatorB;
    private User $admin;
    private User $citizenOwner;
    private User $citizenOther;

    private ComplaintCategory $categoryA;
    private ComplaintCategory $categoryB;
    private DinasUnit $unitA;
    private DinasUnit $unitB;

    private int $kecamatanId;
    private int $kelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin   = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin        = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizenOwner = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->citizenOther = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

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
     * A `resolved` complaint owned by unit A / category A by default.
     */
    private function makeComplaint(array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-P3C-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $this->citizenOwner->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => $this->unitA->id,
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'title'          => 'Laporan uji penutupan ' . static::$seq,
            'description'    => 'Deskripsi laporan uji penutupan / finalisasi.',
            'status'         => ComplaintStatus::Resolved,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'resolved_at'    => now(),
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function closeUrl(Complaint $complaint): string
    {
        return route('operator.complaint.update-status', $complaint);
    }

    private function closeStatusHistoryCount(Complaint $complaint): int
    {
        return ComplaintStatusHistory::where('complaint_id', $complaint->id)
            ->where('to_status', 'closed')
            ->count();
    }

    private function closeAuditCount(Complaint $complaint): int
    {
        return AuditLog::where('subject_id', $complaint->id)
            ->where('action', 'complaint.status_updated')
            ->where('metadata->to_status', 'closed')
            ->count();
    }

    // =========================================================================
    // 1. MANUAL CLOSE IS RETIRED (LC-2 / D4) — all actors
    // =========================================================================

    public function test_operator_cannot_close_a_resolved_complaint(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
        $this->assertSame(0, $this->closeStatusHistoryCount($complaint));
        $this->assertSame(0, $this->closeAuditCount($complaint));
    }

    public function test_super_admin_cannot_close_a_resolved_complaint_either(): void
    {
        // Complaint belongs to unit B — Super Admin still has global scope, but
        // the matrix itself now forbids the close edge for EVERY actor.
        $complaint = $this->makeComplaint([
            'category_id'   => $this->categoryB->id,
            'dinas_unit_id' => $this->unitB->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
        $this->assertSame(0, $this->closeStatusHistoryCount($complaint));
    }

    public function test_rejected_cannot_be_closed(): void
    {
        // LC-2 — `rejected` is a final business outcome; the close edge is gone.
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Rejected, 'resolved_at' => null]);

        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Rejected, $complaint->fresh()->status);
        $this->assertSame(0, $this->closeStatusHistoryCount($complaint));
    }

    public function test_close_does_not_create_a_public_response_note(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed']);

        // `closed` does NOT require (nor create) a public response — and no
        // close transition happened at all.
        $this->assertSame(0, ComplaintNote::where('complaint_id', $complaint->id)
            ->where('visibility', 'public_response')->count());
    }

    public function test_no_auto_close_route_or_scheduler_touches_resolved(): void
    {
        $complaint = $this->makeComplaint();

        // Manual-only (and now retired): without an authorized actor nothing
        // moves the complaint.
        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);

        // There is no dedicated close endpoint invented for this part.
        $this->assertFalse(Route::has('operator.complaint.close'));
        $this->assertFalse(Route::has('super-admin.complaint.close'));
    }

    // =========================================================================
    // 2. FORBIDDEN ACTORS (authorization unchanged)
    // =========================================================================

    public function test_admin_cannot_close(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertForbidden();

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
    }

    public function test_citizen_cannot_close(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizenOwner)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertForbidden();

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
    }

    // =========================================================================
    // 3. NO STATUS MAY BE CLOSED (close edge fully retired)
    // =========================================================================

    public function test_no_status_can_transition_to_closed(): void
    {
        foreach (ComplaintStatus::cases() as $status) {
            $this->assertFalse(
                $status->canTransitionTo(ComplaintStatus::Closed),
                "Status {$status->value} must not be able to transition to closed.",
            );

            $complaint = $this->makeComplaint(['status' => $status, 'resolved_at' => null]);

            $this->actingAs($this->operatorA)
                ->patch($this->closeUrl($complaint), ['status' => 'closed'])
                ->assertSessionHasErrors('status');

            $this->assertSame($status, $complaint->fresh()->status, "Status {$status->value} must not be closable.");
            $this->assertSame(0, $this->closeStatusHistoryCount($complaint));
        }
    }

    public function test_closed_is_legacy_and_cannot_transition_anywhere(): void
    {
        $this->assertEmpty(ComplaintStatus::Closed->allowedTransitions());
        $this->assertTrue(ComplaintStatus::Closed->isLegacy());
        $this->assertFalse(ComplaintStatus::Closed->isTerminal());

        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        foreach (ComplaintStatus::cases() as $target) {
            $this->actingAs($this->operatorA)
                ->patch($this->closeUrl($complaint), ['status' => $target->value])
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(ComplaintStatus::Closed, $complaint->fresh()->status);
    }

    // =========================================================================
    // 4. RATING IS UNAFFECTED BY THE RETIRED CLOSE (D-3.3)
    // =========================================================================

    public function test_rating_state_does_not_enable_a_close(): void
    {
        // A NULL rating never enabled closing before — and closing is now gone.
        $complaint = $this->makeComplaint();
        $this->assertFalse($complaint->rating()->exists());

        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_existing_rating_is_preserved_when_a_close_is_attempted(): void
    {
        $complaint = $this->makeComplaint();
        ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 4,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
        $this->assertDatabaseHas('complaint_ratings', ['complaint_id' => $complaint->id, 'rating' => 4]);
    }

    // =========================================================================
    // 5. CITIZEN AFTER `closed` (historical read-only)
    // =========================================================================

    public function test_citizen_can_still_read_a_closed_complaint(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Ditutup');
    }

    public function test_citizen_cannot_submit_a_new_rating_after_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $this->actingAs($this->citizenOwner)
            ->post(route('citizen.rating.store', $complaint), ['rating' => 5])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseMissing('complaint_ratings', ['complaint_id' => $complaint->id]);
    }

    public function test_citizen_can_still_read_an_existing_rating_after_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);
        ComplaintRating::create([
            'complaint_id' => $complaint->id,
            'user_id'      => $this->citizenOwner->id,
            'rating'       => 3,
        ]);

        $this->actingAs($this->citizenOwner)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Rating Anda')
            ->assertSee('★★★☆☆')
            ->assertDontSee('Berikan Rating Kepuasan');
    }

    // =========================================================================
    // 6. STAFF NON-STATUS MUTATION AFTER `closed` (D-3.4 — PRESERVED)
    // =========================================================================

    public function test_internal_note_is_allowed_after_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $this->actingAs($this->operatorA)
            ->post(route('operator.complaint.add-note', $complaint), [
                'body' => 'Catatan internal setelah penutupan.', 'visibility' => 'internal',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $complaint->id, 'visibility' => 'internal',
        ]);
    }

    public function test_public_response_is_allowed_after_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $this->actingAs($this->operatorA)
            ->post(route('operator.complaint.add-note', $complaint), [
                'body' => 'Respons publik setelah penutupan.', 'visibility' => 'public_response',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $complaint->id, 'visibility' => 'public_response',
        ]);
    }

    public function test_location_update_is_allowed_after_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);
        [$newKecamatan, $newKelurahan] = $this->makeLocationPair('Kecamatan Baru', 'Kelurahan Baru');

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $newKecamatan, 'kelurahan_id' => $newKelurahan,
            ])
            ->assertSessionHas('success');

        $this->assertSame($newKecamatan, $complaint->fresh()->kecamatan_id);
    }

    public function test_completion_submission_is_denied_after_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.submit-completion', $complaint))
            ->assertSessionHasErrors('completion');

        $this->assertNull($complaint->fresh()->completion_submitted_at);
    }

    public function test_routing_metadata_mutation_paths_remain_absent(): void
    {
        // D-3.4: category / Dinas-Unit / assignment stay immutable (no code path).
        $this->assertFalse(Route::has('operator.complaint.update-category'));
        $this->assertFalse(Route::has('operator.complaint.update-destination'));
        $this->assertFalse(Route::has('operator.complaint.assign'));

        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);
        $before = $complaint->only(['category_id', 'dinas_unit_id', 'assigned_to']);

        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed']);

        $this->assertSame($before, $complaint->fresh()->only(['category_id', 'dinas_unit_id', 'assigned_to']));
    }

    // =========================================================================
    // 7. LEGACY NON-STATUS MUTATION ON `under_review` / `waiting_for_information`
    //    (LC-1-R2 — same non-status permission as `closed`)
    // =========================================================================

    /**
     * The non-status mutation permissions that survive on `closed` must also
     * apply to the OTHER two legacy statuses (under_review / waiting_for_information).
     */
    private function legacyStatusValues(): array
    {
        return ['under_review', 'waiting_for_information'];
    }

    public function test_internal_note_is_allowed_on_legacy_status(): void
    {
        foreach ($this->legacyStatusValues() as $statusValue) {
            $complaint = $this->makeComplaint(['status' => ComplaintStatus::from($statusValue)]);

            $this->actingAs($this->operatorA)
                ->post(route('operator.complaint.add-note', $complaint), [
                    'body' => 'Catatan internal pada status legacy.', 'visibility' => 'internal',
                ])
                ->assertSessionHas('success');

            $this->assertDatabaseHas('complaint_notes', [
                'complaint_id' => $complaint->id, 'visibility' => 'internal',
            ]);
        }
    }

    public function test_public_response_is_allowed_on_legacy_status(): void
    {
        foreach ($this->legacyStatusValues() as $statusValue) {
            $complaint = $this->makeComplaint(['status' => ComplaintStatus::from($statusValue)]);

            $this->actingAs($this->operatorA)
                ->post(route('operator.complaint.add-note', $complaint), [
                    'body' => 'Respons publik pada status legacy.', 'visibility' => 'public_response',
                ])
                ->assertSessionHas('success');

            $this->assertDatabaseHas('complaint_notes', [
                'complaint_id' => $complaint->id, 'visibility' => 'public_response',
            ]);
        }
    }

    public function test_location_update_is_allowed_on_legacy_status(): void
    {
        foreach ($this->legacyStatusValues() as $statusValue) {
            $complaint = $this->makeComplaint(['status' => ComplaintStatus::from($statusValue)]);
            [$newKecamatan, $newKelurahan] = $this->makeLocationPair(
                'Kecamatan Legacy ' . $statusValue,
                'Kelurahan Legacy ' . $statusValue,
            );

            $this->actingAs($this->operatorA)
                ->patch(route('operator.complaint.update-location', $complaint), [
                    'kecamatan_id' => $newKecamatan, 'kelurahan_id' => $newKelurahan,
                ])
                ->assertSessionHas('success');

            $this->assertSame($newKecamatan, $complaint->fresh()->kecamatan_id);
        }
    }

    public function test_status_transition_is_still_blocked_on_legacy_status(): void
    {
        foreach ($this->legacyStatusValues() as $statusValue) {
            $complaint = $this->makeComplaint(['status' => ComplaintStatus::from($statusValue)]);

            foreach (ComplaintStatus::cases() as $target) {
                $this->actingAs($this->operatorA)
                    ->patch($this->closeUrl($complaint), ['status' => $target->value])
                    ->assertSessionHasErrors('status');
            }

            $this->assertSame($statusValue, $complaint->fresh()->status->value);
        }
    }

    // =========================================================================
    // 8. CONCURRENCY / STALE-STATE RE-CHECK (D-3.5 mechanism preserved)
    // =========================================================================

    public function test_duplicate_close_attempts_write_nothing(): void
    {
        $complaint = $this->makeComplaint();

        // Request A (close) is rejected by the matrix.
        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        // Request B (sequential double-submit) is rejected too.
        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
        $this->assertSame(0, $this->closeStatusHistoryCount($complaint));
        $this->assertSame(0, $this->closeAuditCount($complaint));
    }

    /**
     * Deterministic simulation of the lost-race scenario that the in-transaction
     * `lockForUpdate()` + stale-state re-check protects against, exercised with a
     * VALID transition (submitted → in_progress) since the close edge is retired:
     *
     *   - the controller is handed a STALE in-memory model (`status = submitted`);
     *   - the database row has ALREADY been moved to `in_progress` by the
     *     "winning" concurrent request;
     *   - the locked re-read therefore sees `in_progress`, which cannot transition
     *     to `in_progress`, so NOTHING is written.
     */
    public function test_stale_state_recheck_blocks_a_duplicate_transition(): void
    {
        $complaint = $this->makeComplaint([
            'status'      => ComplaintStatus::Submitted,
            'resolved_at' => null,
        ]);

        // Stale snapshot still saying `submitted`.
        $stale = Complaint::findOrFail($complaint->id);
        $this->assertSame(ComplaintStatus::Submitted, $stale->status);

        // The "winning" concurrent request already advanced the row.
        Complaint::whereKey($complaint->id)->update(['status' => ComplaintStatus::InProgress->value]);

        $request = Request::create(
            '/operator/laporan/' . $complaint->id . '/status',
            'PATCH',
            ['status' => 'in_progress']
        );
        $request->setUserResolver(fn () => $this->operatorA);

        $response = app(\App\Http\Controllers\Staff\OperatorComplaintController::class)
            ->updateStatus($request, $stale);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'to_status'    => 'in_progress',
        ]);
    }

    // =========================================================================
    // 9. IDOR / DINAS SCOPE (authorization unchanged)
    // =========================================================================

    public function test_operator_cannot_mutate_a_complaint_of_another_dinas(): void
    {
        $complaint = $this->makeComplaint([
            'category_id'   => $this->categoryB->id,
            'dinas_unit_id' => $this->unitB->id,
        ]);

        $this->actingAs($this->operatorA)
            ->patch($this->closeUrl($complaint), ['status' => 'closed'])
            ->assertNotFound();

        $this->assertSame(ComplaintStatus::Resolved, $complaint->fresh()->status);
        $this->assertSame(0, $this->closeStatusHistoryCount($complaint));
    }

    // =========================================================================
    // 10. RETENTION (D-3.2 — unchanged)
    // =========================================================================

    public function test_legacy_closed_does_not_change_the_retention_reference_or_add_closed_at(): void
    {
        $submittedAt = now()->subYears(6);
        $complaint = $this->makeComplaint([
            'status'       => ComplaintStatus::Closed,
            'submitted_at' => $submittedAt,
        ]);

        // Capture the DB-persisted value (no microsecond drift) as the baseline.
        $persistedSubmittedAt = $complaint->fresh()->submitted_at;

        $fresh = $complaint->fresh();

        // Retention reference is `submitted_at` — nothing rewrites it.
        $this->assertTrue($fresh->submitted_at->equalTo($persistedSubmittedAt));
        $this->assertFalse(Schema::hasColumn('complaints', 'closed_at'));

        // The legacy closed complaint is still eligible for the 5-year purge.
        $this->assertTrue(
            app(ComplaintRetentionService::class)->expiredQuery()->whereKey($complaint->id)->exists()
        );
    }

    // =========================================================================
    // 11. UI (J) — close action is no longer offered
    // =========================================================================

    public function test_operator_ui_does_not_offer_close_for_resolved(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Resolved]);

        $html = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tutup Laporan', $html);
        $this->assertStringNotContainsString('name="status" value="closed"', $html);
    }

    public function test_operator_ui_shows_no_transition_controls_when_closed(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::Closed]);

        $html = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tutup Laporan', $html);
        $this->assertStringNotContainsString('Simpan Status', $html);
        $this->assertStringNotContainsString('name="status"', $html);
        // But the allowed post-closed actions remain available.
        $this->assertStringContainsString('catatan', strtolower($html));
    }

    public function test_operator_ui_does_not_offer_close_for_non_resolved_status(): void
    {
        $complaint = $this->makeComplaint(['status' => ComplaintStatus::InProgress]);

        $html = $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tutup Laporan', $html);
        $this->assertStringContainsString('Simpan Status', $html);
    }
}
