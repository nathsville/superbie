<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\ComplaintRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLocationFixtures;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 4/5 — Map + GPS + Coordinates
 *
 * Covers the OPTIONAL geographic location on top of the REQUIRED administrative
 * location:
 *   - latitude/longitude columns (nullable, DECIMAL(10,7))
 *   - server-side range + PAIR validation (the security boundary)
 *   - citizen create surface (map container, GPS, reset, hidden inputs, old input)
 *   - citizen detail (read-only map, NULL-safe)
 *   - operator view + edit (coordinates change, location_text untouched, audit)
 *   - authorization (scope / 403 / 404)
 *   - privacy / static audit (no watchPosition, no console logging, no URL leak)
 *
 * All fixtures are test-only. No fake coordinates are written to the real DB.
 */
class LocationCoordinatesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLocationFixtures;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;
    private User $otherCitizen;

    private DinasUnit $unit;
    private DinasUnit $otherUnit;

    private ComplaintCategory $category;

    private int $kecamatanId;
    private int $kelurahanId;
    private int $otherKelurahanId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->unit = DinasUnit::create([
            'name' => 'Dinas Uji Koordinat', 'code' => 'UJI-COORD',
            'is_active' => true, 'sort_order' => 0,
        ]);
        $this->otherUnit = DinasUnit::create([
            'name' => 'Dinas Uji Koordinat Lain', 'code' => 'UJI-COORD-OTHER',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->operator = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $this->unit->id,
        ]);
        $this->citizen = User::factory()->create([
            'role' => 'masyarakat', 'is_active' => true,
            'phone_number' => '081100000009', 'address' => 'Alamat Warga Koordinat',
        ]);
        $this->otherCitizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Koordinat', 'slug' => 'infrastruktur-koordinat',
            'dinas_name' => 'Dinas PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->category->dinasUnits()->attach($this->unit->id);

        [$this->kecamatanId, $this->kelurahanId] = $this->makeLocationPair('Kecamatan Koordinat', 'Kelurahan Koordinat');
        $this->otherKelurahanId = \App\Models\Kelurahan::create([
            'kecamatan_id' => $this->kecamatanId,
            'name'         => 'Kelurahan Koordinat Lain',
        ])->id;
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-COORD-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'dinas_unit_id'  => $this->unit->id,
            'title'          => 'Laporan Koordinat',
            'description'    => 'Deskripsi laporan dengan koordinat opsional.',
            'kecamatan_id'   => $this->kecamatanId,
            'kelurahan_id'   => $this->kelurahanId,
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
        ], $overrides));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'category_id'  => $this->category->id,
            'title'        => 'Laporan koordinat opsional warga',
            'description'  => 'Deskripsi laporan yang cukup panjang untuk lolos validasi.',
            'kecamatan_id' => $this->kecamatanId,
            'kelurahan_id' => $this->kelurahanId,
        ], $overrides);
    }

    // =========================================================================
    // §AO — Database / model
    // =========================================================================

    public function test_complaints_table_has_nullable_coordinate_columns(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('complaints', 'latitude'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('complaints', 'longitude'));

        // Both nullable — a complaint is valid without any geographic point.
        $complaint = $this->makeComplaint();
        $this->assertNull($complaint->latitude);
        $this->assertNull($complaint->longitude);
    }

    public function test_complaint_can_store_latitude_and_longitude(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'  => -4.1234567,
            'longitude' => 119.6543210,
        ]);

        $fresh = $complaint->fresh();
        $this->assertSame(-4.1234567, (float) $fresh->latitude);
        $this->assertSame(119.6543210, (float) $fresh->longitude);
    }

    public function test_coordinates_are_persisted_with_seven_decimals(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'  => -4.5,
            'longitude' => 119.5,
        ]);

        // decimal:7 cast → fixed 7-decimal string.
        $this->assertSame('-4.5000000', $complaint->fresh()->latitude);
        $this->assertSame('119.5000000', $complaint->fresh()->longitude);
    }

    // =========================================================================
    // §AO / §AP — Server-side validation (security boundary)
    // =========================================================================

    public function test_valid_coordinates_are_accepted_and_stored_on_store(): void
    {
        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->storePayload([
                'latitude'  => -4.0211111,
                'longitude' => 119.6222222,
            ]))
            ->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->latest('id')->firstOrFail();
        $this->assertSame(-4.0211111, (float) $complaint->latitude);
        $this->assertSame(119.6222222, (float) $complaint->longitude);
    }

    public function test_complaint_can_be_created_without_coordinates(): void
    {
        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->storePayload())
            ->assertSessionHasNoErrors();

        $complaint = Complaint::where('reporter_id', $this->citizen->id)->latest('id')->firstOrFail();
        $this->assertNull($complaint->latitude);
        $this->assertNull($complaint->longitude);
    }

    public function test_latitude_out_of_range_is_rejected(): void
    {
        foreach ([91, -91] as $bad) {
            $this->actingAs($this->citizen)
                ->from(route('citizen.complaint.create'))
                ->post(route('citizen.complaint.store'), $this->storePayload([
                    'latitude'  => $bad,
                    'longitude' => 119.5,
                ]))
                ->assertSessionHasErrors('latitude');
        }

        $this->assertSame(0, Complaint::where('reporter_id', $this->citizen->id)->count());
    }

    public function test_longitude_out_of_range_is_rejected(): void
    {
        foreach ([181, -181] as $bad) {
            $this->actingAs($this->citizen)
                ->from(route('citizen.complaint.create'))
                ->post(route('citizen.complaint.store'), $this->storePayload([
                    'latitude'  => -4.5,
                    'longitude' => $bad,
                ]))
                ->assertSessionHasErrors('longitude');
        }

        $this->assertSame(0, Complaint::where('reporter_id', $this->citizen->id)->count());
    }

    public function test_partial_pair_is_rejected_on_store(): void
    {
        // latitude without longitude.
        $this->actingAs($this->citizen)
            ->from(route('citizen.complaint.create'))
            ->post(route('citizen.complaint.store'), $this->storePayload(['latitude' => -4.5]))
            ->assertSessionHasErrors('longitude');

        // longitude without latitude.
        $this->actingAs($this->citizen)
            ->from(route('citizen.complaint.create'))
            ->post(route('citizen.complaint.store'), $this->storePayload(['longitude' => 119.5]))
            ->assertSessionHasErrors('latitude');

        $this->assertSame(0, Complaint::where('reporter_id', $this->citizen->id)->count());
    }

    public function test_old_coordinates_are_preserved_after_validation_failure(): void
    {
        $this->actingAs($this->citizen)
            ->from(route('citizen.complaint.create'))
            ->post(route('citizen.complaint.store'), $this->storePayload([
                'title'     => 'x', // too short → fails on an unrelated field
                'latitude'  => -4.1234567,
                'longitude' => 119.7654321,
            ]))
            ->assertSessionHasErrors('title');

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // The map component is seeded with the old pair (valid pair only).
        $this->assertStringContainsString('-4.1234567', $html);
        $this->assertStringContainsString('119.7654321', $html);
    }

    public function test_partial_old_coordinates_do_not_seed_the_map(): void
    {
        $this->actingAs($this->citizen)
            ->from(route('citizen.complaint.create'))
            ->post(route('citizen.complaint.store'), $this->storePayload([
                'title'     => 'x', // fails validation
                'latitude'  => -4.1234567, // longitude intentionally omitted
            ]))
            ->assertSessionHasErrors();

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // A partial pair is never turned into a marker seed: the component's
        // config receives `lat: null` (the incomplete pair is discarded).
        $this->assertStringContainsString('\u0022lat\u0022:null', $html);
        $this->assertStringNotContainsString('\u0022lat\u0022:-4.1234567', $html);
    }

    // =========================================================================
    // §AP — Citizen UI surface
    // =========================================================================

    public function test_create_page_has_map_gps_reset_and_hidden_coordinate_inputs(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('location-map-canvas', $html);
        $this->assertStringContainsString('locationMap(', $html);
        $this->assertStringContainsString('Deteksi Lokasi Presisi Saya', $html);
        $this->assertStringContainsString('Hapus titik lokasi', $html);
        $this->assertStringContainsString('name="latitude"', $html);
        $this->assertStringContainsString('name="longitude"', $html);
        // Coordinates are optional: never `required`.
        $this->assertStringNotContainsString('name="latitude" required', $html);
        $this->assertStringNotContainsString('name="longitude" required', $html);
    }

    public function test_map_is_not_rendered_on_pages_that_do_not_need_it(): void
    {
        // Citizen history must NOT initialise a map.
        $history = $this->actingAs($this->citizen)
            ->get(route('citizen.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('location-map-canvas', $history);
        $this->assertStringNotContainsString('locationMap(', $history);

        // Citizen dashboard must NOT initialise a map either.
        $dashboard = $this->actingAs($this->citizen)
            ->get(route('citizen.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('location-map-canvas', $dashboard);
        $this->assertStringNotContainsString('locationMap(', $dashboard);
    }

    // =========================================================================
    // §V — Citizen detail
    // =========================================================================

    public function test_citizen_detail_shows_readonly_map_when_coordinates_exist(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'  => -4.1234567,
            'longitude' => 119.7654321,
        ]);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('location-map-canvas', $html);
        $this->assertStringContainsString('-4.1234567', $html);
        $this->assertStringContainsString('119.7654321', $html);
        // Read-only: no GPS button on the detail page.
        $this->assertStringNotContainsString('Deteksi Lokasi Presisi Saya', $html);
    }

    public function test_citizen_detail_without_coordinates_is_null_safe(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $complaint))
            ->assertOk()
            ->assertSee('Lokasi pada peta belum tersedia.');
    }

    public function test_citizen_cannot_view_other_citizen_complaint_map(): void
    {
        $other = $this->makeComplaint([
            'reporter_id' => $this->otherCitizen->id,
            'latitude'    => -4.1,
            'longitude'   => 119.1,
        ]);

        $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.show', $other))
            ->assertForbidden();
    }

    // =========================================================================
    // §AQ / §X — Operator view + edit
    // =========================================================================

    public function test_operator_detail_shows_map_when_coordinates_exist(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'  => -4.2222222,
            'longitude' => 119.3333333,
        ]);

        $html = $this->actingAs($this->operator)
            ->get(route('operator.complaint.show', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('location-map-canvas', $html);
        $this->assertStringContainsString('-4.2222222', $html);
        $this->assertStringContainsString('119.3333333', $html);
    }

    public function test_operator_can_edit_coordinates(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->kelurahanId,
                'latitude'     => -4.4444444,
                'longitude'    => 119.5555555,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();
        $this->assertSame(-4.4444444, (float) $fresh->latitude);
        $this->assertSame(119.5555555, (float) $fresh->longitude);
    }

    public function test_operator_can_clear_coordinates(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'  => -4.4444444,
            'longitude' => 119.5555555,
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->kelurahanId,
                'latitude'     => '',
                'longitude'    => '',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();
        $this->assertNull($fresh->latitude);
        $this->assertNull($fresh->longitude);
    }

    public function test_operator_location_update_keeps_location_text_and_pair(): void
    {
        $complaint = $this->makeComplaint([
            'location_text' => 'Detail alamat yang harus tetap sama',
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->otherKelurahanId,
                'latitude'     => -4.1,
                'longitude'    => 119.1,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();
        $this->assertSame($this->otherKelurahanId, $fresh->kelurahan_id);
        $this->assertSame('Detail alamat yang harus tetap sama', $fresh->location_text);
    }

    public function test_operator_invalid_coordinates_are_rejected(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->kelurahanId,
                'latitude'     => 120, // out of range
                'longitude'    => 119.5,
            ])
            ->assertSessionHasErrors('latitude');

        $this->assertNull($complaint->fresh()->latitude);
    }

    public function test_operator_partial_coordinate_pair_is_rejected_atomically(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->otherKelurahanId, // valid administrative change
                'latitude'     => -4.5,                     // but partial pair
            ])
            ->assertSessionHasErrors('longitude');

        // Atomic: the administrative change must NOT be persisted either.
        $fresh = $complaint->fresh();
        $this->assertSame($this->kelurahanId, $fresh->kelurahan_id);
        $this->assertNull($fresh->latitude);
    }

    public function test_operator_coordinate_update_writes_audit_with_old_and_new(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'  => -4.1111111,
            'longitude' => 119.1111111,
        ]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->kelurahanId,
                'latitude'     => -4.9999999,
                'longitude'    => 119.9999999,
            ])
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'complaint.location_updated')
            ->where('subject_id', $complaint->id)
            ->firstOrFail();

        $this->assertSame('-4.1111111', $log->metadata['old_latitude']);
        $this->assertSame('-4.9999999', $log->metadata['new_latitude']);
        $this->assertSame('119.1111111', $log->metadata['old_longitude']);
        $this->assertSame('119.9999999', $log->metadata['new_longitude']);
    }

    public function test_operator_out_of_scope_cannot_edit_coordinates(): void
    {
        $complaint = $this->makeComplaint(['dinas_unit_id' => $this->otherUnit->id]);

        $this->actingAs($this->operator)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->kelurahanId,
                'latitude'     => -4.5,
                'longitude'    => 119.5,
            ])
            ->assertNotFound();

        $this->assertNull($complaint->fresh()->latitude);
    }

    public function test_admin_cannot_edit_coordinates(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->admin)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->kelurahanId,
                'latitude'     => -4.5,
                'longitude'    => 119.5,
            ])
            ->assertForbidden();
    }

    public function test_citizen_cannot_edit_coordinates_via_operator_endpoint(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->citizen)
            ->patch(route('operator.complaint.update-location', $complaint), [
                'kecamatan_id' => $this->kecamatanId,
                'kelurahan_id' => $this->kelurahanId,
                'latitude'     => -4.5,
                'longitude'    => 119.5,
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // §AM — Retention
    // =========================================================================

    public function test_retention_purge_removes_complaint_with_coordinates(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'     => -4.5,
            'longitude'    => 119.5,
            'submitted_at' => now()->subYears(6),
        ]);

        app(ComplaintRetentionService::class)->purge($complaint);

        $this->assertDatabaseMissing('complaints', ['id' => $complaint->id]);
    }

    // =========================================================================
    // §AR / §AS — Static privacy / security audit
    // =========================================================================

    public function test_app_js_has_no_watch_position_or_coordinate_logging(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('watchPosition', $js);
        $this->assertStringNotContainsString('console.log(lat', $js);
        $this->assertStringNotContainsString('console.log(long', $js);
        $this->assertStringNotContainsString('console.log(coords', $js);
        // No OTHER third-party geolocation providers are used.
        $this->assertStringNotContainsString('googleapis', strtolower($js));
        $this->assertStringNotContainsString('mapbox', strtolower($js));
    }

    /**
     * SCOPE / PRIVACY DEVIATION (owner-approved) — reverse geocoding.
     *
     * The PRD listed "geocoding" as out of scope and the map contract promised
     * coordinates were "never sent anywhere except the form". The owner approved
     * pre-filling the OPTIONAL address from Nominatim, so the blanket ban was
     * lifted. In its place we now enforce the NEW contract: the third-party call
     * is confined to the dedicated `locationAddress` component and is GATED by an
     * explicit citizen opt-in — the coordinate is never transmitted unless the
     * citizen ticks the box.
     */
    public function test_reverse_geocoding_is_confined_and_gated_by_explicit_opt_in(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        // The only geocoder in use is the dedicated, opt-in-gated component.
        $this->assertStringContainsString("Alpine.data('locationAddress'", $js);
        $this->assertStringContainsString('nominatim', strtolower($js));

        // Consent gate: a boolean opt-in exists and short-circuits the request.
        $this->assertStringContainsString('optIn', $js);
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*!this\.optIn\s*\)\s*\{/s',
            $js,
            'The reverse-geocode request must be skipped unless the citizen opts in.',
        );

        // "Fill only when empty": a manual address must short-circuit too.
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*this\.hasText\s*\)\s*\{/s',
            $js,
            'A manually typed address must never be overwritten by detection.',
        );

        // It must remain a one-shot request per point — never a background loop.
        $this->assertStringNotContainsString('setInterval', $js);
    }

    public function test_geocoding_surface_is_citizen_only_and_absent_from_staff_views(): void
    {
        // Citizen create/edit MAY offer the opt-in reverse geocoder.
        foreach (['citizen/complaint/create', 'citizen/complaint/edit'] as $view) {
            $html = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertStringContainsString('locationAddress(', $html, "{$view} should offer the opt-in geocoder.");
        }

        // The staff detail views must NEVER instantiate it (read-only, no consent UI).
        foreach ([
            'operator/complaint/show',
            'super-admin/complaints/show',
            'super-admin/validation/show',
            'super-admin/completion-validation/show',
            'citizen/complaint/show',
        ] as $view) {
            $html = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertStringNotContainsString('locationAddress(', $html, "{$view} must not offer reverse geocoding.");
        }
    }

    public function test_create_page_renders_opt_in_geocoder_surface(): void
    {
        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // The dedicated component is instantiated with the opt-in checkbox and a
        // visible consent disclosure naming the third-party provider.
        $this->assertStringContainsString('locationAddress(', $html);
        $this->assertStringContainsString('Isi alamat otomatis dari titik lokasi.', $html);
        $this->assertStringContainsString('Nominatim', $html);

        // The address field is still an ordinary editable text input (x-model),
        // NOT a locked/readonly control — the suggestion never removes control.
        $this->assertStringContainsString('name="location_text"', $html);
        $this->assertStringContainsString('x-model="text"', $html);
    }

    public function test_create_page_omits_geocoder_when_disabled_by_config(): void
    {
        config()->set('map.reverse_geocode.enabled', false);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.create'))
            ->assertOk()
            ->getContent();

        // The component still exists (config carries `enabled: false`) but the
        // consent UI/checkbox is NOT rendered — no third-party call is possible.
        $this->assertStringContainsString('locationAddress(', $html);
        $this->assertStringNotContainsString('Isi alamat otomatis dari titik lokasi.', $html);
    }

    public function test_edit_page_renders_opt_in_geocoder_for_returned_complaint(): void
    {
        $complaint = $this->makeComplaint([
            'review_state' => \App\Enums\ComplaintReviewState::ReturnedToCitizen,
        ]);

        $html = $this->actingAs($this->citizen)
            ->get(route('citizen.complaint.edit', $complaint))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('locationAddress(', $html);
        $this->assertStringContainsString('Isi alamat otomatis dari titik lokasi.', $html);
    }

    public function test_geolocation_is_requested_once_only(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        // getCurrentPosition is used (one-shot) and watchPosition is never used.
        $this->assertStringContainsString('getCurrentPosition', $js);
        $this->assertStringNotContainsString('watchPosition', $js);
        $this->assertSame(1, substr_count($js, 'getCurrentPosition('));
    }

    public function test_no_public_tracking_or_coordinate_routes_exist(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->implode("\n");

        foreach (['tracking', 'public/complaint', 'public/map', 'public/location', 'latitude', 'longitude'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $routes, "No public route should expose '{$forbidden}'.");
        }
    }

    public function test_guest_cannot_reach_complaint_map(): void
    {
        $complaint = $this->makeComplaint([
            'latitude'  => -4.5,
            'longitude' => 119.5,
        ]);

        $this->get(route('citizen.complaint.show', $complaint))
            ->assertRedirect(route('login'));
    }

    public function test_complaint_map_is_not_exposed_on_landing_page(): void
    {
        $html = $this->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('location-map-canvas', $html);
        $this->assertStringNotContainsString('locationMap(', $html);
    }

    // =========================================================================
    // §AU — Admin dashboard geographic card (Prompt 5/5)
    // =========================================================================

    public function test_admin_dashboard_shows_geographic_map_without_geolocation(): void
    {
        // Prompt 5/5 ADDS a read-only geographic card to the Admin dashboard.
        // The old "no map on admin dashboard" assertion is now obsolete: the
        // dashboard MUST render the map component while STILL never requesting
        // device geolocation (that stays a citizen/operator-form-only concern).
        $this->makeComplaint([
            'latitude'  => -4.01,
            'longitude' => 119.62,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        // The geographic card and its Leaflet canvas ARE present.
        $this->assertStringContainsString('Peta Wilayah Aduan', $html);
        $this->assertStringContainsString('geographic-map-canvas', $html);
        $this->assertStringContainsString('geographicMap(', $html);

        // Admin is READ-ONLY: no geolocation affordance leaks into the dashboard.
        $this->assertStringNotContainsString('Deteksi Lokasi Presisi Saya', $html);
        $this->assertStringNotContainsString('getCurrentPosition', $html);
        $this->assertStringNotContainsString('watchPosition', $html);

        // The old report-location form map must NOT be reused here.
        $this->assertStringNotContainsString('location-map-canvas', $html);
        $this->assertStringNotContainsString('locationMap(', $html);
    }

    public function test_admin_dashboard_map_is_not_rendered_for_other_roles(): void
    {
        // The geographic card lives on the Admin dashboard ONLY (Prompt 5/5
        // scope). Citizen surfaces must not initialise the analytics map.
        $citizenDashboard = $this->actingAs($this->citizen)
            ->get(route('citizen.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('geographic-map-canvas', $citizenDashboard);
        $this->assertStringNotContainsString('geographicMap(', $citizenDashboard);
    }

    public function test_no_fake_coordinates_are_seeded(): void
    {
        // The seeder must not fabricate coordinates (no fake data, Prompt 4/5 §AW).
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString("'latitude'", $seeder);
        $this->assertStringNotContainsString("'longitude'", $seeder);
    }

    public function test_coordinates_are_not_stored_in_audit_on_citizen_create(): void
    {
        // Creating a complaint with coordinates must NOT emit a new GPS/map audit
        // event (Prompt 4/5 §Z) — only the existing complaint_created audit.
        $this->actingAs($this->citizen)
            ->post(route('citizen.complaint.store'), $this->storePayload([
                'latitude'  => -4.5,
                'longitude' => 119.5,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AuditLog::where('action', 'complaint_created')->count());
        $this->assertSame(0, AuditLog::whereIn('action', [
            'complaint.gps_updated', 'gps_detected', 'map_clicked', 'location_permission_granted',
        ])->count());
    }

    public function test_coordinates_are_stored_as_decimal_not_geometry(): void
    {
        // Guard against an accidental spatial/geometry column (Prompt 4/5 §D).
        $columns = DB::select('SHOW COLUMNS FROM complaints');
        $byName = collect($columns)->keyBy('Field');

        $this->assertSame('decimal(10,7)', strtolower($byName['latitude']->Type));
        $this->assertSame('decimal(10,7)', strtolower($byName['longitude']->Type));
        // No index of any kind (spatial or otherwise) on the coordinate columns.
        $this->assertSame('', (string) $byName['latitude']->Key, 'latitude must not be indexed as spatial.');
        $this->assertSame('', (string) $byName['longitude']->Key, 'longitude must not be indexed as spatial.');
    }
}
