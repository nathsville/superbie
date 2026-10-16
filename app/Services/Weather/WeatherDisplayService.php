<?php

namespace App\Services\Weather;

use App\Models\WaveForecast;
use App\Models\WeatherForecast;
use App\Models\WeatherLocation;
use App\Models\WeatherSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * WeatherDisplayService — the READ side of the WINDY feature.
 *
 * MULTI-LOCATION: every snapshot is built for ONE location (resolved by its
 * stable `key`). Land rows are filtered by `weather_location_id`, and wave rows
 * by that location's `marine_area_code`, so two locations never share or leak
 * data between each other. Each snapshot is cached under its own key.
 *
 * Everything here is DERIVED from stored forecast rows; nothing is invented.
 *   - "current"  = the forecast slot nearest to now (explicitly labelled as a
 *                  forecast slot, NOT an observation — the BMKG source provides
 *                  no observations).
 *   - "forecast" = stored slots grouped into up to `forecast_days` days, each
 *                  reduced by honest aggregation (min/max/sum) that is skipped
 *                  when the underlying values are NULL.
 *
 * CACHE SAFETY: the snapshot is cached (config `weather.cache_ttl_seconds`) and
 * must survive ANY cache store. Datetimes are therefore emitted as ISO-8601
 * STRINGS, never as Carbon/objects, so a serialising store (file/database) can
 * never hand back an incomplete object. The view parses them back when needed.
 */
class WeatherDisplayService
{
    public function __construct(
        private readonly RiskEvaluationService $risk,
        private readonly WeatherLocationRegistry $registry,
    ) {}

    /**
     * Public location picker: the configured locations, each flagged with
     * whether it already has stored data.
     *
     * @return list<array{key:string, name:string, is_default:bool, has_data:bool}>
     */
    public function locations(): array
    {
        $ttl = (int) config('weather.cache_ttl_seconds', 1800);

        return Cache::remember($this->registry->indexCacheKey(), $ttl, function (): array {
            $source = WeatherSource::query()->where('code', 'bmkg')->first();
            $defaultKey = $this->registry->defaultKey();

            $result = [];

            foreach ($this->registry->all() as $definition) {
                $hasData = false;

                if ($source !== null) {
                    $hasData = WeatherForecast::query()
                        ->where('weather_source_id', $source->id)
                        ->whereHas('location', fn ($q) => $q->where('key', $definition['key']))
                        ->exists();
                }

                $result[] = [
                    'key' => $definition['key'],
                    'name' => $definition['name'],
                    'is_default' => $definition['key'] === $defaultKey,
                    'has_data' => $hasData,
                ];
            }

            return $result;
        });
    }

    /**
     * Full display snapshot for the public page, for ONE location.
     *
     * @param  string|null  $key  Location key; NULL uses the configured default.
     * @return array{
     *     available: bool,
     *     reason: ?string,
     *     location_key: ?string,
     *     location: ?array<string,mixed>,
     *     attribution: ?string,
     *     generated_at: ?Carbon,
     *     current: ?array<string,mixed>,
     *     wave: array<string,mixed>,
     *     wave_forecast: array{available:bool, area_name:?string, days:list<array<string,mixed>>},
     *     forecast: list<array<string,mixed>>,
     *     charts: array<string,mixed>,
     *     risk: ?array<string,mixed>,
     *     embed: array{enabled:bool, url:string}
     * }
     */
    public function snapshot(?string $key = null): array
    {
        $key ??= $this->registry->defaultKey();

        if ($key === null) {
            return $this->empty(null, 'Data cuaca belum tersedia. Sinkronisasi belum pernah dijalankan.');
        }

        $definition = $this->registry->find($key);

        if ($definition === null) {
            return $this->empty($key, 'Lokasi pantau tidak dikenal.');
        }

        $ttl = (int) config('weather.cache_ttl_seconds', 1800);

        return Cache::remember(
            $this->registry->cacheKey($key),
            $ttl,
            fn (): array => $this->build($definition),
        );
    }

