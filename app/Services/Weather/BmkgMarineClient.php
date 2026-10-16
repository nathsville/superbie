<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BmkgMarineClient — fetches and normalises the BMKG maritime (wave) forecast.
 *
 * VERIFICATION STATUS (2026-10-15): VERIFIED against the live BMKG feed across
 * ALL 375 sea areas listed in `meta/area_province.json` (38 provinces, both the
 * `P.*` perairan family and the `M.*` laut family). Every area returned HTTP
 * 200 with a parseable body.
 *
 *   GET {base_url}/perairan/{KODE}.json
 *
 * Confirmed payload shape:
 *   {
 *     "code": "P.AE.01", "name": "Perairan Pinrang",
 *     "issued": "2026-10-12 12:00 UTC",
 *     "valid_from": "2026-10-13 00:00 UTC", "valid_to": "2026-10-16 00:00 UTC",
 *     "forecast_day1":  [ { time, weather, temp_avg, rh_avg, wind_from,
 *                           wind_speed, wind_gust, wave_cat, wave_height,
 *                           current_to, current_speed }, ... ],   // 24 hourly
 *     "forecast_day2-4":[ { ... same fields ... }, ... ]           // 17 x 3-hourly
 *   }
 *
 * REAL VARIATIONS found across the 375 areas (the parser tolerates each):
 *   - 1 area (P.AF.05) omits the station block entirely (only 7 top-level keys)
 *     → the station fields are optional and never required.
 *   - 12 areas (all P.J.*) publish `wind_gust: null` for every slot
 *     → a NULL gust is stored as NULL, never coerced to 0.
 *   - Slot counts are uniform: 24 (day1) + 17 (day2-4) = 41 per area.
 *   - `forecast_day*` groups are discovered DYNAMICALLY (any top-level key
 *     starting with `forecast_`), so an added/renamed day group is still read.
 *
 * Notes:
 *   - Source times are UTC strings ending in " UTC"; they are parsed and
 *     converted to the application timezone (WITA, owner decision O-15).
 *   - Wind speeds are KNOTS (marine feed unit) and kept as such.
 *   - `wave_height` is the significant wave height in metres.
 *
 * This class performs NO persistence and stays DEFENSIVE: missing fields become
 * NULL so the display can render "Data tidak tersedia" honestly.
 */
