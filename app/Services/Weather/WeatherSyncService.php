<?php

namespace App\Services\Weather;

use App\Models\WaveForecast;
use App\Models\WeatherForecast;
use App\Models\WeatherLocation;
use App\Models\WeatherSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * WeatherSyncService — synchronisation of BMKG data into the DB.
 *
 * SCOPE (owner decisions):
 *   - Manual sync (Super Admin) + scheduled sync (D-16, command `weather:sync`).
 *   - MULTI-LOCATION: every configured location (config `weather.locations`) is
 *     synced in turn, each with its OWN adm4 code and its OWN maritime area.
 *   - Each location's land write is atomic (DB transaction). The marine (wave)
 *     fetch is BEST-EFFORT per location: if it fails, that location's land
 *     forecast still commits and its wave renders "Data tidak tersedia" — the
 *     sync as a whole still succeeds.
 *
 * VERIFICATION STATUS: concurrency uses `Cache::lock()`. The default cache
 * store is `database` (config/cache.php) whose DatabaseStore supports `lock()`
 * with a `cache_locks` table that exists in this repo. The locking path is
 * exercised by the test suite and a live smoke run; sustained concurrent load
 * is still not verified.
 */
class WeatherSyncService
{
    public function __construct(
        private readonly BmkgClient $client,
        private readonly BmkgMarineClient $marineClient,
        private readonly WeatherLocationRegistry $registry,
    ) {}

