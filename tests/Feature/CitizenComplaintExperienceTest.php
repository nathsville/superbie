<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 4 — Citizen / Masyarakat Complaint Experience
 *
 * Regression + hardening suite for the citizen-facing complaint lifecycle.
 * Business rules (statuses, transitions, terminal `closed`, no reopen) are FROZEN
 * and MUST NOT be changed by these tests — they assert existing frozen behavior.
 *
 * Coverage:
 *  1. Citizen dashboard isolation
 *  2. Citizen complaint creation
 *  3. Initial status = submitted
 *  4. Citizen history isolation
 *  5. Citizen detail isolation
 *  6. Citizen status timeline (real history only)
 *  7. Public response visibility
 *  8. Internal note invisibility (incl. status-history note never leaks)
 *  9. Rejected reason visibility
 * 10. Resolved public response visibility
 * 11. Closed visibility
 * 12. Attachment authorization
 * 13. IDOR protection
 * 14. Input tampering
 * 15. Guest protection
 * 16. Citizen cannot mutate lifecycle
 */
class CitizenComplaintExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $citizenA;
    private User $citizenB;
    private User $operator;
    private ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizenA = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);

        $this->citizenB = User::factory()->create([
            'role'      => 'masyarakat',
            'is_active' => true,
        ]);

        $this->operator = User::factory()->create([
            'role'      => 'operator',
            'is_active' => true,
        ]);

        $this->category = ComplaintCategory::create([
            'name'       => 'Infrastruktur Jalan',
            'slug'       => 'infrastruktur-jalan',
            'description' => 'Kerusakan jalan raya dan jembatan',
            'dinas_name' => 'Dinas PUPR',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function makeComplaint(User $reporter, ComplaintStatus $status = ComplaintStatus::Submitted): Complaint
    {
        return Complaint::create([
            'reference_code' => 'LPW-TEST-' . strtoupper(uniqid()),
            'reporter_id'    => $reporter->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan milik ' . $reporter->name,
            'description'    => 'Deskripsi laporan pengujian citizen experience.',
            'status'         => $status,
            'submitted_at'   => now(),
        ]);
    }

    // =========================================================================
    // 1. Dashboard isolation
    // =========================================================================

    public function test_dashboard_only_counts_own_complaints(): void
    {
        $this->makeComplaint($this->citizenA);
        $this->makeComplaint($this->citizenA, ComplaintStatus::Resolved);
        $this->makeComplaint($this->citizenB); // must not be counted

        $response = $this->actingAs($this->citizenA)->get(route('citizen.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalLaporan', 2);
        $response->assertViewHas('laporanSelesai', 1);
    }

    public function test_dashboard_never_lists_other_citizens_complaints(): void
    {
        $this->makeComplaint($this->citizenA);
        $other = $this->makeComplaint($this->citizenB);
        $other->update(['title' => 'JUDUL RAHASIA CITIZEN B']);

        $response = $this->actingAs($this->citizenA)->get(route('citizen.dashboard'));

        $response->assertOk();
        $response->assertDontSee('JUDUL RAHASIA CITIZEN B');
    }

    // =========================================================================
    // 2 & 3. Complaint creation + initial status
    // =========================================================================

    public function test_citizen_can_create_complaint_and_initial_status_is_submitted(): void
    {
        $response = $this->actingAs($this->citizenA)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Jalan berlubang di Jl. Bau Massepe',
            'description' => 'Lubang cukup dalam membahayakan pengendara motor saat malam.',
        ]);

        $complaint = Complaint::where('reporter_id', $this->citizenA->id)->first();
        $this->assertNotNull($complaint);

        $this->assertDatabaseHas('complaints', [
            'id'          => $complaint->id,
            'reporter_id' => $this->citizenA->id,
            'status'      => 'submitted',
        ]);

        $response->assertRedirect(route('citizen.complaint.show', $complaint));
    }

    public function test_creation_sets_owner_from_authenticated_user(): void
    {
        $this->actingAs($this->citizenA)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan kepemilikan',
            'description' => 'Memastikan reporter_id berasal dari session, bukan input.',
        ]);

        $complaint = Complaint::latest('id')->first();
        $this->assertSame($this->citizenA->id, $complaint->reporter_id);
    }

    public function test_creation_writes_initial_status_history(): void
    {
        $this->actingAs($this->citizenA)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan riwayat awal',
            'description' => 'Memastikan history awal submitted tercatat.',
        ]);

        $complaint = Complaint::latest('id')->first();

        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $complaint->id,
            'from_status'  => null,
            'to_status'    => 'submitted',
            'changed_by'   => $this->citizenA->id,
        ]);
    }

    // =========================================================================
    // 4. History isolation
    // =========================================================================

    public function test_history_only_shows_own_complaints(): void
    {
        $mine = $this->makeComplaint($this->citizenA);
        $mine->update(['title' => 'LAPORAN SAYA SENDIRI']);

        $theirs = $this->makeComplaint($this->citizenB);
        $theirs->update(['title' => 'LAPORAN MILIK CITIZEN B']);

        $response = $this->actingAs($this->citizenA)->get(route('citizen.history'));

        $response->assertOk();
        $response->assertSee('LAPORAN SAYA SENDIRI');
        $response->assertDontSee('LAPORAN MILIK CITIZEN B');
    }

    // =========================================================================
    // 5 & 13. Detail isolation + IDOR
    // =========================================================================

    public function test_citizen_can_view_own_complaint_detail(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee($complaint->reference_code);
    }

    public function test_citizen_cannot_view_other_citizen_complaint_by_id(): void
    {
        $complaintB = $this->makeComplaint($this->citizenB);

        $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaintB))
            ->assertForbidden();
    }

    // =========================================================================
    // 6. Status timeline — real history only
    // =========================================================================

    public function test_timeline_reflects_actual_history_not_full_enum(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        // Real recorded history: submitted -> under_review
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => null,
            'to_status'    => 'submitted',
            'changed_by'   => $this->citizenA->id,
            'created_at'   => now()->subHour(),
        ]);
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => 'submitted',
            'to_status'    => 'under_review',
            'changed_by'   => $this->operator->id,
            'created_at'   => now(),
        ]);
        $complaint->update(['status' => ComplaintStatus::UnderReview]);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Diajukan');
        $response->assertSee('Sedang Ditinjau');
        // Statuses that never occurred must not appear in the timeline labels
        $response->assertDontSee('Menunggu Informasi');
        $response->assertDontSee('Ditutup');
    }

    public function test_timeline_never_leaks_internal_status_history_note(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => 'submitted',
            'to_status'    => 'under_review',
            'changed_by'   => $this->operator->id,
            'note'         => 'CATATAN OPERASIONAL INTERNAL RAHASIA - JANGAN TAMPIL KE WARGA',
            'created_at'   => now(),
        ]);
        $complaint->update(['status' => ComplaintStatus::UnderReview]);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertDontSee('CATATAN OPERASIONAL INTERNAL RAHASIA - JANGAN TAMPIL KE WARGA');
    }

    // =========================================================================
    // 7 & 8. Public response visible / internal note hidden
    // =========================================================================

    public function test_citizen_sees_public_response_but_not_internal_note(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'CATATAN INTERNAL OPERATOR - JANGAN BOCOR',
            'created_at'   => now(),
        ]);
        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::PublicResponse,
            'body'         => 'TANGGAPAN RESMI UNTUK WARGA',
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('TANGGAPAN RESMI UNTUK WARGA');
        $response->assertDontSee('CATATAN INTERNAL OPERATOR - JANGAN BOCOR');
    }

    // =========================================================================
    // 9. Rejected reason visibility
    // =========================================================================

    public function test_rejection_reason_is_visible_via_public_response(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::Rejected);
        $reason = 'Laporan ditolak karena bukti yang dilampirkan tidak mencukupi.';

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::PublicResponse,
            'body'         => $reason,
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Ditolak');
        $response->assertSee($reason);
    }

    // =========================================================================
    // 10. Resolved visibility
    // =========================================================================

    public function test_resolved_complaint_shows_status_and_public_response(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::Resolved);

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::PublicResponse,
            'body'         => 'Masalah telah ditangani oleh Dinas PUPR Parepare.',
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Selesai');
        $response->assertSee('Masalah telah ditangani oleh Dinas PUPR Parepare.');
    }

    // =========================================================================
    // 11. Closed visibility (terminal)
    // =========================================================================

    public function test_closed_complaint_is_visible_and_terminal(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::Closed);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Ditutup');
        // No reopen affordance anywhere in the citizen surface
        $response->assertDontSee('Buka Kembali');
        $response->assertDontSee('Reopen');

        // Frozen rule: closed has no transitions out
        $this->assertEmpty(ComplaintStatus::Closed->allowedTransitions());
    }

    // =========================================================================
    // 12 & 13. Attachment authorization + IDOR
    // =========================================================================

    public function test_citizen_can_download_own_attachment(): void
    {
        Storage::fake('private');

        $complaint = $this->makeComplaint($this->citizenA);
        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaint->id . '/bukti.jpg',
            'original_name' => 'bukti.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 1024,
        ]);
        Storage::disk('private')->put($attachment->path, 'dummy-bytes');

        $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.attachment', [$complaint, $attachment]))
            ->assertOk();
    }

    public function test_citizen_cannot_download_other_citizen_attachment(): void
    {
        Storage::fake('private');

        $complaintB = $this->makeComplaint($this->citizenB);
        $attachmentB = ComplaintAttachment::create([
            'complaint_id'  => $complaintB->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaintB->id . '/rahasia.jpg',
            'original_name' => 'rahasia.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 2048,
        ]);
        Storage::disk('private')->put($attachmentB->path, 'dummy-bytes');

        $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.attachment', [$complaintB, $attachmentB]))
            ->assertForbidden();
    }

    public function test_citizen_cannot_cross_link_attachment_from_other_complaint(): void
    {
        Storage::fake('private');

        // Citizen A owns complaint A, but tries to pull complaint B's attachment
        // through their own complaint route (mismatched pair).
        $complaintA = $this->makeComplaint($this->citizenA);
        $complaintB = $this->makeComplaint($this->citizenB);

        $attachmentB = ComplaintAttachment::create([
            'complaint_id'  => $complaintB->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaintB->id . '/b.jpg',
            'original_name' => 'b.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 100,
        ]);
        Storage::disk('private')->put($attachmentB->path, 'x');

        $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.attachment', [$complaintA, $attachmentB]))
            ->assertForbidden();
    }

    public function test_private_attachment_is_not_served_via_public_storage_route_without_signature(): void
    {
        Storage::fake('private');

        $complaint = $this->makeComplaint($this->citizenA);
        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaint->id . '/private-proof.jpg',
            'original_name' => 'private-proof.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 123,
        ]);
        Storage::disk('private')->put($attachment->path, 'private-bytes');

        // Direct unsigned access to the storage route must NOT expose the file.
        // The disk root is shared with the `local` disk, so this guards against
        // accidental public exposure of the private storage path.
        $response = $this->get('/storage/' . $attachment->path);

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_missing_attachment_returns_not_found_not_500(): void
    {
        Storage::fake('private');

        $complaint = $this->makeComplaint($this->citizenA);
        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaint->id . '/missing.jpg',
            'original_name' => 'missing.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 10,
        ]);
        // Deliberately do NOT put the file on disk

        $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.attachment', [$complaint, $attachment]))
            ->assertNotFound();
    }

    // =========================================================================
    // 14. Input tampering — allowlist enforcement
    // =========================================================================

    public function test_citizen_cannot_inject_status_or_assignment_on_create(): void
    {
        $this->actingAs($this->citizenA)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Upaya tampering field',
            'description' => 'Mencoba menyuntikkan status, assigned_to, dan owner palsu.',
            // Tampering attempts — must all be ignored by the allowlist
            'status'      => 'resolved',
            'assigned_to' => $this->operator->id,
            'created_by'  => $this->citizenB->id,
            'reporter_id' => $this->citizenB->id,
            'role'        => 'super_admin',
            'visibility'  => 'internal',
        ]);

        $complaint = Complaint::latest('id')->first();

        $this->assertSame('submitted', $complaint->status->value);
        $this->assertNull($complaint->assigned_to);
        $this->assertSame($this->citizenA->id, $complaint->reporter_id);
    }

    // =========================================================================
    // 15. Guest protection
    // =========================================================================

    public function test_guest_cannot_access_citizen_surface(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        $this->get(route('citizen.dashboard'))->assertRedirect(route('login'));
        $this->get(route('citizen.history'))->assertRedirect(route('login'));
        $this->get(route('citizen.complaint.create'))->assertRedirect(route('login'));
        $this->get(route('citizen.complaint.show', $complaint))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_complaint(): void
    {
        $this->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Laporan dari guest',
            'description' => 'Guest tidak boleh membuat laporan.',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan dari guest']);
    }

    // =========================================================================
    // 16. Citizen cannot mutate lifecycle / reach staff routes
    // =========================================================================

    public function test_citizen_cannot_update_complaint_status(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        $this->actingAs($this->citizenA)
            ->patch(route('operator.complaint.update-status', $complaint), ['status' => 'under_review'])
            ->assertForbidden();

        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'submitted']);
    }

    public function test_citizen_cannot_assign_complaint(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        $this->actingAs($this->citizenA)
            ->patch(route('operator.complaint.assign', $complaint), ['assigned_to' => $this->operator->id])
            ->assertForbidden();
    }

    public function test_citizen_cannot_add_note_as_staff(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        $this->actingAs($this->citizenA)
            ->post(route('operator.complaint.add-note', $complaint), [
                'body'       => 'Mencoba membuat catatan operator',
                'visibility' => 'public_response',
            ])
            ->assertForbidden();
    }

    public function test_citizen_cannot_change_complaint_category(): void
    {
        $complaint = $this->makeComplaint($this->citizenA);

        $this->actingAs($this->citizenA)
            ->patch(route('operator.complaint.update-category', $complaint), [
                'category_id' => $this->category->id,
            ])
            ->assertForbidden();
    }

    public function test_citizen_cannot_access_staff_dashboards(): void
    {
        $this->actingAs($this->citizenA)->get(route('operator.dashboard'))->assertForbidden();
        $this->actingAs($this->citizenA)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->citizenA)->get(route('super-admin.dashboard'))->assertForbidden();
    }

    public function test_citizen_cannot_access_staff_attachment_route(): void
    {
        Storage::fake('private');

        $complaint = $this->makeComplaint($this->citizenA);
        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaint->id . '/x.jpg',
            'original_name' => 'x.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 10,
        ]);
        Storage::disk('private')->put($attachment->path, 'x');

        $this->actingAs($this->citizenA)
            ->get(route('operator.complaint.attachment', [$complaint, $attachment]))
            ->assertForbidden();
    }
}
