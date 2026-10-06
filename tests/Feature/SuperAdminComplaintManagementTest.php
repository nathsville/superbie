<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatusHistory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 12 — Super Admin "Semua Laporan" (READ-ONLY monitoring).
 *
 * Covers §24 test requirements:
 *   - Authorization: Super Admin only; masyarakat/operator/admin → 403; guest →
 *     login; INACTIVE super admin → denied.
 *   - Index: real DB rows, server-side pagination, honest empty state, search,
 *     status/category/Dinas-Unit/date filters, combined filters.
 *   - Detail: real data, status history timeline, historical routing, assignment.
 *   - IDOR: hand-crafted detail requests denied for other roles + inactive admin.
 *   - Read-only: no mutation route exists for super-admin complaints.
 *   - Historical safety: opening a complaint mutates NOTHING.
 *   - Performance: the list eager-loads relations (no N+1).
 */
class SuperAdminComplaintManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->operator   = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
    }

    private function makeComplaint(array $attributes = []): Complaint
    {
        return Complaint::factory()->create($attributes);
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    public function test_super_admin_can_access_list_and_detail(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.show', $complaint))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $complaint = $this->makeComplaint();

        $this->get(route('super-admin.complaints.index'))->assertRedirect(route('login'));
        $this->get(route('super-admin.complaints.show', $complaint))->assertRedirect(route('login'));
    }

    public function test_non_super_admin_roles_are_forbidden(): void
    {
        $complaint = $this->makeComplaint();

        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.complaints.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.complaints.show', $complaint))->assertForbidden();
        }
    }

    public function test_inactive_super_admin_is_denied(): void
    {
        $complaint = $this->makeComplaint();
        $inactive = User::factory()->create(['role' => 'super_admin', 'is_active' => false]);

        $this->actingAs($inactive)
            ->get(route('super-admin.complaints.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($inactive)
            ->get(route('super-admin.complaints.show', $complaint))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // IDOR
    // =========================================================================

    public function test_idor_direct_detail_request_is_rejected_for_other_roles(): void
    {
        $complaint = $this->makeComplaint();

        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)
                ->get("/super-admin/laporan/{$complaint->id}")
                ->assertForbidden();
        }
    }

    public function test_unknown_complaint_id_returns_not_found(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/super-admin/laporan/999999')
            ->assertNotFound();
    }

    // =========================================================================
    // INDEX
    // =========================================================================

    public function test_index_lists_real_database_complaints(): void
    {
        $a = $this->makeComplaint(['title' => 'Jalan Berlubang Parah']);
        $b = $this->makeComplaint(['title' => 'Lampu Jalan Mati']);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'));

        $response->assertOk();
        $response->assertSee('Jalan Berlubang Parah');
        $response->assertSee('Lampu Jalan Mati');
        $response->assertSee($a->reference_code);
        $response->assertSee($b->reference_code);
        $response->assertViewHas('complaints', fn ($c) => $c->total() === 2);
    }

    public function test_empty_state_is_shown_when_no_complaints(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'));

        $response->assertOk();
        $response->assertSee('Belum ada laporan yang sesuai dengan filter.');
        $response->assertViewHas('complaints', fn ($c) => $c->total() === 0);
    }

    public function test_pagination_is_server_side(): void
    {
        $this->makeComplaint();
        for ($i = 0; $i < 24; $i++) {
            $this->makeComplaint();
        }

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'));

        $response->assertOk();
        $response->assertViewHas('complaints', function ($complaints) {
            return $complaints->perPage() === 20
                && $complaints->total() === 25
                && $complaints->count() === 20;
        });
    }

    // =========================================================================
    // SEARCH & FILTERS
    // =========================================================================

    public function test_search_by_reference_code_title_and_description(): void
    {
        $byRef  = $this->makeComplaint(['reference_code' => 'LPW-20260101-AAAA']);
        $byTtl  = $this->makeComplaint(['title' => 'KataUnikJudul']);
        $byDesc = $this->makeComplaint(['description' => 'Deskripsi dengan KataUnikDeskripsi di dalamnya.']);
        $this->makeComplaint(['title' => 'Tidak Cocok']);

        $r1 = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['q' => 'LPW-20260101-AAAA']));
        $r1->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $byRef->id);

        $r2 = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['q' => 'KataUnikJudul']));
        $r2->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $byTtl->id);

        $r3 = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['q' => 'KataUnikDeskripsi']));
        $r3->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $byDesc->id);
    }

    public function test_status_filter_works_and_rejects_unknown_status(): void
    {
        $submitted = $this->makeComplaint(['status' => ComplaintStatus::Submitted]);
        $resolved  = $this->makeComplaint(['status' => ComplaintStatus::Resolved]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['status' => 'resolved']));

        $response->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $resolved->id);

        // Unknown status must be ignored (allowlist), never injected.
        $ignored = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['status' => 'not_a_status']));
        $ignored->assertOk();
        $ignored->assertViewHas('complaints', fn ($c) => $c->total() === 2);
    }

    public function test_category_filter_works(): void
    {
        $catA = ComplaintCategory::factory()->create();
        $catB = ComplaintCategory::factory()->create();
        $inA  = $this->makeComplaint(['category_id' => $catA->id]);
        $this->makeComplaint(['category_id' => $catB->id]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['category' => $catA->id]));

        $response->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $inA->id);
    }

    public function test_dinas_unit_filter_works(): void
    {
        $unitA = DinasUnit::factory()->create();
        $unitB = DinasUnit::factory()->create();
        $inA   = $this->makeComplaint(['dinas_unit_id' => $unitA->id]);
        $this->makeComplaint(['dinas_unit_id' => $unitB->id]);
        $this->makeComplaint(['dinas_unit_id' => null]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['dinas_unit' => $unitA->id]));

        $response->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $inA->id);
    }

    public function test_date_range_filter_works(): void
    {
        $old = $this->makeComplaint(['submitted_at' => now()->subDays(10)]);
        $new = $this->makeComplaint(['submitted_at' => now()->subDay()]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index', [
            'date_from' => now()->subDays(3)->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]));

        $response->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $new->id);
    }

    public function test_invalid_date_input_is_ignored_safely(): void
    {
        $this->makeComplaint();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['date_from' => 'not-a-date']));

        $response->assertOk();
        $response->assertViewHas('complaints', fn ($c) => $c->total() === 1);
    }

    public function test_combined_filters_work(): void
    {
        $cat = ComplaintCategory::factory()->create();
        $unit = DinasUnit::factory()->create();

        $match = $this->makeComplaint([
            'category_id' => $cat->id,
            'dinas_unit_id' => $unit->id,
            'status' => ComplaintStatus::InProgress,
        ]);
        // Same category, different status → excluded.
        $this->makeComplaint([
            'category_id' => $cat->id,
            'dinas_unit_id' => $unit->id,
            'status' => ComplaintStatus::Submitted,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index', [
            'category'   => $cat->id,
            'dinas_unit' => $unit->id,
            'status'     => 'in_progress',
        ]));

        $response->assertViewHas('complaints', fn ($c) => $c->total() === 1 && $c->first()->id === $match->id);
    }

    public function test_search_is_safe_from_sql_injection_payloads(): void
    {
        $this->makeComplaint(['title' => 'Aman Sentosa']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.index', ['q' => "' OR 1=1 --"]));

        // No error, no leak — the payload is treated as a literal string.
        $response->assertOk();
        $response->assertViewHas('complaints', fn ($c) => $c->total() === 0);
    }

    // =========================================================================
    // DETAIL
    // =========================================================================

    public function test_detail_shows_real_complaint_data_and_routing_and_assignment(): void
    {
        $category = ComplaintCategory::factory()->create(['name' => 'Infrastruktur Jalan']);
        $unit     = DinasUnit::factory()->create(['name' => 'Dinas Pekerjaan Umum']);
        $assignee = User::factory()->create(['name' => 'Operator Satu', 'role' => 'operator']);

        $complaint = $this->makeComplaint([
            'category_id'   => $category->id,
            'dinas_unit_id' => $unit->id,
            'assigned_to'   => $assignee->id,
            'title'         => 'Judul Detail Unik',
            'description'   => 'Deskripsi detail unik untuk pengujian.',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint));

        $response->assertOk();
        $response->assertSee($complaint->reference_code);
        $response->assertSee('Judul Detail Unik');
        $response->assertSee('Deskripsi detail unik untuk pengujian.');
        $response->assertSee('Infrastruktur Jalan');
        $response->assertSee('Dinas Pekerjaan Umum');
        $response->assertSee('Operator Satu');
    }

    public function test_detail_shows_status_history_timeline(): void
    {
        $complaint = $this->makeComplaint();

        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => null,
            'to_status'    => 'submitted',
            'changed_by'   => $this->citizen->id,
            'note'         => 'Laporan berhasil dibuat dan diajukan ke sistem.',
            'created_at'   => now()->subDays(2),
        ]);
        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => 'submitted',
            'to_status'    => 'under_review',
            'changed_by'   => $this->operator->id,
            'note'         => 'Sedang ditinjau oleh operator.',
            'created_at'   => now()->subDay(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint));

        $response->assertOk();
        $response->assertSee('Riwayat Status');
        $response->assertSee('Sedang ditinjau oleh operator.');
        $response->assertSee('Sedang Ditinjau'); // label of under_review
        $response->assertViewHas('complaint', fn ($c) => $c->statusHistories->count() === 2);
    }

    public function test_detail_shows_attachment_metadata(): void
    {
        $complaint = $this->makeComplaint();

        ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/' . $complaint->id . '/contoh.pdf',
            'original_name' => 'bukti-foto-jalan.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 2048,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint));

        $response->assertOk();
        $response->assertSee('bukti-foto-jalan.pdf');
        $response->assertViewHas('complaint', fn ($c) => $c->attachments->count() === 1);
    }

    public function test_detail_empty_state_when_no_history_notes_or_attachments(): void
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.complaints.show', $complaint));

        $response->assertOk();
        $response->assertSee('Belum ada riwayat.');
        $response->assertSee('Belum ada catatan internal.');
        $response->assertSee('Belum ada respons publik.');
        $response->assertSee('Tidak ada lampiran.');
    }

    // =========================================================================
    // READ-ONLY / NO MUTATION
    // =========================================================================

    public function test_no_super_admin_complaint_mutation_routes_exist(): void
    {
        $mutating = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'super-admin/laporan'))
            ->flatMap(fn ($route) => $route->methods())
            ->filter(fn ($method) => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true))
            ->values();

        $this->assertTrue($mutating->isEmpty(), 'Super Admin complaint surface must be read-only: ' . $mutating->implode(', '));
    }

    public function test_super_admin_cannot_post_to_complaint_routes(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)
            ->post("/super-admin/laporan/{$complaint->id}", [])
            ->assertStatus(405);

        $this->actingAs($this->superAdmin)
            ->delete("/super-admin/laporan/{$complaint->id}")
            ->assertStatus(405);
    }

    // =========================================================================
    // HISTORICAL SAFETY — reading must never mutate
    // =========================================================================

    public function test_opening_a_complaint_does_not_mutate_any_state(): void
    {
        $category = ComplaintCategory::factory()->create();
        $otherCategory = ComplaintCategory::factory()->create();
        $unit = DinasUnit::factory()->create();
        $assignee = User::factory()->create(['role' => 'operator']);

        $complaint = $this->makeComplaint([
            'category_id'   => $category->id,
            'dinas_unit_id' => $unit->id,
            'assigned_to'   => $assignee->id,
            'status'        => ComplaintStatus::InProgress,
        ]);

        ComplaintStatusHistory::create([
            'complaint_id' => $complaint->id,
            'from_status'  => null,
            'to_status'    => 'submitted',
            'changed_by'   => $this->citizen->id,
            'created_at'   => now()->subDay(),
        ]);

        $before = $complaint->fresh()->toArray();
        $historyBefore = ComplaintStatusHistory::where('complaint_id', $complaint->id)->count();

        // Read the list AND the detail.
        $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.show', $complaint))->assertOk();

        $after = $complaint->fresh()->toArray();

        $this->assertSame($before, $after, 'Reading a complaint must not mutate it.');
        $this->assertSame($complaint->category_id, $category->id);
        $this->assertNotSame($complaint->category_id, $otherCategory->id);
        $this->assertSame($complaint->dinas_unit_id, $unit->id);
        $this->assertSame($complaint->assigned_to, $assignee->id);
        $this->assertSame($complaint->status, ComplaintStatus::InProgress);
        $this->assertSame($historyBefore, ComplaintStatusHistory::where('complaint_id', $complaint->id)->count());
    }

    // =========================================================================
    // PERFORMANCE — eager loading (no N+1)
    // =========================================================================

    public function test_index_eager_loads_relations_without_n_plus_one(): void
    {
        DB::enableQueryLog();

        // 3 complaints
        foreach (range(1, 3) as $i) {
            $this->makeComplaint();
        }
        DB::flushQueryLog(); // drop the factory INSERT statements
        $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'))->assertOk();
        $queriesForThree = count(DB::getQueryLog());

        // 6 complaints (same page size — still one page)
        foreach (range(1, 3) as $i) {
            $this->makeComplaint();
        }
        DB::flushQueryLog(); // drop the factory INSERT statements
        $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'))->assertOk();
        $queriesForSix = count(DB::getQueryLog());

        DB::disableQueryLog();

        // Doubling the row count must NOT increase the query count (proves the
        // rendered relations are eager-loaded rather than lazy-loaded per row).
        $this->assertSame(
            $queriesForThree,
            $queriesForSix,
            "Complaint list query count grew with row count (N+1): {$queriesForThree} → {$queriesForSix}"
        );
    }

    // =========================================================================
    // NAVIGATION
    // =========================================================================

    public function test_navigation_route_is_registered_and_old_placeholder_is_gone(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('super-admin.complaints.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('super-admin.complaints.show'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('super-admin.complaint.index'));
    }

    public function test_nav_renders_semua_laporan_as_active_link(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.complaints.index'));

        $response->assertOk();
        $response->assertSee('Semua Laporan');
        $response->assertSee(route('super-admin.complaints.index'), false);
    }
}
