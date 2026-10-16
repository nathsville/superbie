<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
/**
 * BmkgClient — fetches and normalises the BMKG `prakiraan-cuaca` response.
 *
 * VERIFICATION STATUS (2026-10-15): the endpoint, the `adm4` query parameter,
 * and the payload shape below were VERIFIED against a live BMKG response.
 * Confirmed structure:
 *
 *   {
 *     "data": [ {
 *       "lokasi": { adm1..adm4, provinsi, kotkab, kecamatan, desa, lon, lat, timezone },
 *       "cuaca":  [ [ slot, ... ], [ slot, ... ], [ slot, ... ] ]   // 3 groups
 *     } ]
 *   }
 *
 * Each slot carries: datetime, t, tcc, tp, weather, weather_desc,
 * weather_desc_en, wd_deg, wd, wd_to, ws, hu, vs, vs_text, time_index,
 * analysis_date, image, utc_datetime, local_datetime.
 *
 * `local_datetime` is the local (WITA) time and is what we store. `tp` is
 * precipitation in mm per 3-hourly slot (owner decision O-14a).
 *
 * The parser stays DEFENSIVE: a missing field becomes NULL (never 0, never
 * synthetic) so the display layer can render "Data tidak tersedia" honestly.
 * This class performs NO persistence.
 */
class BmkgClient
{
    /**
     * Fetch the forecast series.
     *
     * @param  string|null  $adm4  BMKG adm4 code to fetch. When NULL the
     *                             configured default location is used.
     * @return array{ok:bool, error:?string, location:?array<string,mixed>, slots:list<array<string,mixed>>}
     */
    public function fetch(?string $adm4 = null): array
    {
        $adm4 ??= $this->defaultAdm4();

        if (! is_string($adm4) || $adm4 === '') {
            return ['ok' => false, 'error' => 'missing_adm4', 'location' => null, 'slots' => []];
        }

        $url = (string) config('weather.bmkg.base_url');
        $timeout = (int) config('weather.bmkg.timeout', 10);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, ['adm4' => $adm4]);
        } catch (Throwable $e) {
            Log::warning('WINDY: BMKG request threw', ['message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'exception', 'location' => null, 'slots' => []];
        }

        if (! $response->successful()) {
            Log::warning('WINDY: BMKG request failed', ['status' => $response->status()]);

            return ['ok' => false, 'error' => 'http_'.$response->status(), 'location' => null, 'slots' => []];
        }

        $json = $response->json();
        $slots = $this->normalize($json);

        if ($slots === []) {
            return ['ok' => false, 'error' => 'empty_response', 'location' => null, 'slots' => []];
        }

        return [
            'ok' => true,
            'error' => null,
            'location' => $this->normalizeLocation($json),
            'slots' => $slots,
        ];
    }

    /**
     * Extract the `lokasi` metadata block (province/city/district/village/coords).
     *
     * @return array<string,mixed>|null
     */
    public function normalizeLocation(mixed $json): ?array
    {
        $lokasi = $json['data'][0]['lokasi'] ?? null;

        if (! is_array($lokasi)) {
            return null;
        }

        return [
            'province' => $this->toText($lokasi['provinsi'] ?? null),
            'city' => $this->toText($lokasi['kotkab'] ?? null),
            'district' => $this->toText($lokasi['kecamatan'] ?? null),
            'village' => $this->toText($lokasi['desa'] ?? null),
            'latitude' => $this->toFloat($lokasi['lat'] ?? null),
            'longitude' => $this->toFloat($lokasi['lon'] ?? null),
        ];
    }

    /**
     * Flatten + normalise the raw BMKG JSON into a list of forecast slots.
     *
     * @return list<array<string,mixed>>
     */
    public function normalize(mixed $json): array
    {
        $raw = $json['data'][0]['cuaca'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        // The series is nested as cuaca[groupIndex][slotIndex]. Flatten it.
        $items = [];
        foreach ($raw as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        $byTime = [];

        foreach ($items as $item) {
            // Prefer local (WITA) time; fall back to utc/datetime defensively.
            $at = $item['local_datetime']
                ?? $item['utc_datetime']
                ?? $item['datetime']
                ?? null;

            if (! is_string($at) || $at === '') {
                continue;
            }

            try {
                $forecastAt = Carbon::parse($at);
            } catch (Throwable) {
                continue;
            }

            $analysisDate = null;
            if (isset($item['analysis_date']) && is_string($item['analysis_date']) && $item['analysis_date'] !== '') {
                try {
                    $analysisDate = Carbon::parse($item['analysis_date']);
                } catch (Throwable) {
                    $analysisDate = null;
                }
            }

            // Keyed by timestamp so duplicate slots collapse deterministically.
            $byTime[$forecastAt->format('Y-m-d H:i:s')] = [
                'forecast_at' => $forecastAt,
                'temperature_c' => $this->toFloat($item['t'] ?? null),
                'humidity_pct' => $this->toInt($item['hu'] ?? null),
                'wind_speed_kmh' => $this->toFloat($item['ws'] ?? null),
                'wind_dir' => $this->toText($item['wd'] ?? null),
                'wind_dir_deg' => $this->toInt($item['wd_deg'] ?? null),
                'wind_dir_to' => $this->toText($item['wd_to'] ?? null),
                // `tp` = precipitation, mm per 3-hourly slot (VERIFIED).
                'rain_mm' => $this->toFloat($item['tp'] ?? null),
                'cloud_cover_pct' => $this->toInt($item['tcc'] ?? null),
                'weather_code' => $this->toInt($item['weather'] ?? null),
                'weather_desc' => $this->toText($item['weather_desc'] ?? ($item['weather_desc_en'] ?? null)),
                'visibility_text' => $this->toText($item['vs_text'] ?? null),
                'analysis_date' => $analysisDate,
            ];
        }

        ksort($byTime);

        return array_values($byTime);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function toText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * The adm4 code of the configured default location (multi-location aware).
     */
    private function defaultAdm4(): ?string
    {
        $key = app(WeatherLocationRegistry::class)->defaultKey();

        return $key !== null ? app(WeatherLocationRegistry::class)->find($key)['adm4'] : null;
    }
}
