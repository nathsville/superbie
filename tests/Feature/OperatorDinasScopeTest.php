<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\DashboardCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 15 — Operator Dinas/Unit scoping + IDOR hardening.
 *
 * Approved contract under test (do NOT re-interpret):
 *   BDR-1 = 1a : each Operator belongs to EXACTLY ONE Dinas/Unit (`users.dinas_unit_id`).
 *   BDR-2 = 2c : HYBRID scope (see rule below).
 *   BDR-3      : an Operator WITHOUT a unit is DENIED everything (no global fallback).
 *   Super Admin: GLOBAL scope (sees everything; may mutate status) — never scoped.
 *
 * Formal visibility rule (§4) — a Complaint is visible to an Operator iff:
 *   operator.dinas_unit_id IS NOT NULL
 *   AND (
 *        complaint.dinas_unit_id = operator.dinas_unit_id
 *        OR (complaint.dinas_unit_id IS NULL
 *            AND the complaint's category is mapped to operator.dinas_unit_id
 *            via `category_dinas_unit`)
 *   )
 *
 * Out-of-scope access to a per-complaint endpoint answers 404 (not 403) so the
 * endpoint does not leak the existence of complaints outside the Operator scope.
 */
class OperatorDinasScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $operatorA;
    private User $operatorB;
    private User $operatorNoUnit;
    private User $citizen;

    private DinasUnit $unitA;
    private DinasUnit $unitB;

    private ComplaintCategory $categoryA;
    private ComplaintCategory $categoryB;
    private ComplaintCategory $categoryUnmapped;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->unitA = DinasUnit::create([
            'name' => 'Dinas A', 'code' => 'SCOPE-A', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->unitB = DinasUnit::create([
            'name' => 'Dinas B', 'code' => 'SCOPE-B', 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->operatorA = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitA->id,
        ]);
        $this->operatorB = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unitB->id,
        ]);
        // BDR-3 — operator with NO unit: denied, never a global fallback.
        $this->operatorNoUnit = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => null,
        ]);

        $this->categoryA = ComplaintCategory::create([
            'name' => 'Kategori A', 'slug' => 'scope-kategori-a', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->categoryB = ComplaintCategory::create([
            'name' => 'Kategori B', 'slug' => 'scope-kategori-b', 'is_active' => true, 'sort_order' => 2,
        ]);
        $this->categoryUnmapped = ComplaintCategory::create([
            'name' => 'Kategori Tanpa Mapping', 'slug' => 'scope-kategori-unmapped', 'is_active' => true, 'sort_order' => 3,
        ]);

        $this->categoryA->dinasUnits()->attach($this->unitA->id);
        $this->categoryB->dinasUnits()->attach($this->unitB->id);
        // categoryUnmapped intentionally has NO mapping.

        // Make the Super Admin dashboard/actor valid: nothing else needed.
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-SCOPE-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->categoryA->id,
            'dinas_unit_id'  => null,
            'title'          => 'Laporan uji scope',
            'description'    => 'Deskripsi laporan pengujian scope Dinas/Unit.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // §4 — INDEX SCOPING (hybrid rule)
    // =========================================================================

    public function test_operator_index_is_limited_to_own_unit(): void
    {
        $inUnitA  = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id, 'title' => 'SCOPE_IN_A']);
        $inUnitB  = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'title' => 'SCOPE_IN_B']);

        $response = $this->actingAs($this->operatorA)->get(route('operator.complaint.index'));

        $response->assertOk();
        $response->assertSee($inUnitA->reference_code);
        $response->assertDontSee($inUnitB->reference_code);
    }

    public function test_operator_index_includes_unrouted_complaint_mapped_to_own_unit(): void
    {
        // NULL destination, category mapped to unit A → visible to operator A (hybrid).
        $unroutedA = $this->makeComplaint([
            'category_id' => $this->categoryA->id, 'dinas_unit_id' => null, 'title' => 'SCOPE_UNROUTED_A',
        ]);
        // NULL destination, category mapped to unit B → NOT visible to operator A.
        $unroutedB = $this->makeComplaint([
            'category_id' => $this->categoryB->id, 'dinas_unit_id' => null, 'title' => 'SCOPE_UNROUTED_B',
        ]);
        // NULL destination, category with NO mapping → NOT visible to anyone.
        $unmapped = $this->makeComplaint([
            'category_id' => $this->categoryUnmapped->id, 'dinas_unit_id' => null, 'title' => 'SCOPE_UNMAPPED',
        ]);

        $response = $this->actingAs($this->operatorA)->get(route('operator.complaint.index'));

        $response->assertOk();
        $response->assertSee($unroutedA->reference_code);
        $response->assertDontSee($unroutedB->reference_code);
        $response->assertDontSee($unmapped->reference_code);
    }

    public function test_operator_without_unit_sees_nothing_in_index(): void
    {
        $inUnitA = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id, 'title' => 'SCOPE_IN_A']);
        $inUnitB = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'title' => 'SCOPE_IN_B']);

        $response = $this->actingAs($this->operatorNoUnit)->get(route('operator.complaint.index'));

        $response->assertOk();
        $response->assertDontSee($inUnitA->reference_code);
        $response->assertDontSee($inUnitB->reference_code);
        $response->assertSee('Tidak ada laporan ditemukan');
    }

    public function test_super_admin_index_is_global(): void
    {
        $inUnitA = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id, 'title' => 'SCOPE_IN_A']);
        $inUnitB = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id, 'title' => 'SCOPE_IN_B']);

        $response = $this->actingAs($this->superAdmin)->get(route('operator.complaint.index'));

        $response->assertOk();
        $response->assertSee($inUnitA->reference_code);
        $response->assertSee($inUnitB->reference_code);
    }

    // =========================================================================
    // §4 — SHOW SCOPING
    // =========================================================================

    public function test_operator_can_show_same_unit_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk();
    }

    public function test_operator_can_show_unrouted_complaint_mapped_to_own_unit(): void
    {
        $complaint = $this->makeComplaint(['category_id' => $this->categoryA->id, 'dinas_unit_id' => null]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk();
    }

    public function test_operator_cannot_show_other_unit_complaint_returns_404(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertNotFound();
    }

    public function test_operator_cannot_show_unrouted_complaint_mapped_to_other_unit(): void
    {
        $complaint = $this->makeComplaint(['category_id' => $this->categoryB->id, 'dinas_unit_id' => null]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.show', $complaint))
            ->assertNotFound();
    }

    public function test_operator_without_unit_cannot_show_any_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $this->actingAs($this->operatorNoUnit)
            ->get(route('operator.complaint.show', $complaint))
            ->assertNotFound();
    }

    public function test_super_admin_can_show_any_unit_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk();
    }

    // =========================================================================
    // §11 — IDOR: per-complaint mutations must honour the scope (404, not 403)
    // =========================================================================

    public function test_operator_cannot_update_category_out_of_scope(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-category', $complaint), [
                'category_id' => $this->categoryA->id,
            ])
            ->assertNotFound();

        $this->assertSame($this->categoryA->id, $complaint->fresh()->category_id);
    }

    public function test_operator_cannot_update_destination_out_of_scope(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-destination', $complaint), [
                'dinas_unit_id' => $this->unitA->id,
            ])
            ->assertNotFound();

        $this->assertSame($this->unitB->id, $complaint->fresh()->dinas_unit_id);
    }

    public function test_operator_cannot_assign_out_of_scope(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.assign', $complaint), [
                'assigned_to' => $this->operatorA->id,
            ])
            ->assertNotFound();

        $this->assertNull($complaint->fresh()->assigned_to);
    }

    public function test_operator_cannot_update_status_out_of_scope(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-status', $complaint), [
                'status' => 'under_review',
            ])
            ->assertNotFound();

        $this->assertSame('submitted', $complaint->fresh()->status->value);
    }

    public function test_operator_cannot_add_note_out_of_scope(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->operatorA)
            ->post(route('operator.complaint.add-note', $complaint), [
                'body' => 'IDOR note attempt',
                'visibility' => 'internal',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('complaint_notes', ['complaint_id' => $complaint->id]);
    }

    public function test_operator_cannot_download_attachment_out_of_scope(): void
    {
        Storage::fake('private');

        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => 'complaints/'.$complaint->id.'/secret.pdf',
            'original_name' => 'secret.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 10,
        ]);
        Storage::disk('private')->put($attachment->path, 'x');

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.attachment', [$complaint, $attachment]))
            ->assertNotFound();
    }

    public function test_operator_without_unit_cannot_mutate_any_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $this->actingAs($this->operatorNoUnit)
            ->patch(route('operator.complaint.update-status', $complaint), ['status' => 'under_review'])
            ->assertNotFound();

        $this->actingAs($this->operatorNoUnit)
            ->post(route('operator.complaint.add-note', $complaint), [
                'body' => 'n', 'visibility' => 'internal',
            ])
            ->assertNotFound();

        $this->assertSame('submitted', $complaint->fresh()->status->value);
    }

    // =========================================================================
    // In-scope control — the same endpoints DO work within scope
    // =========================================================================

    public function test_operator_can_mutate_same_unit_complaint(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $this->actingAs($this->operatorA)
            ->patch(route('operator.complaint.update-status', $complaint), ['status' => 'under_review'])
            ->assertRedirect(route('operator.complaint.show', $complaint));

        $this->assertSame('under_review', $complaint->fresh()->status->value);
    }

    public function test_operator_can_download_attachment_in_scope(): void
    {
        Storage::fake('private');

        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $file = UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf');
        $path = $file->storeAs('complaints/'.$complaint->id, 'ok.pdf', 'private');

        $attachment = ComplaintAttachment::create([
            'complaint_id'  => $complaint->id,
            'disk'          => 'private',
            'path'          => $path,
            'original_name' => 'ok.pdf',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 10,
        ]);

        $this->actingAs($this->operatorA)
            ->get(route('operator.complaint.attachment', [$complaint, $attachment]))
            ->assertOk();
    }

    public function test_super_admin_can_mutate_any_complaint_status(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);

        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-status', $complaint), ['status' => 'under_review'])
            ->assertRedirect(route('operator.complaint.show', $complaint));

        $this->assertSame('under_review', $complaint->fresh()->status->value);
    }

    // =========================================================================
    // Model-level rule — scope + per-instance guard agree
    // =========================================================================

    public function test_scope_and_guard_agree_on_visibility(): void
    {
        $inA      = $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $unroutedA = $this->makeComplaint(['category_id' => $this->categoryA->id, 'dinas_unit_id' => null]);
        $inB      = $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);
        $unroutedB = $this->makeComplaint(['category_id' => $this->categoryB->id, 'dinas_unit_id' => null]);

        $this->assertTrue($inA->isVisibleToOperator($this->operatorA));
        $this->assertTrue($unroutedA->isVisibleToOperator($this->operatorA));
        $this->assertFalse($inB->isVisibleToOperator($this->operatorA));
        $this->assertFalse($unroutedB->isVisibleToOperator($this->operatorA));

        // Null-unit operator: everything denied.
        $this->assertFalse($inA->isVisibleToOperator($this->operatorNoUnit));

        $visibleIds = Complaint::visibleToOperator($this->operatorA)->pluck('id')->all();
        $this->assertContains($inA->id, $visibleIds);
        $this->assertContains($unroutedA->id, $visibleIds);
        $this->assertNotContains($inB->id, $visibleIds);
        $this->assertNotContains($unroutedB->id, $visibleIds);
    }

    public function test_scope_returns_nothing_for_operator_without_unit(): void
    {
        $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $this->assertSame(0, Complaint::visibleToOperator($this->operatorNoUnit)->count());
    }

    // =========================================================================
    // Dashboard scoping + cache key (Prompt 15 §17)
    // =========================================================================

    public function test_operator_dashboard_is_scoped(): void
    {
        $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);
        $this->makeComplaint(['dinas_unit_id' => $this->unitB->id]);
        $this->makeComplaint(['category_id' => $this->categoryA->id, 'dinas_unit_id' => null]);

        $service = app(DashboardCacheService::class);

        $this->assertSame(2, $service->getOperatorData($this->operatorA, true)['totalLaporan']);
        $this->assertSame(1, $service->getOperatorData($this->operatorB, true)['totalLaporan']);
        // Super Admin keeps the GLOBAL operational overview.
        $this->assertSame(3, $service->getOperatorData($this->superAdmin, true)['totalLaporan']);
    }

    public function test_operator_without_unit_has_empty_dashboard(): void
    {
        $this->makeComplaint(['dinas_unit_id' => $this->unitA->id]);

        $service = app(DashboardCacheService::class);

        $this->assertSame(0, $service->getOperatorData($this->operatorNoUnit, true)['totalLaporan']);
    }

    public function test_operator_cache_key_is_scoped_and_versioned(): void
    {
        $service = app(DashboardCacheService::class);

        $keyA = $service->operatorCacheKey($this->operatorA);
        $keyB = $service->operatorCacheKey($this->operatorB);
        $keyNoUnit = $service->operatorCacheKey($this->operatorNoUnit);
        $keyGlobal = $service->operatorCacheKey($this->superAdmin);

        // Distinct scope per unit; null-unit operator gets its own (denied) scope.
        $this->assertNotSame($keyA, $keyB);
        $this->assertNotSame($keyA, $keyNoUnit);
        $this->assertStringContainsString('unit:'.$this->unitA->id, $keyA);
        $this->assertStringContainsString('unit:none', $keyNoUnit);
        $this->assertStringContainsString('global', $keyGlobal);

        // Bumping the version invalidates every operator key at once.
        $service->bumpOperatorCacheVersion();
        $this->assertNotSame($keyA, $service->operatorCacheKey($this->operatorA));
    }

    // =========================================================================
    // §22 — User Management: dinas_unit_id is required for Operators
    // =========================================================================

    public function test_super_admin_must_supply_unit_when_creating_operator(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), [
                'name' => 'Operator Tanpa Unit',
                'email' => 'operator.tanpa.unit@superbie.local',
                'password' => 'RahasiaKuat123!',
                'password_confirmation' => 'RahasiaKuat123!',
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => '',
            ])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertDatabaseMissing('users', ['email' => 'operator.tanpa.unit@superbie.local']);
    }

    public function test_super_admin_can_create_operator_with_unit(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), [
                'name' => 'Operator Berunit',
                'email' => 'operator.berunit@superbie.local',
                'password' => 'RahasiaKuat123!',
                'password_confirmation' => 'RahasiaKuat123!',
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->unitA->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'operator.berunit@superbie.local',
            'dinas_unit_id' => $this->unitA->id,
        ]);
    }

    public function test_non_operator_user_never_keeps_a_unit(): void
    {
        // A crafted payload tries to give an admin a unit → it is cleared server-side.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.store'), [
                'name' => 'Admin Unit Palsu',
                'email' => 'admin.unit.palsu@superbie.local',
                'password' => 'RahasiaKuat123!',
                'password_confirmation' => 'RahasiaKuat123!',
                'role' => 'admin',
                'is_active' => '1',
                'dinas_unit_id' => $this->unitA->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin.unit.palsu@superbie.local',
            'dinas_unit_id' => null,
        ]);
    }

    public function test_promoting_citizen_to_operator_requires_unit(): void
    {
        $target = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => '',
            ])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertSame('masyarakat', $target->fresh()->role);
    }

    public function test_changing_operator_unit_is_audited(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'operator',
                'is_active' => '1',
                'dinas_unit_id' => $this->unitB->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertSame($this->unitB->id, $this->operatorA->fresh()->dinas_unit_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.dinas_unit_changed',
            'subject_type' => 'user',
            'subject_id' => $this->operatorA->id,
        ]);
    }

    public function test_demoting_operator_clears_the_unit(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.users.update', $this->operatorA), [
                'name' => $this->operatorA->name,
                'email' => $this->operatorA->email,
                'role' => 'admin',
                'is_active' => '1',
                'dinas_unit_id' => $this->unitA->id,
            ])
            ->assertRedirect(route('super-admin.users.index'));

        $this->assertSame('admin', $this->operatorA->fresh()->role);
        $this->assertNull($this->operatorA->fresh()->dinas_unit_id);
    }
}
