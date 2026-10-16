<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SUPERBIE — PROMPT 5/5 — Peta Wilayah Aduan (geographic dashboard card).
 *
 * Guards the read-only geographic analytics card added to the Admin dashboard:
 *   - administrative counts come from kecamatan_id / kelurahan_id (GROUP BY);
 *   - pins/heat points come ONLY from valid latitude+longitude PAIRS;
 *   - location_text is NEVER a coordinate source;
 *   - complaints without coordinates STILL count administratively;
 *   - no public map/tracking route, no geolocation on the dashboard;
 *   - privacy: only display-safe fields reach the client;
 *   - constant query count (no N+1);
 *   - cache miss / hit / invalidation wiring;
 *   - UI / accessibility / static-audit invariants.
 *
 * All fixtures are test-only. No fake coordinates are written to the real DB.
 */
class GeographicDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superAdmin;
    private User $citizen;
    private User $operator;

    private ComplaintCategory $category;

    private Kecamatan $kecamatanA;
    private Kecamatan $kecamatanB;
    private Kelurahan $kelurahanA1;
    private Kelurahan $kelurahanB1;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = DinasUnit::create([
            'name' => 'Dinas Uji Peta', 'code' => 'UJI-PETA',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);
        $this->operator   = User::factory()->create([
            'role' => 'operator', 'is_active' => true, 'dinas_unit_id' => $unit->id,
        ]);

        $this->category = ComplaintCategory::create([
            'name' => 'Infrastruktur Peta', 'slug' => 'infrastruktur-peta',
            'dinas_name' => 'Dinas PUPR', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->kecamatanA  = Kecamatan::create(['name' => 'Kecamatan Uji', 'code' => '91121']);
        $this->kecamatanB  = Kecamatan::create(['name' => 'Kecamatan Lain', 'code' => '91122']);
        $this->kelurahanA1 = Kelurahan::create(['kecamatan_id' => $this->kecamatanA->id, 'name' => 'Kelurahan Satu']);
        $this->kelurahanB1 = Kelurahan::create(['kecamatan_id' => $this->kecamatanB->id, 'name' => 'Kelurahan Dua']);
    }

    private function makeComplaint(array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'reference_code' => 'LPW-GEO-' . strtoupper(uniqid()),
            'reporter_id'    => $this->citizen->id,
            'category_id'    => $this->category->id,
            'title'          => 'Laporan peta wilayah',
            'description'    => 'Deskripsi laporan untuk pengujian peta wilayah aduan.',
            'location_text'  => 'Kec. Uji, Kota Parepare',
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ], $overrides));
    }

    /**
     * Create a complaint whose `submitted_at` falls inside the calendar month
     * `$monthsAgo` months before the current month (0 = current month). Used by
     * the regional-trend tests so fixtures land inside the dashboard window.
     */
    private function complaintInMonth(int $monthsAgo, array $overrides = []): Complaint
    {
        return $this->makeComplaint(array_merge([
            'submitted_at' => now()->startOfMonth()->subMonths($monthsAgo)->addDays(3)->setTime(10, 0),
        ], $overrides));
    }

    /**
     * The expected month labels for the dashboard trend window (mirrors the
     * service's trendPeriod(): last 6 calendar months, oldest first).
     *
     * @return list<string>
     */
    private function expectedTrendLabels(int $months = 6): array
    {
        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $labels[] = now()->startOfMonth()->subMonths($months - 1 - $i)->translatedFormat('M Y');
        }

        return $labels;
    }

    /**
     * Create a complaint whose `submitted_at` is exactly `$days` days before the
     * current time (used by the Layer 2B age metrics). `$days = 0` → submitted
     * today (age 0).
     */
    private function complaintDaysAgo(int $days, array $overrides = []): Complaint
    {
        return $this->makeComplaint(array_merge([
            'submitted_at' => now()->subDays($days),
        ], $overrides));
    }

    // =========================================================================
    // §1 — Authorization: the geographic card is Admin-only
    // =========================================================================

    public function test_admin_dashboard_renders_geographic_card(): void
    {
        // A coordinate-bearing complaint so the Leaflet canvas actually renders.
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Peta Wilayah Aduan', $html);
        $this->assertStringContainsString('geographic-map-canvas', $html);
        $this->assertStringContainsString('geographicMap(', $html);
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_citizen_and_operator_cannot_reach_admin_dashboard(): void
    {
        $this->actingAs($this->citizen)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->operator)->get(route('admin.dashboard'))->assertForbidden();
    }

    // =========================================================================
    // §2 — Payload shape + counts
    // =========================================================================

    public function test_payload_shape_and_coordinate_counts(): void
    {
        // 3 with coordinates, 2 without.
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['latitude' => -4.02, 'longitude' => 119.63, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['latitude' => -4.03, 'longitude' => 119.64, 'kecamatan_id' => $this->kecamatanB->id, 'kelurahan_id' => $this->kelurahanB1->id]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(); // legacy: no kecamatan, no kelurahan, no coords

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicMap', function ($map) {
            if (! is_array($map)) {
                return false;
            }

            foreach (['totalReports', 'reportsWithCoordinates', 'reportsWithoutCoordinates', 'points', 'byKecamatan', 'byKelurahan'] as $key) {
                if (! array_key_exists($key, $map)) {
                    return false;
                }
            }

            return $map['totalReports'] === 5
                && $map['reportsWithCoordinates'] === 3
                && $map['reportsWithoutCoordinates'] === 2
                && count($map['points']) === 3;
        });
    }

    public function test_complaints_without_coordinates_still_count_administratively(): void
    {
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicMap', function ($map) {
            $byKec = collect($map['byKecamatan'])->pluck('total', 'name');

            // All 3 count administratively even though only 1 has coordinates.
            return $map['reportsWithCoordinates'] === 1
                && $map['reportsWithoutCoordinates'] === 2
                && ($byKec['Kecamatan Uji'] ?? 0) === 3;
        });
    }

    public function test_location_text_is_never_a_coordinate_source(): void
    {
        // A complaint with a location_text but NO latitude/longitude pair must
        // NOT become a map point (the two concepts stay separate).
        $this->makeComplaint([
            'location_text' => '-4.1234, 119.5678',
            'kecamatan_id'  => $this->kecamatanA->id,
            'kelurahan_id'  => $this->kelurahanA1->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicMap', fn ($map) => $map['reportsWithCoordinates'] === 0
            && count($map['points']) === 0);
    }

    // =========================================================================
    // §3 — Administrative aggregation + deterministic ordering
    // =========================================================================

    public function test_administrative_aggregation_and_sorting(): void
    {
        // Kecamatan Uji: 3 (2 with coords + 1 without). Kecamatan Lain: 1.
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['latitude' => -4.02, 'longitude' => 119.63, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['latitude' => -4.03, 'longitude' => 119.64, 'kecamatan_id' => $this->kecamatanB->id, 'kelurahan_id' => $this->kelurahanB1->id]);
        $this->makeComplaint(); // legacy: grouped under "Belum Ditentukan"

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicMap', function ($map) {
            // byKecamatan: Uji(3) first, then the 1-count tie broken by name ASC
            // → "Belum Ditentukan" before "Kecamatan Lain".
            $kecNames = array_column($map['byKecamatan'], 'name');
            $kecTotals = array_column($map['byKecamatan'], 'total');

            $byKel = collect($map['byKelurahan'])->pluck('total', 'name');

            return $kecNames === ['Kecamatan Uji', 'Belum Ditentukan', 'Kecamatan Lain']
                && $kecTotals === [3, 1, 1]
                && ($byKel['Kelurahan Satu'] ?? 0) === 3
                && ($byKel['Kelurahan Dua'] ?? 0) === 1
                && ($byKel['Belum Ditentukan'] ?? 0) === 1;
        });
    }

    public function test_kelurahan_rows_carry_parent_kecamatan_name(): void
    {
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanB->id, 'kelurahan_id' => $this->kelurahanB1->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicMap', function ($map) {
            $row = collect($map['byKelurahan'])->firstWhere('name', 'Kelurahan Dua');

            return $row !== null
                && $row['kecamatan'] === 'Kecamatan Lain'
                && $row['total'] === 1;
        });
    }

    // =========================================================================
    // §4 — Point payload: fields + privacy
    // =========================================================================

    public function test_point_payload_contains_only_display_safe_fields(): void
    {
        $this->makeComplaint([
            'latitude'       => -4.1234567,
            'longitude'      => 119.7654321,
            'status'         => 'in_progress',
            'kecamatan_id'   => $this->kecamatanA->id,
            'kelurahan_id'   => $this->kelurahanA1->id,
            'reporter_phone' => '081100000099',
            'reporter_email' => 'rahasia@example.test',
            'description'    => 'Deskripsi sensitif yang tidak boleh bocor ke peta.',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicMap', function ($map) {
            $point = $map['points'][0] ?? null;
            if (! is_array($point)) {
                return false;
            }

            $expectedKeys = ['id', 'complaint_number', 'latitude', 'longitude', 'status', 'status_label', 'category', 'kecamatan', 'kelurahan'];
            sort($expectedKeys);
            $actualKeys = array_keys($point);
            sort($actualKeys);

            return $actualKeys === $expectedKeys
                && $point['latitude'] === -4.1234567
                && $point['longitude'] === 119.7654321
                && $point['status'] === 'in_progress'
                && $point['status_label'] === 'Sedang Diproses'
                && $point['category'] === 'Infrastruktur Peta'
                && $point['kecamatan'] === 'Kecamatan Uji'
                && $point['kelurahan'] === 'Kelurahan Satu';
        });
    }

    public function test_no_sensitive_fields_are_serialised_into_the_page(): void
    {
        $this->makeComplaint([
            'latitude'       => -4.5,
            'longitude'      => 119.5,
            'reporter_phone' => '081100007777',
            'reporter_email' => 'janganbocor@example.test',
            'description'    => 'KALIMAT_SENSITIF_JANGAN_BOCOR',
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['081100007777', 'janganbocor@example.test', 'KALIMAT_SENSITIF_JANGAN_BOCOR', 'tracking_secret_hash'] as $leak) {
            $this->assertStringNotContainsString($leak, $html, "Sensitive value '{$leak}' must never reach the dashboard HTML.");
        }
    }

    // =========================================================================
    // §5 — No public map/tracking route (Prompt 5/5 hard forbid)
    // =========================================================================

    public function test_no_public_or_admin_map_routes_exist(): void
    {
        // Compare exact URI SEGMENTS so words like "mapping" (a category route)
        // are not mistaken for a geographic map route.
        $segments = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->flatMap(fn ($route) => explode('/', $route->uri()))
            ->map(fn ($segment) => strtolower($segment))
            ->all();

        foreach (['map', 'geographic', 'tracking', 'choropleth', 'geojson', 'heatmap'] as $forbidden) {
            $this->assertNotContains($forbidden, $segments, "No route may expose '{$forbidden}' (Prompt 5/5 hard forbid).");
        }
    }

    // =========================================================================
    // §6 — Caching
    // =========================================================================

    public function test_geographic_payload_is_cached_and_invalidated_on_new_complaint(): void
    {
        Cache::forget('dashboard:admin');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(Cache::has('dashboard:admin'));

        $cached = Cache::get('dashboard:admin');
        $this->assertSame(0, $cached['geographicMap']['totalReports']);

        // A new complaint invalidates the global staff caches (ComplaintObserver).
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62]);
        $this->assertFalse(Cache::has('dashboard:admin'));
    }

    // =========================================================================
    // §7 — No N+1: constant query count as data grows
    // =========================================================================

    public function test_query_count_is_constant_as_complaints_grow(): void
    {
        // Two complaints with coordinates → baseline query count on a cache miss.
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['latitude' => -4.02, 'longitude' => 119.63, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Six more complaints across the same + a new kelurahan.
        for ($i = 0; $i < 6; $i++) {
            $this->makeComplaint(['latitude' => -4.0 - ($i / 100), 'longitude' => 119.6, 'kecamatan_id' => $this->kecamatanB->id, 'kelurahan_id' => $this->kelurahanB1->id]);
        }

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $grown = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $baseline,
            $grown,
            'The dashboard (incl. the geographic card) must not issue per-complaint queries (N+1).'
        );
    }

    // =========================================================================
    // §8 — UI / accessibility / static audit
    // =========================================================================

    public function test_mode_buttons_are_accessible(): void
    {
        // Buttons only render when there is at least one mappable point.
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Accessible segmented control: real buttons + aria-pressed state.
        $this->assertStringContainsString('aria-label="Mode tampilan peta"', $html);
        $this->assertSame(3, substr_count($html, 'setMode('), 'exactly three mode controls');
        $this->assertSame(3, substr_count($html, ':aria-pressed="mode ==='), 'each mode button exposes aria-pressed');
        // The mode controls are real buttons, never submit buttons.
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringNotContainsString('<button type="submit" @click="setMode', $html);
    }

    public function test_admin_dashboard_never_requests_device_geolocation(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('navigator.geolocation', $html);
        $this->assertStringNotContainsString('getCurrentPosition', $html);
        $this->assertStringNotContainsString('watchPosition', $html);
        $this->assertStringNotContainsString('Deteksi Lokasi Presisi Saya', $html);
    }

    public function test_geographic_map_is_isolated_in_its_own_component(): void
    {
        // The card must live in a dedicated Blade component, never inline
        // Leaflet markup in admin/dashboard.blade.php.
        $dashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString('<x-dashboard.geographic-map', $dashboard);
        $this->assertStringNotContainsString('L.map(', $dashboard);
        $this->assertStringNotContainsString('tileLayer', $dashboard);

        $this->assertFileExists(resource_path('views/components/dashboard/geographic-map.blade.php'));
    }

    public function test_blade_component_has_no_database_queries(): void
    {
        $blade = file_get_contents(resource_path('views/components/dashboard/geographic-map.blade.php'));

        foreach (['DB::', '::query()', '->get()', '->count()', '->paginate(', 'Eloquent'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $blade, "The map component must not query the database ('{$forbidden}').");
        }
    }

    public function test_app_js_map_component_is_defensive_and_offline(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        // Plugin-presence guards: fall back instead of crashing.
        $this->assertStringContainsString("typeof L.markerClusterGroup === 'function'", $js);
        $this->assertStringContainsString("typeof L.heatLayer !== 'function'", $js);

        // No AJAX in the map component (mode switch / zoom / pan / click are local).
        $this->assertStringNotContainsString('XMLHttpRequest', $js);
        $this->assertStringNotContainsString('axios', $js);

        // Center comes from config, never from device geolocation.
        $this->assertStringContainsString('defaultCenter', $js);

        // Minimal read-only popup fields.
        foreach (['Nomor Laporan', 'Kategori', 'Kecamatan', 'Kelurahan', 'Status'] as $label) {
            $this->assertStringContainsString($label, $js);
        }
    }

    public function test_leaflet_plugins_are_registered_via_dedicated_modules(): void
    {
        // Global `L` + plugins must be bootstrapped by dedicated modules, in the
        // correct order (global first, plugins second).
        $this->assertFileExists(resource_path('js/leaflet-global.js'));
        $this->assertFileExists(resource_path('js/leaflet-plugins.js'));

        $global = file_get_contents(resource_path('js/leaflet-global.js'));
        $this->assertStringContainsString('window.L = L', $global);

        $plugins = file_get_contents(resource_path('js/leaflet-plugins.js'));
        $this->assertStringContainsString("./leaflet-global.js", $plugins);
        $this->assertStringContainsString('leaflet.markercluster', $plugins);
        $this->assertStringContainsString('leaflet.heat', $plugins);

        $app = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("./leaflet-global.js", $app);
        $this->assertStringContainsString("./leaflet-plugins.js", $app);
    }

    public function test_empty_state_when_no_complaints(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // No reports at all → honest empty state, no map canvas.
        $this->assertStringContainsString('Peta Wilayah Aduan', $html);
        $this->assertStringContainsString('Belum ada data laporan untuk dipetakan.', $html);
        $this->assertStringNotContainsString('geographic-map-canvas', $html);
    }

    public function test_no_coordinate_state_keeps_administrative_stats(): void
    {
        // Complaints exist but NONE have coordinates → administrative stats stay.
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Belum ada titik lokasi laporan.', $html);
        $this->assertStringContainsString('Kelurahan Satu', $html);
        $this->assertStringNotContainsString('geographic-map-canvas', $html);
    }

    public function test_no_new_migration_was_added_for_geography(): void
    {
        // Prompt 5/5 reuses existing columns; no GIS/spatial migration exists.
        $migrations = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($path) => basename($path))
            ->implode("\n");

        $this->assertStringNotContainsString('geometry', strtolower($migrations));
        $this->assertStringNotContainsString('geograph', strtolower($migrations));
        $this->assertStringNotContainsString('choropleth', strtolower($migrations));
        $this->assertStringNotContainsString('spatial', strtolower($migrations));
    }

    // =========================================================================
    // §9 — PROMPT 6/5 — Geographic Analytics Layer 1
    //      Top Kecamatan · Top Kelurahan  (Backlog Wilayah · Wilayah × Status
    //      cards were removed in the dashboard streamlining pass)
    // =========================================================================

    public function test_admin_dashboard_renders_geographic_analytics_layer_one(): void
    {
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicAnalytics', fn ($a) => is_array($a)
            && array_key_exists('topKecamatan', $a)
            && array_key_exists('topKelurahan', $a)
            && array_key_exists('backlogByKelurahan', $a)
            && array_key_exists('statusByKelurahan', $a));

        $html = $response->getContent();
        foreach (['Top Kecamatan', 'Top Kelurahan'] as $title) {
            $this->assertStringContainsString($title, $html);
        }

        // The "Wilayah dengan Backlog Tertinggi" and "Wilayah × Status" cards
        // were removed in the dashboard streamlining pass.
        $this->assertStringNotContainsString('Wilayah dengan Backlog Tertinggi', $html);
        $this->assertStringNotContainsString('Wilayah × Status', $html);
    }

    public function test_non_admin_roles_do_not_receive_geographic_analytics(): void
    {
        // Admin-only. The payload must be absent for the other roles.
        $this->actingAs($this->operator)->get(route('operator.dashboard'))
            ->assertOk()->assertViewMissing('geographicAnalytics');

        $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))
            ->assertOk()->assertViewMissing('geographicAnalytics');

        $this->actingAs($this->citizen)->get(route('citizen.dashboard'))
            ->assertOk()->assertViewMissing('geographicAnalytics');
    }

    public function test_top_kecamatan_aggregation_and_ordering(): void
    {
        // Kecamatan Uji: 3, Kecamatan Lain: 1.
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanB->id, 'kelurahan_id' => $this->kelurahanB1->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $rows = $a['topKecamatan'];

                return array_column($rows, 'name') === ['Kecamatan Uji', 'Kecamatan Lain']
                    && array_column($rows, 'total') === [3, 1];
            });
    }

    public function test_top_kelurahan_aggregation_and_ordering(): void
    {
        // Kelurahan Satu: 3, Kelurahan Dua: 1.
        for ($i = 0; $i < 3; $i++) {
            $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        }
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanB->id, 'kelurahan_id' => $this->kelurahanB1->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $rows = $a['topKelurahan'];

                return array_column($rows, 'name') === ['Kelurahan Satu', 'Kelurahan Dua']
                    && array_column($rows, 'total') === [3, 1]
                    && $rows[0]['kecamatan'] === 'Kecamatan Uji'
                    && $rows[1]['kecamatan'] === 'Kecamatan Lain';
            });
    }

    public function test_backlog_counts_only_active_statuses(): void
    {
        // 2 active (submitted/in_progress) + legacy + final outcomes.
        foreach (['submitted', 'in_progress'] as $s) {
            $this->makeComplaint(['status' => $s, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        }
        foreach (['under_review', 'waiting_for_information', 'resolved', 'rejected', 'closed'] as $s) {
            $this->makeComplaint(['status' => $s, 'kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $row = collect($a['backlogByKelurahan'])->firstWhere('name', 'Kelurahan Satu');

                // Backlog = active only (2), NOT the 7 total (LC-2C: legacy and
                // final outcomes are never counted as active backlog).
                return $row !== null && $row['backlog'] === 2;
            });
    }

    public function test_resolved_is_excluded_from_backlog(): void
    {
        $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $row = collect($a['backlogByKelurahan'])->firstWhere('name', 'Kelurahan Satu');

                // The kelurahan is still ranked by TOTAL, but backlog is 0.
                return $row !== null && $row['backlog'] === 0;
            });
    }

    public function test_rejected_is_excluded_from_backlog(): void
    {
        $this->makeComplaint(['status' => 'rejected', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $row = collect($a['backlogByKelurahan'])->firstWhere('name', 'Kelurahan Satu');

                return $row !== null && $row['backlog'] === 0;
            });
    }

    public function test_closed_is_excluded_from_backlog(): void
    {
        $this->makeComplaint(['status' => 'closed', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $row = collect($a['backlogByKelurahan'])->firstWhere('name', 'Kelurahan Satu');

                return $row !== null && $row['backlog'] === 0;
            });
    }

    public function test_wilayah_by_status_matrix_is_correct(): void
    {
        $this->makeComplaint(['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->makeComplaint(['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->makeComplaint(['status' => 'closed', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $row = collect($a['statusByKelurahan'])->firstWhere('name', 'Kelurahan Satu');

                return $row !== null
                    && $row['total'] === 4
                    && $row['submitted'] === 2
                    && $row['resolved'] === 1
                    && $row['closed'] === 1
                    && $row['under_review'] === 0
                    && $row['in_progress'] === 0
                    && $row['waiting_for_information'] === 0
                    && $row['rejected'] === 0;
            });
    }

    public function test_status_columns_match_the_official_enum(): void
    {
        // Single source of truth: the matrix must expose exactly the seven
        // official status keys — never an ad-hoc status list.
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $expected = array_map(fn (\App\Enums\ComplaintStatus $s) => $s->value, \App\Enums\ComplaintStatus::cases());
        sort($expected);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) use ($expected) {
                $row = $a['statusByKelurahan'][0] ?? null;
                if ($row === null) {
                    return false;
                }

                // Row keys = name, kecamatan, total + the 7 official statuses.
                $actual = array_values(array_diff(array_keys($row), ['name', 'kecamatan', 'total']));
                sort($actual);

                return $actual === $expected;
            });
    }

    public function test_tie_breakers_are_deterministic(): void
    {
        // Equal totals across two kecamatan/kelurahan → name ASC decides.
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanB->id, 'kelurahan_id' => $this->kelurahanB1->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                // "Kecamatan Lain" < "Kecamatan Uji" alphabetically.
                $kec = array_column($a['topKecamatan'], 'name');
                // Kecamatan ASC first → Lain (Kec. Lain) before Uji.
                $kel = array_column($a['topKelurahan'], 'name');

                return $kec === ['Kecamatan Lain', 'Kecamatan Uji']
                    && $kel === ['Kelurahan Dua', 'Kelurahan Satu'];
            });
    }

    public function test_null_kecamatan_is_not_ranked_as_official_wilayah(): void
    {
        // A legacy complaint with NO kecamatan (and no kelurahan).
        $this->makeComplaint();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                $names = array_column($a['topKecamatan'], 'name');

                // Never an official Kecamatan; "Belum Ditentukan" must not appear
                // in the Layer-1 ranking (the map card owns that fallback).
                return $names === []
                    && ! in_array('Belum Ditentukan', $names, true);
            });
    }

    public function test_null_kelurahan_is_not_ranked_as_official_wilayah(): void
    {
        // Kecamatan set, but kelurahan NULL (legacy).
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                return $a['topKelurahan'] === []
                    && $a['backlogByKelurahan'] === []
                    && $a['statusByKelurahan'] === [];
            });
    }

    public function test_analytics_payload_carries_no_pii(): void
    {
        $this->makeComplaint([
            'kelurahan_id'   => $this->kelurahanA1->id,
            'kecamatan_id'   => $this->kecamatanA->id,
            'reporter_phone' => '081100003333',
            'reporter_email' => 'janganbocor6@example.test',
            'description'    => 'KALIMAT_SENSITIF_LAYER1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicAnalytics', function ($a) {
            $blob = json_encode($a);

            foreach (['081100003333', 'janganbocor6@example.test', 'KALIMAT_SENSITIF_LAYER1', 'reference_code', 'phone', 'email', 'description'] as $leak) {
                if (str_contains($blob, $leak)) {
                    return false;
                }
            }

            return true;
        });

        // And nothing leaks into the rendered page either.
        $html = $response->getContent();
        foreach (['081100003333', 'janganbocor6@example.test', 'KALIMAT_SENSITIF_LAYER1'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
    }

    public function test_each_ranking_is_capped_at_five(): void
    {
        // 7 kecamatan + 7 kelurahan, all with complaints.
        $kecs = [];
        for ($i = 0; $i < 7; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan Skala ' . $i, 'code' => '92' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan Skala ' . $i]);
            $kecs[] = [$kec, $kel];
            // Vary counts so ordering is unambiguous.
            for ($j = 0; $j <= $i; $j++) {
                $this->makeComplaint(['kecamatan_id' => $kec->id, 'kelurahan_id' => $kel->id]);
            }
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicAnalytics', function ($a) {
                return count($a['topKecamatan']) === 5
                    && count($a['topKelurahan']) === 5
                    && count($a['backlogByKelurahan']) === 5
                    && count($a['statusByKelurahan']) === 5;
            });
    }

    public function test_empty_analytics_renders_honest_empty_states(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Belum ada data Kecamatan.', $html);
        $this->assertStringContainsString('Belum ada data Kelurahan.', $html);
        // The "Wilayah × Status" card (and its empty state) was removed.
        $this->assertStringNotContainsString('Belum ada data status berdasarkan wilayah.', $html);
    }

    public function test_geographic_analytics_is_cached_and_invalidated(): void
    {
        Cache::forget('dashboard:admin');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(Cache::has('dashboard:admin'));

        $cached = Cache::get('dashboard:admin');
        $this->assertSame([], $cached['geographicAnalytics']['topKecamatan']);

        // A new complaint invalidates the shared dashboard cache.
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);
        $this->assertFalse(Cache::has('dashboard:admin'));
    }

    public function test_analytics_query_count_is_constant_as_wilayah_grow(): void
    {
        // Baseline: one complaint in one kelurahan.
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Add 10 more wilayah (each with a complaint + a distinct status).
        $statuses = ['submitted', 'under_review', 'in_progress', 'waiting_for_information', 'resolved'];
        for ($i = 0; $i < 10; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan Banyak ' . $i, 'code' => '93' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan Banyak ' . $i]);
            $this->makeComplaint([
                'kecamatan_id' => $kec->id,
                'kelurahan_id' => $kel->id,
                'status'       => $statuses[$i % count($statuses)],
            ]);
        }

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $grown = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $baseline,
            $grown,
            'Geographic Analytics Layer 1 must not issue per-wilayah or per-status queries (N+1).'
        );
    }

    public function test_geographic_analytics_components_have_no_database_queries(): void
    {
        foreach (['geographic-ranking', 'geographic-status-matrix'] as $component) {
            $blade = file_get_contents(resource_path("views/components/dashboard/{$component}.blade.php"));

            foreach (['DB::', '::query()', '->get()', '->count()', '->paginate(', 'Eloquent'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $blade, "{$component}.blade.php must not query the database ('{$forbidden}').");
            }
        }
    }

    public function test_other_role_dashboards_are_unchanged(): void
    {
        // Super Admin / Operator / Citizen dashboards must not gain the Layer-1
        // cards (scope is Admin-only; those views are untouched).
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id, 'kelurahan_id' => $this->kelurahanA1->id]);

        $superHtml = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Top Kecamatan', $superHtml);
        $this->assertStringNotContainsString('Wilayah × Status', $superHtml);

        $opHtml = $this->actingAs($this->operator)->get(route('operator.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Top Kecamatan', $opHtml);
        $this->assertStringNotContainsString('Wilayah × Status', $opHtml);

        $citizenHtml = $this->actingAs($this->citizen)->get(route('citizen.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Top Kecamatan', $citizenHtml);
        $this->assertStringNotContainsString('Wilayah × Status', $citizenHtml);
    }

    public function test_analytics_is_isolated_in_dedicated_components(): void
    {
        $dashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString('<x-dashboard.geographic-ranking', $dashboard);

        // The Wilayah × Status matrix card was removed from the dashboard.
        $this->assertStringNotContainsString('<x-dashboard.geographic-status-matrix', $dashboard);

        // No raw SQL / model access leaks into the dashboard template.
        $this->assertStringNotContainsString('DB::', $dashboard);
        $this->assertStringNotContainsString('::query()', $dashboard);
    }

    // =========================================================================
    // §10 — PROMPT 7/5 (Bagian 1/3) — Geographic Layer 2A: Wilayah × Kategori
    // =========================================================================

    public function test_layer2a_payload_is_present_on_admin_dashboard(): void
    {
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2A', fn ($a) => is_array($a)
            && array_key_exists('kelurahanCategory', $a)
            && is_array($a['kelurahanCategory']));
    }

    public function test_layer2a_aggregates_by_kelurahan_and_category(): void
    {
        // Kelurahan Satu × Infrastruktur Peta = 2
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        // Kelurahan Dua × Infrastruktur Peta = 1
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanB1->id, 'kecamatan_id' => $this->kecamatanB->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $rows = $a['kelurahanCategory'];

                $satu = collect($rows)->firstWhere('kelurahan', 'Kelurahan Satu');
                $dua  = collect($rows)->firstWhere('kelurahan', 'Kelurahan Dua');

                return $satu !== null && $satu['total'] === 2
                    && $dua !== null && $dua['total'] === 1
                    && $satu['category'] === 'Infrastruktur Peta';
            });
    }

    public function test_layer2a_carries_kelurahan_kecamatan_and_category_names(): void
    {
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $row = $a['kelurahanCategory'][0] ?? null;

                return $row !== null
                    && $row['kelurahan'] === 'Kelurahan Satu'
                    && $row['kecamatan'] === 'Kecamatan Uji'
                    && $row['category'] === 'Infrastruktur Peta'
                    && $row['total'] === 1;
            });
    }

    public function test_layer2a_is_capped_at_top_ten(): void
    {
        // 12 distinct (kelurahan, category) combinations.
        $categories = [];
        for ($i = 0; $i < 12; $i++) {
            $categories[] = ComplaintCategory::create([
                'name' => 'Kategori L2A ' . $i, 'slug' => 'kategori-l2a-' . $i,
                'dinas_name' => 'Dinas X', 'is_active' => true, 'sort_order' => $i,
            ]);
        }

        // Two kelurahan, each paired with all 12 categories → 24 combos.
        foreach ([$this->kelurahanA1, $this->kelurahanB1] as $kel) {
            foreach ($categories as $cat) {
                $this->makeComplaint(['kelurahan_id' => $kel->id, 'category_id' => $cat->id]);
            }
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => count($a['kelurahanCategory']) === 10);
    }

    public function test_layer2a_ordering_is_deterministic(): void
    {
        // Build a controlled dataset with a total tie to exercise the
        // kelurahan ASC then category ASC tie-breakers.
        $catA = ComplaintCategory::create(['name' => 'A Kategori', 'slug' => 'a-kategori', 'dinas_name' => 'D', 'is_active' => true, 'sort_order' => 1]);
        $catB = ComplaintCategory::create(['name' => 'B Kategori', 'slug' => 'b-kategori', 'dinas_name' => 'D', 'is_active' => true, 'sort_order' => 2]);

        // Kelurahan Satu: A=2, B=1  → total desc puts A first.
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'category_id' => $catA->id]);
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'category_id' => $catA->id]);
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'category_id' => $catB->id]);
        // Kelurahan Dua: A=2 → ties with Satu/A on total(2) but "Kelurahan Dua"
        // < "Kelurahan Satu" so Dua/A comes first.
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanB1->id, 'category_id' => $catA->id]);
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanB1->id, 'category_id' => $catA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $rows = $a['kelurahanCategory'];

                // total DESC (2,2,1) → then kelurahan ASC → Dua before Satu.
                return $rows[0]['kelurahan'] === 'Kelurahan Dua'
                    && $rows[0]['category'] === 'A Kategori'
                    && $rows[0]['total'] === 2
                    && $rows[1]['kelurahan'] === 'Kelurahan Satu'
                    && $rows[1]['category'] === 'A Kategori'
                    && $rows[1]['total'] === 2
                    && $rows[2]['kelurahan'] === 'Kelurahan Satu'
                    && $rows[2]['category'] === 'B Kategori'
                    && $rows[2]['total'] === 1;
            });
    }

    public function test_layer2a_excludes_null_kelurahan(): void
    {
        // A legacy complaint with no kelurahan (but a category) must not appear.
        $this->makeComplaint(['kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a['kelurahanCategory'] === []);
    }

    public function test_layer2a_excludes_null_category(): void
    {
        // A complaint with a kelurahan but no category must not appear.
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id, 'category_id' => null]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a['kelurahanCategory'] === []);
    }

    public function test_layer2a_drops_orphaned_master_rows(): void
    {
        // The DB FKs (kelurahan restrictOnDelete, category FK) normally prevent
        // orphaned references, but the service must still defend against them.
        // Insert orphans directly with FK checks temporarily disabled, then
        // restore the connection state.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->makeComplaint(['kelurahan_id' => 999999, 'kecamatan_id' => 999999, 'category_id' => 999999]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $blob = json_encode($a['kelurahanCategory']);

                return $a['kelurahanCategory'] === []
                    && ! str_contains($blob, 'Unknown')
                    && ! str_contains($blob, 'Tidak Diketahui');
            });
    }

    public function test_layer2a_payload_carries_no_pii(): void
    {
        $this->makeComplaint([
            'kelurahan_id'   => $this->kelurahanA1->id,
            'kecamatan_id'   => $this->kecamatanA->id,
            'reporter_phone' => '081100005555',
            'reporter_email' => 'janganbocor7@example.test',
            'description'    => 'KALIMAT_SENSITIF_LAYER2A',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2A', function ($a) {
            $blob = json_encode($a);

            foreach (['081100005555', 'janganbocor7@example.test', 'KALIMAT_SENSITIF_LAYER2A', 'reference_code', 'phone', 'email', 'description'] as $leak) {
                if (str_contains($blob, $leak)) {
                    return false;
                }
            }

            return true;
        });

        $html = $response->getContent();
        foreach (['081100005555', 'janganbocor7@example.test', 'KALIMAT_SENSITIF_LAYER2A'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
    }

    public function test_layer2a_query_count_is_constant_as_wilayah_category_grow(): void
    {
        // Baseline: one combination.
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Many more (kelurahan, category) combinations.
        for ($i = 0; $i < 8; $i++) {
            $cat = ComplaintCategory::create(['name' => 'Kategori N1 ' . $i, 'slug' => 'kategori-n1-' . $i, 'dinas_name' => 'D', 'is_active' => true, 'sort_order' => $i]);
            $kec = Kecamatan::create(['name' => 'Kecamatan N1 ' . $i, 'code' => '94' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan N1 ' . $i]);
            $this->makeComplaint(['kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id, 'category_id' => $cat->id]);
        }

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $grown = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $baseline,
            $grown,
            'Layer 2A must not issue per-kelurahan or per-category queries (N+1).'
        );
    }

    public function test_layer2a_is_cached_and_served_from_cache(): void
    {
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');

        // First request populates the cache.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(Cache::has('dashboard:admin'));
        $cached = Cache::get('dashboard:admin');
        $this->assertSame(1, $cached['geographicLayer2A']['kelurahanCategory'][0]['total']);

        // Second request must reuse the cached payload (no re-query). Assert the
        // served view data equals the cached payload.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a === $cached['geographicLayer2A']);
    }

    public function test_layer2a_is_not_available_to_other_roles(): void
    {
        $this->actingAs($this->operator)->get(route('operator.dashboard'))
            ->assertOk()->assertViewMissing('geographicLayer2A');

        $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))
            ->assertOk()->assertViewMissing('geographicLayer2A');

        $this->actingAs($this->citizen)->get(route('citizen.dashboard'))
            ->assertOk()->assertViewMissing('geographicLayer2A');
    }

    // =========================================================================
    // §11 — PROMPT 7/5 (Bagian 2/3) — Layer 2A: Tren Kasus per Wilayah
    // =========================================================================

    public function test_regional_trend_is_present_in_admin_payload(): void
    {
        $this->complaintInMonth(0, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2A', function ($a) {
            // Bagian 1 key must remain intact.
            return array_key_exists('kelurahanCategory', $a)
                && array_key_exists('regionalTrend', $a)
                && array_key_exists('labels', $a['regionalTrend'])
                && array_key_exists('series', $a['regionalTrend']);
        });
    }

    public function test_regional_trend_labels_use_the_shared_dashboard_period(): void
    {
        $this->complaintInMonth(0, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                // Same 6-month window/labels as the main dashboard trend.
                return $a['regionalTrend']['labels'] === $this->expectedTrendLabels();
            });
    }

    public function test_regional_trend_uses_submitted_at_for_month_bucketing(): void
    {
        // submitted_at lands 2 months ago, but created_at is now(). The point must
        // fall in the month of submitted_at (index 3 of a 6-month window), NOT
        // the current month.
        $c = $this->makeComplaint([
            'kelurahan_id' => $this->kelurahanA1->id,
            'kecamatan_id' => $this->kecamatanA->id,
            'submitted_at' => now()->startOfMonth()->subMonths(2)->addDays(5),
        ]);
        // Force a divergent created_at to prove submitted_at is the source.
        DB::table('complaints')->where('id', $c->id)->update(['created_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $series = $a['regionalTrend']['series'];
                $row    = collect($series)->firstWhere('name', 'Kelurahan Satu');
                if ($row === null) {
                    return false;
                }

                $labels = $a['regionalTrend']['labels'];
                $index  = 3; // 6-month window: index 3 = 2 months ago

                // Only the submitted_at month is non-zero; the current month is 0.
                return $row['data'][$index] === 1
                    && $row['data'][5] === 0
                    && count($row['data']) === count($labels);
            });
    }

    public function test_regional_trend_top_five_is_by_window_total_and_zero_fills(): void
    {
        // Build 6 kelurahan with distinct window totals. Only Top 5 survive.
        // Kelurahan Satu (A): Jan(-2)=5, Feb(-1)=0, Mar(0)=3  → total 8
        // Kelurahan Dua  (B): Jan(-2)=2, Feb(-1)=4, Mar(0)=1  → total 7
        $a = $this->kelurahanA1; // Kelurahan Satu
        $b = $this->kelurahanB1; // Kelurahan Dua

        foreach ([5, 0, 3] as $i => $n) {
            for ($k = 0; $k < $n; $k++) {
                $this->complaintInMonth(2 - $i, ['kelurahan_id' => $a->id, 'kecamatan_id' => $this->kecamatanA->id]);
            }
        }
        foreach ([2, 4, 1] as $i => $n) {
            for ($k = 0; $k < $n; $k++) {
                $this->complaintInMonth(2 - $i, ['kelurahan_id' => $b->id, 'kecamatan_id' => $this->kecamatanB->id]);
            }
        }

        // Four more kelurahan with smaller totals so Top 5 truncation is real.
        for ($i = 0; $i < 4; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan Trend ' . $i, 'code' => '95' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan Trend ' . $i]);
            for ($k = 0; $k < ($i + 1); $k++) {
                $this->complaintInMonth(0, ['kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
            }
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $trend = $a['regionalTrend'];
                $labels = $trend['labels'];
                $series = $trend['series'];

                // Exactly Top 5.
                if (count($series) !== 5) {
                    return false;
                }

                $satu = collect($series)->firstWhere('name', 'Kelurahan Satu');
                $dua  = collect($series)->firstWhere('name', 'Kelurahan Dua');
                if ($satu === null || $dua === null) {
                    return false;
                }

                // Top by window total: Satu (8) first, then Dua (7).
                if ($series[0]['name'] !== 'Kelurahan Satu' || $series[0]['total'] !== 8) {
                    return false;
                }
                if ($series[1]['name'] !== 'Kelurahan Dua' || $series[1]['total'] !== 7) {
                    return false;
                }

                // Zero-fill: Satu has Feb = 0 but the point is PRESENT, and every
                // series has exactly labels-count points.
                $satuData = $satu['data'];
                $duaData  = $dua['data'];

                $ok = count($satuData) === count($labels) && count($duaData) === count($labels);

                // Last 3 months are indices 3,4,5 → Jan(-2)=5, Feb(-1)=0, Mar(0)=3.
                $ok = $ok
                    && $satuData[3] === 5
                    && $satuData[4] === 0   // zero-filled, NOT null and NOT omitted
                    && $satuData[5] === 3
                    && $duaData[3] === 2
                    && $duaData[4] === 4
                    && $duaData[5] === 1;

                // No nulls anywhere in the data arrays.
                foreach ($series as $s) {
                    foreach ($s['data'] as $v) {
                        if (! is_int($v)) {
                            return false;
                        }
                    }
                }

                return $ok;
            });
    }

    public function test_regional_trend_series_names_and_kecamatan_are_present(): void
    {
        $this->complaintInMonth(0, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $row = $a['regionalTrend']['series'][0] ?? null;

                return $row !== null
                    && $row['name'] === 'Kelurahan Satu'
                    && $row['kecamatan'] === 'Kecamatan Uji'
                    && $row['total'] === 1
                    && is_array($row['data']);
            });
    }

    public function test_regional_trend_is_deterministic_and_stable_across_months(): void
    {
        // Two kelurahan with an identical window total → name ASC decides, and
        // the SAME five series appear for every month (never a per-month Top 5).
        $this->complaintInMonth(0, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintInMonth(0, ['kelurahan_id' => $this->kelurahanB1->id, 'kecamatan_id' => $this->kecamatanB->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $names = array_column($a['regionalTrend']['series'], 'name');

                // "Kelurahan Dua" < "Kelurahan Satu" → deterministic tie-break.
                return $names === ['Kelurahan Dua', 'Kelurahan Satu'];
            });
    }

    public function test_regional_trend_excludes_null_kelurahan(): void
    {
        // A complaint with no kelurahan (submitted this month) must not appear.
        $this->complaintInMonth(0, ['kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                return $a['regionalTrend']['labels'] === []
                    && $a['regionalTrend']['series'] === [];
            });
    }

    public function test_regional_trend_drops_orphaned_kelurahan(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->complaintInMonth(0, ['kelurahan_id' => 999998, 'kecamatan_id' => 999998]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $blob = json_encode($a['regionalTrend']);

                return $a['regionalTrend']['labels'] === []
                    && $a['regionalTrend']['series'] === []
                    && ! str_contains($blob, 'Unknown')
                    && ! str_contains($blob, 'Tidak Diketahui');
            });
    }

    public function test_regional_trend_empty_when_no_data(): void
    {
        // No complaints at all → honest empty structure.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a['regionalTrend']['labels'] === []
                && $a['regionalTrend']['series'] === []);
    }

    public function test_regional_trend_excludes_complaints_before_the_window(): void
    {
        // A complaint 8 months ago is OUTSIDE the 6-month window → not counted.
        $this->complaintInMonth(8, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a['regionalTrend']['series'] === []);
    }

    public function test_regional_trend_payload_carries_no_pii(): void
    {
        $this->complaintInMonth(0, [
            'kelurahan_id'   => $this->kelurahanA1->id,
            'kecamatan_id'   => $this->kecamatanA->id,
            'reporter_phone' => '081100009999',
            'reporter_email' => 'janganbocor7b@example.test',
            'description'    => 'KALIMAT_SENSITIF_TREN',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2A', function ($a) {
            $blob = json_encode($a['regionalTrend']);

            foreach (['081100009999', 'janganbocor7b@example.test', 'KALIMAT_SENSITIF_TREN', 'reference_code', 'phone', 'email', 'description', 'title'] as $leak) {
                if (str_contains($blob, $leak)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_regional_trend_query_count_is_constant_as_kelurahan_grow(): void
    {
        // Baseline: one kelurahan, with a long-aged active complaint so that the
        // pre-existing `dinasLongAging` category eager-load (data-dependent) is
        // active in BOTH runs — the only variable left is the wilayah/month count.
        $this->complaintInMonth(1, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Many more kelurahan, each with complaints across several months.
        for ($i = 0; $i < 10; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan RT ' . $i, 'code' => '96' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan RT ' . $i]);
            foreach ([1, 2] as $m) {
                $this->complaintInMonth($m, ['kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
            }
        }

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $grown = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $baseline,
            $grown,
            'Regional trend must not issue per-kelurahan or per-month queries (N+1).'
        );
    }

    public function test_regional_trend_is_served_from_cache(): void
    {
        $this->complaintInMonth(0, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $cached = Cache::get('dashboard:admin');
        $this->assertArrayHasKey('regionalTrend', $cached['geographicLayer2A']);

        // Second request reuses the cached payload unchanged.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a['regionalTrend'] === $cached['geographicLayer2A']['regionalTrend']);
    }

    // =========================================================================
    // §12 — PROMPT 7/5 (Bagian 3/3) — Layer 2A: Completion Rate per Wilayah
    // =========================================================================

    public function test_completion_payload_is_present_on_admin_dashboard(): void
    {
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2A', function ($a) {
            // Bagian 1 + 2 keys must remain intact.
            return array_key_exists('kelurahanCategory', $a)
                && array_key_exists('regionalTrend', $a)
                && array_key_exists('completionByKelurahan', $a)
                && is_array($a['completionByKelurahan']);
        });
    }

    public function test_completion_counts_resolved_as_completed(): void
    {
        $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->makeComplaint(['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $row = collect($a['completionByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null && $row['total'] === 2 && $row['completed'] === 1 && $row['completionRate'] === 50.0;
            });
    }

    public function test_completion_counts_rejected_as_completed(): void
    {
        $this->makeComplaint(['status' => 'rejected', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->makeComplaint(['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $row = collect($a['completionByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null && $row['total'] === 2 && $row['completed'] === 1;
            });
    }

    public function test_completion_does_not_count_closed_as_completed(): void
    {
        // closed is a SEPARATE terminal status — NOT completed.
        $this->makeComplaint(['status' => 'closed', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->makeComplaint(['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $row = collect($a['completionByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                // total 2, completed 0 (closed excluded), rate 0%.
                return $row !== null && $row['total'] === 2 && $row['completed'] === 0 && $row['completionRate'] === 0.0;
            });
    }

    public function test_completion_formula_and_denominator_are_correct(): void
    {
        // 14 completed (10 resolved + 4 rejected) of 20 total → 70%.
        for ($i = 0; $i < 10; $i++) {
            $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }
        for ($i = 0; $i < 4; $i++) {
            $this->makeComplaint(['status' => 'rejected', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }
        for ($i = 0; $i < 6; $i++) {
            $this->makeComplaint(['status' => 'in_progress', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $row = collect($a['completionByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null
                    && $row['total'] === 20
                    && $row['completed'] === 14
                    && $row['completionRate'] === 70.0;
            });
    }

    public function test_completion_handles_total_zero_safely(): void
    {
        // A kelurahan with complaints would have total > 0; total = 0 can only
        // happen for a wilayah with no complaints → it must NOT be ranked at all
        // (no division, no NaN/Infinity). Only Kelurahan Satu (with data) appears.
        $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        // Kelurahan Dua exists as master data but has zero complaints.

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $names = array_column($a['completionByKelurahan'], 'kelurahan');
                $blob  = json_encode($a['completionByKelurahan']);

                return $names === ['Kelurahan Satu']
                    && ! str_contains($blob, 'NaN')
                    && ! str_contains($blob, 'Infinity')
                    && ! str_contains($blob, 'undefined');
            });
    }

    public function test_completion_is_capped_at_top_five_and_deterministic(): void
    {
        // 7 kelurahan with descending completion rates so Top 5 truncation + the
        // rate DESC ordering are both exercised. Kelurahan i gets (7-i) resolved
        // and 1 non-completed → rates 7/8, 6/7, ... descending.
        for ($i = 0; $i < 7; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan CR ' . $i, 'code' => '97' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan CR ' . $i]);
            for ($r = 0; $r < (7 - $i); $r++) {
                $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
            }
            $this->makeComplaint(['status' => 'submitted', 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $rows = $a['completionByKelurahan'];

                if (count($rows) !== 5) {
                    return false;
                }

                // Rate DESC → Kelurahan CR 0 (7/8=87.5) first, CR 1 (6/7=85.7) next.
                if ($rows[0]['kelurahan'] !== 'Kelurahan CR 0' || $rows[0]['completionRate'] !== 87.5) {
                    return false;
                }

                // Monotonic non-increasing rate.
                for ($i = 1; $i < count($rows); $i++) {
                    if ($rows[$i]['completionRate'] > $rows[$i - 1]['completionRate']) {
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_completion_ordering_tie_break_is_deterministic(): void
    {
        // Same rate (100%) for two wilayah → name ASC then total DESC decides.
        // Kelurahan Dua: 1/1 = 100%; Kelurahan Satu: 3/3 = 100%.
        // Tie-break: total DESC → Satu (3) before Dua (1).
        for ($i = 0; $i < 3; $i++) {
            $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }
        $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanB1->id, 'kecamatan_id' => $this->kecamatanB->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $names = array_column($a['completionByKelurahan'], 'kelurahan');

                // Both 100%; total DESC → Kelurahan Satu (3) first.
                return $names === ['Kelurahan Satu', 'Kelurahan Dua'];
            });
    }

    public function test_completion_excludes_null_kelurahan(): void
    {
        $this->makeComplaint(['status' => 'resolved', 'kecamatan_id' => $this->kecamatanA->id]); // no kelurahan

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a['completionByKelurahan'] === []);
    }

    public function test_completion_drops_orphaned_kelurahan(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => 999997, 'kecamatan_id' => 999997]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', function ($a) {
                $blob = json_encode($a['completionByKelurahan']);

                return $a['completionByKelurahan'] === []
                    && ! str_contains($blob, 'Unknown')
                    && ! str_contains($blob, 'Tidak Diketahui');
            });
    }

    public function test_completion_payload_carries_no_pii(): void
    {
        $this->makeComplaint([
            'status'         => 'resolved',
            'kelurahan_id'   => $this->kelurahanA1->id,
            'kecamatan_id'   => $this->kecamatanA->id,
            'reporter_phone' => '081100007070',
            'reporter_email' => 'janganbocor7c@example.test',
            'description'    => 'KALIMAT_SENSITIF_COMPLETION',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2A', function ($a) {
            $blob = json_encode($a['completionByKelurahan']);

            foreach (['081100007070', 'janganbocor7c@example.test', 'KALIMAT_SENSITIF_COMPLETION', 'reference_code', 'phone', 'email', 'description', 'title'] as $leak) {
                if (str_contains($blob, $leak)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_completion_query_count_is_constant_as_kelurahan_grow(): void
    {
        // Baseline with a long-aged active complaint so the data-dependent
        // `dinasLongAging` eager-load is active in both runs (isolates the var).
        $this->complaintInMonth(1, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($i = 0; $i < 10; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan CQ ' . $i, 'code' => '98' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan CQ ' . $i]);
            foreach (['resolved', 'rejected', 'submitted'] as $st) {
                $this->complaintInMonth(1, ['status' => $st, 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
            }
        }

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $grown = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $baseline,
            $grown,
            'Completion rate must not issue per-kelurahan queries (N+1).'
        );
    }

    public function test_completion_is_served_from_cache(): void
    {
        $this->makeComplaint(['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $cached = Cache::get('dashboard:admin');
        $this->assertArrayHasKey('completionByKelurahan', $cached['geographicLayer2A']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2A', fn ($a) => $a['completionByKelurahan'] === $cached['geographicLayer2A']['completionByKelurahan']);
    }

    // =========================================================================
    // §13 — Layer 2A UI: cards render on the Admin dashboard, never elsewhere
    // =========================================================================

    public function test_layer2a_ui_cards_render_on_admin_dashboard(): void
    {
        // Seed data that produces all three Layer 2A datasets.
        $this->complaintInMonth(0, ['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintInMonth(1, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Retained Layer 2A component titles.
        $this->assertStringContainsString('Tren Kasus per Wilayah', $html);
        $this->assertStringContainsString('Tingkat Penyelesaian per Wilayah', $html);

        // The "Wilayah × Kategori" card was removed in the streamlining pass.
        $this->assertStringNotContainsString('Wilayah × Kategori', $html);

        // Trend chart is a real accessible SVG (role=img + aria-label).
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('Tren jumlah laporan per bulan', $html);
    }

    public function test_layer2a_ui_empty_states_render_without_error(): void
    {
        // No complaints at all → every retained Layer 2A card shows an honest
        // empty state. The removed "Wilayah × Kategori" card's empty state must
        // no longer appear.
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Belum ada data tren wilayah.', $html);
        $this->assertStringContainsString('Belum ada data penyelesaian per wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data kategori per wilayah.', $html);
    }

    public function test_layer2a_ui_is_not_rendered_for_other_roles(): void
    {
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $superHtml = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Wilayah × Kategori', $superHtml);
        $this->assertStringNotContainsString('Tingkat Penyelesaian per Wilayah', $superHtml);

        $opHtml = $this->actingAs($this->operator)->get(route('operator.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Wilayah × Kategori', $opHtml);

        $citizenHtml = $this->actingAs($this->citizen)->get(route('citizen.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Wilayah × Kategori', $citizenHtml);
    }

    public function test_layer2a_regression_layer1_and_map_still_render(): void
    {
        $this->makeComplaint(['latitude' => -4.01, 'longitude' => 119.62, 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Layer 1 + Geographic Map remain present alongside Layer 2A.
        $this->assertStringContainsString('Peta Wilayah Aduan', $html);
        $this->assertStringContainsString('geographic-map-canvas', $html);
        $this->assertStringContainsString('Top Kecamatan', $html);
        $this->assertStringContainsString('Tingkat Penyelesaian per Wilayah', $html);
        $this->assertStringContainsString('Tren Kasus per Wilayah', $html);

        // The "Wilayah × Status" and "Wilayah × Kategori" cards were removed.
        $this->assertStringNotContainsString('Wilayah × Status', $html);
        $this->assertStringNotContainsString('Wilayah × Kategori', $html);
    }

    public function test_layer2a_components_have_no_database_queries(): void
    {
        foreach (['geographic-category-ranking', 'geographic-trend', 'geographic-completion'] as $component) {
            $blade = file_get_contents(resource_path("views/components/dashboard/{$component}.blade.php"));

            foreach (['DB::', '::query()', '->get()', '->count()', '->paginate(', 'Eloquent'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $blade, "{$component}.blade.php must not query the database ('{$forbidden}').");
            }
        }
    }

    public function test_layer2a_is_isolated_in_dedicated_components(): void
    {
        $dashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString('<x-dashboard.geographic-trend', $dashboard);
        $this->assertStringContainsString('<x-dashboard.geographic-completion', $dashboard);

        // The Wilayah × Kategori card was removed from the dashboard.
        $this->assertStringNotContainsString('<x-dashboard.geographic-category-ranking', $dashboard);

        // No raw SQL / model access leaks into the dashboard template.
        $this->assertStringNotContainsString('DB::', $dashboard);
        $this->assertStringNotContainsString('::query()', $dashboard);
    }

    // =========================================================================
    // §14 — PROMPT 8/5 (Bagian 1/3) — Layer 2B: operational wilayah analytics
    //      (oldest case · average active age · active proportion · active load)
    //
    // Time is frozen with travelTo() so the day-based age metrics are exact and
    // never flake across a midnight boundary. Age is ANALYTICAL AGE from
    // submitted_at — NOT an SLA.
    // =========================================================================

    private function freezeLayer2BTime(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-06-15 12:00:00'));
    }

    public function test_layer2b_payload_is_present_on_admin_dashboard(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(3, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2B', function ($b) {
            foreach (['oldestCasesByKelurahan', 'averageAgeByKelurahan', 'activeRateByKelurahan', 'activeLoadByKelurahan'] as $key) {
                if (! array_key_exists($key, $b) || ! is_array($b[$key])) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_layer2b_oldest_cases_counts_the_oldest_active_complaint(): void
    {
        $this->freezeLayer2BTime();
        // Kelurahan Satu: active at 5 days and 18 days → oldest 18, active 2.
        $this->complaintDaysAgo(5, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(18, ['status' => 'in_progress', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $row = collect($b['oldestCasesByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null
                    && $row['oldestAgeDays'] === 18
                    && $row['activeCount'] === 2
                    && $row['kecamatan'] === 'Kecamatan Uji';
            });
    }

    public function test_layer2b_terminal_statuses_are_not_counted_as_active(): void
    {
        $this->freezeLayer2BTime();
        // The OLDEST complaint is resolved (terminal) → must be ignored.
        $this->complaintDaysAgo(100, ['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(5, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $oldest = collect($b['oldestCasesByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');
                $load   = collect($b['activeLoadByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                // Only the active (5-day) case counts.
                return $oldest !== null && $oldest['oldestAgeDays'] === 5 && $oldest['activeCount'] === 1
                    && $load !== null && $load['activeCount'] === 1 && $load['totalCount'] === 2;
            });
    }

    public function test_layer2b_oldest_ranking_is_deterministic(): void
    {
        $this->freezeLayer2BTime();
        // Same oldest age (10 days) → activeCount DESC decides.
        $this->complaintDaysAgo(10, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(10, ['kelurahan_id' => $this->kelurahanB1->id, 'kecamatan_id' => $this->kecamatanB->id]);
        $this->complaintDaysAgo(10, ['kelurahan_id' => $this->kelurahanB1->id, 'kecamatan_id' => $this->kecamatanB->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $names = array_column($b['oldestCasesByKelurahan'], 'kelurahan');

                // Dua has 2 active at 10 days → ranks above Satu (1 at 10 days).
                return $names === ['Kelurahan Dua', 'Kelurahan Satu'];
            });
    }

    public function test_layer2b_average_age_uses_submitted_at(): void
    {
        $this->freezeLayer2BTime();
        // Two active cases at 10 and 20 days → average 15.0. A divergent
        // created_at must NOT change the result (submitted_at is the source).
        $a = $this->complaintDaysAgo(10, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(20, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        DB::table('complaints')->where('id', $a->id)->update(['created_at' => now()->subDays(500)]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $row = collect($b['averageAgeByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null && $row['averageAgeDays'] === 15.0 && $row['activeCount'] === 2;
            });
    }

    public function test_layer2b_average_age_rounds_to_one_decimal(): void
    {
        $this->freezeLayer2BTime();
        // Ages 10, 10, 11 → 31 / 3 = 10.333… → 10.3.
        $this->complaintDaysAgo(10, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(10, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(11, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $row = collect($b['averageAgeByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null && $row['averageAgeDays'] === 10.3;
            });
    }

    public function test_layer2b_average_age_denominator_counts_only_active(): void
    {
        $this->freezeLayer2BTime();
        // One active at 10 days + one TERMINAL at 100 days. The terminal case
        // must NOT dilute the mean → 10 / 1 = 10.0 (not 110/2 = 55.0).
        $this->complaintDaysAgo(10, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(100, ['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $row = collect($b['averageAgeByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null && $row['averageAgeDays'] === 10.0 && $row['activeCount'] === 1;
            });
    }

    public function test_layer2b_active_rate_uses_total_as_denominator(): void
    {
        $this->freezeLayer2BTime();
        // 14 active + 6 terminal = 20 total → 70.0%.
        for ($i = 0; $i < 14; $i++) {
            $this->complaintDaysAgo(3, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }
        for ($i = 0; $i < 6; $i++) {
            $this->complaintDaysAgo(3, ['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $row = collect($b['activeRateByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null && $row['total'] === 20 && $row['active'] === 14 && $row['activeRate'] === 70.0;
            });
    }

    public function test_layer2b_active_rate_excludes_terminal_from_active(): void
    {
        $this->freezeLayer2BTime();
        // 1 active + 3 terminal (rejected/closed/resolved) → 1/4 = 25.0%.
        $this->complaintDaysAgo(3, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        foreach (['resolved', 'rejected', 'closed'] as $st) {
            $this->complaintDaysAgo(3, ['status' => $st, 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $row = collect($b['activeRateByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                return $row !== null && $row['total'] === 4 && $row['active'] === 1 && $row['activeRate'] === 25.0;
            });
    }

    public function test_layer2b_active_rate_has_no_divide_by_zero_or_nonfinite(): void
    {
        $this->freezeLayer2BTime();
        // Kelurahan Dua exists as master data but has ZERO complaints → must not
        // appear (no denominator, no fabricated 0%).
        $this->complaintDaysAgo(3, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $names = array_column($b['activeRateByKelurahan'], 'kelurahan');
                $blob  = json_encode($b);

                return $names === ['Kelurahan Satu']
                    && ! str_contains($blob, 'NaN')
                    && ! str_contains($blob, 'Infinity')
                    && ! str_contains($blob, 'undefined');
            });
    }

    public function test_layer2b_active_load_counts_only_the_two_active_statuses(): void
    {
        $this->freezeLayer2BTime();
        // All 7 statuses once → active 2 (submitted, in_progress), total 7.
        foreach (['submitted', 'under_review', 'in_progress', 'waiting_for_information', 'resolved', 'rejected', 'closed'] as $st) {
            $this->complaintDaysAgo(3, ['status' => $st, 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $row = collect($b['activeLoadByKelurahan'])->firstWhere('kelurahan', 'Kelurahan Satu');

                // LC-2C: active = the 2 active lifecycle statuses only.
                return $row !== null && $row['activeCount'] === 2 && $row['totalCount'] === 7;
            });
    }

    public function test_layer2b_excludes_null_kelurahan(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(5, ['kecamatan_id' => $this->kecamatanA->id]); // no kelurahan

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', fn ($b) => $b['oldestCasesByKelurahan'] === []
                && $b['averageAgeByKelurahan'] === []
                && $b['activeRateByKelurahan'] === []
                && $b['activeLoadByKelurahan'] === []);
    }

    public function test_layer2b_excludes_orphaned_kelurahan(): void
    {
        $this->freezeLayer2BTime();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->complaintDaysAgo(5, ['kelurahan_id' => 999995, 'kecamatan_id' => 999995]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', fn ($b) => $b['oldestCasesByKelurahan'] === []
                && $b['activeLoadByKelurahan'] === []);
    }

    public function test_layer2b_excludes_kelurahan_with_orphaned_kecamatan(): void
    {
        $this->freezeLayer2BTime();
        // A Kelurahan whose parent Kecamatan row is missing (orphan) → excluded.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $kel = Kelurahan::create(['kecamatan_id' => 999994, 'name' => 'Kelurahan Yatim']);
            $this->complaintDaysAgo(5, ['kelurahan_id' => $kel->id, 'kecamatan_id' => 999994]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                $blob = json_encode($b);

                return $b['oldestCasesByKelurahan'] === []
                    && $b['activeLoadByKelurahan'] === []
                    && ! str_contains($blob, 'Kelurahan Yatim')
                    && ! str_contains($blob, 'Belum Ditentukan');
            });
    }

    public function test_layer2b_each_metric_is_capped_at_top_five(): void
    {
        $this->freezeLayer2BTime();
        // 7 kelurahan, active counts 7..1 → every metric truncates to 5.
        for ($i = 0; $i < 7; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan L2B ' . $i, 'code' => '99' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan L2B ' . $i]);
            for ($j = 0; $j <= $i; $j++) {
                $this->complaintDaysAgo(3 + $j, ['status' => 'submitted', 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
            }
        }

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', fn ($b) => count($b['oldestCasesByKelurahan']) === 5
                && count($b['averageAgeByKelurahan']) === 5
                && count($b['activeRateByKelurahan']) === 5
                && count($b['activeLoadByKelurahan']) === 5);
    }

    public function test_layer2b_empty_dataset_returns_empty_arrays(): void
    {
        $this->freezeLayer2BTime();
        // No complaints at all → honest empty structures (never dummy data).
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', fn ($b) => $b === [
                'oldestCasesByKelurahan' => [],
                'averageAgeByKelurahan'  => [],
                'activeRateByKelurahan'  => [],
                'activeLoadByKelurahan'  => [],
            ]);
    }

    public function test_layer2b_payload_carries_no_pii(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(5, [
            'status'         => 'submitted',
            'kelurahan_id'   => $this->kelurahanA1->id,
            'kecamatan_id'   => $this->kecamatanA->id,
            'reporter_phone' => '081100008080',
            'reporter_email' => 'janganbocor8@example.test',
            'description'    => 'KALIMAT_SENSITIF_LAYER2B',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2B', function ($b) {
            $blob = json_encode($b);

            foreach (['081100008080', 'janganbocor8@example.test', 'KALIMAT_SENSITIF_LAYER2B', 'reference_code', 'phone', 'email', 'description', 'title', 'reporter_id', 'assigned_to'] as $leak) {
                if (str_contains($blob, $leak)) {
                    return false;
                }
            }

            return true;
        });

        $html = $response->getContent();
        foreach (['081100008080', 'janganbocor8@example.test', 'KALIMAT_SENSITIF_LAYER2B'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
    }

    public function test_layer2b_query_count_is_constant_as_kelurahan_grow(): void
    {
        $this->freezeLayer2BTime();

        // Baseline with a >7-day active complaint so the data-dependent
        // `dinasLongAging` eager-load is active in BOTH runs (comparable shape).
        $this->complaintDaysAgo(9, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();

        // +10 kelurahan, each with active (incl. >7-day) + terminal complaints.
        for ($i = 0; $i < 10; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan Q ' . $i, 'code' => '88' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan Q ' . $i]);
            $this->complaintDaysAgo(9, ['status' => 'submitted', 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
            $this->complaintDaysAgo(2, ['status' => 'in_progress', 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
            $this->complaintDaysAgo(2, ['status' => 'resolved', 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
        }

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $grown = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $baseline,
            $grown,
            'Layer 2B must not issue per-kelurahan queries (N+1).'
        );
    }

    public function test_layer2b_is_cached_in_the_dashboard_admin_key_with_60s_ttl(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(4, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        // Same shared Admin cache key, unchanged TTL.
        $this->assertTrue(Cache::has('dashboard:admin'));
        $this->assertSame(60, \App\Services\DashboardCacheService::CACHE_TTL_SECONDS);

        $cached = Cache::get('dashboard:admin');
        $this->assertArrayHasKey('geographicLayer2B', $cached);

        // Second request reuses the cached payload unchanged.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', fn ($b) => $b === $cached['geographicLayer2B']);
    }

    public function test_layer2b_regression_layer1_layer2a_and_map_still_present(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(5, [
            'status'        => 'submitted',
            'latitude'      => -4.01,
            'longitude'     => 119.62,
            'kelurahan_id'  => $this->kelurahanA1->id,
            'kecamatan_id'  => $this->kecamatanA->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        // Layer 1 + Layer 2A + Map payloads remain intact alongside Layer 2B.
        $response->assertViewHas('geographicMap', fn ($m) => array_key_exists('points', $m));
        $response->assertViewHas('geographicAnalytics', fn ($a) => array_key_exists('topKecamatan', $a)
            && array_key_exists('backlogByKelurahan', $a));
        $response->assertViewHas('geographicLayer2A', fn ($a) => array_key_exists('kelurahanCategory', $a)
            && array_key_exists('regionalTrend', $a)
            && array_key_exists('completionByKelurahan', $a));
        $response->assertViewHas('geographicLayer2B');

        $html = $response->getContent();
        $this->assertStringContainsString('Peta Wilayah Aduan', $html);
        $this->assertStringContainsString('Top Kecamatan', $html);
        $this->assertStringContainsString('Tingkat Penyelesaian per Wilayah', $html);
        $this->assertStringContainsString('Tren Kasus per Wilayah', $html);
        // The Wilayah × Status / Wilayah × Kategori cards were removed.
        $this->assertStringNotContainsString('Wilayah × Status', $html);
        $this->assertStringNotContainsString('Wilayah × Kategori', $html);
    }

    public function test_layer2b_active_statuses_come_from_the_enum_not_a_second_list(): void
    {
        // The ACTIVE statuses are exactly the enum's activeCases() — no second
        // hard-coded list can drift from ComplaintStatus.
        $expected = array_map(
            fn (\App\Enums\ComplaintStatus $s) => $s->value,
            \App\Enums\ComplaintStatus::activeCases(),
        );

        $ref = new \ReflectionClass(\App\Services\DashboardCacheService::class);
        $active = $ref->getConstant('ACTIVE_STATUS_VALUES');

        $this->assertSame($expected, array_values($active));

        // The remainder are the final outcomes + the legacy history statuses.
        $all = array_map(fn ($c) => $c->value, \App\Enums\ComplaintStatus::cases());
        $this->assertSame(
            ['under_review', 'waiting_for_information', 'resolved', 'rejected', 'closed'],
            array_values(array_diff($all, $active)),
        );
    }

    public function test_layer2b_is_not_available_to_other_roles(): void
    {
        $this->actingAs($this->operator)->get(route('operator.dashboard'))
            ->assertOk()->assertViewMissing('geographicLayer2B');

        $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))
            ->assertOk()->assertViewMissing('geographicLayer2B');

        $this->actingAs($this->citizen)->get(route('citizen.dashboard'))
            ->assertOk()->assertViewMissing('geographicLayer2B');
    }

    // =========================================================================
    // §15 — PROMPT 8/5 (Bagian 2/3) — Layer 2B UI: four operational wilayah
    //       cards on the Admin dashboard (rendering / empty states / privacy /
    //       role isolation / static no-query audits).
    // =========================================================================

    /**
     * Fixture producing all four Layer 2B datasets with KNOWN values:
     *   Kelurahan Satu: active @5d + active @18d + resolved @30d
     *       → oldest 18 days · average 11.5 days · active 2/3 = 66.7% · load 2/3
     *   Kelurahan Dua : active @3d
     *       → oldest 3 days · average 3.0 days · active 1/1 = 100% · load 1/1
     */
    private function seedLayer2BFixture(): void
    {
        $this->freezeLayer2BTime();

        $this->complaintDaysAgo(5, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(18, ['status' => 'in_progress', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(30, ['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $this->complaintDaysAgo(3, ['status' => 'under_review', 'kelurahan_id' => $this->kelurahanB1->id, 'kecamatan_id' => $this->kecamatanB->id]);
    }

    public function test_layer2b_ui_section_and_cards_are_removed_from_admin_dashboard(): void
    {
        $this->seedLayer2BFixture();

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // The "Analitik Operasional Wilayah" section and all four Layer 2B cards
        // were removed in the dashboard streamlining pass. The backend payload
        // (`geographicLayer2B`) is still computed — see the payload tests below —
        // but it is no longer surfaced on the Admin dashboard.
        foreach ([
            'Analitik Operasional Wilayah',
            'Wilayah dengan Kasus Tertua',
            'Rata-rata Umur Laporan Aktif',
            'Proporsi Kasus Aktif',
            'Beban Kasus Aktif Terbesar',
        ] as $title) {
            $this->assertStringNotContainsString($title, $html);
        }
    }

    public function test_layer2b_empty_states_are_not_rendered_on_admin_dashboard(): void
    {
        // No complaints → the (now-removed) Layer 2B cards would each have shown
        // their own empty state. The section is gone, so none of those strings
        // may appear; the payload is still an honest empty set.
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2B', function ($b) {
            return $b['oldestCasesByKelurahan'] === []
                && $b['averageAgeByKelurahan'] === []
                && $b['activeRateByKelurahan'] === []
                && $b['activeLoadByKelurahan'] === [];
        });

        $html = $response->getContent();
        $this->assertStringNotContainsString('Belum ada data umur kasus per wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data rata-rata umur laporan per wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data proporsi kasus aktif per wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data beban kasus aktif per wilayah.', $html);
    }

    public function test_layer2b_partial_data_keeps_payload_valid(): void
    {
        // ONLY terminal complaints → active-rate has rows (0% active), while the
        // three active-only datasets fall back to empty sets. The payload must
        // remain valid and never fabricate rows.
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(4, ['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('geographicLayer2B', function ($b) {
            return count($b['activeRateByKelurahan']) === 1
                && $b['activeRateByKelurahan'][0]['active'] === 0
                && $b['oldestCasesByKelurahan'] === []
                && $b['averageAgeByKelurahan'] === []
                && $b['activeLoadByKelurahan'] === [];
        });

        // The removed cards no longer surface their empty-state strings.
        $html = $response->getContent();
        $this->assertStringNotContainsString('Belum ada data umur kasus per wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data beban kasus aktif per wilayah.', $html);
    }

    public function test_layer2b_ui_carries_no_pii(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(5, [
            'status'         => 'submitted',
            'kelurahan_id'   => $this->kelurahanA1->id,
            'kecamatan_id'   => $this->kecamatanA->id,
            'reporter_phone' => '081100009191',
            'reporter_email' => 'rahasia8ui@example.test',
            'description'    => 'KALIMAT_SENSITIF_UI_LAYER2B',
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['081100009191', 'rahasia8ui@example.test', 'KALIMAT_SENSITIF_UI_LAYER2B'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
    }

    public function test_layer2b_ui_is_not_rendered_for_other_roles(): void
    {
        $this->seedLayer2BFixture();

        $superHtml = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Analitik Operasional Wilayah', $superHtml);
        $this->assertStringNotContainsString('Wilayah dengan Kasus Tertua', $superHtml);

        $opHtml = $this->actingAs($this->operator)->get(route('operator.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Analitik Operasional Wilayah', $opHtml);
        $this->assertStringNotContainsString('Beban Kasus Aktif Terbesar', $opHtml);

        $citizenHtml = $this->actingAs($this->citizen)->get(route('citizen.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Analitik Operasional Wilayah', $citizenHtml);
    }

    public function test_layer2b_ui_has_no_duplicate_section_or_cards(): void
    {
        $this->seedLayer2BFixture();

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // The Layer 2B section/cards were removed entirely — zero occurrences.
        $this->assertSame(0, substr_count($html, 'Analitik Operasional Wilayah'));
        $this->assertSame(0, substr_count($html, 'Wilayah dengan Kasus Tertua'));
        $this->assertSame(0, substr_count($html, 'Rata-rata Umur Laporan Aktif'));
        $this->assertSame(0, substr_count($html, 'Proporsi Kasus Aktif'));
        $this->assertSame(0, substr_count($html, 'Beban Kasus Aktif Terbesar'));
    }

    public function test_layer2b_ui_uses_no_sla_or_overdue_terminology(): void
    {
        $this->seedLayer2BFixture();

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['SLA', 'Overdue', 'overdue', 'Breached', 'breach', 'Terlambat', 'terlambat'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "Layer 2B UI must not use '{$forbidden}'.");
        }
    }

    public function test_layer2b_ui_regression_layer1_layer2a_and_map_still_render(): void
    {
        $this->seedLayer2BFixture();
        // A coordinate-bearing complaint so the Leaflet canvas actually renders.
        $this->complaintDaysAgo(6, ['latitude' => -4.01, 'longitude' => 119.62, 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Map (Prompt 5/5).
        $this->assertStringContainsString('Peta Wilayah Aduan', $html);
        $this->assertStringContainsString('geographic-map-canvas', $html);
        // Layer 1 (Prompt 6/5).
        $this->assertStringContainsString('Top Kecamatan', $html);
        $this->assertStringContainsString('Top Kelurahan', $html);
        // Layer 2A (Prompt 7/5).
        $this->assertStringContainsString('Tren Kasus per Wilayah', $html);
        $this->assertStringContainsString('Tingkat Penyelesaian per Wilayah', $html);
        // The "Wilayah dengan Backlog Tertinggi", "Wilayah × Status" and
        // "Wilayah × Kategori" cards were removed in the streamlining pass.
        $this->assertStringNotContainsString('Wilayah dengan Backlog Tertinggi', $html);
        $this->assertStringNotContainsString('Wilayah × Status', $html);
        $this->assertStringNotContainsString('Wilayah × Kategori', $html);
        // Existing KPI row still present.
        $this->assertStringContainsString('Total Laporan', $html);
        $this->assertStringContainsString('Masuk Hari Ini', $html);
    }

    public function test_layer2b_ui_section_is_removed_from_admin_dashboard(): void
    {
        $this->seedLayer2BFixture();

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // The Layer 2B section and the legacy ROW 7 monitoring/audit feed were
        // removed; the page now ends on Layer 2A + Rating analytics.
        $this->assertStringNotContainsString('Analitik Operasional Wilayah', $html);
        $this->assertStringNotContainsString('Pemantauan Laporan Aktif', $html);
        $this->assertStringNotContainsString('Laporan Terbaru', $html);

        // Retained analytics still render, in order: Layer 2A then Rating.
        $pos2A    = strpos($html, 'Tren Kasus per Wilayah');
        $posRating = strpos($html, 'Analitik Rating Kepuasan');
        $this->assertNotFalse($pos2A);
        $this->assertNotFalse($posRating);
        $this->assertTrue($pos2A < $posRating, 'Rating analytics must follow Layer 2A.');
    }

    public function test_layer2b_ui_payload_keeps_the_four_backend_keys(): void
    {
        $this->seedLayer2BFixture();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                return array_keys($b) === [
                    'oldestCasesByKelurahan',
                    'averageAgeByKelurahan',
                    'activeRateByKelurahan',
                    'activeLoadByKelurahan',
                ];
            });
    }

    public function test_layer2b_ui_components_have_no_database_queries(): void
    {
        foreach (['geographic-oldest-cases', 'geographic-average-age', 'geographic-active-rate', 'geographic-active-load'] as $component) {
            $blade = file_get_contents(resource_path("views/components/dashboard/{$component}.blade.php"));

            foreach (['DB::', '::query()', '->get()', '->count()', '->paginate(', 'Eloquent'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $blade, "{$component}.blade.php must not query the database ('{$forbidden}').");
            }
        }
    }

    public function test_layer2b_ui_is_isolated_in_dedicated_components(): void
    {
        $dashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        // The Layer 2B card components were removed from the Admin dashboard in
        // the streamlining pass; the dedicated component files remain (and the
        // backend payload is still computed) but are no longer referenced here.
        $this->assertStringNotContainsString('<x-dashboard.geographic-oldest-cases', $dashboard);
        $this->assertStringNotContainsString('<x-dashboard.geographic-average-age', $dashboard);
        $this->assertStringNotContainsString('<x-dashboard.geographic-active-rate', $dashboard);
        $this->assertStringNotContainsString('<x-dashboard.geographic-active-load', $dashboard);

        // No raw SQL / model access leaks into the dashboard template.
        $this->assertStringNotContainsString('DB::', $dashboard);
        $this->assertStringNotContainsString('::query()', $dashboard);
    }

    public function test_layer2b_ui_adds_no_query_to_the_admin_dashboard(): void
    {
        // Freeze time so the age fixtures are stable, then compare the query count
        // with vs. without any Layer 2B rows — the UI must add ZERO queries.
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(9, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $withRows = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Add 10 more kelurahan worth of Layer 2B rows → query count unchanged.
        for ($i = 0; $i < 10; $i++) {
            $kec = Kecamatan::create(['name' => 'Kecamatan UI ' . $i, 'code' => '77' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $kel = Kelurahan::create(['kecamatan_id' => $kec->id, 'name' => 'Kelurahan UI ' . $i]);
            $this->complaintDaysAgo(9, ['status' => 'submitted', 'kelurahan_id' => $kel->id, 'kecamatan_id' => $kec->id]);
        }

        Cache::forget('dashboard:admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $grownRows = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withRows, $grownRows, 'Layer 2B UI must not add per-row queries.');
    }

    // =========================================================================
    // §16 — PROMPT 8/5 (Bagian 3/3) — FINAL QA: payload contract, value safety,
    //       cache key/TTL/invalidation, and privacy across ALL geographic layers.
    // =========================================================================

    public function test_final_qa_admin_payload_has_all_four_geographic_keys(): void
    {
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        foreach (['geographicMap', 'geographicAnalytics', 'geographicLayer2A', 'geographicLayer2B'] as $key) {
            $response->assertViewHas($key);
        }

        $response->assertViewHas('geographicLayer2B', function ($b) {
            return array_keys($b) === [
                'oldestCasesByKelurahan',
                'averageAgeByKelurahan',
                'activeRateByKelurahan',
                'activeLoadByKelurahan',
            ];
        });
    }

    public function test_final_qa_layer2b_numeric_values_are_safe(): void
    {
        // A spread of ages/counts so every metric produces rows.
        $this->freezeLayer2BTime();
        for ($i = 0; $i < 3; $i++) {
            $this->complaintDaysAgo(7 + $i, ['status' => 'submitted', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        }
        $this->complaintDaysAgo(2, ['status' => 'resolved', 'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);
        $this->complaintDaysAgo(4, ['status' => 'in_progress', 'kelurahan_id' => $this->kelurahanB1->id, 'kecamatan_id' => $this->kecamatanB->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('geographicLayer2B', function ($b) {
                foreach ($b['oldestCasesByKelurahan'] as $r) {
                    if (! is_int($r['oldestAgeDays']) || $r['oldestAgeDays'] < 0 || ! is_int($r['activeCount']) || $r['activeCount'] < 0) {
                        return false;
                    }
                }
                foreach ($b['averageAgeByKelurahan'] as $r) {
                    if (! is_numeric($r['averageAgeDays']) || $r['averageAgeDays'] < 0) {
                        return false;
                    }
                    // one decimal place max
                    if (round((float) $r['averageAgeDays'], 1) !== (float) $r['averageAgeDays']) {
                        return false;
                    }
                }
                foreach ($b['activeRateByKelurahan'] as $r) {
                    if (! is_numeric($r['activeRate']) || $r['activeRate'] < 0 || $r['activeRate'] > 100) {
                        return false;
                    }
                }
                foreach ($b['activeLoadByKelurahan'] as $r) {
                    if (! is_int($r['activeCount']) || $r['activeCount'] < 0 || ! is_int($r['totalCount']) || $r['totalCount'] < 0) {
                        return false;
                    }
                }

                $blob = json_encode($b);

                return ! str_contains($blob, 'NaN') && ! str_contains($blob, 'Infinity') && ! str_contains($blob, 'undefined') && ! str_contains($blob, 'null');
            });
    }

    public function test_final_qa_geographic_layers_use_only_the_shared_admin_cache_key(): void
    {
        $this->makeComplaint(['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::flush();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        // The one and only Admin cache entry holds every geographic layer.
        $this->assertTrue(Cache::has('dashboard:admin'));
        $cached = Cache::get('dashboard:admin');
        foreach (['geographicMap', 'geographicAnalytics', 'geographicLayer2A', 'geographicLayer2B'] as $key) {
            $this->assertArrayHasKey($key, $cached);
        }

        // No per-layer cache keys were introduced.
        foreach (['dashboard:admin:map', 'dashboard:admin:layer1', 'dashboard:admin:layer2a', 'dashboard:admin:layer2b'] as $forbidden) {
            $this->assertFalse(Cache::has($forbidden), "Unexpected per-layer cache key '{$forbidden}'.");
        }
    }

    public function test_final_qa_admin_cache_ttl_is_sixty_seconds(): void
    {
        $this->assertSame(60, \App\Services\DashboardCacheService::CACHE_TTL_SECONDS);
    }

    public function test_final_qa_geographic_cache_hit_returns_stable_payload(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(5, ['kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id]);

        Cache::forget('dashboard:admin');
        $first = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->viewData('geographicLayer2B');

        // Second request is served from cache → identical geographic payload.
        $second = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->viewData('geographicLayer2B');

        $this->assertSame($first, $second);
    }

    public function test_final_qa_category_and_dinas_changes_invalidate_the_geographic_cache(): void
    {
        Cache::forget('dashboard:admin');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(Cache::has('dashboard:admin'));

        // Category master change → ComplaintCategoryObserver clears staff caches.
        $this->category->update(['name' => 'Infrastruktur Peta (diperbarui)']);
        $this->assertFalse(Cache::has('dashboard:admin'));

        // Dinas/Unit master change → DinasUnitObserver clears staff caches.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(Cache::has('dashboard:admin'));
        $unit = DinasUnit::create(['name' => 'Dinas QA Uji', 'code' => 'QA-1', 'is_active' => true, 'sort_order' => 3]);
        $unit->update(['name' => 'Dinas QA Uji (diperbarui)']);
        $this->assertFalse(Cache::has('dashboard:admin'));
    }

    public function test_final_qa_no_geographic_route_exists(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $geographic = $routes->filter(function ($route) {
            return str_contains($route->uri(), 'geographic') || str_contains($route->uri(), 'geo/');
        });

        $this->assertCount(0, $geographic, 'No geographic route may exist (Admin-only, read-only).');
    }

    public function test_final_qa_all_geographic_components_have_no_db_access_or_pii(): void
    {
        $components = [
            'geographic-map', 'geographic-ranking', 'geographic-status-matrix',
            'geographic-category-ranking', 'geographic-completion', 'geographic-trend',
            'geographic-oldest-cases', 'geographic-average-age',
            'geographic-active-rate', 'geographic-active-load',
        ];

        foreach ($components as $component) {
            $blade = file_get_contents(resource_path("views/components/dashboard/{$component}.blade.php"));

            foreach (['DB::', '::query()', '->get()', '->paginate(', 'Eloquent', 'auth()->user()', 'request()->'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $blade, "{$component}.blade.php must not contain '{$forbidden}'.");
            }
        }
    }

    public function test_final_qa_geographic_payload_has_no_pii_across_all_layers(): void
    {
        $this->makeComplaint([
            'reporter_phone' => '081100006600',
            'reporter_email' => 'rahasiafinal@example.test',
            'description'    => 'KALIMAT_SENSITIF_FINAL_QA',
            'title'          => 'JUDUL_SENSITIF_FINAL_QA',
            'kelurahan_id'   => $this->kelurahanA1->id,
            'kecamatan_id'   => $this->kecamatanA->id,
            'latitude'       => -4.01,
            'longitude'      => 119.62,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        foreach (['geographicMap', 'geographicAnalytics', 'geographicLayer2A', 'geographicLayer2B'] as $key) {
            $response->assertViewHas($key, function ($payload) {
                $blob = json_encode($payload);

                foreach (['081100006600', 'rahasiafinal@example.test', 'KALIMAT_SENSITIF_FINAL_QA', 'JUDUL_SENSITIF_FINAL_QA', 'reporter', 'phone', 'email'] as $leak) {
                    if (str_contains($blob, $leak)) {
                        return false;
                    }
                }

                return true;
            });
        }

        $html = $response->getContent();
        // Phone/email must never surface anywhere. (A report's own title/description
        // could legitimately appear in the retained monitoring surfaces; the
        // payload-level check above is the authoritative guarantee for the
        // geographic layers.)
        foreach (['081100006600', 'rahasiafinal@example.test'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
    }

    public function test_final_qa_full_admin_dashboard_renders_all_layers_in_order(): void
    {
        $this->freezeLayer2BTime();
        $this->complaintDaysAgo(5, [
            'status' => 'submitted', 'latitude' => -4.01, 'longitude' => 119.62,
            'kelurahan_id' => $this->kelurahanA1->id, 'kecamatan_id' => $this->kecamatanA->id,
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $order = [
            'Total Laporan',
            'Tren Laporan',
            'Tingkat Penyelesaian',
            'Peta Wilayah Aduan',
            'Top Kecamatan',
            'Tingkat Penyelesaian per Wilayah',
            'Tren Kasus per Wilayah',
            'Analitik Rating Kepuasan',
        ];

        $last = -1;
        foreach ($order as $needle) {
            $pos = strpos($html, $needle);
            $this->assertNotFalse($pos, "Missing section: {$needle}");
            $this->assertGreaterThan($last, $pos, "Section out of order: {$needle}");
            $last = $pos;
        }

        // The removed cards must be absent from the streamlined dashboard.
        foreach ([
            'Beban Dinas/Unit Berdasarkan Status',
            'Kategori × Status',
            'Kategori dengan Backlog Tertinggi',
            'Wilayah × Status',
            'Wilayah × Kategori',
            'Analitik Operasional Wilayah',
            'Wilayah dengan Kasus Tertua',
            'Rata-rata Umur Laporan Aktif',
            'Proporsi Kasus Aktif',
            'Beban Kasus Aktif Terbesar',
            'Pemantauan Laporan Aktif',
            'Aktivitas Audit Terkini',
            'Laporan Terbaru',
        ] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }
    }

    public function test_final_qa_layer1_and_layer2a_empty_states_coexist(): void
    {
        // Completely empty DB → every retained geographic card shows an honest
        // empty state and the page still returns 200 with no fabricated data.
        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Belum ada data laporan untuk dipetakan.', $html);
        $this->assertStringContainsString('Belum ada data Kecamatan.', $html);
        $this->assertStringContainsString('Belum ada data penyelesaian per wilayah.', $html);
        // The removed cards' empty states must not appear.
        $this->assertStringNotContainsString('Belum ada data kategori per wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data status berdasarkan wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data umur kasus per wilayah.', $html);
        $this->assertStringNotContainsString('Belum ada data beban kasus aktif per wilayah.', $html);
    }
}
