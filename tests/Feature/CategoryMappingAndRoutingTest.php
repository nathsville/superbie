<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 6 (Bagian 2) — Category ↔ Dinas/Unit Integration & Routing
 *
 * Focused coverage for:
 *   - Mapping management authorization (Super Admin only; others 403)
 *   - Mapping validation (exists, de-dup, IDOR scoping)
 *   - Inactive handling (category / Dinas) without destroying mapping
 *   - Historical safety (mapping change never alters stored destination)
 *   - Operator routing validation (exists + active + mapped-to-category)
 *   - Citizen form: no Dinas/Unit selection; inactive category rejected
 *   - Regression: Dinas/Unit delete protection (Prompt 5C.1)
 */
class CategoryMappingAndRoutingTest extends TestCase
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

        // Prompt 15 — operators are scoped to a Dinas/Unit. Routing tests below
        // map the category to this unit so the complaints they create are visible.
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
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'is_active' => true, 'sort_order' => 1,
        ]);

        // Map the category to the operator's unit so unrouted complaints are in scope.
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
            'reference_code' => 'LPW-MAP-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan uji mapping',
            'description'    => 'Deskripsi laporan pengujian mapping.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    public function test_only_super_admin_can_view_and_change_mapping(): void
    {
        $dinas = $this->makeDinas();

        // View
        $this->actingAs($this->superAdmin)->get(route('super-admin.categories.mapping.edit', $this->category))->assertOk();
        foreach ([$this->operator, $this->admin, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.categories.mapping.edit', $this->category))->assertForbidden();
            $this->actingAs($user)
                ->put(route('super-admin.categories.mapping.update', $this->category), ['dinas_unit_ids' => [$dinas->id]])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('category_dinas_unit', [
            'category_id' => $this->category->id, 'dinas_unit_id' => $dinas->id,
        ]);
    }

    public function test_guest_is_redirected_from_mapping(): void
    {
        $this->get(route('super-admin.categories.mapping.edit', $this->category))->assertRedirect(route('login'));
    }

    // =========================================================================
    // MAPPING — MANY-TO-MANY + VALIDATION
    // =========================================================================

    public function test_category_can_map_many_dinas(): void
    {
        $a = $this->makeDinas();
        $b = $this->makeDinas();
        $c = $this->makeDinas();

        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$a->id, $b->id, $c->id],
        ])->assertRedirect();

        $this->assertCount(3, $this->category->fresh()->dinasUnits);
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

    public function test_duplicate_pair_is_not_created(): void
    {
        $dinas = $this->makeDinas();

        // Same id submitted twice → collapsed to a single pivot row.
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$dinas->id, $dinas->id],
        ])->assertRedirect();

        $this->assertSame(1, \DB::table('category_dinas_unit')
            ->where('category_id', $this->category->id)
            ->where('dinas_unit_id', $dinas->id)
            ->count());
    }

    public function test_mapping_rejects_nonexistent_dinas(): void
    {
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [999999],
        ])->assertSessionHasErrors('dinas_unit_ids.0');

        // The invalid id is never persisted; only the pre-existing mapping (setUp) remains.
        $this->assertDatabaseMissing('category_dinas_unit', ['dinas_unit_id' => 999999]);
        $this->assertDatabaseCount('category_dinas_unit', 1);
    }

    public function test_mapping_rejects_non_integer_dinas(): void
    {
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => ['not-an-id'],
        ])->assertSessionHasErrors('dinas_unit_ids.0');
    }

    public function test_mapping_update_only_affects_bound_category_idor(): void
    {
        $catB = ComplaintCategory::create(['name' => 'Kategori B', 'slug' => 'kategori-b', 'is_active' => true]);
        $dinasA = $this->makeDinas();
        $dinasB = $this->makeDinas();
        $catB->dinasUnits()->attach($dinasB->id);

        // Update category A only.
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$dinasA->id],
        ])->assertRedirect();

        // Category B mapping is untouched.
        $this->assertDatabaseHas('category_dinas_unit', ['category_id' => $catB->id, 'dinas_unit_id' => $dinasB->id]);
        $this->assertDatabaseMissing('category_dinas_unit', ['category_id' => $catB->id, 'dinas_unit_id' => $dinasA->id]);
    }

    public function test_mapping_change_writes_granular_audit(): void
    {
        $a = $this->makeDinas();
        $b = $this->makeDinas();
        $this->category->dinasUnits()->attach($a->id);

        // Replace A with B.
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$b->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'category_mapping.updated', 'subject_type' => 'complaint_category']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category_mapping.dinas_added', 'subject_type' => 'complaint_category']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category_mapping.dinas_removed', 'subject_type' => 'complaint_category']);
    }

    public function test_mapping_page_renders(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Terlihat']);
        $this->category->dinasUnits()->attach($dinas->id);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.categories.mapping.edit', $this->category))
            ->assertOk()
            ->assertSee('Dinas Terlihat');
    }

    // =========================================================================
    // INACTIVE — MAPPING PRESERVED
    // =========================================================================

    public function test_inactive_category_keeps_its_mapping(): void
    {
        $dinas = $this->makeDinas();
        $this->category->dinasUnits()->attach($dinas->id);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.categories.toggle', $this->category))
            ->assertRedirect();

        // Deactivation does NOT clear mapping.
        $this->assertDatabaseHas('category_dinas_unit', ['category_id' => $this->category->id, 'dinas_unit_id' => $dinas->id]);
    }

    public function test_inactive_dinas_stays_in_mapping(): void
    {
        $active = $this->makeDinas(['is_active' => true]);
        $inactive = $this->makeDinas(['is_active' => false]);
        $this->category->dinasUnits()->attach([$active->id, $inactive->id]);

        // Both remain mapped; inactive is not auto-removed.
        $this->assertDatabaseHas('category_dinas_unit', ['category_id' => $this->category->id, 'dinas_unit_id' => $active->id]);
        $this->assertDatabaseHas('category_dinas_unit', ['category_id' => $this->category->id, 'dinas_unit_id' => $inactive->id]);
        $mappedIds = $this->category->fresh()->dinasUnits->pluck('id');
        $this->assertTrue($mappedIds->contains($active->id) && $mappedIds->contains($inactive->id));
    }

    // =========================================================================
    // OPERATOR ROUTING — VALIDATION
    // =========================================================================

    public function test_operator_valid_destination_allowed(): void
    {
        $dinas = $this->makeDinas(['is_active' => true]);
        $this->category->dinasUnits()->attach($dinas->id);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-destination', $complaint), ['dinas_unit_id' => $dinas->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($dinas->id, $complaint->fresh()->dinas_unit_id);
    }

    public function test_operator_rejects_dinas_not_mapped_to_category(): void
    {
        $mapped = $this->makeDinas();
        $unmapped = $this->makeDinas();
        $this->category->dinasUnits()->attach($mapped->id);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-destination', $complaint), ['dinas_unit_id' => $unmapped->id])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    public function test_operator_rejects_inactive_dinas(): void
    {
        $inactive = $this->makeDinas(['is_active' => false]);
        $this->category->dinasUnits()->attach($inactive->id);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-destination', $complaint), ['dinas_unit_id' => $inactive->id])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    public function test_operator_rejects_nonexistent_dinas(): void
    {
        $this->category->dinasUnits()->attach($this->makeDinas()->id);
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-destination', $complaint), ['dinas_unit_id' => 999999])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }

    public function test_operator_show_lists_only_active_mapped_destinations(): void
    {
        $active = $this->makeDinas(['name' => 'Dinas Aktif Pilihan']);
        $inactive = $this->makeDinas(['name' => 'Dinas Inaktif Pilihan', 'is_active' => false]);
        $unmapped = $this->makeDinas(['name' => 'Dinas Tak Terhubung']);
        $this->category->dinasUnits()->attach([$active->id, $inactive->id]);

        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->operator)->get(route('operator.complaint.show', $complaint));
        $response->assertOk();
        $response->assertSee('Dinas Aktif Pilihan');
        $response->assertDontSee('Dinas Inaktif Pilihan');
        $response->assertDontSee('Dinas Tak Terhubung');
    }

    // =========================================================================
    // HISTORICAL SAFETY
    // =========================================================================

    public function test_mapping_change_does_not_alter_stored_destination(): void
    {
        $d1 = $this->makeDinas(['name' => 'Dinas 1']);
        $d2 = $this->makeDinas(['name' => 'Dinas 2']);
        $d3 = $this->makeDinas(['name' => 'Dinas 3']);
        $this->category->dinasUnits()->attach([$d1->id, $d2->id]);

        $complaint = $this->makeComplaint(['dinas_unit_id' => $d1->id]);

        // Mapping becomes Dinas 2 + Dinas 3.
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$d2->id, $d3->id],
        ])->assertRedirect();

        // Complaint #001 keeps Dinas 1 (historical actual destination).
        $this->assertSame($d1->id, $complaint->fresh()->dinas_unit_id);
    }

    public function test_deactivated_dinas_does_not_change_historical_complaint(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Histori', 'is_active' => true]);
        $this->category->dinasUnits()->attach($dinas->id);
        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinas->id]);
        $statusBefore = $complaint->status->value;

        $this->actingAs($this->superAdmin)->put(route('super-admin.dinas.update', $dinas), [
            'name' => $dinas->name, 'code' => $dinas->code, 'is_active' => '0', 'sort_order' => 0,
        ])->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame($dinas->id, $fresh->dinas_unit_id);
        $this->assertSame($statusBefore, $fresh->status->value);
    }

    public function test_complaint_with_inactive_destination_still_openable(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Nonaktif Histori', 'is_active' => false]);
        $this->category->dinasUnits()->attach($dinas->id);

        // Prompt 15 — the operator must belong to the complaint's unit to see it.
        $this->operator->forceFill(['dinas_unit_id' => $dinas->id])->save();

        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinas->id]);

        $response = $this->actingAs($this->operator)->get(route('operator.complaint.show', $complaint));
        $response->assertOk();
        $response->assertSee('Dinas Nonaktif Histori');
        $this->assertSame($dinas->id, $complaint->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // CITIZEN FORM
    // =========================================================================

    public function test_citizen_form_offers_category_but_no_dinas_selection(): void
    {
        $this->makeDinas(['name' => 'Dinas Jangan Muncul Sebagai Input']);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.create'));
        $response->assertOk();
        $response->assertSee('Infrastruktur Jalan');   // category selectable
        $response->assertDontSee('name="dinas_unit_id"', false); // no dinas input field
    }

    public function test_citizen_form_previews_mapped_active_dinas(): void
    {
        $active = $this->makeDinas(['name' => 'Dinas Pemetaan Aktif']);
        $inactive = $this->makeDinas(['name' => 'Dinas Pemetaan Nonaktif', 'is_active' => false]);
        $this->category->dinasUnits()->attach([$active->id, $inactive->id]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.create'));
        $response->assertOk();
        // The mapped ACTIVE Dinas name is embedded for the client-side preview.
        $response->assertSee('Dinas Pemetaan Aktif');
    }

    public function test_citizen_cannot_set_destination_via_request_manipulation(): void
    {
        $dinas = $this->makeDinas();

        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id'   => $this->category->id,
            'title'         => 'Laporan manipulasi tujuan',
            'description'   => 'Deskripsi cukup panjang untuk lolos validasi.',
            'dinas_unit_id' => $dinas->id,
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('title', 'Laporan manipulasi tujuan')->firstOrFail();
        $this->assertNull($complaint->dinas_unit_id);
    }

    // =========================================================================
    // REGRESSION — DINAS/UNIT DELETE PROTECTION (Prompt 5C.1)
    // =========================================================================

    public function test_used_dinas_cannot_be_hard_deleted_regression(): void
    {
        $dinas = $this->makeDinas();
        $this->category->dinasUnits()->attach($dinas->id);
        $this->makeComplaint(['dinas_unit_id' => $dinas->id]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.dinas.destroy', $dinas))
            ->assertSessionHasErrors('dinas_unit');

        $this->assertDatabaseHas('dinas_units', ['id' => $dinas->id]);
    }

    public function test_unused_dinas_can_still_be_deleted_regression(): void
    {
        $dinas = $this->makeDinas();
        $this->category->dinasUnits()->attach($dinas->id); // mapped but never used by a complaint

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.dinas.destroy', $dinas))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('dinas_units', ['id' => $dinas->id]);
        $this->assertDatabaseMissing('category_dinas_unit', ['dinas_unit_id' => $dinas->id]);
    }
}