    /**
     * @param  array{key:string, name:string, adm4:string, latitude:?string, longitude:?string, marine_area_code:?string, is_default:bool}  $definition
     * @return array<string,mixed>
     */
    private function build(array $definition): array
    {
        $embed = [
            'enabled' => (bool) config('weather.embed.enabled'),
            'url' => (string) config('weather.embed.url'),
        ];

        $source = WeatherSource::query()->where('code', 'bmkg')->first();

        if ($source === null) {
            return $this->empty($definition['key'], 'Data cuaca belum tersedia. Sinkronisasi belum pernah dijalankan.');
        }

        $locationModel = WeatherLocation::query()->where('key', $definition['key'])->first();

        if ($locationModel === null) {
            return $this->empty($definition['key'], 'Data cuaca belum tersedia. Sinkronisasi belum pernah dijalankan.');
        }

        $slots = WeatherForecast::query()
            ->with('location')
            ->where('weather_source_id', $source->id)
            ->where('weather_location_id', $locationModel->id)
            ->orderBy('forecast_at')
            ->get();

        if ($slots->isEmpty()) {
            return $this->empty($definition['key'], 'Data cuaca belum tersedia. Sinkronisasi belum pernah dijalankan.');
        }

        $current = $this->currentSlot($slots);

        $wave = $this->currentWave($source->id, $definition['marine_area_code']);

        $waveForecast = $this->dailyWave($source->id, $definition['marine_area_code']);

        $forecast = $this->dailyForecast($slots);

        $charts = $this->charts($forecast, $waveForecast['days']);

        $risk = $this->risk->evaluate(
            $current['rain_mm'] !== null ? (float) $current['rain_mm'] : null,
            $current['wind_speed_kmh'] !== null ? (float) $current['wind_speed_kmh'] : null,
            $wave['wave_height_m'] !== null ? (float) $wave['wave_height_m'] : null,
        );

        $location = [
            'name' => $locationModel->name,
            'city' => $locationModel->city,
            'district' => $locationModel->district,
            'village' => $locationModel->village,
            'province' => $locationModel->province,
            'latitude' => $locationModel->latitude ?? $definition['latitude'],
            'longitude' => $locationModel->longitude ?? $definition['longitude'],
        ];

        return [
            'available' => true,
            'reason' => null,
            'location_key' => $definition['key'],
            'location' => $location,
            'attribution' => $source->attribution_text ?? (string) config('weather.attribution.bmkg'),
            'generated_at' => $slots->max('updated_at')?->toIso8601String(),
            'current' => $current,
            'wave' => $wave,
            'wave_forecast' => $waveForecast,
            'forecast' => $forecast,
            'charts' => $charts,
            'risk' => $risk,
            'embed' => $embed,
        ];
    }

