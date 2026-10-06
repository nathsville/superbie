<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 6 (Bagian 1) — Category & Master Data Management
 *
 * Covers §23 test requirements:
 *   - Super Admin Category CRUD + activate/deactivate (authorization server-side)
 *   - Operator/Admin/Masyarakat cannot manage categories (403) / guest → login
 *   - Complaint creation: active category OK, inactive/nonexistent rejected
 *   - Masyarakat cannot set Dinas/Unit on the complaint form
 *   - Historical safety: deactivating a category / changing mapping never
 *     alters existing complaints or their actual Dinas/Unit destination
 *   - Deletion safety: a used category cannot be hard-deleted (Prompt 5C.1 parity)
 *   - Dinas/Unit regression: used Dinas not deletable; inactive not assignable
 */
class SuperAdminCategoryManagementTest extends TestCase
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

        // Prompt 15 — operators are scoped to a Dinas/Unit; the category is mapped
        // to this unit so unrouted complaints remain in the operator's scope.
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

        $this->category->dinasUnits()->attach($this->operatorUnit->id);
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-CAT-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan uji kategori',
            'description'    => 'Deskripsi laporan pengujian kategori.',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    private static int $dinasSeq = 0;

    private function makeDinas(array $overrides = []): DinasUnit
    {
        return DinasUnit::create(array_merge([
            'name' => 'Dinas Uji ' . uniqid(), 'code' => 'UJI' . (++self::$dinasSeq),
            'is_active' => true, 'sort_order' => 0,
        ], $overrides));
    }

    // =========================================================================
    // AUTHORIZATION — SUPER ADMIN ONLY
    // =========================================================================

    public function test_only_super_admin_can_access_category_management(): void
    {
        $this->actingAs($this->superAdmin)->get(route('super-admin.categories.index'))->assertOk();

        foreach ([$this->operator, $this->admin, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.categories.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.categories.create'))->assertForbidden();
            $this->actingAs($user)->post(route('super-admin.categories.store'), ['name' => 'X'])->assertForbidden();
        }
    }

    public function test_guest_cannot_access_category_management(): void
    {
        $this->get(route('super-admin.categories.index'))->assertRedirect(route('login'));
    }

    public function test_category_management_pages_render(): void
    {
        $this->actingAs($this->superAdmin)->get(route('super-admin.categories.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.categories.create'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.categories.edit', $this->category))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.dinas.index'))->assertOk();
    }

    public function test_non_super_admin_cannot_update_or_toggle_category(): void
    {
        foreach ([$this->operator, $this->admin, $this->citizen] as $user) {
            $this->actingAs($user)
                ->put(route('super-admin.categories.update', $this->category), ['name' => 'Hacked'])
                ->assertForbidden();
            $this->actingAs($user)
                ->patch(route('super-admin.categories.toggle', $this->category))
                ->assertForbidden();
        }

        // Nothing changed.
        $this->assertDatabaseHas('complaint_categories', ['id' => $this->category->id, 'name' => 'Infrastruktur Jalan', 'is_active' => 1]);
    }

    // =========================================================================
    // CRUD + ACTIVATE / DEACTIVATE
    // =========================================================================

    public function test_super_admin_can_create_category(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.categories.store'), [
            'name' => 'Kebersihan Lingkungan', 'description' => 'Sampah & kebersihan',
            'is_active' => '1', 'sort_order' => 3,
        ])->assertRedirect(route('super-admin.categories.index'));

        $this->assertDatabaseHas('complaint_categories', [
            'name' => 'Kebersihan Lingkungan', 'slug' => 'kebersihan-lingkungan', 'is_active' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint_category.created', 'subject_type' => 'complaint_category']);
    }

    public function test_super_admin_can_update_category(): void
    {
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.update', $this->category), [
            'name' => 'Infrastruktur Jalan & Jembatan', 'description' => 'Diperbarui',
            'is_active' => '1', 'sort_order' => 2,
        ])->assertRedirect(route('super-admin.categories.index'));

        $this->assertDatabaseHas('complaint_categories', ['id' => $this->category->id, 'name' => 'Infrastruktur Jalan & Jembatan']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint_category.updated', 'subject_type' => 'complaint_category']);
    }

    public function test_super_admin_can_deactivate_and_reactivate_category(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.categories.toggle', $this->category))
            ->assertRedirect(route('super-admin.categories.index'));
        $this->assertDatabaseHas('complaint_categories', ['id' => $this->category->id, 'is_active' => 0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint_category.deactivated', 'subject_type' => 'complaint_category']);

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.categories.toggle', $this->category))
            ->assertRedirect(route('super-admin.categories.index'));
        $this->assertDatabaseHas('complaint_categories', ['id' => $this->category->id, 'is_active' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint_category.activated', 'subject_type' => 'complaint_category']);
    }

    public function test_category_name_is_required(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.categories.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_slug_is_generated_and_kept_unique(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.categories.store'), [
            'name' => 'Infrastruktur Jalan', 'is_active' => '1',
        ])->assertRedirect();

        // Second category with the same name gets a distinct, unique slug.
        $this->assertDatabaseHas('complaint_categories', ['slug' => 'infrastruktur-jalan-2']);
        $this->assertSame(2, ComplaintCategory::where('name', 'Infrastruktur Jalan')->count());
    }

    public function test_slug_cannot_be_manipulated_from_request(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.categories.store'), [
            'name' => 'Pelayanan Publik', 'slug' => 'hacked-slug', 'is_active' => '1',
        ])->assertRedirect();

        // Slug is derived server-side; the submitted value is ignored.
        $this->assertDatabaseHas('complaint_categories', ['name' => 'Pelayanan Publik', 'slug' => 'pelayanan-publik']);
        $this->assertDatabaseMissing('complaint_categories', ['slug' => 'hacked-slug']);
    }

    // =========================================================================
    // COMPLAINT CREATION — CATEGORY ACTIVE / INACTIVE / MISSING
    // =========================================================================

    public function test_active_category_can_be_used_for_new_complaint(): void
    {
        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title' => 'Jalan berlubang',
            'description' => 'Ada lubang besar di jalan utama kota.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('complaints', ['category_id' => $this->category->id, 'title' => 'Jalan berlubang']);
    }

    public function test_inactive_category_is_rejected_server_side(): void
    {
        $inactive = ComplaintCategory::create([
            'name' => 'Kategori Nonaktif', 'slug' => 'kategori-nonaktif',
            'is_active' => false, 'sort_order' => 99,
        ]);

        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $inactive->id,
            'title' => 'Laporan kategori nonaktif',
            'description' => 'Deskripsi cukup panjang untuk lolos validasi.',
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan kategori nonaktif']);
    }

    public function test_nonexistent_category_is_rejected(): void
    {
        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => 999999,
            'title' => 'Laporan kategori tidak ada',
            'description' => 'Deskripsi cukup panjang untuk lolos validasi.',
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan kategori tidak ada']);
    }

    public function test_citizen_cannot_set_dinas_unit_on_complaint_form(): void
    {
        $dinas = $this->makeDinas();

        $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), [
            'category_id' => $this->category->id,
            'title' => 'Laporan tanpa dinas',
            'description' => 'Deskripsi cukup panjang untuk lolos validasi.',
            'dinas_unit_id' => $dinas->id, // must be ignored
        ])->assertSessionHasNoErrors();

        $complaint = Complaint::where('title', 'Laporan tanpa dinas')->firstOrFail();
        $this->assertNull($complaint->dinas_unit_id);
    }

    // =========================================================================
    // HISTORICAL SAFETY
    // =========================================================================

    public function test_deactivating_category_does_not_change_existing_complaint(): void
    {
        $dinas = $this->makeDinas();
        $this->category->dinasUnits()->attach($dinas->id);
        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinas->id]);
        $statusBefore = $complaint->status->value;

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.categories.toggle', $this->category))
            ->assertRedirect();

        $fresh = $complaint->fresh();
        $this->assertSame($this->category->id, $fresh->category_id);
        $this->assertSame($dinas->id, $fresh->dinas_unit_id);
        $this->assertSame($statusBefore, $fresh->status->value);
        $this->assertDatabaseHas('complaint_categories', ['id' => $this->category->id, 'is_active' => 0]);
    }

    public function test_changing_mapping_does_not_change_existing_complaint_destination(): void
    {
        $a = $this->makeDinas(['name' => 'Dinas A']);
        $b = $this->makeDinas(['name' => 'Dinas B']);
        $this->category->dinasUnits()->attach([$a->id, $b->id]);

        $complaint = $this->makeComplaint(['dinas_unit_id' => $a->id]);

        // Remove Dinas A from the mapping entirely.
        $this->actingAs($this->superAdmin)->put(route('super-admin.categories.mapping.update', $this->category), [
            'dinas_unit_ids' => [$b->id],
        ])->assertRedirect();

        $this->assertSame($a->id, $complaint->fresh()->dinas_unit_id);
        $this->assertDatabaseMissing('category_dinas_unit', ['category_id' => $this->category->id, 'dinas_unit_id' => $a->id]);
    }

    public function test_inactive_dinas_still_shown_on_existing_complaint_history(): void
    {
        $dinas = $this->makeDinas(['name' => 'Dinas Histori Kategori', 'is_active' => true]);
        $this->category->dinasUnits()->attach($dinas->id);

        // Prompt 15 — the operator must belong to the complaint's unit to see it.
        $this->operator->forceFill(['dinas_unit_id' => $dinas->id])->save();

        $complaint = $this->makeComplaint(['dinas_unit_id' => $dinas->id]);

        $dinas->update(['is_active' => false]);

        $response = $this->actingAs($this->operator)->get(route('operator.complaint.show', $complaint));
        $response->assertOk();
        $response->assertSee('Dinas Histori Kategori');
        $this->assertSame($dinas->id, $complaint->fresh()->dinas_unit_id);
    }

    // =========================================================================
    // DELETION SAFETY (Prompt 5C.1 parity for categories)
    // =========================================================================

    public function test_used_category_cannot_be_deleted(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.categories.destroy', $this->category))
            ->assertRedirect(route('super-admin.categories.index'))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('complaint_categories', ['id' => $this->category->id]);
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'category_id' => $this->category->id]);
    }

    public function test_delete_attempt_must_not_null_complaint_category(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.categories.destroy', $this->category))
            ->assertSessionHasErrors('category');

        $this->assertNotNull($complaint->fresh()->category_id);
        $this->assertSame($this->category->id, $complaint->fresh()->category_id);
    }

    public function test_model_blocks_hard_delete_of_used_category(): void
    {
        $this->makeComplaint();

        $this->expectException(\RuntimeException::class);
        $this->category->delete();
    }

    public function test_unused_category_can_be_deleted(): void
    {
        $unused = ComplaintCategory::create([
            'name' => 'Kategori Kosong', 'slug' => 'kategori-kosong', 'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.categories.destroy', $unused))
            ->assertRedirect(route('super-admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('complaint_categories', ['id' => $unused->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint_category.deleted', 'subject_type' => 'complaint_category']);
    }

    public function test_only_super_admin_can_delete_category(): void
    {
        $unused = ComplaintCategory::create([
            'name' => 'Kategori Kosong 2', 'slug' => 'kategori-kosong-2', 'is_active' => true,
        ]);

        $this->actingAs($this->operator)->delete(route('super-admin.categories.destroy', $unused))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('super-admin.categories.destroy', $unused))->assertForbidden();
        $this->actingAs($this->citizen)->delete(route('super-admin.categories.destroy', $unused))->assertForbidden();

        $this->assertDatabaseHas('complaint_categories', ['id' => $unused->id]);
    }

    // =========================================================================
    // DINAS/UNIT REGRESSION (Prompt 5C.1 rules must still hold)
    // =========================================================================

    public function test_used_dinas_still_cannot_be_deleted_regression(): void
    {
        $dinas = $this->makeDinas();
        $this->category->dinasUnits()->attach($dinas->id);
        $this->makeComplaint(['dinas_unit_id' => $dinas->id]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.dinas.destroy', $dinas))
            ->assertSessionHasErrors('dinas_unit');

        $this->assertDatabaseHas('dinas_units', ['id' => $dinas->id]);
    }

    public function test_inactive_dinas_still_not_available_for_new_assignment_regression(): void
    {
        $inactive = $this->makeDinas(['name' => 'Dinas Inaktif Regresi', 'is_active' => false]);
        $this->category->dinasUnits()->attach($inactive->id);

        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-destination', $complaint), ['dinas_unit_id' => $inactive->id])
            ->assertSessionHasErrors('dinas_unit_id');

        $this->assertNull($complaint->fresh()->dinas_unit_id);
    }
}