class BmkgMarineClient
{
    /**
     * Fetch a sea-area wave forecast.
     *
     * @param  string|null  $areaCode  BMKG sea-area code (e.g. "P.AE.01"). When
     *                                 NULL the default location's configured
     *                                 area is used.
     * @return array{ok:bool, error:?string, area:?array<string,mixed>, slots:list<array<string,mixed>>}
     */
    public function fetch(?string $areaCode = null): array
    {
        if (! (bool) config('weather.marine.enabled')) {
            return ['ok' => false, 'error' => 'disabled', 'area' => null, 'slots' => []];
        }

        $code = $areaCode ?? $this->defaultAreaCode();

        if (! is_string($code) || $code === '') {
            return ['ok' => false, 'error' => 'missing_area_code', 'area' => null, 'slots' => []];
        }

        $base = rtrim((string) config('weather.marine.base_url'), '/');
        $url = $base.'/perairan/'.rawurlencode($code).'.json';
        $timeout = (int) config('weather.marine.timeout', 10);

        try {
            $response = Http::timeout($timeout)->acceptJson()->get($url);
        } catch (Throwable $e) {
            Log::warning('WINDY: BMKG marine request threw', ['message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'exception', 'area' => null, 'slots' => []];
        }

        if (! $response->successful()) {
            Log::warning('WINDY: BMKG marine request failed', ['status' => $response->status()]);

            return ['ok' => false, 'error' => 'http_'.$response->status(), 'area' => null, 'slots' => []];
        }

        $json = $response->json();
        $slots = $this->normalize($json);

        if ($slots === []) {
            return ['ok' => false, 'error' => 'empty_response', 'area' => null, 'slots' => []];
        }

        return [
            'ok' => true,
            'error' => null,
            'area' => $this->normalizeArea($json),
            'slots' => $slots,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function normalizeArea(mixed $json): ?array
    {
        if (! is_array($json)) {
            return null;
        }

        // `code` is not required: every observed area carries it, but the caller
        // already knows the requested code and falls back to it. Requiring it
        // would needlessly discard usable metadata for a slightly-off payload.
        return [
            'code' => $this->toText($json['code'] ?? null),
            'name' => $this->toText($json['name'] ?? null),
            'issued_at' => $this->parseUtc($json['issued'] ?? null),
            'valid_from' => $this->parseUtc($json['valid_from'] ?? null),
            'valid_to' => $this->parseUtc($json['valid_to'] ?? null),
        ];
    }

    /**
     * Flatten every `forecast_*` group into one normalised list.
     *
     * The day groups are DISCOVERED dynamically (any top-level key beginning
     * with `forecast_`) rather than hard-coded to `forecast_day1`/`day2-4`, so a
     * new or renamed group is still read. Slots are de-duplicated by time and
     * sorted ascending.
     *
     * @return list<array<string,mixed>>
     */
    public function normalize(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $items = [];

        foreach ($json as $key => $group) {
            if (! is_string($key) || ! str_starts_with($key, 'forecast_')) {
                continue;
            }

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
            $at = $this->parseUtc($item['time'] ?? null);

            if ($at === null) {
                continue;
            }

            $byTime[$at->format('Y-m-d H:i:s')] = [
                'forecast_at' => $at,
                'wave_height_m' => $this->toFloat($item['wave_height'] ?? null),
                'wave_cat' => $this->toText($item['wave_cat'] ?? null),
                'wind_from' => $this->toText($item['wind_from'] ?? null),
                'wind_speed_kt' => $this->toFloat($item['wind_speed'] ?? null),
                'wind_gust_kt' => $this->toFloat($item['wind_gust'] ?? null),
                'current_to' => $this->toText($item['current_to'] ?? null),
                'current_speed_kt' => $this->toFloat($item['current_speed'] ?? null),
                'weather_desc' => $this->toText($item['weather'] ?? null),
                'temp_avg_c' => $this->toInt($item['temp_avg'] ?? null),
                'rh_avg_pct' => $this->toInt($item['rh_avg'] ?? null),
                'visibility_km' => $this->toInt($item['visibility'] ?? null),
            ];
        }

        ksort($byTime);

        return array_values($byTime);
    }

    /**
     * Parse a BMKG timestamp into a Carbon instance in the application timezone
     * (WITA, owner decision O-15).
     *
     * Tolerates the variations the feed actually uses, plus plausible ones:
     *   - "2026-10-13 00:00 UTC"   (the observed canonical form)
     *   - "2026-10-13T00:00:00Z"   (ISO-8601 with a Z marker)
     *   - "2026-10-13T00:00:00+00:00" (ISO-8601 with an explicit offset)
     *   - "2026-10-13 00:00"       (no zone marker → assumed UTC, as the feed is)
     */
    private function parseUtc(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $clean = trim($value);

        // Strip a trailing " UTC" / "UTC" marker; the feed is UTC by contract.
        $clean = preg_replace('/\s*UTC\s*$/i', '', $clean);

        // ISO-8601 uses a "T" separator; Carbon accepts both, but normalising
        // keeps behaviour identical across forms.
        $clean = str_replace('T', ' ', (string) $clean);

        try {
            // A trailing Z or explicit offset already carries the zone; a bare
            // datetime is interpreted as UTC (the feed's contract).
            return Carbon::parse($clean, 'UTC')->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Coerce a value to float, tolerating numeric strings and a comma decimal
     * separator (e.g. "0,8"). Returns NULL for anything non-numeric so a bad
     * value is never silently turned into 0.
     */
    private function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $clean = trim($value);

        if ($clean === '') {
            return null;
        }

        // Accept a comma as the decimal separator when there is no dot.
        if (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Coerce a value to int, tolerating numeric strings. Returns NULL when the
     * value is not numeric (never coerced to 0).
     */
    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $clean = trim($value);

        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (int) $clean : null;
    }

    private function toText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * The marine area code of the configured default location.
     */
    private function defaultAreaCode(): ?string
    {
        $registry = app(WeatherLocationRegistry::class);
        $key = $registry->defaultKey();

        return $key !== null ? ($registry->find($key)['marine_area_code'] ?? null) : null;
    }
}