    /**
     * The marine forecast slot nearest to now for ONE sea area, or an
     * "unavailable" shape when that area has no stored data.
     *
     * @return array<string,mixed>
     */
    private function currentWave(int $sourceId, ?string $areaCode): array
    {
        if ($areaCode === null) {
            return $this->emptyWave();
        }

        $slot = WaveForecast::query()
            ->where('weather_source_id', $sourceId)
            ->where('area_code', $areaCode)
            ->orderBy('forecast_at')
            ->get()
            ->sortBy(fn (WaveForecast $s): float => abs($s->forecast_at->diffInSeconds(now(), false)))
            ->first();

        if ($slot === null) {
            return $this->emptyWave();
        }

        return [
            'available' => true,
            'area_name' => $slot->area_name,
            'forecast_at' => $slot->forecast_at->toIso8601String(),
            'wave_height_m' => $slot->wave_height_m !== null ? (float) $slot->wave_height_m : null,
            'wave_cat' => $slot->wave_cat,
            'wind_speed_kt' => $slot->wind_speed_kt !== null ? (float) $slot->wind_speed_kt : null,
            'current_speed_kt' => $slot->current_speed_kt !== null ? (float) $slot->current_speed_kt : null,
            'current_to' => $slot->current_to,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyWave(): array
    {
        return [
            'available' => false,
            'area_name' => null,
            'forecast_at' => null,
            'wave_height_m' => null,
            'wave_cat' => null,
            'wind_speed_kt' => null,
            'current_speed_kt' => null,
            'current_to' => null,
        ];
    }

    /**
     * Per-day wave detail for ONE sea area, derived ONLY from stored slots.
     *
     * Days are grouped by the WITA calendar day (the app timezone), because the
     * source's day-1 hourly slots cross WITA midnight and grouping by the raw
     * UTC day would split a single WITA day in two. Everything is derived by
     * honest aggregation; NULLs are skipped rather than coerced to zero.
     *
     * @return array{available:bool, area_name:?string, days:list<array<string,mixed>>}
     */
    private function dailyWave(int $sourceId, ?string $areaCode): array
    {
        if ($areaCode === null) {
            return $this->emptyWaveForecast();
        }

        $slots = WaveForecast::query()
            ->where('weather_source_id', $sourceId)
            ->where('area_code', $areaCode)
            ->orderBy('forecast_at')
            ->get();

        if ($slots->isEmpty()) {
            return $this->emptyWaveForecast();
        }

        $days = (int) config('weather.forecast_days', 3);

        $grouped = $slots
            ->groupBy(fn (WaveForecast $slot): string => $slot->forecast_at->format('Y-m-d'))
            ->take($days)
            ->map(function (Collection $daySlots, string $date): array {
                // decimal:2 casts return STRINGS; cast to float before min/max.
                $heights = $daySlots
                    ->pluck('wave_height_m')
                    ->filter(fn ($v) => $v !== null)
                    ->map(fn ($v): float => (float) $v);

                $winds = $daySlots
                    ->pluck('wind_speed_kt')
                    ->filter(fn ($v) => $v !== null)
                    ->map(fn ($v): float => (float) $v);

                $currents = $daySlots
                    ->pluck('current_speed_kt')
                    ->filter(fn ($v) => $v !== null)
                    ->map(fn ($v): float => (float) $v);

                // Peak slot = the highest wave of the day. `wave_cat` rises
                // monotonically with `wave_height`, so the category of the
                // highest slot IS the day's worst category — no invented
                // severity ranking, just the source's own label on a real slot.
                $peak = $daySlots
                    ->filter(fn (WaveForecast $s) => $s->wave_height_m !== null)
                    ->sortBy(fn (WaveForecast $s): float => (float) $s->wave_height_m)
                    ->last();

                return [
                    'date' => $date,
                    'wave_min_m' => $heights->isNotEmpty() ? (float) $heights->min() : null,
                    'wave_max_m' => $heights->isNotEmpty() ? (float) $heights->max() : null,
                    'wave_cat' => $peak?->wave_cat,
                    'wind_max_kt' => $winds->isNotEmpty() ? (float) $winds->max() : null,
                    'current_max_kt' => $currents->isNotEmpty() ? (float) $currents->max() : null,
                    'slots' => $daySlots->map(fn (WaveForecast $s): array => [
                        'time' => $s->forecast_at->format('H:i'),
                        'wave_height_m' => $s->wave_height_m !== null ? (float) $s->wave_height_m : null,
                        'wave_cat' => $s->wave_cat,
                        'wind_speed_kt' => $s->wind_speed_kt !== null ? (float) $s->wind_speed_kt : null,
                        'current_speed_kt' => $s->current_speed_kt !== null ? (float) $s->current_speed_kt : null,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'available' => true,
            'area_name' => $slots->first()?->area_name,
            'days' => $grouped,
        ];
    }

    /**
     * @return array{available:bool, area_name:?string, days:list<array<string,mixed>>}
     */
    private function emptyWaveForecast(): array
    {
        return [
            'available' => false,
            'area_name' => null,
            'days' => [],
        ];
    }

    /**
     * The slot nearest to "now" (by absolute time difference).
     *
     * @param  Collection<int, WeatherForecast>  $slots
     * @return array<string,mixed>
     */
    private function currentSlot(Collection $slots): array
    {
        $now = now();

        $nearest = $slots->sortBy(
            fn (WeatherForecast $slot): float => abs($slot->forecast_at->diffInSeconds($now, false))
        )->first();

        return [
            'forecast_at' => $nearest->forecast_at->toIso8601String(),
            'temperature_c' => $nearest->temperature_c,
            'humidity_pct' => $nearest->humidity_pct,
            'wind_speed_kmh' => $nearest->wind_speed_kmh,
            'wind_dir' => $nearest->wind_dir,
            'wind_dir_deg' => $nearest->wind_dir_deg,
            'wind_dir_to' => $nearest->wind_dir_to,
            'rain_mm' => $nearest->rain_mm,
            'cloud_cover_pct' => $nearest->cloud_cover_pct,
            'weather_code' => $nearest->weather_code,
            'weather_desc' => $nearest->weather_desc,
            'visibility_text' => $nearest->visibility_text,
        ];
    }

    /**
     * Group stored slots into up to `forecast_days` calendar days (WITA).
     *
     * @param  Collection<int, WeatherForecast>  $slots
     * @return list<array<string,mixed>>
     */
    private function dailyForecast(Collection $slots): array
    {
        $days = (int) config('weather.forecast_days', 3);

        return $slots
            ->groupBy(fn (WeatherForecast $slot): string => $slot->forecast_at->format('Y-m-d'))
            ->take($days)
            ->map(function (Collection $daySlots, string $date): array {
                $temps = $daySlots->pluck('temperature_c')->filter(fn ($v) => $v !== null);
                $winds = $daySlots->pluck('wind_speed_kmh')->filter(fn ($v) => $v !== null);
                $rains = $daySlots->pluck('rain_mm')->filter(fn ($v) => $v !== null);

                // Description nearest to midday, if any.
                $midday = $daySlots
                    ->filter(fn (WeatherForecast $s) => $s->weather_desc !== null)
                    ->sortBy(fn (WeatherForecast $s) => abs($s->forecast_at->diffInSeconds(
                        Carbon::parse($date.' 12:00:00')
                    )))
                    ->first();

                return [
                    'date' => $date,
                    'temp_min' => $temps->isNotEmpty() ? (float) $temps->min() : null,
                    'temp_max' => $temps->isNotEmpty() ? (float) $temps->max() : null,
                    'wind_max_kmh' => $winds->isNotEmpty() ? (float) $winds->max() : null,
                    // Rain summed only when at least one value exists; NULL if
                    // the source never provided rain (never coerced to 0).
                    'rain_total_mm' => $rains->isNotEmpty() ? (float) $rains->sum() : null,
                    'description' => $midday?->weather_desc,
                    'slots' => $daySlots->map(fn (WeatherForecast $s): array => [
                        'time' => $s->forecast_at->format('H:i'),
                        'temperature_c' => $s->temperature_c !== null ? (float) $s->temperature_c : null,
                        'humidity_pct' => $s->humidity_pct,
                        'wind_speed_kmh' => $s->wind_speed_kmh !== null ? (float) $s->wind_speed_kmh : null,
                        'wind_dir' => $s->wind_dir,
                        'rain_mm' => $s->rain_mm !== null ? (float) $s->rain_mm : null,
                        'weather_desc' => $s->weather_desc,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Chart series for the public page, derived ONLY from the aggregated days.
     *
     * Each chart is a plain list of `{label, value}` points where `value` is a
     * REAL stored/aggregated figure (or NULL → rendered as a gap, never 0). No
     * smoothing, no interpolation, no forecast beyond the stored horizon.
     *
     * @param  list<array<string,mixed>>  $forecast   Output of dailyForecast().
     * @param  list<array<string,mixed>>  $waveDays   Output of dailyWave()['days'].
     * @return list<array{key:string,title:string,unit:string,tone:string,variant:string,points:list<array{label:string,value:?float}>}>
     */
    private function charts(array $forecast, array $waveDays): array
    {
        $label = static fn (string $date): string => Carbon::parse($date)->translatedFormat('D, d M');

        $temp = [];
        $wind = [];
        $rain = [];

        foreach ($forecast as $day) {
            $temp[] = ['label' => $label($day['date']), 'value' => $day['temp_max']];
            $wind[] = ['label' => $label($day['date']), 'value' => $day['wind_max_kmh']];
            $rain[] = ['label' => $label($day['date']), 'value' => $day['rain_total_mm']];
        }

        $charts = [
            ['key' => 'temp_max', 'title' => 'Suhu maksimum harian', 'unit' => '°C', 'tone' => '#E11D48', 'variant' => 'line', 'points' => $temp],
            ['key' => 'wind_max', 'title' => 'Angin maksimum harian', 'unit' => ' km/jam', 'tone' => '#013FF6', 'variant' => 'line', 'points' => $wind],
            ['key' => 'rain_total', 'title' => 'Curah hujan harian', 'unit' => ' mm', 'tone' => '#7C3AED', 'variant' => 'bar', 'points' => $rain],
        ];

        $wave = [];
        foreach ($waveDays as $day) {
            $wave[] = ['label' => $label($day['date']), 'value' => $day['wave_max_m']];
        }

        if ($wave !== []) {
            $charts[] = ['key' => 'wave_max', 'title' => 'Tinggi gelombang maksimum', 'unit' => ' m', 'tone' => '#0D9488', 'variant' => 'line', 'points' => $wave];
        }

        return $charts;
    }

    /**
     * @return array<string,mixed>
     */
    private function empty(?string $locationKey, string $reason): array
    {
        $definition = $locationKey !== null ? $this->registry->find($locationKey) : null;

        return [
            'available' => false,
            'reason' => $reason,
            'location_key' => $locationKey,
            // Even with no data yet, carry the configured coordinates so the
            // map embed still centres on the right place.
            'location' => $definition !== null ? [
                'name' => $definition['name'],
                'city' => null,
                'district' => null,
                'village' => null,
                'province' => null,
                'latitude' => $definition['latitude'],
                'longitude' => $definition['longitude'],
            ] : null,
            'attribution' => (string) config('weather.attribution.bmkg'),
            'generated_at' => null,
            'current' => null,
            'wave' => $this->emptyWave(),
            'wave_forecast' => $this->emptyWaveForecast(),
            'forecast' => [],
            'charts' => $this->charts([], []),
            'risk' => null,
            'embed' => [
                'enabled' => (bool) config('weather.embed.enabled'),
                'url' => (string) config('weather.embed.url'),
            ],
        ];
    }
}
