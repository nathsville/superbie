<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 2/3 — Master Data Kecamatan & Kelurahan + Backend Validation
 *
 * Covers the FINAL location business rules:
 *   - kecamatan / kelurahan master tables + models + relations
 *   - Kecamatan 1 ── N Kelurahan (and both 1 ── N Complaint)
 *   - Kecamatan/Kelurahan REQUIRED for every NEW complaint (all categories)
 *   - Server-side pair validation (Kelurahan MUST belong to the Kecamatan)
 *   - Legacy complaints keep NULL location + intact location_text
 *   - Super Admin CRUD (authorization: ONLY super_admin)
 *   - Historical-integrity delete guards
 *   - No invented BPS data
 */
class LocationMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;
    private User $otherCitizen;
    private ComplaintCategory $category;
    private DinasUnit $unit;

    private Kecamatan $kecamatanA;
    private Kecamatan $kecamatanB;
    private Kelurahan $kelurahanA;
    private Kelurahan $kelurahanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas Uji Lokasi', 'code' => 'UJI-LOC',
            'is_active' => true, 'sort_order' => 0,
        ]);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);

        $this->citizen = User::factory()->create([
            'role' => 'masyarakat', 'is_active' => true,
            'phone_number' => '081100000001', 'address' => 'Alamat Warga Uji',
        ]);
        $this->otherCitizen = User::factory()->create([
            'role' => 'masyarakat', 'is_active' => true,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'dinas_name' => 'Dinas PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        // Route the category to the operator's unit so created complaints are
        // visible to that operator (hybrid visibility rule).
        $this->category->dinasUnits()->attach($this->unit->id);

        // Test-only location fixtures (arbitrary labels — NOT official BPS data).
        $this->kecamatanA = Kecamatan::create(['name' => 'Kecamatan A']);
        $this->kecamatanB = Kecamatan::create(['name' => 'Kecamatan B']);
        $this->kelurahanA = Kelurahan::create(['kecamatan_id' => $this->kecamatanA->id, 'name' => 'Kelurahan A1']);
        $this->kelurahanB = Kelurahan::create(['kecamatan_id' => $this->kecamatanB->id, 'name' => 'Kelurahan B1']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title'       => 'Laporan uji lokasi',
            'description' => 'Deskripsi laporan pengujian lokasi terstruktur.',
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ], $overrides);
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-LOC-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan lokasi',
            'description'    => 'Deskripsi laporan lokasi.',
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // 1–6. Schema + relations
    // =========================================================================

    public function test_kecamatan_and_kelurahan_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('kecamatan'));
        $this->assertTrue(Schema::hasTable('kelurahan'));

        foreach (['id', 'name', 'code', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('kecamatan', $column), "kecamatan missing {$column}");
        }
        foreach (['id', 'kecamatan_id', 'name', 'code', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('kelurahan', $column), "kelurahan missing {$column}");
        }

        // FINAL rule: location master has NO active/inactive and NO GIS fields.
        $this->assertFalse(Schema::hasColumn('kecamatan', 'is_active'));
        $this->assertFalse(Schema::hasColumn('kelurahan', 'is_active'));
        foreach (['latitude', 'longitude', 'polygon', 'geometry', 'postal_code'] as $gis) {
            $this->assertFalse(Schema::hasColumn('kecamatan', $gis), "kecamatan must not have {$gis}");
            $this->assertFalse(Schema::hasColumn('kelurahan', $gis), "kelurahan must not have {$gis}");
        }
    }

    public function test_complaints_have_nullable_location_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('complaints', 'kecamatan_id'));
        $this->assertTrue(Schema::hasColumn('complaints', 'kelurahan_id'));
        $this->assertTrue(Schema::hasColumn('complaints', 'location_text'));
    }

    public function test_kelurahan_belongs_to_kecamatan_and_kecamatan_has_many_kelurahan(): void
    {
        $this->assertTrue($this->kelurahanA->kecamatan->is($this->kecamatanA));
        $this->assertCount(1, $this->kecamatanA->kelurahan);
        $this->assertTrue($this->kecamatanA->kelurahan->first()->is($this->kelurahanA));
    }

    public function test_complaint_belongs_to_kecamatan_and_kelurahan(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ]);

        $this->assertTrue($complaint->kecamatan->is($this->kecamatanA));
        $this->assertTrue($complaint->kelurahan->is($this->kelurahanA));
        $this->assertCount(1, $this->kecamatanA->complaints);
        $this->assertCount(1, $this->kelurahanA->complaints);
    }

    // =========================================================================
    // 7–8. Legacy complaints
    // =========================================================================

    public function test_legacy_complaint_remains_valid_with_null_location_and_intact_text(): void
    {
        $legacy = $this->makeComplaint([
            'location_text' => 'Jl. Bau Massepe No. 45 Parepare',
            'kecamatan_id'  => null,
            'kelurahan_id'  => null,
        ]);

        $fresh = $legacy->fresh();
        $this->assertNull($fresh->kecamatan_id);
        $this->assertNull($fresh->kelurahan_id);
        $this->assertSame('Jl. Bau Massepe No. 45 Parepare', $fresh->location_text);
        $this->assertNull($fresh->kecamatan);
        $this->assertNull($fresh->kelurahan);
    }

    // =========================================================================
    // 9–15. Create complaint validation
    // =========================================================================

    public function test_create_fails_when_kecamatan_missing(): void
    {
        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->payload(['kecamatan_id' => null]));

        $response->assertSessionHasErrors('kecamatan_id');
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan uji lokasi']);
    }

    public function test_create_fails_when_kelurahan_missing(): void
    {
        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->payload(['kelurahan_id' => null]));

        $response->assertSessionHasErrors('kelurahan_id');
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan uji lokasi']);
    }

    public function test_create_fails_when_kecamatan_does_not_exist(): void
    {
        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->payload(['kecamatan_id' => 999999]));

        $response->assertSessionHasErrors('kecamatan_id');
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan uji lokasi']);
    }

    public function test_create_fails_when_kelurahan_does_not_exist(): void
    {
        $response = $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->payload(['kelurahan_id' => 999999]));

        $response->assertSessionHasErrors('kelurahan_id');
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan uji lokasi']);
    }

    public function test_create_fails_when_kelurahan_belongs_to_a_different_kecamatan(): void
    {
        // Kecamatan A + Kelurahan of Kecamatan B — must be rejected.
        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->payload([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanB->id,
        ]));

        $response->assertSessionHasErrors('kelurahan_id');
        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan uji lokasi']);
    }

    public function test_create_succeeds_with_valid_pair_and_persists_location_text(): void
    {
        $response = $this->actingAs($this->citizen)->post(route('citizen.complaint.store'), $this->payload([
            'location_text' => 'Depan pasar, dekat jembatan',
        ]));

        $response->assertSessionHasNoErrors();

        $complaint = Complaint::where('title', 'Laporan uji lokasi')->firstOrFail();
        $this->assertSame($this->kecamatanA->id, $complaint->kecamatan_id);
        $this->assertSame($this->kelurahanA->id, $complaint->kelurahan_id);
        $this->assertSame('Depan pasar, dekat jembatan', $complaint->location_text);
    }

    // =========================================================================
    // 16–18. Operator location update
    // =========================================================================

    public function test_operator_can_update_location_with_valid_pair(): void
    {
        $complaint = $this->makeComplaint();

        $response = $this->actingAs($this->operator)->patch(route('operator.complaint.update-location', $complaint), [
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ]);

        $response->assertRedirect(route('operator.complaint.show', $complaint));
        $this->assertSame($this->kecamatanA->id, $complaint->fresh()->kecamatan_id);
        $this->assertSame($this->kelurahanA->id, $complaint->fresh()->kelurahan_id);
    }

    public function test_operator_cannot_update_location_with_invalid_pair(): void
    {
        $complaint = $this->makeComplaint();

        // Kecamatan A + Kelurahan of Kecamatan B.
        $response = $this->actingAs($this->operator)->patch(route('operator.complaint.update-location', $complaint), [
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanB->id,
        ]);

        $response->assertSessionHasErrors('kelurahan_id');
        $this->assertNull($complaint->fresh()->kecamatan_id);
    }

    public function test_operator_cannot_change_only_kecamatan_keeping_foreign_kelurahan(): void
    {
        // Complaint already at Kecamatan A / Kelurahan A1.
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ]);

        // Attempt: switch to Kecamatan B but keep Kelurahan A1 (belongs to A).
        $response = $this->actingAs($this->operator)->patch(route('operator.complaint.update-location', $complaint), [
            'kecamatan_id' => $this->kecamatanB->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ]);

        $response->assertSessionHasErrors('kelurahan_id');
        // Unchanged.
        $this->assertSame($this->kecamatanA->id, $complaint->fresh()->kecamatan_id);
        $this->assertSame($this->kelurahanA->id, $complaint->fresh()->kelurahan_id);
    }

    public function test_operator_location_update_writes_audit_log(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ]);

        $this->actingAs($this->operator)->patch(route('operator.complaint.update-location', $complaint), [
            'kecamatan_id' => $this->kecamatanB->id,
            'kelurahan_id' => $this->kelurahanB->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'complaint.location_updated',
            'subject_type' => 'complaint',
            'subject_id'   => $complaint->id,
            'actor_id'     => $this->operator->id,
        ]);

        $log = AuditLog::where('action', 'complaint.location_updated')->firstOrFail();
        $this->assertSame($this->kecamatanA->id, $log->metadata['old_kecamatan_id']);
        $this->assertSame($this->kecamatanB->id, $log->metadata['new_kecamatan_id']);
    }

    // =========================================================================
    // 19. Citizen isolation
    // =========================================================================

    public function test_citizen_cannot_update_location_of_any_complaint(): void
    {
        $complaint = $this->makeComplaint();

        // The citizen cannot even reach the operator location endpoint.
        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanA->id,
            ])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->kecamatan_id);
    }

    public function test_citizen_cannot_update_location_of_another_citizens_complaint(): void
    {
        $complaint = $this->makeComplaint(['reporter_id' => $this->otherCitizen->id]);

        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanA->id,
            ])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->kecamatan_id);
    }

    // =========================================================================
    // 20–23. Master data CRUD authorization
    // =========================================================================

    public function test_admin_cannot_access_location_master_crud(): void
    {
        $this->actingAs($this->admin)->get(route('super-admin.kecamatan.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('super-admin.kelurahan.index'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('super-admin.kecamatan.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_operator_cannot_access_location_master_crud(): void
    {
        $this->actingAs($this->operator)->get(route('super-admin.kecamatan.index'))->assertForbidden();
        $this->actingAs($this->operator)->get(route('super-admin.kelurahan.index'))->assertForbidden();
        $this->actingAs($this->operator)->post(route('super-admin.kelurahan.store'), [
            'kecamatan_id' => $this->kecamatanA->id, 'name' => 'X',
        ])->assertForbidden();
    }

    public function test_citizen_cannot_access_location_master_crud(): void
    {
        $this->actingAs($this->citizen)->get(route('super-admin.kecamatan.index'))->assertForbidden();
        $this->actingAs($this->citizen)->get(route('super-admin.kelurahan.index'))->assertForbidden();
    }

    public function test_super_admin_can_access_location_master_crud(): void
    {
        $this->actingAs($this->superAdmin)->get(route('super-admin.kecamatan.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.kecamatan.create'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.kelurahan.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.kelurahan.create'))->assertOk();
    }

    public function test_super_admin_can_create_and_update_kecamatan(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.kecamatan.store'), ['name' => 'Kecamatan Baru', 'code' => 'KB-01'])
            ->assertRedirect(route('super-admin.kecamatan.index'));

        $this->assertDatabaseHas('kecamatan', ['name' => 'Kecamatan Baru', 'code' => 'KB-01']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kecamatan.created', 'subject_type' => 'kecamatan']);

        $created = Kecamatan::where('name', 'Kecamatan Baru')->firstOrFail();
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.kecamatan.update', $created), ['name' => 'Kecamatan Diubah'])
            ->assertRedirect(route('super-admin.kecamatan.index'));

        $this->assertSame('Kecamatan Diubah', $created->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kecamatan.updated', 'subject_id' => $created->id]);
    }

    public function test_super_admin_can_create_and_update_kelurahan(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.kelurahan.store'), [
                'kecamatan_id' => $this->kecamatanA->id, 'name' => 'Kelurahan Baru',
            ])
            ->assertRedirect(route('super-admin.kelurahan.index'));

        $this->assertDatabaseHas('kelurahan', ['name' => 'Kelurahan Baru', 'kecamatan_id' => $this->kecamatanA->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kelurahan.created', 'subject_type' => 'kelurahan']);

        $created = Kelurahan::where('name', 'Kelurahan Baru')->firstOrFail();
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.kelurahan.update', $created), [
                'kecamatan_id' => $this->kecamatanB->id, 'name' => 'Kelurahan Dipindah',
            ])
            ->assertRedirect(route('super-admin.kelurahan.index'));

        $this->assertSame($this->kecamatanB->id, $created->fresh()->kecamatan_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kelurahan.updated', 'subject_id' => $created->id]);
    }

    public function test_kelurahan_creation_requires_existing_kecamatan(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.kelurahan.store'), ['kecamatan_id' => 999999, 'name' => 'Kelurahan X'])
            ->assertSessionHasErrors('kecamatan_id');

        $this->assertDatabaseMissing('kelurahan', ['name' => 'Kelurahan X']);
    }

    // =========================================================================
    // 24–26. Historical-integrity delete guards
    // =========================================================================

    public function test_kelurahan_used_by_complaint_cannot_be_deleted(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.kelurahan.destroy', $this->kelurahanA))
            ->assertSessionHasErrors('kelurahan');

        $this->assertDatabaseHas('kelurahan', ['id' => $this->kelurahanA->id]);
        // Complaint is untouched.
        $this->assertSame($this->kelurahanA->id, $complaint->fresh()->kelurahan_id);
    }

    public function test_kecamatan_used_by_complaint_cannot_be_deleted(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA->id,
        ]);

        // Remove the child Kelurahan first so only the complaint reference remains.
        // (Kelurahan A1 is used by the complaint, so it cannot be deleted either —
        //  create a fresh unused Kecamatan to isolate the "used by complaint" case.)
        $soloKecamatan = Kecamatan::create(['name' => 'Kecamatan Solo']);
        $complaint->update(['kecamatan_id' => $soloKecamatan->id, 'kelurahan_id' => null]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.kecamatan.destroy', $soloKecamatan))
            ->assertSessionHasErrors('kecamatan');

        $this->assertDatabaseHas('kecamatan', ['id' => $soloKecamatan->id]);
    }

    public function test_kecamatan_with_kelurahan_cannot_be_deleted(): void
    {
        // Kecamatan A still owns Kelurahan A1 (unused by any complaint).
        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.kecamatan.destroy', $this->kecamatanA))
            ->assertSessionHasErrors('kecamatan');

        $this->assertDatabaseHas('kecamatan', ['id' => $this->kecamatanA->id]);
    }

    public function test_unused_kecamatan_and_kelurahan_can_be_deleted(): void
    {
        $soloKecamatan = Kecamatan::create(['name' => 'Kecamatan Kosong']);
        $soloKelurahan = Kelurahan::create(['kecamatan_id' => $soloKecamatan->id, 'name' => 'Kelurahan Kosong']);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.kelurahan.destroy', $soloKelurahan))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('kelurahan', ['id' => $soloKelurahan->id]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.kecamatan.destroy', $soloKecamatan))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('kecamatan', ['id' => $soloKecamatan->id]);
    }

    // =========================================================================
    // 27. No invented BPS data
    // =========================================================================

    public function test_no_bps_seed_data_is_present_in_a_fresh_database(): void
    {
        // RefreshDatabase starts from migrations only — there must be NO seeded
        // Kecamatan/Kelurahan master rows (the official BPS dataset is not
        // provided). This guards against shipping fabricated wilayah names.
        $this->assertSame(0, Kecamatan::whereNotIn('name', ['Kecamatan A', 'Kecamatan B'])->count());
        $this->assertSame(0, Kelurahan::whereNotIn('name', ['Kelurahan A1', 'Kelurahan B1'])->count());

        // The production seeders must not create Kecamatan/Kelurahan master data
        // (a legacy free-text location_text mention is fine; a master-model insert
        // or a wilayah table write is NOT).
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString('Kecamatan::', $seeder);
        $this->assertStringNotContainsString('Kelurahan::', $seeder);
        $this->assertStringNotContainsString("table('kecamatan')", $seeder);
        $this->assertStringNotContainsString("table('kelurahan')", $seeder);
    }
}
