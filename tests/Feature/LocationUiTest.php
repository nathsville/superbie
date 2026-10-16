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
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 3/3 — Dependent Location UI (Kecamatan → Kelurahan)
 *
 * Covers the FRONTEND surface of the FINAL location rule:
 *   - "Buat Laporan" exposes Kecamatan + Kelurahan controls driven by master data
 *   - Dependent dropdown component (`locationPicker`) is wired in
 *   - Empty master data degrades gracefully (no 500, honest message)
 *   - Old input survives a failed-validation redirect
 *   - Citizen detail / history render structured location NULL-safely
 *   - Operator detail shows location and offers an edit form on the EXISTING route
 *   - Operator update keeps location_text intact, writes audit, enforces scope
 *   - Admin / Citizen cannot reach the Operator location endpoint
 *   - Super Admin master views stay clean (no is_active / GIS fields)
 *
 * Server-side validation remains the security boundary — these tests also
 * confirm the backend REJECTS a mismatched Kecamatan/Kelurahan pair even when
 * the request is crafted by hand (frontend filtering is UX only).
 */
class LocationUiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $otherOperator;
    private User $citizen;
    private User $otherCitizen;

    private DinasUnit $unit;
    private DinasUnit $otherUnit;

    private ComplaintCategory $category;

    private Kecamatan $kecamatanA;
    private Kecamatan $kecamatanB;
    private Kelurahan $kelurahanA1;
    private Kelurahan $kelurahanA2;
    private Kelurahan $kelurahanB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas Uji Lokasi UI', 'code' => 'UJI-LOC-UI',
            'is_active' => true, 'sort_order' => 0,
        ]);
        $this->otherUnit = DinasUnit::create([
            'name' => 'Dinas Uji Lain', 'code' => 'UJI-LOC-OTHER',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);
        $this->otherOperator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->otherUnit->id,
        ]);

        $this->citizen = User::factory()->create([
            'role' => 'masyarakat', 'is_active' => true,
            'phone_number' => '081100000002', 'address' => 'Alamat Warga Uji UI',
        ]);
        $this->otherCitizen = User::factory()->create([
            'role' => 'masyarakat', 'is_active' => true,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Jalan', 'slug' => 'infrastruktur-jalan',
            'dinas_name' => 'Dinas PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->category->dinasUnits()->attach($this->unit->id);

        // Test-only location fixtures (arbitrary labels — NOT official BPS data).
        $this->kecamatanA = Kecamatan::create(['name' => 'Kecamatan A']);
        $this->kecamatanB = Kecamatan::create(['name' => 'Kecamatan B']);
        $this->kelurahanA1 = Kelurahan::create(['kecamatan_id' => $this->kecamatanA->id, 'name' => 'Kelurahan A1']);
        $this->kelurahanA2 = Kelurahan::create(['kecamatan_id' => $this->kecamatanA->id, 'name' => 'Kelurahan A2']);
        $this->kelurahanB1 = Kelurahan::create(['kecamatan_id' => $this->kecamatanB->id, 'name' => 'Kelurahan B1']);
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-UI-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan UI lokasi',
            'description'    => 'Deskripsi laporan UI lokasi terstruktur.',
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
        ], $overrides));
    }

    // =========================================================================
    // §Y — Citizen: "Buat Laporan" form
    // =========================================================================

    public function test_create_page_renders_kecamatan_and_kelurahan_controls(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="kecamatan_id"', $html);
        $this->assertStringContainsString('name="kelurahan_id"', $html);
        $this->assertStringContainsString('Kecamatan', $html);
        $this->assertStringContainsString('Kelurahan', $html);

        // Dependent dropdown component is wired (progressive enhancement).
        $this->assertStringContainsString('locationPicker({', $html);
        $this->assertStringContainsString('x-for="kel in location.filteredKelurahan"', $html);
        // Native selects (not a custom widget / library): category + kecamatan + kelurahan.
        $this->assertSame(3, substr_count($html, '<select'));
    }

    public function test_create_page_embeds_master_data_from_database(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // Kecamatan + Kelurahan master data comes from the DB (not hard-coded).
        $this->assertStringContainsString('Kecamatan A', $html);
        $this->assertStringContainsString('Kecamatan B', $html);
        $this->assertStringContainsString('Kelurahan A1', $html);
        $this->assertStringContainsString('Kelurahan A2', $html);
        $this->assertStringContainsString('Kelurahan B1', $html);
    }

    public function test_kelurahan_belongs_to_kecamatan(): void
    {
        $this->assertSame($this->kecamatanA->id, $this->kelurahanA1->kecamatan_id);
        $this->assertTrue($this->kelurahanA1->kecamatan->is($this->kecamatanA));
    }

    public function test_create_page_handles_empty_master_data_without_error(): void
    {
        Kelurahan::query()->delete();
        Kecamatan::query()->delete();

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.create'));

        $response->assertOk(); // no 500
        $response->assertSee('Data Kecamatan belum tersedia.');
        // Submission is blocked until master data exists.
        $response->assertSee('!hasKecamatan', false);
    }

    public function test_create_page_keeps_old_location_input_after_validation_failure(): void
    {
        // Fail validation on an UNRELATED field (title too short) while supplying
        // a valid Kecamatan/Kelurahan pair.
        $this->actingAs($this->citizen)
            ->from(route('citizen.complaint.create'))
            ->post(route('citizen.complaint.store'), [
                'category_id'  => $this->category->id,
                'title'        => 'x', // too short
                'description'  => 'Deskripsi laporan yang cukup panjang.',
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanA1->id,
            ])
            ->assertSessionHasErrors('title');

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // Old input is preserved for the dependent picker initial state.
        $this->assertStringContainsString("selectedKecamatanId: '{$this->kecamatanA->id}'", $html);
        $this->assertStringContainsString("selectedKelurahanId: '{$this->kelurahanA1->id}'", $html);
    }

    public function test_valid_complaint_persists_structured_location_and_text(): void
    {
        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), [
                'category_id'   => $this->category->id,
                'title'         => 'Jalan rusak di Kelurahan A1',
                'description'   => 'Deskripsi laporan dengan lokasi terstruktur lengkap.',
                'location_text' => 'Depan pasar, dekat jembatan',
                'kecamatan_id'  => $this->kecamatanA->id,
                'kelurahan_id'  => $this->kelurahanA2->id,
            ])
            ->assertSessionHasNoErrors();

        $complaint = Complaint::where('title', 'Jalan rusak di Kelurahan A1')->firstOrFail();
        $this->assertSame($this->kecamatanA->id, $complaint->kecamatan_id);
        $this->assertSame($this->kelurahanA2->id, $complaint->kelurahan_id);
        $this->assertSame('Depan pasar, dekat jembatan', $complaint->location_text);
    }

    public function test_citizen_cannot_bypass_pair_validation_via_request_manipulation(): void
    {
        // Kecamatan A + Kelurahan of Kecamatan B (crafted, bypassing the UI).
        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), [
                'category_id'  => $this->category->id,
                'title'        => 'Laporan manipulasi pasangan',
                'description'  => 'Deskripsi laporan manipulasi pasangan lokasi.',
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanB1->id,
            ])
            ->assertSessionHasErrors('kelurahan_id');

        $this->assertDatabaseMissing('complaints', ['title' => 'Laporan manipulasi pasangan']);
    }

    // =========================================================================
    // §Y — Citizen: detail & history
    // =========================================================================

    public function test_citizen_detail_shows_structured_location(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id'  => $this->kecamatanA->id,
            'kelurahan_id'  => $this->kelurahanA1->id,
            'location_text' => 'Jl. Detail No. 1',
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.show', $complaint));

        $response->assertOk();
        $response->assertSee('Kecamatan A');
        $response->assertSee('Kelurahan A1');
        $response->assertSee('Jl. Detail No. 1');
    }

    public function test_legacy_complaint_detail_renders_without_structured_location(): void
    {
        $legacy = $this->makeComplaint([
            'kecamatan_id'  => null,
            'kelurahan_id'  => null,
            'location_text' => 'Lokasi lama tanpa struktur',
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.complaint.show', $legacy));

        $response->assertOk(); // no error on NULL location
        $response->assertSee('—'); // empty-state for Kecamatan/Kelurahan
        $response->assertSee('Lokasi lama tanpa struktur');
    }

    public function test_citizen_history_renders_structured_and_legacy_locations(): void
    {
        $structured = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA1->id,
            'title'        => 'Laporan Baru Terstruktur',
        ]);
        $legacy = $this->makeComplaint([
            'kecamatan_id'  => null,
            'kelurahan_id'  => null,
            'location_text' => 'Lokasi Lama Riwayat',
            'title'         => 'Laporan Lama',
        ]);

        $response = $this->actingAs($this->citizen)->get(route('citizen.history'));

        $response->assertOk();
        $response->assertSee('Laporan Baru Terstruktur');
        $response->assertSee('Kelurahan A1');
        $response->assertSee('Laporan Lama');
        $response->assertSee('Lokasi Lama Riwayat');
    }

    // =========================================================================
    // §Z — Operator: detail + edit location
    // =========================================================================

    public function test_operator_detail_shows_location_and_edit_form(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id'  => $this->kecamatanA->id,
            'kelurahan_id'  => $this->kelurahanA1->id,
            'location_text' => 'Jl. Operator Detail',
        ]);

        $html = $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Kecamatan A', $html);
        $this->assertStringContainsString('Kelurahan A1', $html);
        $this->assertStringContainsString('Jl. Operator Detail', $html);

        // Edit form uses the EXISTING operator location route.
        $this->assertStringContainsString('Ubah Lokasi', $html);
        $this->assertStringContainsString(route('operator.complaint.update-location', $complaint), $html);
        $this->assertStringContainsString('name="kecamatan_id"', $html);
        $this->assertStringContainsString('name="kelurahan_id"', $html);
        // Edit form is prefilled with the current pair.
        $this->assertStringContainsString("selectedKecamatanId: '{$this->kecamatanA->id}'", $html);
        $this->assertStringContainsString("selectedKelurahanId: '{$this->kelurahanA1->id}'", $html);
    }

    public function test_operator_update_location_keeps_location_text_unchanged(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id'  => $this->kecamatanA->id,
            'kelurahan_id'  => $this->kelurahanA1->id,
            'location_text' => 'Detail yang tidak boleh berubah',
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanB->id,
                'kelurahan_id' => $this->kelurahanB1->id,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();
        $this->assertSame($this->kecamatanB->id, $fresh->kecamatan_id);
        $this->assertSame($this->kelurahanB1->id, $fresh->kelurahan_id);
        // location_text is NOT touched by a location (Kecamatan/Kelurahan) update.
        $this->assertSame('Detail yang tidak boleh berubah', $fresh->location_text);
    }

    public function test_operator_update_location_rejects_mismatched_pair(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA1->id,
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanB1->id, // belongs to Kecamatan B
            ])
            ->assertSessionHasErrors('kelurahan_id');

        $fresh = $complaint->fresh();
        $this->assertSame($this->kecamatanA->id, $fresh->kecamatan_id);
        $this->assertSame($this->kelurahanA1->id, $fresh->kelurahan_id);
    }

    public function test_operator_update_location_writes_audit_log(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA1->id,
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanB->id,
                'kelurahan_id' => $this->kelurahanB1->id,
            ])
            ->assertSessionHasNoErrors();

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

    public function test_operator_cannot_update_location_outside_scope(): void
    {
        // Complaint routed to a DIFFERENT unit — not visible to this operator.
        $complaint = $this->makeComplaint([
            'dinas_unit_id' => $this->otherUnit->id,
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanA1->id,
            ])
            ->assertNotFound();

        $this->assertNull($complaint->fresh()->kecamatan_id);
    }

    public function test_admin_cannot_update_location(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanA1->id,
            ])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->kecamatan_id);
    }

    public function test_citizen_cannot_update_location(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanA1->id,
            ])
            ->assertForbidden();

        $this->assertNull($complaint->fresh()->kecamatan_id);
    }

    public function test_super_admin_can_update_location(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->superAdmin)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanA->id,
                'kelurahan_id' => $this->kelurahanA1->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->kecamatanA->id, $complaint->fresh()->kecamatan_id);
    }

    public function test_citizen_can_only_see_own_complaint_detail(): void
    {
        $other = $this->makeComplaint([
            'reporter_id'  => $this->otherCitizen->id,
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA1->id,
        ]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $other))
            ->assertForbidden();
    }

    // =========================================================================
    // §U / §AA — Super Admin master UI audit
    // =========================================================================

    public function test_super_admin_master_views_render_without_forbidden_fields(): void
    {
        foreach (['kecamatan', 'kelurahan'] as $resource) {
            foreach (['index', 'create'] as $action) {
                $response = $this->actingAs($this->superAdmin)
                    ->get(route("super-admin.{$resource}.{$action}"));
                $response->assertOk();
                $response->assertDontSee('is_active');
                $response->assertDontSee('latitude');
                $response->assertDontSee('longitude');
                $response->assertDontSee('polygon');
            }
        }

        // Edit views too.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.kecamatan.edit', $this->kecamatanA))
            ->assertOk()
            ->assertDontSee('is_active');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.kelurahan.edit', $this->kelurahanA1))
            ->assertOk()
            ->assertDontSee('is_active');
    }

    public function test_kelurahan_form_lists_kecamatan_options(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.kelurahan.create'));

        $response->assertOk();
        $response->assertSee('Kecamatan A');
        $response->assertSee('Kecamatan B');
        $response->assertSee('name="kecamatan_id"', false);
    }

    public function test_kelurahan_form_shows_empty_state_without_kecamatan(): void
    {
        Kelurahan::query()->delete();
        Kecamatan::query()->delete();

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.kelurahan.create'));

        $response->assertOk();
        $response->assertSee('Belum ada Kecamatan');
    }

    // =========================================================================
    // §N / §AR — Modular structure + Prompt 4/5 map/GPS/coordinate surface
    // =========================================================================

    public function test_create_page_exposes_modular_location_structure(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // Modular containers Prompt 3/5 introduced and Prompt 4/5 extends.
        $this->assertStringContainsString('location-section', $html);
        $this->assertStringContainsString('administrative-location', $html);
        $this->assertStringContainsString('location-detail', $html);
        $this->assertStringContainsString('location-map-slot', $html);
    }

    public function test_create_page_exposes_map_gps_and_coordinate_inputs(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // Map container (Leaflet canvas) + GPS + reset controls.
        $this->assertStringContainsString('location-map-canvas', $html);
        $this->assertStringContainsString('locationMap(', $html);
        $this->assertStringContainsString('Deteksi Lokasi Presisi Saya', $html);
        $this->assertStringContainsString('Hapus titik lokasi', $html);

        // Hidden coordinate inputs submitted with the normal form.
        $this->assertStringContainsString('name="latitude"', $html);
        $this->assertStringContainsString('name="longitude"', $html);

        // OpenStreetMap attribution is present and must never be removed.
        $this->assertStringContainsString('OpenStreetMap', $html);
        $this->assertStringContainsString('openstreetmap.org', $html);

        // Coordinates are OPTIONAL — no coordinate input is `required`.
        $this->assertStringNotContainsString('name="latitude" required', $html);
        $this->assertStringNotContainsString('name="longitude" required', $html);
    }

    public function test_create_page_does_not_request_geolocation_on_load(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // The permission prompt must be triggered ONLY by the GPS button, so no
        // inline geolocation call is rendered into the page markup.
        $this->assertStringNotContainsString('getCurrentPosition', $html);
        $this->assertStringNotContainsString('watchPosition', $html);
    }

    public function test_operator_detail_exposes_modular_structure_and_readonly_map(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA1->id,
        ]);

        $html = $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        // Modular structure preserved.
        $this->assertStringContainsString('location-section', $html);
        $this->assertStringContainsString('administrative-location', $html);
        $this->assertStringContainsString('location-map-slot', $html);
        $this->assertStringContainsString('Lokasi Laporan', $html);
        $this->assertStringContainsString('Lokasi/Alamat Detail', $html);

        // Prompt 4/5: an interactive map + coordinate inputs exist for the
        // authorised Operator edit flow.
        $this->assertStringContainsString('location-map-canvas', $html);
        $this->assertStringContainsString('name="latitude"', $html);
        $this->assertStringContainsString('name="longitude"', $html);
    }

    public function test_operator_detail_without_coordinates_shows_map_unavailable_note(): void
    {
        $complaint = $this->makeComplaint([
            'kecamatan_id' => $this->kecamatanA->id,
            'kelurahan_id' => $this->kelurahanA1->id,
        ]);
        $this->assertNull($complaint->latitude);
        $this->assertNull($complaint->longitude);

        $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Lokasi pada peta belum tersedia.');
    }

    public function test_create_form_marks_invalid_location_fields_for_accessibility(): void
    {
        $html = $this->actingAs($this->citizen)
            ->from(route('citizen.complaint.create'))
            ->followingRedirects()
            ->post(route('citizen.complaint.store'), [
                'category_id'  => $this->category->id,
                'title'        => 'Judul valid untuk uji aksesibilitas',
                'description'  => 'Deskripsi valid yang cukup panjang untuk lolos.',
                'kecamatan_id' => null,
                'kelurahan_id' => null,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="kecamatan_id-error"', $html);
        $this->assertStringContainsString('aria-describedby="kelurahan_id-error"', $html);
    }
}
