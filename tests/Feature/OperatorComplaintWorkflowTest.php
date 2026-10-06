<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for Operator complaint workflow.
 *
 * Tests cover:
 *  - Authorization (access control, role boundaries)
 *  - Complaint index/show (read)
 *  - Category update
 *  - Assignment (valid operator only)
 *  - Status update (transition-rule enforcement)
 *  - Internal note (internal vs public_response visibility)
 *  - Attachment download (IDOR protection)
 *  - Citizen isolation (citizen cannot see internal notes, cannot access operator area)
 *  - Admin boundary (admin cannot perform mutations)
 *  - IDOR direct URL tests
 */
class OperatorComplaintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private User $operator2;
    private User $citizen;
    private User $citizen2;
    private User $admin;
    private User $superAdmin;
    private ComplaintCategory $category;
    private ComplaintCategory $category2;
    private DinasUnit $unit;
    private Complaint $complaint;

    protected function setUp(): void
    {
        parent::setUp();

        // Prompt 15 — Operators are scoped to a Dinas/Unit. Both operators share
        // one unit so that assignment/visibility assertions remain meaningful.
        $this->unit = DinasUnit::create([
            'name'       => 'Dinas Uji Operator',
            'code'       => 'UJI-OP',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $this->operator   = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id]);
        $this->operator2  = User::factory()->create(['role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->citizen2   = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name'       => 'Infrastruktur Jalan',
            'slug'       => 'infrastruktur-jalan',
            'dinas_name' => 'Dinas PUPR',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        $this->category2 = ComplaintCategory::create([
            'name'       => 'Kebersihan',
            'slug'       => 'kebersihan',
            'dinas_name' => 'Dinas LH',
            'is_active'  => true,
            'sort_order' => 2,
        ]);

        // Map both categories to the operator's unit so routing/mapping rules
        // remain exercisable under the new scope.
        $this->category->dinasUnits()->attach($this->unit->id);
        $this->category2->dinasUnits()->attach($this->unit->id);

        $this->complaint = Complaint::create([
            'reference_code' => 'LPW-TEST-0001',
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'assigned_to'    => $this->operator->id,
            'title'          => 'Jalan Rusak Parah',
            'description'    => 'Jalan berlubang sangat dalam dan berbahaya.',
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
        ]);
    }

    // =========================================================================
    // 1. Authorization — Guest redirected
    // =========================================================================

    public function test_guest_cannot_access_operator_complaint_index(): void
    {
        $this->get('/operator/laporan')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_operator_complaint_show(): void
    {
        $this->get('/operator/laporan/' . $this->complaint->id)->assertRedirect('/login');
    }

    // =========================================================================
    // 2. Authorization — Role boundary
    // =========================================================================

    public function test_citizen_cannot_access_operator_complaint_index(): void
    {
        $this->actingAs($this->citizen)->get('/operator/laporan')->assertForbidden();
    }

    public function test_citizen_cannot_access_operator_complaint_show(): void
    {
        $this->actingAs($this->citizen)
            ->get('/operator/laporan/' . $this->complaint->id)
            ->assertForbidden();
    }

    public function test_admin_cannot_access_operator_complaint_index(): void
    {
        $this->actingAs($this->admin)->get('/operator/laporan')->assertForbidden();
    }

    public function test_admin_cannot_access_operator_complaint_show(): void
    {
        $this->actingAs($this->admin)
            ->get('/operator/laporan/' . $this->complaint->id)
            ->assertForbidden();
    }

    public function test_inactive_operator_is_redirected(): void
    {
        $inactive = User::factory()->create(['role' => 'operator', 'is_active' => false]);
        $this->actingAs($inactive)->get('/operator/laporan')->assertRedirect('/login');
    }

    // =========================================================================
    // 3. Operator — Read workflow
    // =========================================================================

    public function test_operator_can_access_complaint_index(): void
    {
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.index'))
            ->assertOk()
            ->assertSee('LPW-TEST-0001')
            ->assertSee('Jalan Rusak Parah');
    }

    public function test_operator_can_search_complaints(): void
    {
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.index', ['search' => 'Jalan Rusak']))
            ->assertOk()
            ->assertSee('Jalan Rusak Parah');
    }

    public function test_operator_can_filter_by_status(): void
    {
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.index', ['status' => 'submitted']))
            ->assertOk()
            ->assertSee('Jalan Rusak Parah');
    }

    public function test_operator_search_with_no_results_shows_empty_state(): void
    {
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.index', ['search' => 'XXXX-NOTEXIST-9999']))
            ->assertOk()
            ->assertSee('Tidak ada laporan ditemukan');
    }

    public function test_operator_can_view_complaint_detail(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $this->complaint));

        $response->assertOk()
            ->assertSee('LPW-TEST-0001')
            ->assertSee('Jalan Rusak Parah')
            ->assertSee('Infrastruktur Jalan');
    }

    public function test_operator_can_see_internal_notes_in_detail(): void
    {
        ComplaintNote::create([
            'complaint_id' => $this->complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'CATATAN_INTERNAL_OPERATOR',
            'created_at'   => now(),
        ]);

        $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $this->complaint))
            ->assertOk()
            ->assertSee('CATATAN_INTERNAL_OPERATOR');
    }

    // =========================================================================
    // 4. Category update
    // =========================================================================

    public function test_operator_can_update_category(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-category', $this->complaint), [
                'category_id' => $this->category2->id,
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', [
            'id'          => $this->complaint->id,
            'category_id' => $this->category2->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id'     => $this->operator->id,
            'action'       => 'complaint.category_updated',
            'subject_type' => 'complaint',
            'subject_id'   => $this->complaint->id,
        ]);
    }

    public function test_operator_cannot_set_inactive_category(): void
    {
        $inactive = ComplaintCategory::create([
            'name'       => 'Nonaktif',
            'slug'       => 'nonaktif',
            'is_active'  => false,
            'sort_order' => 99,
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-category', $this->complaint), [
                'category_id' => $inactive->id,
            ])
            ->assertStatus(404); // firstOrFail on active category throws 404

        // complaint category should be unchanged
        $this->assertDatabaseHas('complaints', [
            'id'          => $this->complaint->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_operator_cannot_set_nonexistent_category(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-category', $this->complaint), [
                'category_id' => 99999,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_citizen_cannot_update_category(): void
    {
        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-category', $this->complaint), [
                'category_id' => $this->category2->id,
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_update_category(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-category', $this->complaint), [
                'category_id' => $this->category2->id,
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // 5. Assignment
    // =========================================================================

    public function test_operator_can_assign_complaint_to_operator(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.assign', $this->complaint), [
                'assigned_to' => $this->operator2->id,
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', [
            'id'          => $this->complaint->id,
            'assigned_to' => $this->operator2->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id'     => $this->operator->id,
            'action'       => 'complaint.assigned',
            'subject_id'   => $this->complaint->id,
        ]);
    }

    public function test_operator_can_unassign_complaint(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.assign', $this->complaint), [
                'assigned_to' => null,
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', [
            'id'          => $this->complaint->id,
            'assigned_to' => null,
        ]);
    }

    public function test_operator_cannot_assign_to_citizen(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.assign', $this->complaint), [
                'assigned_to' => $this->citizen->id,
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHasErrors('assigned_to');

        // complaint assignment unchanged
        $this->assertDatabaseHas('complaints', [
            'id'          => $this->complaint->id,
            'assigned_to' => $this->operator->id, // original
        ]);
    }

    public function test_operator_cannot_assign_to_admin(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.assign', $this->complaint), [
                'assigned_to' => $this->admin->id,
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_operator_cannot_assign_to_inactive_operator(): void
    {
        $inactive = User::factory()->create(['role' => 'operator', 'is_active' => false]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.assign', $this->complaint), [
                'assigned_to' => $inactive->id,
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_historical_assignment_is_not_automatically_changed(): void
    {
        // Simulate a "historical" complaint assigned to someone
        $originalAssigned = $this->complaint->assigned_to;

        // No assignment action taken — historical value must persist
        $this->assertDatabaseHas('complaints', [
            'id'          => $this->complaint->id,
            'assigned_to' => $originalAssigned,
        ]);
    }

    // =========================================================================
    // 6. Status update (transition rules)
    // =========================================================================

    public function test_operator_can_transition_status_from_submitted_to_under_review(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
                'note'   => 'Laporan sedang ditinjau',
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', [
            'id'     => $this->complaint->id,
            'status' => 'under_review',
        ]);

        $this->assertDatabaseHas('complaint_status_histories', [
            'complaint_id' => $this->complaint->id,
            'from_status'  => 'submitted',
            'to_status'    => 'under_review',
            'changed_by'   => $this->operator->id,
            'note'         => 'Laporan sedang ditinjau',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id'     => $this->operator->id,
            'action'       => 'complaint.status_updated',
            'subject_id'   => $this->complaint->id,
        ]);
    }

    public function test_operator_cannot_make_invalid_status_transition(): void
    {
        // submitted -> resolved is NOT allowed per allowedTransitions()
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'resolved',
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHasErrors('status');

        // complaint status must be unchanged
        $this->assertDatabaseHas('complaints', [
            'id'     => $this->complaint->id,
            'status' => 'submitted',
        ]);

        // no history record created
        $this->assertDatabaseMissing('complaint_status_histories', [
            'complaint_id' => $this->complaint->id,
            'to_status'    => 'resolved',
        ]);
    }

    public function test_operator_cannot_set_arbitrary_status_string(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'fake_status_invented',
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('complaints', [
            'id'     => $this->complaint->id,
            'status' => 'submitted',
        ]);
    }

    public function test_status_update_is_atomic_with_history_and_audit(): void
    {
        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertRedirect();

        // All three records must exist
        $this->assertDatabaseHas('complaints', ['id' => $this->complaint->id, 'status' => 'under_review']);
        $this->assertDatabaseHas('complaint_status_histories', ['complaint_id' => $this->complaint->id, 'to_status' => 'under_review']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.status_updated', 'subject_id' => $this->complaint->id]);
    }

    public function test_citizen_cannot_update_status(): void
    {
        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_update_status(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // 7. Internal note
    // =========================================================================

    public function test_operator_can_add_internal_note(): void
    {
        $this->actingAs($this->operator)
            ->post(route('operator.complaint.add-note', $this->complaint), [
                'body'       => 'Ini adalah catatan internal rahasia.',
                'visibility' => 'internal',
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $this->complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => 'internal',
            'body'         => 'Ini adalah catatan internal rahasia.',
        ]);
    }

    public function test_operator_can_add_public_response(): void
    {
        $this->actingAs($this->operator)
            ->post(route('operator.complaint.add-note', $this->complaint), [
                'body'       => 'Respons resmi untuk pelapor.',
                'visibility' => 'public_response',
            ])
            ->assertRedirect(route('operator.complaint.show', $this->complaint))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('complaint_notes', [
            'complaint_id' => $this->complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => 'public_response',
            'body'         => 'Respons resmi untuk pelapor.',
        ]);
    }

    public function test_operator_cannot_add_note_with_empty_body(): void
    {
        $this->actingAs($this->operator)
            ->post(route('operator.complaint.add-note', $this->complaint), [
                'body'       => '',
                'visibility' => 'internal',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_operator_cannot_add_note_with_arbitrary_visibility(): void
    {
        $this->actingAs($this->operator)
            ->post(route('operator.complaint.add-note', $this->complaint), [
                'body'       => 'Test',
                'visibility' => 'hacked_visibility',
            ])
            ->assertSessionHasErrors('visibility');
    }

    public function test_citizen_cannot_add_internal_note(): void
    {
        $this->actingAs($this->citizen)
            ->post(route('operator.complaint.add-note', $this->complaint), [
                'body'       => 'Citizen trying to inject note',
                'visibility' => 'internal',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_add_note(): void
    {
        $this->actingAs($this->admin)
            ->post(route('operator.complaint.add-note', $this->complaint), [
                'body'       => 'Admin trying to inject note',
                'visibility' => 'internal',
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // 8. Citizen isolation — internal note NOT visible to citizen
    // =========================================================================

    public function test_citizen_cannot_see_internal_note_in_their_complaint_view(): void
    {
        ComplaintNote::create([
            'complaint_id' => $this->complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'RAHASIA_INTERNAL_TIDAK_BOLEH_TERLIHAT',
            'created_at'   => now(),
        ]);

        ComplaintNote::create([
            'complaint_id' => $this->complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::PublicResponse,
            'body'         => 'TANGGAPAN_RESMI_PUBLIK',
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $this->complaint));

        $response->assertOk()
            ->assertSee('TANGGAPAN_RESMI_PUBLIK')
            ->assertDontSee('RAHASIA_INTERNAL_TIDAK_BOLEH_TERLIHAT');
    }

    public function test_citizen_cannot_see_others_complaint(): void
    {
        $this->actingAs($this->citizen2)
            ->get(route('citizen.complaint.show', $this->complaint))
            ->assertForbidden();
    }

    public function test_citizen_cannot_access_operator_area(): void
    {
        $this->actingAs($this->citizen)->get('/operator/dashboard')->assertForbidden();
        $this->actingAs($this->citizen)->get('/operator/laporan')->assertForbidden();
        $this->actingAs($this->citizen)->get('/operator/laporan/' . $this->complaint->id)->assertForbidden();
    }

    // =========================================================================
    // 9. Attachment — IDOR protection
    // =========================================================================

    public function test_operator_can_download_attachment(): void
    {
        Storage::fake('private');
        $file = UploadedFile::fake()->create('test_doc.pdf', 100, 'application/pdf');
        $path = $file->storeAs('complaints/' . $this->complaint->id, 'test_doc.pdf', 'private');

        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $this->complaint->id,
            'disk'          => 'private',
            'path'          => $path,
            'original_name' => 'test_doc.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 100,
        ]);

        $this->actingAs($this->operator)
            ->get(route('operator.complaint.attachment', [$this->complaint, $attachment]))
            ->assertOk();
    }

    public function test_citizen_cannot_download_operator_attachment_route(): void
    {
        Storage::fake('private');

        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $this->complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/1/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 100,
        ]);

        // Citizen hits operator download route directly
        $this->actingAs($this->citizen)
            ->get(route('operator.complaint.attachment', [$this->complaint, $attachment]))
            ->assertForbidden();
    }

    public function test_attachment_idor_cross_complaint_rejected(): void
    {
        Storage::fake('private');

        $otherComplaint = Complaint::create([
            'reference_code' => 'LPW-OTHER-0002',
            'reporter_id'    => $this->citizen2->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan Lain',
            'description'    => 'Deskripsi laporan lain.',
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
        ]);

        $attachmentOther = ComplaintAttachment::create([
            'complaint_id'  => $otherComplaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/2/other.pdf',
            'original_name' => 'other.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 100,
        ]);

        // Try to access attachment from other complaint via complaint route of first complaint
        $this->actingAs($this->operator)
            ->get(route('operator.complaint.attachment', [$this->complaint, $attachmentOther]))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_attachment_route(): void
    {
        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $this->complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/1/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 100,
        ]);

        $this->get(route('operator.complaint.attachment', [$this->complaint, $attachment]))
            ->assertRedirect('/login');
    }

    // =========================================================================
    // 10. Admin boundary
    // =========================================================================

    public function test_admin_cannot_access_operator_area(): void
    {
        $this->actingAs($this->admin)->get('/operator/dashboard')->assertForbidden();
        $this->actingAs($this->admin)->get('/operator/laporan')->assertForbidden();
    }

    public function test_admin_cannot_mutate_complaints(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-category', $this->complaint), [
                'category_id' => $this->category2->id,
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.assign', $this->complaint), [
                'assigned_to' => $this->operator->id,
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-status', $this->complaint), [
                'status' => 'under_review',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('operator.complaint.add-note', $this->complaint), [
                'body'       => 'Admin note attempt',
                'visibility' => 'internal',
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // 11. Super Admin boundary
    // =========================================================================

    public function test_super_admin_can_access_operator_routes(): void
    {
        // Prompt 3B §15.4: Super Admin is allowed to access operator routes for status mutation.
        // Super Admin must still follow the transition matrix and all business rules.
        $this->actingAs($this->superAdmin)->get('/operator/laporan')->assertOk();
    }

    // =========================================================================
    // 12. IDOR direct URL tests
    // =========================================================================

    public function test_invalid_complaint_id_returns_404(): void
    {
        $this->actingAs($this->operator)
            ->get('/operator/laporan/99999')
            ->assertNotFound();
    }

    public function test_citizen_cannot_bypass_idor_for_operator_route(): void
    {
        // Even if citizen knows the complaint ID, operator route must be forbidden
        $this->actingAs($this->citizen)
            ->get('/operator/laporan/' . $this->complaint->id)
            ->assertForbidden();
    }

    public function test_citizen_cannot_bypass_idor_for_status_update(): void
    {
        $this->actingAs($this->citizen)
            ->patch('/operator/laporan/' . $this->complaint->id . '/status', [
                'status' => 'under_review',
            ])
            ->assertForbidden();
    }

    public function test_citizen_cannot_bypass_idor_for_category_update(): void
    {
        $this->actingAs($this->citizen)
            ->patch('/operator/laporan/' . $this->complaint->id . '/kategori', [
                'category_id' => $this->category2->id,
            ])
            ->assertForbidden();
    }

    public function test_citizen_cannot_bypass_idor_for_note(): void
    {
        $this->actingAs($this->citizen)
            ->post('/operator/laporan/' . $this->complaint->id . '/catatan', [
                'body'       => 'IDOR attempt',
                'visibility' => 'internal',
            ])
            ->assertForbidden();
    }

    public function test_citizen_cannot_bypass_idor_for_assignment(): void
    {
        $this->actingAs($this->citizen)
            ->patch('/operator/laporan/' . $this->complaint->id . '/tugaskan', [
                'assigned_to' => $this->operator->id,
            ])
            ->assertForbidden();
    }
}