    /**
     * Run a sync across every configured location.
     *
     * @return array{ok:bool, status:string, message:string, saved:int, wave_saved:int, wave_ok:bool, locations:int}
     */
    public function sync(): array
    {
        $lock = Cache::lock('weather:sync', 300);

        if (! $lock->get()) {
            return [
                'ok' => false,
                'status' => 'locked',
                'message' => 'Sinkronisasi lain sedang berjalan. Silakan coba lagi.',
                'saved' => 0,
                'wave_saved' => 0,
                'wave_ok' => false,
                'locations' => 0,
            ];
        }

        try {
            return $this->run();
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{ok:bool, status:string, message:string, saved:int, wave_saved:int, wave_ok:bool, locations:int}
     */
    private function run(): array
    {
        $locations = $this->registry->all();

        if ($locations === []) {
            return [
                'ok' => false,
                'status' => 'no_locations',
                'message' => 'Tidak ada lokasi pantau yang dikonfigurasi.',
                'saved' => 0,
                'wave_saved' => 0,
                'wave_ok' => false,
                'locations' => 0,
            ];
        }

        $source = WeatherSource::firstOrCreate(
            ['code' => 'bmkg'],
            [
                'label' => 'BMKG',
                'is_enabled' => true,
                'attribution_text' => (string) config('weather.attribution.bmkg'),
            ],
        );

        if (! $source->is_enabled) {
            return [
                'ok' => false,
                'status' => 'disabled',
                'message' => 'Sumber data BMKG sedang dinonaktifkan.',
                'saved' => 0,
                'wave_saved' => 0,
                'wave_ok' => false,
                'locations' => 0,
            ];
        }

        $totalSaved = 0;
        $totalWave = 0;
        $okCount = 0;
        $failures = [];

        // Track sea areas already synced this run so two locations sharing one
        // sea area do not fetch (or write) the same wave data twice.
        $syncedAreas = [];

        foreach ($locations as $definition) {
            $location = $this->upsertLocation($definition);

            $result = $this->client->fetch($definition['adm4']);

            if (! $result['ok']) {
                $failures[] = $definition['name'].' ('.$result['error'].')';
                continue;
            }

            $this->refreshLocationMetadata($location, $result['location']);

            $totalSaved += $this->persistLand($source, $location, $result['slots']);

            // Best-effort marine sync for this location's sea area.
            $areaCode = $definition['marine_area_code'];

            if ($areaCode !== null && ! isset($syncedAreas[$areaCode])) {
                $syncedAreas[$areaCode] = true;
                $totalWave += $this->syncWaves($source, $areaCode);
            }

            $okCount++;
        }

        // Invalidate every location snapshot + the picker index so the next read
        // reflects the sync.
        $this->forgetCaches();

        if ($okCount === 0) {
            return [
                'ok' => false,
                'status' => 'fetch_failed',
                'message' => 'Gagal mengambil data dari BMKG: '.implode('; ', $failures).'.',
                'saved' => 0,
                'wave_saved' => 0,
                'wave_ok' => false,
                'locations' => 0,
            ];
        }

        $message = "Sinkronisasi berhasil: {$okCount} lokasi, {$totalSaved} titik prakiraan darat"
            .($totalWave > 0 ? ", {$totalWave} titik prakiraan gelombang" : '')
            .' diperbarui.';

        if ($failures !== []) {
            $message .= ' Sebagian lokasi gagal: '.implode('; ', $failures).'.';
        }

        return [
            'ok' => true,
            'status' => $failures === [] ? 'success' : 'partial',
            'message' => $message,
            'saved' => $totalSaved,
            'wave_saved' => $totalWave,
            'wave_ok' => $totalWave > 0,
            'locations' => $okCount,
        ];
    }

    /**
     * Create/update the DB row for one configured location.
     *
     * Adopts a LEGACY row (created before multi-location support, so it has a
     * NULL `key`) by matching on `adm4_code`, so upgrading does not duplicate
     * the location or orphan its existing forecasts.
     *
     * @param  array{key:string, name:string, adm4:string, latitude:?string, longitude:?string, marine_area_code:?string, is_default:bool}  $definition
     */
    private function upsertLocation(array $definition): WeatherLocation
    {
        $location = WeatherLocation::query()
            ->where('key', $definition['key'])
            ->orWhere(fn ($q) => $q->whereNull('key')->where('adm4_code', $definition['adm4']))
            ->first()
            ?? new WeatherLocation();

        $location->fill([
            'key' => $definition['key'],
            'name' => $definition['name'],
            'adm4_code' => $definition['adm4'],
            'marine_area_code' => $definition['marine_area_code'],
            'latitude' => $definition['latitude'],
            'longitude' => $definition['longitude'],
            'is_active' => true,
        ])->save();

        return $location;
    }

    /**
     * Overwrite location metadata with the verified BMKG `lokasi` block.
     *
     * @param  array<string,mixed>|null  $metadata
     */
    private function refreshLocationMetadata(WeatherLocation $location, ?array $metadata): void
    {
        if (! is_array($metadata)) {
            return;
        }

        $location->fill(array_filter([
            'province' => $metadata['province'] ?? null,
            'city' => $metadata['city'] ?? null,
            'district' => $metadata['district'] ?? null,
            'village' => $metadata['village'] ?? null,
            'latitude' => $metadata['latitude'] ?? null,
            'longitude' => $metadata['longitude'] ?? null,
        ], static fn ($v) => $v !== null))->save();
    }

    /**
     * Persist one location's land forecast slots and prune stale ones.
     *
     * @param  list<array<string,mixed>>  $slots
     */
    private function persistLand(WeatherSource $source, WeatherLocation $location, array $slots): int
    {
        $issuedAt = now();
        $saved = 0;

        DB::transaction(function () use ($source, $location, $slots, $issuedAt, &$saved): void {
            $keptTimes = [];

            foreach ($slots as $slot) {
                WeatherForecast::updateOrCreate(
                    [
                        'weather_location_id' => $location->id,
                        'weather_source_id' => $source->id,
                        'forecast_at' => $slot['forecast_at'],
                    ],
                    [
                        'issued_at' => $issuedAt,
                        'temperature_c' => $slot['temperature_c'],
                        'humidity_pct' => $slot['humidity_pct'],
                        'wind_speed_kmh' => $slot['wind_speed_kmh'],
                        'wind_dir' => $slot['wind_dir'],
                        'wind_dir_deg' => $slot['wind_dir_deg'],
                        'wind_dir_to' => $slot['wind_dir_to'],
                        'rain_mm' => $slot['rain_mm'],
                        'cloud_cover_pct' => $slot['cloud_cover_pct'],
                        'weather_code' => $slot['weather_code'],
                        'weather_desc' => $slot['weather_desc'],
                        'visibility_text' => $slot['visibility_text'],
                        'analysis_date' => $slot['analysis_date'],
                    ],
                );

                $keptTimes[] = $slot['forecast_at']->format('Y-m-d H:i:s');
                $saved++;
            }

            // The fetch returns the COMPLETE published window, so any slot not in
            // this batch is stale (a previous analysis cycle) and must go, so the
            // table mirrors exactly what BMKG currently publishes.
            WeatherForecast::query()
                ->where('weather_location_id', $location->id)
                ->where('weather_source_id', $source->id)
                ->whereNotIn('forecast_at', $keptTimes)
                ->delete();
        });

        return $saved;
    }

    /**
     * Fetch + persist one sea area's wave forecast. Returns the number of slots
     * written (0 when the marine source is unavailable or disabled).
     */
    private function syncWaves(WeatherSource $source, string $areaCode): int
    {
        $marine = $this->marineClient->fetch($areaCode);

        if (! $marine['ok']) {
            return 0;
        }

        $area = $marine['area'] ?? [];
        $resolvedCode = $area['code'] ?? $areaCode;
        $areaName = $area['name'] ?? null;
        $issuedAt = now();

        $saved = 0;

        DB::transaction(function () use ($marine, $source, $area, $resolvedCode, $areaName, $issuedAt, &$saved): void {
            $keptTimes = [];

            foreach ($marine['slots'] as $slot) {
                WaveForecast::updateOrCreate(
                    [
                        'weather_source_id' => $source->id,
                        'area_code' => $resolvedCode,
                        'forecast_at' => $slot['forecast_at'],
                    ],
                    [
                        'area_name' => $areaName,
                        'issued_at' => $area['issued_at'] ?? $issuedAt,
                        'valid_from' => $area['valid_from'] ?? null,
                        'valid_to' => $area['valid_to'] ?? null,
                        'wave_height_m' => $slot['wave_height_m'],
                        'wave_cat' => $slot['wave_cat'],
                        'wind_from' => $slot['wind_from'],
                        'wind_speed_kt' => $slot['wind_speed_kt'],
                        'wind_gust_kt' => $slot['wind_gust_kt'],
                        'current_to' => $slot['current_to'],
                        'current_speed_kt' => $slot['current_speed_kt'],
                        'weather_desc' => $slot['weather_desc'],
                        'temp_avg_c' => $slot['temp_avg_c'],
                        'rh_avg_pct' => $slot['rh_avg_pct'],
                        'visibility_km' => $slot['visibility_km'],
                    ],
                );

                $keptTimes[] = $slot['forecast_at']->format('Y-m-d H:i:s');
                $saved++;
            }

            // Drop stale slots from a previous marine cycle.
            WaveForecast::query()
                ->where('weather_source_id', $source->id)
                ->where('area_code', $resolvedCode)
                ->whereNotIn('forecast_at', $keptTimes)
                ->delete();
        });

        return $saved;
    }

    /**
     * Forget every location snapshot plus the picker index.
     */
    private function forgetCaches(): void
    {
        foreach ($this->registry->all() as $location) {
            Cache::forget($this->registry->cacheKey($location['key']));
        }

        Cache::forget($this->registry->indexCacheKey());
    }
}
