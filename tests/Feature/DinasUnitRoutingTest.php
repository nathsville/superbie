<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 5C — Dinas/Unit Master Data & Category Routing
 *
 * Covers:
 *   - Super Admin CRUD Dinas/Unit (authorization server-side)
 *   - active/inactive behaviour
 *   - Category ↔ Dinas/Unit many-to-many mapping management
 *   - Operator actual-destination selection with server-side validation
 *   - Historical stability (mapping changes never alter existing complaints)
 *   - Privacy / role authorization
 */
class DinasUnitRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $operator;
    private User $admin;
    private User $citizen;
    private ComplaintCategory $category;
    private DinasUnit $operatorUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // Prompt 15 — operators are scoped to a Dinas/Unit. The category is mapped
        // to the operator's unit so unrouted complaints are in scope.
        $this->operatorUnit = DinasUnit::create([
            'name' => 'Dinas Scope Operator', 'code' => 'SCOPE-OP',
            'is_active' => true, 'sort_order' => 0,
        ]);

        $this->operator   = User::factory()->create([
            'role' => 'operator', 'is_active' => true,
            'dinas_unit_id' => $this->operatorUnit->id,
        ]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name' => 'Jalan Rusak', 'slug' => 'jalan-rusak', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->category->dinasUnits()->attach($this->operatorUnit->id);
    }

    private static int $dinasSeq = 0;

    private function makeDinas(array $overrides = []): DinasUnit
    {
        return DinasUnit::create(array_merge([
            'name' => 'Dinas Uji ' . uniqid(), 'code' => 'UJI' . (++self::$dinasSeq),
            'is_active' => true, 'sort_order' => 0,
        ], $overrides));
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-DU-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan uji routing',
            'description'    => 'Deskripsi laporan pengujian routing dinas.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // AUTHORIZATION — SUPER ADMIN ONLY
    // =========================================================================

    public function test_only_super_admin_can_access_dinas_management(): void
    {
        foreach ([$this->operator, $this->admin, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.dinas.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.dinas.create'))->assertForbidden();
            $this->actingAs($user)->post(route('super-admin.dinas.store'), ['name' => 'X'])->assertForbidden();
        }

        $this->actingAs($this->superAdmin)->get(route('super-admin.dinas.index'))->assertOk();
    }

    public function test_guest_cannot_access_dinas_management(): void
    {
        $this->get(route('super-admin.dinas.index'))->assertRedirect(route('login'));
    }

    public function test_only_super_admin_can_manage_mapping(): void
    {
        $dinas = $this->makeDinas();

        $this->actingAs($this->operator)
            ->put(route('super-admin.categories.mapping.update', $this->category), ['dinas_unit_ids' => [$dinas->id]])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('super-admin.categories.mapping.update', $this->category), ['dinas_unit_ids' => [$dinas->id]])
            ->assertForbidden();

        $this->assertDatabaseMissing('category_dinas_unit', [
            'category_id' => $this->category->id, 'dinas_unit_id' => $dinas->id,
        ]);
    }

    // =========================================================================
    // CRUD
    // =========================================================================

    public function test_super_admin_can_create_dinas_unit(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.dinas.store'), [
            'name' => 'Dinas Baru', 'code' => 'NEW01', 'is_active' => '1', 'sort_order' => 5,
        ])->assertRedirect(route('super-admin.dinas.index'));

        $this->assertDatabaseHas('dinas_units', ['name' => 'Dinas Baru', 'code' => 'NEW01', 'is_active' => 1]);
        // audit log uses project convention (plain string subject_type)
        $this->assertDatabaseHas('audit_logs', ['action' => 'dinas_unit.created', 'subject_type' => 'dinas_unit']);
    }

    public function test_super_admin_can_update_and_deactivate_dinas_unit(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Lama']);

        $this->actingAs($this->superAdmin)->put(route('super-admin.dinas.update', $dinas), [
            'name' => 'Dinas Diubah', 'code' => $dinas->code, 'is_active' => '0', 'sort_order' => 2,
        ])->assertRedirect(route('super-admin.dinas.index'));

        $this->assertDatabaseHas('dinas_units', ['id' => $dinas->id, 'name' => 'Dinas Diubah', 'is_active' => 0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dinas_unit.updated', 'subject_type' => 'dinas_unit']);
    }

    public function test_duplicate_code_rejected(): void
    {
        $this->makeDinas(['code' => 'DUP01']);

        $this->actingAs($this->superAdmin)->post(route('super-admin.dinas.store'), [
            'name' => 'Dinas Lain', 'code' => 'DUP01', 'is_active' => '1',
        ])->assertSessionHasErrors('code');
    }

    // =========================================================================
    // MAPPING — MANY-TO-MANY
    // =========================================================================

    public function test_super_admin_can_map_category_to_many_dinas(): void
    {
        $a = $this->makeDinas();
        $b = $this->makeDinas();

        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$a->id, $b->id],
        ])->assertRedirect();

        $this->assertCount(2, $this->category->fresh()->dinasUnits);
    }

    public function test_dinas_can_map_to_many_categories(): void
    {
        $dinas = $this->makeDinas();
        $catB = ComplaintCategory::create(['name' => 'Kategori B', 'slug' => 'kategori-b', 'is_active' => true]);

        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$dinas->id],
        ])->assertRedirect();
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $catB), [
            'dinas_unit_ids' => [$dinas->id],
        ])->assertRedirect();

        $this->assertCount(2, $dinas->fresh()->categories);
    }

    public function test_mapping_sync_removes_unchecked(): void
    {
        $a = $this->makeDinas();
        $b = $this->makeDinas();
        $this->category->dinasUnits()->attach([$a->id, $b->id]);

        // Only $a kept.
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$a->id],
        ])->assertRedirect();

        $this->assertDatabaseMissing('category_dinas_unit', ['category_id' => $this->category->id, 'dinas_unit_id' => $b->id]);
        $this->assertDatabaseHas('category_dinas_unit', ['category_id' => $this->category->id, 'dinas_unit_id' => $a->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category_mapping.updated', 'subject_type' => 'complaint_category']);
    }

    // =========================================================================
    // OPERATOR — ACTUAL DESTINATION
    // =========================================================================

    public function test_operator_can_set_destination_from_mapped_active_dinas(): void
    {
        $pupr = $this->makeDinas(['name' => 'Dinas PU', 'is_active' => true]);
        $dishub = $this->makeDinas(['name' => 'Dinas Perhubungan', 'is_active' => true]);
        $this->category->dinasUnits()->attach([$pupr->id, $dishub->id]);

        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)->patch(route('operator.complaint.update-destination', $complaint), [
            'dinas_unit_id' => $pupr->id,
        ])->assertRedirect(route('operator.complaint.show', $complaint));

        $this->assertSame($pupr->id, $complaint->fresh()->dinas_unit_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.destination_updated', 'subject_type' => 'complaint']);
    }

    public function test_destination_not_mapped_to_category_is_rejected(): void
    {
        $mapped = $this->makeDinas();
        $unmapped = $this->makeDinas();
        $this->category->dinasUnits()->attach($mapped->id);

        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)->patch(route('operator.complaint.update-destination', $complaint), [
            'dinas_unit_id' => $unmapped->id,
        ])->assertSessionHasErrors('dinas_unit_id');

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    public function test_inactive_dinas_cannot_be_new_destination(): void
    {
        $inactive = $this->makeDinas(['is_active' => false]);
        $this->category->dinasUnits()->attach($inactive->id);

        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)->patch(route('operator.complaint.update-destination', $complaint), [
            'dinas_unit_id' => $inactive->id,
        ])->assertSessionHasErrors('dinas_unit_id');

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    public function test_operator_can_clear_destination(): void
    {
        // Prompt 15 — the complaint must be within the operator's scope to be
        // actionable. Route it to the operator's own unit, then clear it.
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->operatorUnit->id]);

        $this->actingAs($this->operator)->patch(route('operator.complaint.update-destination', $complaint), [
            'dinas_unit_id' => '',
        ])->assertRedirect();

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // ACTIVE / INACTIVE + HISTORICAL STABILITY
    // =========================================================================

    public function test_inactive_dinas_keeps_existing_complaint_and_is_not_auto_changed(): void
    {
        $pupr = $this->makeDinas(['name' => 'Dinas PU', 'is_active' => true]);
        $dishub = $this->makeDinas(['name' => 'Dinas Perhubungan', 'is_active' => true]);
        $this->category->dinasUnits()->attach([$pupr->id, $dishub->id]);

        $complaint = $this->makeComplaint(['dinas_unit_id' => $pupr->id]);

        // Deactivate PUPR.
        $this->actingAs($this->superAdmin)->put(route('super-admin.dinas.update', $pupr), [
            'name' => $pupr->name, 'code' => $pupr->code, 'is_active' => '0', 'sort_order' => 0,
        ])->assertRedirect();

        // Complaint still points to (now inactive) PUPR — not unassigned/moved/deleted.
        $this->assertSame($pupr->id, $complaint->fresh()->dinas_unit_id);
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id]);
    }

    public function test_inactive_existing_destination_remains_selectable_for_that_complaint(): void
    {
        $pupr = $this->makeDinas(['name' => 'Dinas PU', 'is_active' => false]);
        $this->category->dinasUnits()->attach($pupr->id);

        // Prompt 15 — the operator must belong to the unit that owns the complaint
        // for it to be actionable (an operator only handles its own Dinas/Unit).
        $this->operator->forceFill(['dinas_unit_id' => $pupr->id])->save();

        $complaint = $this->makeComplaint(['dinas_unit_id' => $pupr->id]);

        // Re-submitting the same (inactive) destination must be accepted (no change).
        $this->actingAs($this->operator)->patch(route('operator.complaint.update-destination', $complaint), [
            'dinas_unit_id' => $pupr->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($pupr->id, $complaint->fresh()->dinas_unit_id);
    }

    public function test_removing_mapping_does_not_change_existing_complaint_destination(): void
    {
        $pupr = $this->makeDinas(['name' => 'Dinas PU']);
        $this->category->dinasUnits()->attach($pupr->id);

        $complaint = $this->makeComplaint(['dinas_unit_id' => $pupr->id]);

        // Super Admin removes the mapping entirely.
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [],
        ])->assertRedirect();

        // Complaint keeps its stored destination — no automatic reassignment.
        $this->assertSame($pupr->id, $complaint->fresh()->dinas_unit_id);
        $this->assertDatabaseMissing('category_dinas_unit', ['category_id' => $this->category->id, 'dinas_unit_id' => $pupr->id]);
    }

    public function test_model_blocks_hard_delete_of_used_dinas_unit(): void
    {
        $dinas = $this->makeDinas();
        $this->category->dinasUnits()->attach($dinas->id);
        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinas->id]);

        // Hard-remove attempt at the model layer must be blocked (Prompt 5C.1).
        $this->expectException(\RuntimeException::class);
        $dinas->delete();
    }

    // =========================================================================
    // OPERATOR SHOW — VALID DESTINATION LIST
    // =========================================================================

    public function test_operator_show_offers_only_active_mapped_destinations(): void
    {
        $active = $this->makeDinas(['name' => 'Dinas Aktif', 'is_active' => true]);
        $inactive = $this->makeDinas(['name' => 'Dinas Nonaktif', 'is_active' => false]);
        $unmapped = $this->makeDinas(['name' => 'Dinas Bukan Mapping', 'is_active' => true]);
        $this->category->dinasUnits()->attach([$active->id, $inactive->id]);

        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->operator)->get(route('operator.complaint.show', $complaint));
        $response->assertOk();
        $response->assertSee('Dinas Aktif');
        $response->assertDontSee('Dinas Nonaktif');
        $response->assertDontSee('Dinas Bukan Mapping');
    }

    // =========================================================================
    // AUTHORIZATION — OPERATOR DESTINATION ROUTE
    // =========================================================================

    public function test_citizen_and_admin_cannot_set_destination(): void
    {
        $dinas = $this->makeDinas();
        $this->category->dinasUnits()->attach($dinas->id);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-destination', $complaint), ['dinas_unit_id' => $dinas->id])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-destination', $complaint), ['dinas_unit_id' => $dinas->id])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // PROMPT 5C.1 — HISTORICAL DINAS/UNIT DELETION SAFETY
    // =========================================================================

    /**
     * TEST 1 — a Dinas/Unit used by a complaint cannot be deleted.
     */
    public function test_1_used_dinas_cannot_be_deleted(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Terpakai']);
        $this->category->dinasUnits()->attach($dinas->id);
        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinas->id]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.dinas.destroy', $dinas))
            ->assertRedirect(route('super-admin.dinas.index'))
            ->assertSessionHasErrors('dinas_unit');

        // Dinas still exists, complaint intact, destination preserved.
        $this->assertDatabaseHas('dinas_units', ['id' => $dinas->id]);
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'dinas_unit_id' => $dinas->id]);
    }

    /**
     * TEST 2 — a used Dinas/Unit can be deactivated; complaint unchanged.
     */
    public function test_2_used_dinas_can_be_deactivated(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Dinonaktifkan', 'is_active' => true]);
        $this->category->dinasUnits()->attach($dinas->id);
        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinas->id]);

        $this->actingAs($this->superAdmin)->put(route('super-admin.dinas.update', $dinas), [
            'name' => $dinas->name, 'code' => $dinas->code, 'is_active' => '0', 'sort_order' => 0,
        ])->assertRedirect(route('super-admin.dinas.index'));

        $this->assertDatabaseHas('dinas_units', ['id' => $dinas->id, 'is_active' => 0]);
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'dinas_unit_id' => $dinas->id]);
    }

    /**
     * TEST 3 — inactive destination remains the complaint's historical value.
     */
    public function test_3_inactive_destination_remains_historical(): void
    {
        $dinasA = $this->makeDinas(['name' => 'Dinas A', 'is_active' => true]);
        $this->category->dinasUnits()->attach($dinasA->id);
        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinasA->id]);

        $dinasA->update(['is_active' => false]);

        $this->assertSame($dinasA->id, $complaint->fresh()->dinas_unit_id);
    }

    /**
     * TEST 4 — an inactive Dinas is not offered for new assignment.
     */
    public function test_4_inactive_not_available_for_new_assignment(): void
    {
        $dinasA = $this->makeDinas(['name' => 'Dinas A Inaktif', 'is_active' => false]);
        $dinasB = $this->makeDinas(['name' => 'Dinas B Aktif', 'is_active' => true]);
        $this->category->dinasUnits()->attach([$dinasA->id, $dinasB->id]);

        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->operator)->get(route('operator.complaint.show', $complaint));
        $response->assertOk();
        $response->assertSee('Dinas B Aktif');
        $response->assertDontSee('Dinas A Inaktif');

        // And the server rejects selecting the inactive one as a NEW destination.
        $this->actingAs($this->operator)->patch(route('operator.complaint.update-destination', $complaint), [
            'dinas_unit_id' => $dinasA->id,
        ])->assertSessionHasErrors('dinas_unit_id');
    }

    /**
     * TEST 5 — existing complaint with an inactive destination stays processable.
     */
    public function test_5_existing_inactive_destination_still_valid_for_existing_complaint(): void
    {
        $dinasA = $this->makeDinas(['name' => 'Dinas A Histori', 'is_active' => false]);
        $this->category->dinasUnits()->attach($dinasA->id);

        // Prompt 15 — the operator must belong to the complaint's unit.
        $this->operator->forceFill(['dinas_unit_id' => $dinasA->id])->save();

        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinasA->id]);

        $response = $this->actingAs($this->operator)->get(route('operator.complaint.show', $complaint));
        $response->assertOk();
        $response->assertSee('Dinas A Histori');

        // Re-submitting the same stored destination is accepted (no change).
        $this->actingAs($this->operator)->patch(route('operator.complaint.update-destination', $complaint), [
            'dinas_unit_id' => $dinasA->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($dinasA->id, $complaint->fresh()->dinas_unit_id);
    }

    /**
     * TEST 6 — a blocked delete attempt must NOT null the destination.
     */
    public function test_6_delete_attempt_must_not_null_destination(): void
    {
        $dinasA = $this->makeDinas(['name' => 'Dinas A Aman']);
        $this->category->dinasUnits()->attach($dinasA->id);
        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinasA->id]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.dinas.destroy', $dinasA))
            ->assertSessionHasErrors('dinas_unit');

        $fresh = $complaint->fresh();
        $this->assertNotNull($fresh->dinas_unit_id);
        $this->assertSame($dinasA->id, $fresh->dinas_unit_id);
    }

    /**
     * Unused Dinas/Unit CAN still be deleted (delete capability preserved).
     */
    public function test_unused_dinas_can_be_deleted(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Belum Terpakai']);
        $this->category->dinasUnits()->attach($dinas->id);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.dinas.destroy', $dinas))
            ->assertRedirect(route('super-admin.dinas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('dinas_units', ['id' => $dinas->id]);
        // Mapping pivot removed too (CASCADE), but no complaint touched.
        $this->assertDatabaseMissing('category_dinas_unit', ['dinas_unit_id' => $dinas->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dinas_unit.deleted', 'subject_type' => 'dinas_unit']);
    }

    /**
     * Only Super Admin may call DELETE.
     */
    public function test_only_super_admin_can_delete_dinas(): void
    {
        $dinas = $this->makeDinas();

        $this->actingAs($this->operator)->delete(route('super-admin.dinas.destroy', $dinas))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('super-admin.dinas.destroy', $dinas))->assertForbidden();
        $this->actingAs($this->citizen)->delete(route('super-admin.dinas.destroy', $dinas))->assertForbidden();

        $this->assertDatabaseHas('dinas_units', ['id' => $dinas->id]);
    }
}
