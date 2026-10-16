<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\WaveForecast;
use App\Models\WeatherForecast;
use App\Models\WeatherLocation;
use App\Services\Weather\BmkgClient;
use App\Services\Weather\BmkgMarineClient;
use App\Services\Weather\RiskEvaluationService;
use App\Services\Weather\WeatherSettingService;
use App\Services\Weather\WeatherSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WINDY weather migration — feature coverage.
 *
 * Covers the owner decisions implemented in this slice:
 *   - O-2  public read-only, no login
 *   - O-3  BMKG only (no OWM)
 *   - O-7  manual sync only (no scheduler)
 *   - O-8  embed + narrow CSP (no ALLOWALL)
 *   - O-14a rain unit = mm/3 jam (from the VERIFIED `tp` field)
 *   - O-14b wave unavailable → "Data tidak tersedia"
 *   - O-15 timestamps stored in WITA
 *   - O-16 configuration stored in `app_settings`
 *
 * `tests/Fixtures/bmkg_prakiraan_cuaca_parepare.json` is a REAL BMKG response
 * captured on 2026-10-15 for adm4 73.72.02.1001 (Kota Parepare / Ujung /
 * Labukkang), used to prove the parser against the real payload shape.
 */
class WindyWeatherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /** Build a fake BMKG response with the known nested shape. */
    private function fakeBmkg(array $slots, ?array $lokasi = null): void
    {
        $lokasi ??= [
            'adm1' => '73', 'adm2' => '73.72', 'adm3' => '73.72.02', 'adm4' => '73.72.02.1001',
            'provinsi' => 'Sulawesi Selatan', 'kotkab' => 'Kota Parepare',
            'kecamatan' => 'Ujung', 'desa' => 'Labukkang',
            'lon' => 119.6249880584, 'lat' => -4.017522847, 'timezone' => '+0800', 'type' => 'adm4',
        ];

        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [
                    ['lokasi' => $lokasi, 'cuaca' => [$slots]],
                ],
            ], 200),
            // Marine (wave) source — default empty so land-only tests are isolated.
            'maritim.bmkg.go.id/*' => Http::response(['message' => 'not found'], 404),
        ]);
    }

    /** Build a fake BMKG maritime (wave) response. */
    private function fakeMarine(array $slots, string $code = 'P.AE.01', string $name = 'Perairan Pinrang'): void
    {
        Http::fake([
            'maritim.bmkg.go.id/*' => Http::response([
                'code' => $code,
                'name' => $name,
                'issued' => '2026-10-12 12:00 UTC',
                'valid_from' => '2026-10-13 00:00 UTC',
                'valid_to' => '2026-10-16 00:00 UTC',
                'forecast_day1' => $slots,
                'forecast_day2-4' => [],
            ], 200),
        ]);
    }

    /**
     * Pin the monitored locations to ONE entry so per-slot COUNT assertions
     * stay deterministic regardless of how many locations ship by default.
     * (The shipped default now covers Kota Parepare + the 4 kecamatan.)
     */
    private function singleParepareConfig(): void
    {
        config()->set('weather.locations', [[
            'key' => 'parepare', 'name' => 'Kota Parepare', 'adm4' => '73.72.02.1001',
            'latitude' => '-4.017522847', 'longitude' => '119.6249880584',
            'marine_area_code' => 'P.AE.01', 'is_default' => true,
        ]]);
    }

    private function marineSlot(string $utc, float $waveHeight, string $cat = 'Rendah', float $currentSpeed = 0.7): array
    {
        return [
            'time' => $utc,
            'weather' => 'Cerah Berawan',
            'temp_avg' => 28,
            'rh_avg' => 70,
            'wind_from' => 'Timur',
            'wind_speed' => 11,
            'wind_gust' => 23,
            'wave_cat' => $cat,
            'wave_height' => $waveHeight,
            'current_to' => 'Selatan',
            'current_speed' => $currentSpeed,
        ];
    }

    private function slot(string $at, float $t, int $hu, float $ws, string $wd, string $desc, float $tp = 0.0, int $tcc = 10): array
    {
        return [
            'local_datetime' => $at,
            't' => $t,
            'hu' => $hu,
            'ws' => $ws,
            'wd' => $wd,
            'wd_deg' => 90,
            'wd_to' => 'Barat',
            'tcc' => $tcc,
            'tp' => $tp,
            'weather' => 1,
            'weather_desc' => $desc,
            'vs_text' => null,
            'analysis_date' => '2026-10-12T12:00:00',
        ];
    }

    private function realFixture(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/bmkg_prakiraan_cuaca_parepare.json')),
            true
        );
    }

    private function realMarineFixture(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/bmkg_marine_perairan_PAE01.json')),
            true
        );
    }

    // ── Public surface (O-2) ─────────────────────────────────────────────────

    public function test_public_weather_page_is_reachable_without_login(): void
    {
        $this->get(route('weather.index'))->assertOk()->assertSee('Informasi Cuaca');
    }

    public function test_public_weather_page_states_no_data_honestly(): void
    {
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('belum tersedia');
    }

    public function test_public_weather_page_returns_404_when_feature_disabled(): void
    {
        config()->set('weather.enabled', false);

        $this->get(route('weather.index'))->assertNotFound();
    }

    // ── Manual sync (O-7) ────────────────────────────────────────────────────

    public function test_super_admin_manual_sync_persists_forecast_slots(): void
    {
        $this->singleParepareConfig();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 30.0, 'Selatan', 'Berawan', 1.5),
            $this->slot('2026-10-15 12:00:00', 32.0, 65, 15.0, 'Barat', 'Cerah', 0.0),
        ]);

        $this->actingAs($admin)
            ->post(route('super-admin.weather.sync'))
            ->assertRedirect(route('super-admin.weather.index'));

        $this->assertSame(2, WeatherForecast::count());
        $this->assertDatabaseHas('weather_sources', ['code' => 'bmkg']);
        // Rain is persisted from the verified `tp` field (mm/3 jam, O-14a).
        $this->assertDatabaseHas('weather_forecasts', ['rain_mm' => 1.50]);
        // Location metadata is refreshed from the verified `lokasi` block.
        $this->assertDatabaseHas('weather_locations', [
            'adm4_code' => '73.72.02.1001',
            'city' => 'Kota Parepare',
            'district' => 'Ujung',
            'village' => 'Labukkang',
        ]);
        // Timestamps are stored in the application (WITA) timezone.
        $this->assertSame('Asia/Makassar', config('app.timezone'));
    }

    public function test_sync_failure_writes_nothing_and_reports_error(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Http::fake([
            'api.bmkg.go.id/*' => Http::response(['message' => 'boom'], 500),
        ]);

        $this->actingAs($admin)
            ->post(route('super-admin.weather.sync'))
            ->assertRedirect(route('super-admin.weather.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, WeatherForecast::count());
    }

    public function test_non_super_admin_cannot_trigger_sync(): void
    {
        $citizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->actingAs($citizen)
            ->post(route('super-admin.weather.sync'))
            ->assertForbidden();
    }

    // ── Verified real payload parsing ────────────────────────────────────────

    public function test_parser_handles_the_real_bmkg_payload(): void
    {
        $client = app(BmkgClient::class);
        $json = $this->realFixture();

        $slots = $client->normalize($json);

        // Real response carries 3 groups of 3-hourly slots (21 total).
        $this->assertSame(21, count($slots));

        $location = $client->normalizeLocation($json);
        $this->assertSame('Kota Parepare', $location['city']);
        $this->assertSame('Ujung', $location['district']);
        $this->assertSame('Labukkang', $location['village']);
        $this->assertSame('Sulawesi Selatan', $location['province']);

        // Rain (`tp`) exists and is a real numeric value — not fabricated.
        $first = $slots[0];
        $this->assertIsFloat($first['rain_mm']);
        $this->assertIsFloat($first['temperature_c']);
        $this->assertIsInt($first['humidity_pct']);
        $this->assertIsInt($first['cloud_cover_pct']);
        $this->assertNotNull($first['weather_desc']);
    }

    public function test_full_sync_from_real_fixture_persists_and_renders(): void
    {
        $this->singleParepareConfig();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Http::fake([
            'api.bmkg.go.id/*' => Http::response($this->realFixture(), 200),
            'maritim.bmkg.go.id/*' => Http::response($this->realMarineFixture(), 200),
        ]);

        $this->actingAs($admin)->post(route('super-admin.weather.sync'))->assertRedirect();

        $this->assertSame(21, WeatherForecast::count());
        $this->assertSame(41, WaveForecast::count());

        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Kondisi Terkini')
            ->assertSee('Kondisi Laut')
            ->assertSee('Prakiraan 3 Hari')
            ->assertSee('Prakiraan Gelombang Laut')
            ->assertSee('Kota Parepare')
            ->assertSee('Perairan Pinrang');
    }

    // ── Risk evaluation (rain + wind; missing data disclosed) ────────────────

    public function test_rain_and_wind_drive_status_and_missing_parameters_are_reported(): void
    {
        /** @var RiskEvaluationService $risk */
        $risk = app(RiskEvaluationService::class);

        // Wind 30 km/jam ≥ 25 (Waspada) but < 40 (Siaga); rain 0 (Normal);
        // wave omitted → reported as missing.
        $result = $risk->evaluate(0.0, 30.0);

        $this->assertSame('WASPADA', $result['status']?->value);
        $this->assertSame(['wave'], $result['missing']);
        $this->assertSame(['rain', 'wind'], $result['basis']);
    }

    public function test_all_three_parameters_are_evaluated_when_available(): void
    {
        $result = app(RiskEvaluationService::class)->evaluate(0.0, 5.0, 3.0);

        // Wave 3.0 m ≥ 2.5 (Siaga) dominates.
        $this->assertSame('SIAGA', $result['status']?->value);
        $this->assertSame([], $result['missing']);
        $this->assertSame(['rain', 'wind', 'wave'], $result['basis']);
    }

    public function test_rain_alone_can_raise_status_to_siaga(): void
    {
        $result = app(RiskEvaluationService::class)->evaluate(25.0, 5.0, 0.3);

        $this->assertSame('SIAGA', $result['status']?->value);
    }

    public function test_status_is_null_when_no_parameter_is_available(): void
    {
        $result = app(RiskEvaluationService::class)->evaluate(null, null, null);

        $this->assertNull($result['status']);
        $this->assertSame(['rain', 'wind', 'wave'], $result['missing']);
    }

    // ── Configuration via app_settings (O-16) ────────────────────────────────

    public function test_super_admin_can_override_a_threshold_and_it_affects_evaluation(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // Raise the wind "Waspada" threshold above 30 so the value becomes Normal.
        $this->actingAs($admin)
            ->put(route('super-admin.weather.setting.update', 'weather_wind_waspada_kmh'), ['value' => 60])
            ->assertRedirect(route('super-admin.weather.index'));

        $this->assertDatabaseHas('app_settings', [
            'key' => 'weather_wind_waspada_kmh',
            'value' => '60',
        ]);

        $result = app(RiskEvaluationService::class)->evaluate(0.0, 30.0);
        $this->assertSame('NORMAL', $result['status']?->value);
    }

    public function test_unmanaged_threshold_key_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // The FormRequest rejects the unknown key during validation (redirect
        // back with an error), so no write can ever occur.
        $this->actingAs($admin)
            ->put(route('super-admin.weather.setting.update', 'weather_arbitrary_key'), ['value' => 10])
            ->assertSessionHasErrors('value');

        $this->assertSame(0, AppSetting::query()->where('key', 'weather_arbitrary_key')->count());
    }

    public function test_threshold_defaults_come_from_config(): void
    {
        $settings = app(WeatherSettingService::class);

        $this->assertSame(10.0, $settings->effectiveValue('weather_rain_waspada_mm'));
        $this->assertSame(25.0, $settings->effectiveValue('weather_wind_waspada_kmh'));
    }

    // ── Security headers / CSP (O-8) ─────────────────────────────────────────

    public function test_csp_allows_only_the_windy_embed_and_never_allowall(): void
    {
        $response = $this->get(route('weather.index'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-src 'self' https://embed.windy.com", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString('ALLOWALL', $csp);
        $this->assertStringNotContainsString('frame-ancestors *', $csp);
    }

    // ── Authorization on the Super Admin surface ─────────────────────────────

    public function test_super_admin_weather_page_requires_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('super-admin.weather.index'))
            ->assertForbidden();
    }

    public function test_super_admin_weather_page_renders_for_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('super-admin.weather.index'))
            ->assertOk()
            ->assertSee('Sinkronisasi Data BMKG');
    }

    // ── Wave (maritime) source — O-14b now available ─────────────────────────

    public function test_marine_parser_handles_the_real_payload(): void
    {
        $json = json_decode(file_get_contents(base_path('tests/Fixtures/bmkg_marine_perairan_PAE01.json')), true);

        $client = app(BmkgMarineClient::class);
        $slots = $client->normalize($json);

        // 24 hourly + 17 three-hourly slots.
        $this->assertSame(41, count($slots));

        $area = $client->normalizeArea($json);
        $this->assertSame('P.AE.01', $area['code']);
        $this->assertSame('Perairan Pinrang', $area['name']);

        $first = $slots[0];
        $this->assertIsFloat($first['wave_height_m']);
        $this->assertIsFloat($first['wind_speed_kt']);
        $this->assertSame('Rendah', $first['wave_cat']);
        // UTC source time is converted to the application timezone (WITA).
        $this->assertSame('Asia/Makassar', $first['forecast_at']->timezoneName);
    }

    /**
     * The real feed was sampled across ALL 375 sea areas (38 provinces, `P.*`
     * perairan + `M.*` laut). These three fixtures are the real outliers:
     *   - P.AF.05 — omits the station block entirely.
     *   - P.J.01  — `wind_gust: null` in every slot.
     *   - M.33    — a `laut` (M.*) area, not a `perairan` (P.*) one.
     */
    public function test_marine_parser_handles_area_without_a_station_block(): void
    {
        $json = json_decode(file_get_contents(base_path('tests/Fixtures/bmkg_marine_perairan_PAF05_minimal.json')), true);

        // This area really does omit every station key.
        $this->assertArrayNotHasKey('nama_stasiun', $json);

        $client = app(BmkgMarineClient::class);
        $slots = $client->normalize($json);
        $area = $client->normalizeArea($json);

        $this->assertSame(41, count($slots));
        $this->assertSame('P.AF.05', $area['code']);
        $this->assertSame('Selat Wawoni', $area['name']);
    }

    public function test_marine_parser_stores_a_null_wind_gust_as_null_never_zero(): void
    {
        $json = json_decode(file_get_contents(base_path('tests/Fixtures/bmkg_marine_perairan_PJ01_null_gust.json')), true);

        $client = app(BmkgMarineClient::class);
        $slots = $client->normalize($json);

        $this->assertSame(41, count($slots));

        // Every slot in this area has a NULL gust; it must stay NULL (not 0).
        foreach ($slots as $slot) {
            $this->assertNull($slot['wind_gust_kt']);
        }

        // ...and a NULL gust must not stop the wave height being read.
        $this->assertNotNull($slots[0]['wave_height_m']);
    }

    public function test_marine_parser_handles_a_laut_area_code(): void
    {
        $json = json_decode(file_get_contents(base_path('tests/Fixtures/bmkg_marine_laut_M33.json')), true);

        $client = app(BmkgMarineClient::class);
        $area = $client->normalizeArea($json);

        $this->assertSame('M.33', $area['code']);
        $this->assertSame('Laut Arafuru bagian tengah', $area['name']);
        $this->assertSame(41, count($client->normalize($json)));
    }

    public function test_marine_parser_discovers_forecast_groups_dynamically(): void
    {
        // A hypothetical future payload that adds a new day group and renames
        // nothing: the parser must still read every `forecast_*` group.
        $json = [
            'code' => 'P.XX.99',
            'name' => 'Perairan Uji',
            'issued' => '2026-10-12 12:00 UTC',
            'valid_from' => '2026-10-13 00:00 UTC',
            'valid_to' => '2026-10-16 00:00 UTC',
            'forecast_day1' => [$this->marineSlot('2026-10-13 00:00 UTC', 0.5)],
            'forecast_day2-4' => [$this->marineSlot('2026-10-13 03:00 UTC', 0.9)],
            'forecast_day5-7' => [$this->marineSlot('2026-10-16 00:00 UTC', 1.5)], // NEW group
        ];

        $slots = app(BmkgMarineClient::class)->normalize($json);

        $this->assertCount(3, $slots);
    }

    public function test_marine_parser_tolerates_iso_and_comma_formats(): void
    {
        $json = [
            'code' => 'P.XX.99',
            'name' => 'Perairan Uji',
            'issued' => '2026-10-12T12:00:00Z',
            'valid_from' => '2026-10-13T00:00:00+00:00',
            'valid_to' => '2026-10-16 00:00 UTC',
            'forecast_day1' => [[
                'time' => '2026-10-13T00:00:00Z',
                'weather' => 'Cerah',
                'temp_avg' => '28',
                'rh_avg' => '70',
                'wind_from' => 'Timur',
                'wind_speed' => '11',
                'wind_gust' => null,
                'wave_cat' => 'Rendah',
                'wave_height' => '0,8',   // comma decimal + string
                'current_to' => 'Selatan',
                'current_speed' => '0,76',
            ]],
            'forecast_day2-4' => [],
        ];

        $client = app(BmkgMarineClient::class);
        $slots = $client->normalize($json);
        $area = $client->normalizeArea($json);

        $this->assertCount(1, $slots);
        $this->assertSame(0.8, $slots[0]['wave_height_m']);
        $this->assertSame(0.76, $slots[0]['current_speed_kt']);
        $this->assertSame(11.0, $slots[0]['wind_speed_kt']);
        $this->assertSame(28, $slots[0]['temp_avg_c']);
        $this->assertNull($slots[0]['wind_gust_kt']);
        // ISO-8601 "T"/"Z" times are parsed and shifted to WITA.
        $this->assertSame('Asia/Makassar', $slots[0]['forecast_at']->timezoneName);
        $this->assertSame('2026-10-13 08:00:00', $slots[0]['forecast_at']->format('Y-m-d H:i:s'));
        $this->assertNotNull($area['issued_at']);
    }

    public function test_marine_parser_returns_null_area_when_payload_is_not_an_object(): void
    {
        $client = app(BmkgMarineClient::class);

        $this->assertNull($client->normalizeArea('not-an-array'));
        $this->assertNull($client->normalizeArea(null));
        $this->assertSame([], $client->normalize('not-an-array'));
    }

    public function test_sync_persists_wave_data_and_it_raises_the_risk_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'Kota Parepare', 'kecamatan' => 'Ujung', 'desa' => 'Labukkang'], 'cuaca' => [[
                    $this->slot('2026-10-13 09:00:00', 31.0, 50, 11.9, 'E', 'Cerah', 0.0),
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response([
                'code' => 'P.AE.01', 'name' => 'Perairan Pinrang',
                'issued' => '2026-10-12 12:00 UTC',
                'valid_from' => '2026-10-13 00:00 UTC', 'valid_to' => '2026-10-16 00:00 UTC',
                'forecast_day1' => [
                    $this->marineSlot('2026-10-13 00:00 UTC', 2.8, 'Tinggi'),
                ],
                'forecast_day2-4' => [],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('super-admin.weather.sync'))->assertRedirect();

        $this->assertSame(1, WaveForecast::count());
        $this->assertDatabaseHas('wave_forecasts', [
            'area_code' => 'P.AE.01',
            'area_name' => 'Perairan Pinrang',
            'wave_cat' => 'Tinggi',
        ]);

        // Wave 2.8 m ≥ 2.5 → Siaga appears on the public page.
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Siaga')
            ->assertSee('Kondisi Laut')
            ->assertSee('Perairan Pinrang')
            ->assertSee('gelombang'); // disclosed in the status basis
    }

    // ── Per-day wave forecast (derived from stored slots only) ───────────────

    public function test_daily_wave_groups_slots_by_wita_day_and_aggregates_honestly(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // Slots at 17:00 and 23:00 UTC on 2026-10-12 fall on 2026-10-13 in WITA
        // (+8). Two more slots land on the next WITA day. Day-1 hourly slots
        // crossing WITA midnight must group by the WITA calendar day, not UTC.
        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'Kota Parepare'], 'cuaca' => [[
                    $this->slot('2026-10-13 09:00:00', 31.0, 50, 11.9, 'E', 'Cerah', 0.0),
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response([
                'code' => 'P.AE.01', 'name' => 'Perairan Pinrang',
                'issued' => '2026-10-12 12:00 UTC',
                'valid_from' => '2026-10-12 00:00 UTC', 'valid_to' => '2026-10-16 00:00 UTC',
                'forecast_day1' => [
                    $this->marineSlot('2026-10-12 17:00 UTC', 0.6, 'Rendah', 0.5),
                    $this->marineSlot('2026-10-12 23:00 UTC', 2.8, 'Tinggi', 0.9),
                    $this->marineSlot('2026-10-13 05:00 UTC', 1.1, 'Rendah', 0.7),
                ],
                'forecast_day2-4' => [
                    $this->marineSlot('2026-10-13 17:00 UTC', 0.4, 'Tenang', 0.3),
                ],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('super-admin.weather.sync'))->assertRedirect();

        $waveForecast = app(\App\Services\Weather\WeatherDisplayService::class)
            ->snapshot('parepare')['wave_forecast'];

        $this->assertTrue($waveForecast['available']);
        $this->assertSame('Perairan Pinrang', $waveForecast['area_name']);
        $this->assertCount(2, $waveForecast['days']);

        // First WITA day (2026-10-13): slots at 17:00 & 23:00 UTC → 01:00 & 07:00
        // WITA, plus 05:00 UTC → 13:00 WITA.
        $day1 = $waveForecast['days'][0];
        $this->assertSame('2026-10-13', $day1['date']);
        $this->assertSame(0.6, $day1['wave_min_m']);
        $this->assertSame(2.8, $day1['wave_max_m']);
        // Peak slot's own category (Tinggi at 2.8 m) — the source's label.
        $this->assertSame('Tinggi', $day1['wave_cat']);
        $this->assertSame(11.0, $day1['wind_max_kt']);
        $this->assertSame(0.9, $day1['current_max_kt']);
        $this->assertCount(3, $day1['slots']);
        $this->assertSame('01:00', $day1['slots'][0]['time']);

        // Second WITA day (2026-10-14): the 17:00 UTC slot → 01:00 WITA next day.
        $day2 = $waveForecast['days'][1];
        $this->assertSame('2026-10-14', $day2['date']);
        $this->assertSame(0.4, $day2['wave_min_m']);
        $this->assertSame('Tenang', $day2['wave_cat']);

        // Still cache-safe: no Carbon/plain objects anywhere in the payload.
        $this->assertNoPlainObjects($waveForecast);
    }

    public function test_daily_wave_is_capped_at_forecast_days(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $slots = [];
        for ($d = 0; $d < 5; $d++) {
            $slots[] = $this->marineSlot(sprintf('2026-10-%02d 00:00 UTC', 13 + $d), 0.8);
        }

        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'Kota Parepare'], 'cuaca' => [[
                    $this->slot('2026-10-13 09:00:00', 31.0, 50, 11.9, 'E', 'Cerah', 0.0),
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response([
                'code' => 'P.AE.01', 'name' => 'Perairan Pinrang',
                'issued' => '2026-10-12 12:00 UTC',
                'valid_from' => '2026-10-13 00:00 UTC', 'valid_to' => '2026-10-18 00:00 UTC',
                'forecast_day1' => $slots,
                'forecast_day2-4' => [],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('super-admin.weather.sync'))->assertRedirect();

        $waveForecast = app(\App\Services\Weather\WeatherDisplayService::class)
            ->snapshot('parepare')['wave_forecast'];

        // 5 stored days, but the display is capped at `forecast_days` (3).
        $this->assertCount((int) config('weather.forecast_days'), $waveForecast['days']);
    }

    public function test_daily_wave_is_unavailable_when_no_wave_data_exists(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $snapshot = app(\App\Services\Weather\WeatherDisplayService::class)->snapshot('parepare');

        $this->assertFalse($snapshot['wave_forecast']['available']);
        $this->assertSame([], $snapshot['wave_forecast']['days']);
        $this->assertNull($snapshot['wave_forecast']['area_name']);

        // The page must still render, without the wave-forecast section.
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertDontSee('Prakiraan Gelombang Laut');
    }

    public function test_wave_source_failure_does_not_break_land_sync(): void
    {
        $this->singleParepareConfig();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'Kota Parepare'], 'cuaca' => [[
                    $this->slot('2026-10-13 09:00:00', 31.0, 50, 11.9, 'E', 'Cerah', 0.0),
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response(['message' => 'down'], 503),
        ]);

        $this->actingAs($admin)->post(route('super-admin.weather.sync'))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Land data still persisted; wave simply absent.
        $this->assertSame(1, WeatherForecast::count());
        $this->assertSame(0, WaveForecast::count());

        // Public page still renders, with wave shown as unavailable.
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Kondisi Terkini')
            ->assertSee('Data tidak tersedia');
    }

    public function test_super_admin_can_override_wave_threshold(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->put(route('super-admin.weather.setting.update', 'weather_wave_waspada_m'), ['value' => 0.5])
            ->assertRedirect(route('super-admin.weather.index'));

        // With Waspada at 0.5 m, a 0.8 m wave is now Waspada.
        $result = app(RiskEvaluationService::class)->evaluate(0.0, 5.0, 0.8);
        $this->assertSame('WASPADA', $result['status']?->value);
    }

    // ── Stale-slot pruning ───────────────────────────────────────────────────

    public function test_sync_prunes_slots_from_a_previous_analysis_window(): void
    {
        $this->singleParepareConfig();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // Current published window: a single 12:00 slot.
        $this->fakeBmkg([
            $this->slot('2026-10-15 12:00:00', 31.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $source = \App\Models\WeatherSource::where('code', 'bmkg')->firstOrFail();
        $location = \App\Models\WeatherLocation::firstOrFail();

        // Simulate a STALE slot left over from a previous analysis cycle.
        WeatherForecast::create([
            'weather_location_id' => $location->id,
            'weather_source_id' => $source->id,
            'forecast_at' => '2026-10-15 09:00:00',
            'temperature_c' => 30,
        ]);
        $this->assertSame(2, WeatherForecast::count());

        // Next sync of the same window must drop the stale slot.
        app(WeatherSyncService::class)->sync();

        $this->assertSame(1, WeatherForecast::count());
        $this->assertDatabaseMissing('weather_forecasts', ['forecast_at' => '2026-10-15 09:00:00']);
    }

    // ── Scheduled / CLI sync (D-16) ──────────────────────────────────────────

    public function test_weather_sync_command_persists_data(): void
    {
        $this->singleParepareConfig();

        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.5),
        ]);

        $this->artisan('weather:sync')->assertSuccessful();

        $this->assertSame(1, WeatherForecast::count());
    }

    public function test_weather_sync_command_reports_failure_without_writing(): void
    {
        Http::fake([
            'api.bmkg.go.id/*' => Http::response(['message' => 'boom'], 500),
        ]);

        $this->artisan('weather:sync')->assertFailed();

        $this->assertSame(0, WeatherForecast::count());
    }

    // ── Cache invalidation on sync ───────────────────────────────────────────

    public function test_sync_invalidates_the_public_read_cache(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // Prime the cache with the empty state.
        $this->get(route('weather.index'))->assertSee('Sinkronisasi belum pernah dijalankan');

        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);

        app(WeatherSyncService::class)->sync();

        // After sync the cache must reflect the new data.
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Kondisi Terkini')
            ->assertDontSee('Sinkronisasi belum pernah dijalankan');
    }

    // ── Multi-location support ───────────────────────────────────────────────

    public function test_registry_reads_configured_locations_and_default(): void
    {
        $registry = app(\App\Services\Weather\WeatherLocationRegistry::class);

        // Shipped default: Kota Parepare + the four kecamatan of Kota Parepare.
        $all = $registry->all();
        $this->assertCount(5, $all);

        $keys = array_column($all, 'key');
        $this->assertSame(
            ['parepare', 'ujung', 'soreang', 'bacukiki-barat', 'bacukiki'],
            $keys,
        );

        // Default remains the city-wide "Kota Parepare" entry.
        $this->assertSame('parepare', $registry->defaultKey());

        $parepare = $registry->find('parepare');
        $this->assertSame('73.72.02.1001', $parepare['adm4']);
        $this->assertSame('P.AE.01', $parepare['marine_area_code']);

        // Every entry carries a verified adm4; the two coastal kecamatan carry
        // the verified sea area P.AE.01, the two inland ones carry NULL (no sea).
        foreach ($all as $entry) {
            $this->assertNotSame('', trim($entry['adm4']));
        }
        $this->assertSame('P.AE.01', $registry->find('ujung')['marine_area_code']);
        $this->assertSame('P.AE.01', $registry->find('soreang')['marine_area_code']);
        $this->assertNull($registry->find('bacukiki-barat')['marine_area_code']);
        $this->assertNull($registry->find('bacukiki')['marine_area_code']);

        // The four kecamatan use DISTINCT verified adm4 codes.
        $this->assertSame('73.72.02.1001', $registry->find('ujung')['adm4']);
        $this->assertSame('73.72.03.1001', $registry->find('soreang')['adm4']);
        $this->assertSame('73.72.04.1001', $registry->find('bacukiki-barat')['adm4']);
        $this->assertSame('73.72.01.1004', $registry->find('bacukiki')['adm4']);
    }

    public function test_default_config_exposes_all_five_locations_in_the_picker(): void
    {
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Pilih wilayah pantau')
            ->assertSee('Kota Parepare')
            ->assertSee('Kecamatan Ujung')
            ->assertSee('Kecamatan Soreang')
            ->assertSee('Kecamatan Bacukiki Barat')
            ->assertSee('Kecamatan Bacukiki');
    }

    public function test_every_configured_location_route_renders(): void
    {
        foreach (['parepare', 'ujung', 'soreang', 'bacukiki-barat', 'bacukiki'] as $key) {
            $this->get(route('weather.location', $key))
                ->assertOk()
                ->assertSee('Informasi Cuaca');
        }
    }

    public function test_area_card_shows_the_configured_name_so_shared_adm4_stays_distinct(): void
    {
        $this->singleParepareConfig();

        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        // "Kota Parepare" and "Kecamatan Ujung" share adm4 73.72.02.1001, so
        // BMKG's own `lokasi` metadata is identical; the page must still show
        // the CONFIGURED area name so the two entries stay distinguishable.
        $this->get(route('weather.location', 'parepare'))
            ->assertOk()
            ->assertSee('Kota Parepare');
    }

    public function test_inland_kecamatan_report_wave_as_unavailable_not_fabricated(): void
    {
        // The two inland kecamatan have NO sea area, so their snapshot must
        // report the wave as unavailable (never a value).
        config()->set('weather.locations', [
            ['key' => 'bacukiki', 'name' => 'Kecamatan Bacukiki', 'adm4' => '73.72.01.1004',
             'marine_area_code' => null, 'is_default' => true],
        ]);

        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $snapshot = app(\App\Services\Weather\WeatherDisplayService::class)->snapshot('bacukiki');

        $this->assertFalse($snapshot['wave']['available']);
        $this->assertNull($snapshot['wave']['wave_height_m']);
        $this->assertNull($snapshot['wave']['area_name']);
        // No wave chart is fabricated either.
        $this->assertNull(collect($snapshot['charts'])->firstWhere('key', 'wave_max'));

        // The page still renders and honestly states the wave is unavailable,
        // and never shows a "Kondisi Laut" panel for an inland kecamatan.
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertDontSee('Kondisi Laut')
            ->assertSee('Data tidak tersedia');
    }

    public function test_registry_drops_entries_without_a_key_or_adm4(): void
    {
        config()->set('weather.locations', [
            ['key' => 'valid', 'name' => 'Valid', 'adm4' => '73.72.02.1001'],
            ['name' => 'No key', 'adm4' => '11.11.11.1111'],   // dropped
            ['key' => 'no-adm4', 'name' => 'No adm4'],          // dropped
        ]);

        $registry = app(\App\Services\Weather\WeatherLocationRegistry::class);

        $this->assertCount(1, $registry->all());
        $this->assertSame('valid', $registry->defaultKey());
    }

    public function test_public_default_page_renders_the_default_location(): void
    {
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Informasi Cuaca');
    }

    public function test_named_location_route_renders_and_unknown_location_is_404(): void
    {
        $this->get(route('weather.location', 'parepare'))->assertOk()->assertSee('Informasi Cuaca');

        $this->get(route('weather.location', 'nowhere'))->assertNotFound();
    }

    public function test_sync_writes_land_and_wave_data_for_every_configured_location(): void
    {
        // Two locations, each with its own adm4 AND its own sea area.
        config()->set('weather.locations', [
            [
                'key' => 'parepare', 'name' => 'Kota Parepare', 'adm4' => '73.72.02.1001',
                'latitude' => '-4.017522847', 'longitude' => '119.6249880584',
                'marine_area_code' => 'P.AE.01', 'is_default' => true,
            ],
            [
                'key' => 'pinrang', 'name' => 'Kab. Pinrang', 'adm4' => '73.71.01.1001',
                'latitude' => '-3.79', 'longitude' => '119.65',
                'marine_area_code' => 'P.AE.02', 'is_default' => false,
            ],
        ]);

        // Route each adm4 to a distinct land response and each sea area to a
        // distinct wave response, proving data does not cross locations.
        Http::fake([
            'api.bmkg.go.id/*' => function ($request) {
                $adm4 = $request['adm4'] ?? null;
                $city = $adm4 === '73.71.01.1001' ? 'Kab. Pinrang' : 'Kota Parepare';

                return Http::response([
                    'data' => [['lokasi' => ['kotkab' => $city], 'cuaca' => [[
                        $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
                    ]]]],
                ], 200);
            },
            'maritim.bmkg.go.id/*' => function ($request) {
                $isPinrang = str_contains($request->url(), 'P.AE.02');

                return Http::response([
                    'code' => $isPinrang ? 'P.AE.02' : 'P.AE.01',
                    'name' => $isPinrang ? 'Perairan Barru' : 'Perairan Pinrang',
                    'issued' => '2026-10-12 12:00 UTC',
                    'valid_from' => '2026-10-13 00:00 UTC', 'valid_to' => '2026-10-16 00:00 UTC',
                    'forecast_day1' => [
                        $this->marineSlot('2026-10-13 00:00 UTC', $isPinrang ? 1.8 : 0.8),
                    ],
                    'forecast_day2-4' => [],
                ], 200);
            },
        ]);

        $result = app(WeatherSyncService::class)->sync();

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['locations']);
        // 2 land slots (one per location) + 2 wave slots (one per sea area).
        $this->assertSame(2, WeatherForecast::count());
        $this->assertSame(2, WaveForecast::count());

        // Each location is linked to its OWN sea area, and the two never mix.
        $this->assertDatabaseHas('weather_locations', ['key' => 'parepare', 'marine_area_code' => 'P.AE.01']);
        $this->assertDatabaseHas('weather_locations', ['key' => 'pinrang', 'marine_area_code' => 'P.AE.02']);
        $this->assertDatabaseHas('wave_forecasts', ['area_code' => 'P.AE.01', 'area_name' => 'Perairan Pinrang']);
        $this->assertDatabaseHas('wave_forecasts', ['area_code' => 'P.AE.02', 'area_name' => 'Perairan Barru']);

        // The Parepare page shows only Parepare's 0,8 m wave, not Pinrang's 1,8 m.
        $this->get(route('weather.location', 'parepare'))
            ->assertOk()
            ->assertSee('Perairan Pinrang')
            ->assertSee('0,8 m')
            ->assertDontSee('Perairan Barru');

        $this->get(route('weather.location', 'pinrang'))
            ->assertOk()
            ->assertSee('Perairan Barru')
            ->assertSee('1,8 m');
    }

    public function test_two_locations_sharing_one_sea_area_fetch_waves_once(): void
    {
        config()->set('weather.locations', [
            ['key' => 'a', 'name' => 'A', 'adm4' => '11.11.11.1001', 'marine_area_code' => 'P.AE.01', 'is_default' => true],
            ['key' => 'b', 'name' => 'B', 'adm4' => '22.22.22.1001', 'marine_area_code' => 'P.AE.01', 'is_default' => false],
        ]);

        $marineCalls = 0;

        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'X'], 'cuaca' => [[
                    $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => function () use (&$marineCalls) {
                $marineCalls++;

                return Http::response([
                    'code' => 'P.AE.01', 'name' => 'Perairan Pinrang',
                    'issued' => '2026-10-12 12:00 UTC',
                    'valid_from' => '2026-10-13 00:00 UTC', 'valid_to' => '2026-10-16 00:00 UTC',
                    'forecast_day1' => [$this->marineSlot('2026-10-13 00:00 UTC', 0.8)],
                    'forecast_day2-4' => [],
                ], 200);
            },
        ]);

        app(WeatherSyncService::class)->sync();

        // Two land fetches, but only ONE wave fetch for the shared sea area.
        $this->assertSame(1, $marineCalls);
        $this->assertSame(2, WeatherForecast::count());
        $this->assertSame(1, WaveForecast::count());
    }

    public function test_public_page_shows_a_location_picker_only_when_multiple_exist(): void
    {
        // Single location: no picker.
        $this->singleParepareConfig();

        $this->get(route('weather.location', 'parepare'))
            ->assertOk()
            ->assertDontSee('Pilih wilayah pantau');

        config()->set('weather.locations', [
            ['key' => 'parepare', 'name' => 'Kota Parepare', 'adm4' => '73.72.02.1001', 'marine_area_code' => 'P.AE.01', 'is_default' => true],
            ['key' => 'pinrang', 'name' => 'Kab. Pinrang', 'adm4' => '73.71.01.1001', 'marine_area_code' => 'P.AE.02', 'is_default' => false],
        ]);

        // The location list is cached; a runtime config change must flush it.
        Cache::flush();

        $this->get(route('weather.location', 'parepare'))
            ->assertOk()
            ->assertSee('Pilih wilayah pantau')
            ->assertSee('Kab. Pinrang');
    }

    public function test_super_admin_panel_lists_every_configured_location(): void
    {
        config()->set('weather.locations', [
            ['key' => 'parepare', 'name' => 'Kota Parepare', 'adm4' => '73.72.02.1001', 'marine_area_code' => 'P.AE.01', 'is_default' => true],
            ['key' => 'pinrang', 'name' => 'Kab. Pinrang', 'adm4' => '73.71.01.1001', 'marine_area_code' => 'P.AE.02', 'is_default' => false],
        ]);

        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('super-admin.weather.index'))
            ->assertOk()
            ->assertSee('Wilayah Pantau')
            ->assertSee('Kota Parepare')
            ->assertSee('Kab. Pinrang')
            ->assertSee('P.AE.02');
    }

    public function test_sync_with_no_configured_locations_reports_honestly(): void
    {
        config()->set('weather.locations', []);

        $result = app(WeatherSyncService::class)->sync();

        $this->assertFalse($result['ok']);
        $this->assertSame('no_locations', $result['status']);
        $this->assertSame(0, WeatherForecast::count());
    }

    public function test_snapshot_is_fully_serialisable_and_cache_safe(): void
    {
        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'Kota Parepare'], 'cuaca' => [[
                    $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response([
                'code' => 'P.AE.01', 'name' => 'Perairan Pinrang',
                'issued' => '2026-10-12 12:00 UTC',
                'valid_from' => '2026-10-13 00:00 UTC', 'valid_to' => '2026-10-16 00:00 UTC',
                'forecast_day1' => [$this->marineSlot('2026-10-13 00:00 UTC', 0.8)],
                'forecast_day2-4' => [],
            ], 200),
        ]);
        app(WeatherSyncService::class)->sync();

        $snapshot = app(\App\Services\Weather\WeatherDisplayService::class)->snapshot('parepare');

        // A serialising cache store (file/database) must never hand back an
        // incomplete object. That only holds if the payload contains NO
        // non-enum objects (Carbon was the culprit). Walk the whole structure.
        $this->assertNoPlainObjects($snapshot);

        // The datetime fields specifically must be strings, not Carbon objects.
        $this->assertIsString($snapshot['current']['forecast_at']);
        $this->assertIsString($snapshot['generated_at']);
        $this->assertIsString($snapshot['wave']['forecast_at']);
    }

    /** Assert the payload holds no object except PHP enums. */
    private function assertNoPlainObjects(mixed $value, string $path = 'snapshot'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->assertNoPlainObjects($item, $path.'.'.$key);
            }

            return;
        }

        if (is_object($value)) {
            $this->assertInstanceOf(
                \UnitEnum::class,
                $value,
                "Unexpected object at {$path} (".get_debug_type($value).') — cache payload must be scalars/arrays/enums only.',
            );
        }
    }

    public function test_sync_adopts_a_legacy_location_row_with_null_key(): void
    {
        $this->singleParepareConfig();

        // Simulate a row created BEFORE multi-location support (NULL key), with
        // an existing forecast that must survive the upgrade.
        $source = \App\Models\WeatherSource::create(['code' => 'bmkg', 'label' => 'BMKG', 'is_enabled' => true]);
        $legacy = WeatherLocation::create([
            'key' => null,
            'name' => 'Kota Parepare',
            'adm4_code' => '73.72.02.1001',
            'is_active' => true,
        ]);
        WeatherForecast::create([
            'weather_location_id' => $legacy->id,
            'weather_source_id' => $source->id,
            'forecast_at' => '2026-10-15 06:00:00',
            'temperature_c' => 30,
        ]);

        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);

        app(WeatherSyncService::class)->sync();

        // Exactly ONE location row — the legacy row was adopted, not duplicated.
        $this->assertSame(1, WeatherLocation::count());
        $this->assertDatabaseHas('weather_locations', [
            'id' => $legacy->id,
            'key' => 'parepare',
            'marine_area_code' => 'P.AE.01',
        ]);

        // The old forecast now belongs to the adopted location and the new slot
        // was added for the SAME location id (no orphaned rows).
        $this->assertSame(1, WeatherForecast::query()->where('weather_location_id', $legacy->id)->count());
    }

    // ── Dashboard layout + 3-day charts (owner-requested redesign) ───────────

    public function test_public_page_renders_the_weather_layout(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Informasi Cuaca')
            ->assertSee('Ringkasan Eksekutif')
            ->assertSee('Peta Cuaca')
            ->assertSee('Prakiraan & Analitik')
            ->assertSee('Status Risiko Wilayah')
            ->assertSee('Kondisi Terkini');
    }

    // ── Audit fixes (D1–D8) ──────────────────────────────────────────────────

    /**
     * D1: the app layout appends "— Lapor Pak Wali"; the page title must not
     * already contain it, otherwise the browser tab shows it twice.
     */
    public function test_document_title_is_not_double_suffixed(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<title>Informasi Cuaca — Lapor Pak Wali</title>', $html);
        $this->assertStringNotContainsString('Lapor Pak Wali — Lapor Pak Wali', $html);
    }

    /**
     * D2: exactly one page-level heading so the outline starts at h1.
     */
    public function test_page_exposes_a_single_h1(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Informasi cuaca', $html);
    }

    /**
     * D3: the tab widget must wire role="tab" ↔ role="tabpanel" via ids and
     * aria-controls/aria-labelledby, and expose arrow-key navigation.
     */
    public function test_tabs_are_wired_with_the_full_aria_tab_pattern(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        foreach (['ringkasan', 'peta', 'prakiraan'] as $key) {
            $this->assertStringContainsString('id="weather-tab-'.$key.'"', $html);
            $this->assertStringContainsString('aria-controls="weather-panel-'.$key.'"', $html);
            $this->assertStringContainsString('id="weather-panel-'.$key.'"', $html);
            $this->assertStringContainsString('aria-labelledby="weather-tab-'.$key.'"', $html);
        }

        $this->assertSame(3, substr_count($html, 'role="tabpanel"'));
        $this->assertStringContainsString('@keydown.arrow-right.prevent', $html);
        $this->assertStringContainsString('@keydown.arrow-left.prevent', $html);
    }

    /**
     * D4: the wave attribution is a self-describing config string; the template
     * must not prepend another "Sumber: ".
     */
    public function test_wave_attribution_is_not_double_prefixed(): void
    {
        $this->singleParepareConfig();

        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [[
                    'lokasi' => [
                        'adm1' => '73', 'adm2' => '73.72', 'adm3' => '73.72.02', 'adm4' => '73.72.02.1001',
                        'provinsi' => 'Sulawesi Selatan', 'kotkab' => 'Kota Parepare',
                        'kecamatan' => 'Ujung', 'desa' => 'Labukkang',
                        'lon' => 119.6249880584, 'lat' => -4.017522847, 'timezone' => '+0800', 'type' => 'adm4',
                    ],
                    'cuaca' => [[$this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0)]],
                ]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response($this->realMarineFixture(), 200),
        ]);

        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Kondisi Laut', $html);
        $this->assertStringContainsString('Sumber data gelombang laut: BMKG', $html);
        $this->assertStringNotContainsString('Sumber: Sumber', $html);
    }

    /**
     * D5: coordinates render as a magnitude with a hemisphere label, never as a
     * negative value next to "LS"/"BB".
     */
    public function test_coordinates_render_as_magnitude_with_hemisphere(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertStringContainsString('° LS', $html);
        $this->assertStringContainsString('° BT', $html);
        $this->assertStringNotContainsString('-4.0175228° LS', $html);
    }

    /**
     * D6: secondary text uses an AA-contrast token; the old slate-400 token is
     * gone from the weather surface.
     */
    public function test_secondary_text_uses_an_accessible_contrast_token(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertStringContainsString('text-[#64748B]', $html);
        $this->assertStringNotContainsString('text-[#94A3B8]', $html);
    }

    /**
     * D7: the tab panels carry `x-cloak` (no flash before Alpine boots) with a
     * `<noscript>` override so the no-JS fallback still shows every section.
     */
    public function test_tab_panels_use_x_cloak_with_a_noscript_fallback(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'x-cloak id="weather-panel-'));
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString('[x-cloak]{display:block !important;}', $html);
    }

    /**
     * D8: the PHP version banner must not be exposed.
     */
    public function test_x_powered_by_header_is_removed(): void
    {
        $response = $this->get(route('weather.index'))->assertOk();

        $this->assertFalse(
            $response->headers->has('X-Powered-By'),
            'the X-Powered-By header must not be exposed',
        );
    }

    /**
     * Regression: the tab Alpine scope MUST live INSIDE `#main-content`.
     *
     * The progressive-navigation layer (Prompt 26) swaps only `#main-content`'s
     * innerHTML. If the `x-data="{ tab: ... }"` wrapper sat OUTSIDE
     * `#main-content`, reaching `/cuaca` through that layer would inject the tab
     * buttons without their scope and Alpine would throw "tab is not defined".
     */
    public function test_tab_alpine_scope_lives_inside_main_content(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $mainPos = strpos($html, 'id="main-content"');
        $scopePos = strpos($html, 'x-data="{ tab:');

        $this->assertNotFalse($mainPos, '#main-content must be rendered');
        $this->assertNotFalse($scopePos, 'the tab Alpine scope must be rendered');
        $this->assertGreaterThan(
            $mainPos,
            $scopePos,
            'the tab Alpine scope must be INSIDE #main-content so it survives the nav layer swap',
        );

        // The header clock is self-contained (its own scope), so it never depends
        // on an ancestor that the nav layer would drop.
        $this->assertStringContainsString('x-data="{ now: new Date() }"', $html);
    }

    public function test_public_page_offers_windy_overlay_layers(): void
    {
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        // The layer control binds the Windy `overlay` parameter; every label is
        // present, and the embed iframe is still rendered (O-8).
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('Lapisan peta')
            ->assertSee('Angin')
            ->assertSee('Hujan')
            ->assertSee('Awan')
            ->assertSee('Suhu')
            ->assertSee('Gelombang')
            ->assertSee('embed.windy.com')
            ->assertSee("overlay=' + overlay", false);
    }

    public function test_charts_are_derived_from_real_stored_aggregates_only(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // Two land days (with rain) + one wave day, all real stored slots.
        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'Kota Parepare'], 'cuaca' => [[
                    $this->slot('2026-10-13 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 1.5),
                    $this->slot('2026-10-13 12:00:00', 32.0, 60, 20.0, 'Barat', 'Cerah', 2.5),
                    $this->slot('2026-10-14 09:00:00', 28.0, 80, 15.0, 'Timur', 'Hujan', 4.0),
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response([
                'code' => 'P.AE.01', 'name' => 'Perairan Pinrang',
                'issued' => '2026-10-12 12:00 UTC',
                'valid_from' => '2026-10-13 00:00 UTC', 'valid_to' => '2026-10-16 00:00 UTC',
                'forecast_day1' => [$this->marineSlot('2026-10-13 00:00 UTC', 0.8)],
                'forecast_day2-4' => [],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('super-admin.weather.sync'))->assertRedirect();

        $charts = app(\App\Services\Weather\WeatherDisplayService::class)
            ->snapshot('parepare')['charts'];

        $byKey = collect($charts)->keyBy('key');

        // Temperature max per day = real stored max (32 on day 1, 28 on day 2).
        $this->assertSame([32.0, 28.0], array_column($byKey['temp_max']['points'], 'value'));
        // Wind max per day = real stored max (20, then 15).
        $this->assertSame([20.0, 15.0], array_column($byKey['wind_max']['points'], 'value'));
        // Rain total per day = honest sum (1.5 + 2.5 = 4.0; then 4.0).
        $this->assertSame([4.0, 4.0], array_column($byKey['rain_total']['points'], 'value'));
        // Wave chart only exists when wave data exists; value = daily max (0.8).
        $this->assertSame([0.8], array_column($byKey['wave_max']['points'], 'value'));

        // Still cache-safe: no Carbon/plain objects in the chart payload.
        $this->assertNoPlainObjects($charts);
    }

    public function test_charts_use_null_gaps_and_never_fabricate_zero(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        // A day whose slots carry NO rain value (`tp` absent) must yield a NULL
        // total (a chart gap), never a fabricated 0.
        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [['lokasi' => ['kotkab' => 'Kota Parepare'], 'cuaca' => [[
                    ['local_datetime' => '2026-10-13 09:00:00', 't' => 30.0, 'hu' => 70, 'ws' => 10.0,
                     'wd' => 'Selatan', 'weather' => 1, 'weather_desc' => 'Berawan', 'analysis_date' => '2026-10-12T12:00:00'],
                ]]]],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response(['message' => 'none'], 404),
        ]);

        $this->actingAs($admin)->post(route('super-admin.weather.sync'))->assertRedirect();

        $charts = app(\App\Services\Weather\WeatherDisplayService::class)
            ->snapshot('parepare')['charts'];

        $rain = collect($charts)->firstWhere('key', 'rain_total');

        $this->assertNotNull($rain);
        $this->assertSame([null], array_column($rain['points'], 'value'));
        // No wave chart when there is no wave data at all.
        $this->assertNull(collect($charts)->firstWhere('key', 'wave_max'));
    }

    public function test_charts_section_renders_only_when_data_exists(): void
    {
        // No data at all → the page renders honestly and shows no chart titles.
        $this->get(route('weather.index'))
            ->assertOk()
            ->assertSee('belum tersedia')
            ->assertDontSee('Suhu maksimum harian')
            ->assertDontSee('Curah hujan harian');
    }

    // ── Micro-interactions & smooth transitions (owner request) ──────────────

    /**
     * The page applies the page-scoped motion hooks. These are the class names
     * the CSS transitions key off; losing them silently disables the polish.
     */
    public function test_weather_page_applies_micro_interaction_classes(): void
    {
        $this->singleParepareConfig();
        Http::fake([
            'api.bmkg.go.id/*' => Http::response($this->realFixture(), 200),
            'maritim.bmkg.go.id/*' => Http::response($this->realMarineFixture(), 200),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        foreach ([
            'wx-header',    // sticky header scroll elevation
            'wx-live-dot',  // pulsing WITA clock indicator
            'wx-card',      // lift-on-hover surface
            'wx-press',     // tactile press on chips/buttons
            'wx-tab',       // selected-tab glow
            'wx-panel',     // panel switch fade/slide
            'wx-tile',      // stat-tile hover
            'wx-sev-dot',   // early-warning step pulse
            'wx-slot',      // per-3-hour row tint
        ] as $class) {
            $this->assertStringContainsString($class, $html, "expected motion hook: {$class}");
        }
    }

    /**
     * Charts carry the reveal hooks (line draw, bar grow, dot pop). They only
     * render when real aggregate data exists.
     */
    public function test_chart_svg_carries_reveal_animation_hooks(): void
    {
        $this->singleParepareConfig();
        Http::fake([
            'api.bmkg.go.id/*' => Http::response($this->realFixture(), 200),
            'maritim.bmkg.go.id/*' => Http::response($this->realMarineFixture(), 200),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertStringContainsString('wx-chart-area', $html);
        $this->assertStringContainsString('wx-chart-line', $html);
        $this->assertStringContainsString('wx-chart-dot', $html);
    }

    /**
     * Every motion hook must be DEFINED in the stylesheet, and the whole set
     * must remain inside the global `prefers-reduced-motion` guard so the
     * accessibility contract (design.md §5 / rules.md §6) is never broken.
     */
    public function test_motion_hooks_are_defined_and_respect_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        foreach ([
            '.wx-card', '.wx-press', '.wx-tab', '.wx-panel', '.wx-tile',
            '.wx-header', '.wx-live-dot', '.wx-sev-dot', '.wx-slot',
            '.wx-chart-area', '.wx-chart-line', '.wx-chart-bar', '.wx-chart-dot',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css, "motion selector not defined: {$selector}");
        }

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString('transition-duration:      0.01ms !important', $css);
        $this->assertStringContainsString('animation-duration:       0.01ms !important', $css);
        // Delays must be neutralised too, otherwise the staggered chart dots
        // still pop in late for reduced-motion users.
        $this->assertStringContainsString('animation-delay:          0ms    !important', $css);
        $this->assertStringContainsString('transition-delay:         0ms    !important', $css);
    }
}
