<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Enums\NoteVisibility;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintNote;
use App\Models\ComplaintStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 4B — Finalize Citizen Experience
 *
 * Business decision for `waiting_for_information` is FINAL: H1–H12 = "Tidak".
 * These tests lock the frozen decision at the implementation level:
 *
 *   - There is NO citizen response / reply / additional-evidence mechanism.
 *   - `waiting_for_information` is DISPLAY-ONLY for citizens.
 *   - Citizens cannot mutate lifecycle (status/reporter/assigned_to/internal fields).
 *   - Public communication stays one-directional (staff-authored public_response).
 *   - `closed` remains terminal — no reopen affordance.
 *
 * These tests MUST NOT change frozen business rules — they assert existing behavior.
 */
class CitizenExperienceFinalTest extends TestCase
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
            'description'    => 'Deskripsi laporan pengujian finalisasi citizen experience.',
            'status'         => $status,
            'submitted_at'   => now(),
        ]);
    }

    // =========================================================================
    // FROZEN DECISION H1–H7 — no citizen response mechanism exists (routes)
    // =========================================================================

    public function test_no_citizen_response_or_reply_route_exists(): void
    {
        // No route may exist that would let a citizen reply / add information /
        // add evidence after a complaint is submitted (H1, H2, H3, H7 = Tidak).
        $forbidden = [
            'response', 'reply', 'balas', 'tanggapi', 'feedback',
            'informasi', 'information', 'bukti', 'evidence', 'followup', 'follow-up',
        ];

        foreach (Route::getRoutes() as $route) {
            $uri = strtolower($route->uri());
            if (!str_starts_with($uri, 'laporan')) {
                continue;
            }
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $uri,
                    "Citizen route [{$uri}] suggests a citizen response mechanism, which is FORBIDDEN by H1–H12 = Tidak."
                );
            }
        }
    }

    public function test_only_staff_can_write_complaint_notes(): void
    {
        // The only note-writing route must be staff-only (operator/super_admin).
        // There must be no citizen-facing note/response creation route.
        $noteRoutes = collect(Route::getRoutes())->filter(function ($route) {
            return in_array('POST', $route->methods(), true)
                && str_contains(strtolower($route->uri()), 'catatan');
        });

        $this->assertNotEmpty($noteRoutes, 'Expected the staff note route to still exist.');

        foreach ($noteRoutes as $route) {
            $this->assertStringStartsWith(
                'operator/',
                $route->uri(),
                "Note-writing route [{$route->uri()}] must remain staff-only."
            );
        }

        // And no citizen route may accept a POST to a notes/response path.
        $citizenWrites = collect(Route::getRoutes())->first(function ($route) {
            return str_starts_with($route->uri(), 'laporan')
                && in_array('POST', $route->methods(), true);
        });

        $this->assertNotNull($citizenWrites, 'Citizen must still be able to create a complaint.');
        $this->assertStringContainsString('buat', $citizenWrites->uri());
    }

    // =========================================================================
    // FROZEN DECISION H1–H7 — waiting_for_information is DISPLAY-ONLY
    // =========================================================================

    public function test_waiting_for_information_status_is_displayable(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::WaitingForInformation);

        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => 'under_review',
            'to_status'    => 'waiting_for_information',
            'changed_by'   => $this->operator->id,
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Menunggu Informasi');
    }

    public function test_waiting_for_information_has_no_citizen_reply_affordance(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::WaitingForInformation);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();

        // No call-to-action that would imply a citizen can respond through the system.
        foreach (['Balas', 'Kirim Informasi', 'Tambah Informasi', 'Tambahkan Informasi',
                  'Tambah Bukti', 'Tambahkan Bukti', 'Upload Bukti', 'Unggah Bukti'] as $label) {
            $response->assertDontSee($label);
        }
    }

    public function test_waiting_for_information_page_has_no_response_form(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::WaitingForInformation);

        $html = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint))
            ->getContent();

        // A response form would post/put to a citizen endpoint. There must be none.
        $this->assertStringNotContainsString('citizen.complaint.response', $html);
        $this->assertStringNotContainsString('citizen.complaint.reply', $html);
        $this->assertStringNotContainsString('citizen.complaint.information', $html);
    }

    // =========================================================================
    // FROZEN DECISION H8 & H9 — no citizen-driven status change
    // =========================================================================

    public function test_citizen_cannot_change_complaint_status_via_request(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::WaitingForInformation);
        $before = $complaint->status;

        // Attempt a status-tampering POST. There is no citizen endpoint that would
        // accept this, so the request must not mutate the stored status.
        $this->actingAs($this->citizenA)
            ->post(route('citizen.complaint.show', $complaint), [
                'status' => 'in_progress',
            ]);

        $this->assertSame(
            $before->value,
            $complaint->fresh()->status->value,
            'Citizen must NOT be able to change complaint status (H8/H9 = Tidak).'
        );
    }

    public function test_citizen_cannot_override_reporter_id_on_creation(): void
    {
        $this->actingAs($this->citizenA)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title'       => 'Uji reporter_id tampering',
            'description' => 'Mencoba menetapkan reporter_id milik user lain.',
            'reporter_id' => $this->citizenB->id,
            'status'      => 'closed',
            'assigned_to' => $this->operator->id,
        ]);

        $complaint = Complaint::where('title', 'Uji reporter_id tampering')->first();
        $this->assertNotNull($complaint);
        $this->assertSame($this->citizenA->id, $complaint->reporter_id);
        $this->assertSame('submitted', $complaint->status->value);
        $this->assertNull($complaint->assigned_to);
    }

    // =========================================================================
    // FROZEN DECISION — closed is terminal (no reopen)
    // =========================================================================

    public function test_closed_complaint_has_no_reopen_affordance(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::Closed);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Ditutup');
        $response->assertDontSee('Buka Kembali');
        $response->assertDontSee('Reopen');
    }

    // =========================================================================
    // FROZEN DECISION — one-directional public communication preserved
    // =========================================================================

    public function test_staff_public_response_is_visible_and_internal_note_is_not(): void
    {
        $complaint = $this->makeComplaint($this->citizenA, ComplaintStatus::WaitingForInformation);

        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::Internal,
            'body'         => 'CATATAN INTERNAL 4B — TIDAK BOLEH TAMPIL',
            'created_at'   => now(),
        ]);
        ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'author_id'    => $this->operator->id,
            'visibility'   => NoteVisibility::PublicResponse,
            'body'         => 'TANGGAPAN RESMI 4B UNTUK WARGA',
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('TANGGAPAN RESMI 4B UNTUK WARGA');
        $response->assertDontSee('CATATAN INTERNAL 4B — TIDAK BOLEH TAMPIL');
    }

    // =========================================================================
    // Isolation — citizen cannot reach another citizen's complaint
    // =========================================================================

    public function test_citizen_cannot_view_another_citizens_complaint(): void
    {
        $other = $this->makeComplaint($this->citizenB);

        $this->actingAs($this->citizenA)
            ->get(route('citizen.complaint.show', $other))
            ->assertForbidden();
    }

    // =========================================================================
    // No petugas active route
    // =========================================================================

    public function test_no_petugas_route_exists(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString(
                'petugas',
                strtolower($route->uri()),
                'The historical petugas role must not expose an active route.'
            );
        }
    }
}
