<?php

namespace Tests\Feature;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
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
 * SUPERBIE — FITUR 1 — TIMELINE PROGRES LAPORAN (sisi Masyarakat).
 *
 * Regression + hardening suite for the citizen "Timeline Progres Laporan".
 *
 * The timeline is a truthful, chronological projection of the REAL recorded
 * status events (`complaint_status_histories`), ordered OLDEST → NEWEST. It is
 * NOT a re-derivation from the current status. The frozen business rules
 * (7 enum cases, 4 active statuses, 3 transitions, legacy statuses history-only,
 * no reopen) are NOT re-tested here — they are asserted to be UNCHANGED.
 *
 * Coverage:
 *   1. Timeline renders on the owner's complaint detail.
 *   2. Event order is consistent (oldest → newest).
 *   3. Complaint creation records the initial event.
 *   4. A successful status change records exactly ONE timeline event.
 *   5. A FAILED status change records NO false success event.
 *   6. No duplicate recording on a single status change.
 *   7. A citizen cannot view another citizen's timeline (IDOR).
 *   8. Internal notes / sensitive data never appear in the public timeline.
 *   9. Legacy statuses remain displayable without reopening forbidden transitions.
 *  10. The detail page still works for a complaint without full history.
 *  11. Operator / Admin / Super Admin access is unchanged.
 */
class CitizenComplaintTimelineTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $citizen;
    private User $otherCitizen;
    private User $operator;
    private User $superAdmin;
    private User $admin;

    private ComplaintCategory $category;
    private DinasUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = DinasUnit::create([
            'name' => 'Dinas PUPR', 'code' => 'PUPR-TL', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan-tl',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->category->dinasUnits()->attach($this->unit->id);

        $this->citizen      = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->otherCitizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->operator     = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private static int $seq = 0;

    /**
     * A complaint owned by the given reporter, validated and routed to the test
     * Dinas/Unit so the Operator may act on it.
     */
    private function makeComplaint(User $reporter, ComplaintStatus $status = ComplaintStatus::Submitted, array $overrides = []): Complaint
    {
        static::$seq++;

        return Complaint::create(array_merge([
            'reference_code' => 'LPW-TL-' . str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'reporter_id'    => $reporter->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan Timeline ' . static::$seq,
            'description'    => 'Deskripsi laporan timeline pengujian.',
            'status'         => $status,
            'review_state'   => ComplaintReviewState::ValidatedForOperator,
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function addHistory(Complaint $complaint, ?string $from, string $to, $at, ?string $note = null): ComplaintStatusHistory
    {
        return ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => $from,
            'to_status'    => $to,
            'changed_by'   => $this->citizen->id,
            'note'         => $note,
            'created_at'   => $at,
        ]);
    }

    // =========================================================================
    // 1. TIMELINE PRESENT ON OWN DETAIL
    // =========================================================================

    public function test_timeline_section_is_rendered_on_own_complaint_detail(): void
    {
        $complaint = $this->makeComplaint($this->citizen);
        $this->addHistory($complaint, null, 'submitted', now()->subDay());

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Timeline Progres Laporan')
            ->assertSee('Diajukan');
    }

    public function test_timeline_is_a_real_projection_not_a_full_enum_enumeration(): void
    {
        // Only the `submitted` event actually happened. Statuses that never
        // occurred must NOT appear as timeline events.
        $complaint = $this->makeComplaint($this->citizen);
        $this->addHistory($complaint, null, 'submitted', now()->subDay());

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Diajukan')
            ->assertDontSee('Sedang Diproses')
            ->assertDontSee('Selesai');
    }

    // =========================================================================
    // 2. EVENT ORDER — OLDEST FIRST (deterministic)
    // =========================================================================

    public function test_timeline_events_are_ordered_oldest_first(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::Resolved);
        $this->addHistory($complaint, null, 'submitted', now()->subDays(3));
        $this->addHistory($complaint, 'submitted', 'in_progress', now()->subDays(2));
        $this->addHistory($complaint, 'in_progress', 'resolved', now()->subDay());

        $entries = $complaint->fresh()->load('statusHistories')->timelineEntries();

        $this->assertSame(['submitted', 'in_progress', 'resolved'], $entries->pluck('status')->all());
        // The LAST entry is the current one.
        $this->assertTrue($entries->last()['is_current']);
        $this->assertFalse($entries->first()['is_current']);
    }

    public function test_timeline_order_is_stable_for_equal_timestamps(): void
    {
        // Two rows written in the SAME second must still have a deterministic,
        // insertion-stable order (tie-break on id).
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $first  = $this->addHistory($complaint, null, 'submitted', now());
        $second = $this->addHistory($complaint, 'submitted', 'in_progress', now());

        $entries = $complaint->fresh()->load('statusHistories')->timelineEntries();

        $this->assertSame(['submitted', 'in_progress'], $entries->pluck('status')->all());
        $this->assertTrue($second->id > $first->id);
    }

    // =========================================================================
    // 3. CREATION RECORDS THE INITIAL EVENT
    // =========================================================================

    public function test_creation_records_the_initial_timeline_event(): void
    {
        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan timeline awal',
            'description' => 'Memastikan kejadian awal timeline tercatat saat dibuat.',
            ...$this->locationPayload(),
        ]);

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->latest('id')->first();
        $this->assertNotNull($complaint);

        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'from_status'  => null,
            'to_status'    => 'submitted',
            'changed_by'   => $this->citizen->id,
        ]);

        $entries = $complaint->fresh()->load('statusHistories')->timelineEntries();
        $this->assertCount(1, $entries);
        $this->assertTrue($entries->first()['is_initial']);
        $this->assertTrue($entries->first()['is_current']);

        // The initial marker is visible to the citizen.
        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Awal')
            ->assertSee('Diajukan');
    }

    // =========================================================================
    // 4 & 6. ONE SUCCESSFUL CHANGE == EXACTLY ONE EVENT (no duplicates)
    // =========================================================================

    public function test_successful_status_change_records_exactly_one_timeline_event(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::Submitted);
        $this->addHistory($complaint, null, 'submitted', now()->subHour());

        $before = ComplaintStatusHistory::where('complaint_id', $complaint->id)->count();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status' => 'in_progress',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $after = ComplaintStatusHistory::where('complaint_id', $complaint->id)->count();
        $this->assertSame($before + 1, $after, 'A successful status change must record exactly ONE timeline event.');

        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'from_status'  => 'submitted',
            'to_status'    => 'in_progress',
        ]);

        // Exactly one in_progress event (no double recording by observers/services).
        $this->assertSame(
            1,
            ComplaintStatusHistory::where('complaint_id', $complaint->id)
                ->where('to_status', 'in_progress')->count(),
        );
    }

    public function test_super_admin_completion_approval_records_exactly_one_resolved_event(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress, [
            'review_state' => ComplaintReviewState::PendingCompletionValidation,
            'completion_submitted_at' => now(),
        ]);
        $this->addHistory($complaint, null, 'submitted', now()->subDays(2));
        $this->addHistory($complaint, 'submitted', 'in_progress', now()->subDay());

        // The publishable public response source is the Operator's INTERNAL
        // completion narrative (located via the completion_submitted audit row).
        $note = ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'Pekerjaan selesai dan telah diverifikasi lapangan.',
            'created_at'   => now(),
        ]);
        \App\Models\AuditLog::create([
            'actor_id' => $this->operator->id,
            'action' => 'complaint.completion_submitted',
            'subject_type' => 'complaint',
            'subject_id' => $complaint->id,
            'metadata' => ['completion_note_id' => $note->id],
            'created_at' => now(),
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.completion-validation.approve', $complaint))
            ->assertRedirect();

        $this->assertSame(
            1,
            ComplaintStatusHistory::where('complaint_id', $complaint->id)
                ->where('to_status', 'resolved')->count(),
        );
    }

    // =========================================================================
    // 5. FAILED STATUS CHANGE RECORDS NO FALSE SUCCESS EVENT
    // =========================================================================

    public function test_failed_status_change_records_no_timeline_event(): void
    {
        // An Operator may NOT set `resolved` directly (completion gate). The
        // attempt must fail AND must not write any timeline event.
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $this->addHistory($complaint, null, 'submitted', now()->subHour());

        $before = ComplaintStatusHistory::where('complaint_id', $complaint->id)->count();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status'               => 'resolved',
                'public_response_body' => 'Upaya penyelesaian langsung tanpa validasi.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame($before, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());
        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'to_status'    => 'resolved',
        ]);
        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    public function test_illegal_transition_records_no_timeline_event(): void
    {
        // in_progress → submitted is NOT in the active matrix.
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $this->addHistory($complaint, null, 'submitted', now()->subHour());

        $before = ComplaintStatusHistory::where('complaint_id', $complaint->id)->count();

        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status' => 'submitted',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame($before, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());
        $this->assertSame(ComplaintStatus::InProgress, $complaint->fresh()->status);
    }

    // =========================================================================
    // 7. IDOR — A CITIZEN CANNOT VIEW ANOTHER CITIZEN'S TIMELINE
    // =========================================================================

    public function test_citizen_cannot_view_another_citizens_timeline(): void
    {
        $foreign = $this->makeComplaint($this->otherCitizen);
        $this->addHistory($foreign, null, 'submitted', now()->subDay(), 'Laporan milik orang lain.');

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $foreign))
            ->assertForbidden();
    }

    // =========================================================================
    // 8. PRIVACY — INTERNAL NOTES / SENSITIVE DATA NEVER IN THE PUBLIC TIMELINE
    // =========================================================================

    public function test_internal_status_history_note_never_appears_in_the_public_timeline(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $secret = 'CATATAN OPERASIONAL INTERNAL RAHASIA - JANGAN TAMPIL KE WARGA';
        $this->addHistory($complaint, null, 'submitted', now()->subHour());
        $this->addHistory($complaint, 'submitted', 'in_progress', now(), $secret);

        $response = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk();

        $response->assertDontSee($secret);

        // The PUBLIC timeline carries only the generic, safe description.
        $entries = $complaint->fresh()->load('statusHistories')->timelineEntries();
        $this->assertSame(
            ComplaintStatus::InProgress->publicDescription(),
            $entries->last()['description'],
        );
        $this->assertStringNotContainsString($secret, (string) $entries->last()['description']);
    }

    public function test_operator_identity_is_not_leaked_in_the_public_timeline(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $this->addHistory($complaint, null, 'submitted', now()->subHour());
        // History changed_by defaults to the citizen in addHistory(); set a
        // distinctive operator note + actor to prove neither leaks.
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => 'submitted',
            'to_status'    => 'in_progress',
            'changed_by'   => $this->operator->id,
            'note'         => 'Ditinjau oleh staf.',
            'created_at'   => now(),
        ]);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        // The timeline must never render the acting staff name or the internal note.
        $this->assertStringNotContainsString($this->operator->name, $html);
        $this->assertStringNotContainsString('Ditinjau oleh staf.', $html);
    }

    public function test_internal_note_body_is_not_copied_into_the_public_timeline(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $this->addHistory($complaint, null, 'submitted', now()->subHour());
        $this->addHistory($complaint, 'submitted', 'in_progress', now());

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'CATATAN INTERNAL OPERATOR - JANGAN BOCOR',
            'created_at'   => now(),
        ]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertDontSee('CATATAN INTERNAL OPERATOR - JANGAN BOCOR');
    }

    // =========================================================================
    // 9. LEGACY STATUSES REMAIN DISPLAYABLE (no reopened transitions)
    // =========================================================================

    public function test_legacy_status_event_renders_with_a_safe_label(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::Closed);
        $this->addHistory($complaint, null, 'submitted', now()->subDays(2));
        $this->addHistory($complaint, 'submitted', 'under_review', now()->subDay());
        $this->addHistory($complaint, 'under_review', 'closed', now());

        $entries = $complaint->fresh()->load('statusHistories')->timelineEntries();
        $this->assertSame(['submitted', 'under_review', 'closed'], $entries->pluck('status')->all());
        $this->assertSame('Ditutup', $entries->last()['label']);
        $this->assertSame(ComplaintStatus::Closed->publicDescription(), $entries->last()['description']);

        // The legacy event is visible on the citizen detail without error.
        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Sedang Ditinjau')
            ->assertSee('Ditutup');

        // Frozen rule: `closed` still has NO outgoing transition (not reopened).
        $this->assertEmpty(ComplaintStatus::Closed->allowedTransitions());
    }

    public function test_unknown_history_status_value_renders_safely_without_description(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $this->addHistory($complaint, 'submitted', 'legacy_unknown_value', now());

        $entries = $complaint->fresh()->load('statusHistories')->timelineEntries();
        $this->assertSame('legacy_unknown_value', $entries->first()['label']);
        $this->assertNull($entries->first()['description']);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('legacy_unknown_value');
    }

    // =========================================================================
    // 10. DETAIL PAGE WORKS WITHOUT FULL HISTORY
    // =========================================================================

    public function test_detail_page_shows_informative_empty_state_when_no_history_exists(): void
    {
        $complaint = $this->makeComplaint($this->citizen);

        $this->assertSame(0, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Timeline Progres Laporan')
            ->assertSee('Belum Ada Riwayat Progres');
    }

    public function test_timeline_handles_missing_timestamp_without_error(): void
    {
        // A history row with no created_at must not crash the view.
        $complaint = $this->makeComplaint($this->citizen);
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => null,
            'to_status'    => 'submitted',
            'changed_by'   => $this->citizen->id,
        ]);
        // Force a NULL timestamp past the DB default.
        \Illuminate\Support\Facades\DB::table('complaint_status_histories')
            ->where('complaint_id', $complaint->id)
            ->update(['created_at' => null]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Waktu tidak tersedia');
    }

    // =========================================================================
    // 11. STAFF ACCESS UNCHANGED
    // =========================================================================

    public function test_staff_access_is_unchanged(): void
    {
        $complaint = $this->makeComplaint($this->citizen, ComplaintStatus::InProgress);
        $this->addHistory($complaint, null, 'submitted', now()->subHour());

        // Operator: own-scope detail is reachable.
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk();

        // Admin: monitoring dashboard is reachable.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Super Admin: monitoring list + detail are reachable.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index'))
            ->assertOk();
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertOk();
    }

    public function test_citizen_cannot_reach_staff_status_endpoints(): void
    {
        $complaint = $this->makeComplaint($this->citizen);

        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-status', $complaint), ['status' => 'in_progress'])
            ->assertForbidden();

        $this->assertSame(ComplaintStatus::Submitted, $complaint->fresh()->status);
    }

    public function test_citizen_cannot_manipulate_timeline_via_request(): void
    {
        // There is NO citizen endpoint that accepts a status/history write.
        $complaint = $this->makeComplaint($this->citizen);
        $before = ComplaintStatusHistory::where('complaint_id', $complaint->id)->count();

        $this->actingAs($this->citizen)->post(route('citizen.complaint.show', $complaint), [
            'status'      => 'resolved',
            'to_status'   => 'resolved',
            'from_status' => null,
        ]);

        $this->assertSame($before, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());
        $this->assertSame(ComplaintStatus::Submitted, $complaint->fresh()->status);
    }
}
