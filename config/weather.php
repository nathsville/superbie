<?php

/*
|--------------------------------------------------------------------------
| WINDY Weather Configuration
|--------------------------------------------------------------------------
|
| Configuration for the migrated legacy "WINDY" weather feature. All values
| here are CONFIGURATION, not measurements. No value in this file is ever
| presented to a user as an observed reading.
|
| VERIFICATION STATUS (updated 2026-10-15):
|   - `bmkg.base_url` is VERIFIED against a live BMKG response (HTTP 200).
|   - All FIVE configured `locations[].adm4` codes are VERIFIED against live
|     BMKG responses: "Kota Parepare" (`73.72.02.1001` = Ujung / Labukkang)
|     plus the four kecamatan of Kota Parepare — Ujung (`73.72.02.1001`),
|     Soreang (`73.72.03.1001`), Bacukiki Barat (`73.72.04.1001`) and
|     Bacukiki (`73.72.01.1004`). The legacy Apps Script code
|     (`73.72.01.1001`) is INVALID (BMKG returns 404), so Bacukiki uses its
|     verified kelurahan code instead.
|   - Each `locations[].marine_area_code` is VERIFIED by point-in-polygon
|     against BMKG's own `meta/wilmetos.min.geojson`: the coastal kecamatan
|     (Ujung, Soreang) fall inside `P.AE.01` (Perairan Pinrang); the inland
|     kecamatan (Bacukiki, Bacukiki Barat) fall inside NO sea polygon and are
|     therefore configured with `marine_area_code = null`, so their wave panel
|     honestly shows "Data tidak tersedia" (never fabricated).
|   - The BMKG `prakiraan-cuaca` payload DOES include a rain field (`tp`), so
|     rain is available in mm per 3 hours (owner decision O-14a). Confirmed on
|     a real response.
|   - Threshold values remain INITIAL, NON-VALIDATED policy numbers (D-13).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Feature switch
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('WINDY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Owner decision O-15: timestamps are STORED in WITA (Asia/Makassar).
    | BMKG reports the monitored region with `timezone: "+0800"` (WITA), and the
    | application timezone is also Asia/Makassar, so parsing `local_datetime` is
    | a no-op shift.
    |
    */
    'timezone' => 'Asia/Makassar',

    /*
    |--------------------------------------------------------------------------
    | Monitored locations (multi-location capable)
    |--------------------------------------------------------------------------
    |
    | WINDY supports monitoring MORE THAN ONE location. Each entry is one
    | monitored area carrying its own BMKG `adm4` code AND its own maritime
    | (wave) sea-area code, so land and wave data are always scoped PER
    | LOCATION and never mixed between locations.
    |
    | OWNER DECISION (2026-10-15): the public may pick the monitoring area from
    | the FOUR kecamatan of Kota Parepare, plus a city-wide "Kota Parepare"
    | entry (the default). BMKG publishes forecasts only at the kelurahan level
    | (`adm4`), so each entry carries a VERIFIED representative kelurahan code —
    | there is no city-level `adm4`. Consequently "Kota Parepare" and
    | "Kecamatan Ujung" share `73.72.02.1001` and show identical data; this is
    | expected and documented (the owner accepted it).
    |
    | Adding another area is a CONFIGURATION change (append an entry) — not a
    | code change. Do NOT add a location without a VERIFIED `adm4` code and a
    | VERIFIED `marine_area_code` (a wrong code returns HTTP 404 or the wrong
    | sea area; see WINDY_IMPLEMENTATION_REPORT.md §1 for how they were
    | verified).
    |
    | `key` is a stable slug used in URLs (`/cuaca/{key}`), DB rows and cache
    | keys; it must stay stable once published. Set `is_default` = true on the
    | entry shown at `/cuaca` (if none is marked, the first entry is used).
    |
    | The env vars below customise ONLY the first (default) entry. To move the
    | default elsewhere, edit this array.
    |
    */
    'locations' => [
        [
            'key' => 'parepare',
            'name' => env('WINDY_LOCATION_NAME', 'Kota Parepare'),
            // VERIFIED — Kota Parepare / Ujung / Labukkang (representative
            // kelurahan; BMKG has no city-level adm4).
            'adm4' => env('WINDY_BMKG_ADM4', '73.72.02.1001'),
            'latitude' => env('WINDY_LATITUDE', '-4.017522847'),
            'longitude' => env('WINDY_LONGITUDE', '119.6249880584'),
            // VERIFIED by point-in-polygon against BMKG's own wilmetos geojson:
            // Parepare falls inside P.AE.01 (Perairan Pinrang).
            'marine_area_code' => env('WINDY_MARINE_AREA_CODE', 'P.AE.01'),
            'is_default' => true,
        ],
        [
            'key' => 'ujung',
            'name' => 'Kecamatan Ujung',
            // VERIFIED — Kota Parepare / Ujung / Labukkang (coastal).
            'adm4' => '73.72.02.1001',
            'latitude' => '-4.017522847',
            'longitude' => '119.6249880584',
            // VERIFIED — coastal kelurahan of Ujung fall inside P.AE.01.
            'marine_area_code' => 'P.AE.01',
            'is_default' => false,
        ],
        [
            'key' => 'soreang',
            'name' => 'Kecamatan Soreang',
            // VERIFIED — Kota Parepare / Soreang / Lakessi (coastal).
            'adm4' => '73.72.03.1001',
            'latitude' => '-4.0054611314',
            'longitude' => '119.6279045015',
            // VERIFIED — coastal kelurahan of Soreang fall inside P.AE.01.
            'marine_area_code' => 'P.AE.01',
            'is_default' => false,
        ],
        [
            'key' => 'bacukiki-barat',
            'name' => 'Kecamatan Bacukiki Barat',
            // VERIFIED — Kota Parepare / Bacukiki Barat / Kampung Baru.
            'adm4' => '73.72.04.1001',
            'latitude' => '-4.0200456241',
            'longitude' => '119.6280661752',
            // INLAND: no kelurahan point falls inside any BMKG sea polygon, so
            // there is NO sea area. `null` → the wave panel shows "Data tidak
            // tersedia" honestly (never a fabricated sea area).
            'marine_area_code' => null,
            'is_default' => false,
        ],
        [
            'key' => 'bacukiki',
            'name' => 'Kecamatan Bacukiki',
            // VERIFIED — Kota Parepare / Bacukiki / Lompoe. (The legacy code
            // `73.72.01.1001` is INVALID / 404; this kelurahan code is the
            // verified one.)
            'adm4' => '73.72.01.1004',
            'latitude' => '-4.0199906259',
            'longitude' => '119.6514036357',
            // INLAND: no sea area (see bacukiki-barat above).
            'marine_area_code' => null,
            'is_default' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | BMKG source
    |--------------------------------------------------------------------------
    |
    | The only enabled source in this slice (owner decision O-3: BMKG only, no
    | OpenWeatherMap). The BMKG `prakiraan-cuaca` endpoint returns a 3-hourly
    | forecast series; it is NOT an observation feed. The system stores FORECAST
    | rows only and never fabricates observations.
    |
    */
    'bmkg' => [
        // VERIFIED endpoint + parameter name.
        'base_url' => env('WINDY_BMKG_BASE_URL', 'https://api.bmkg.go.id/publik/prakiraan-cuaca'),
        'timeout' => (int) env('WINDY_HTTP_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Forecast window
    |--------------------------------------------------------------------------
    |
    | Owner decision D-11: 3-day forecast. BMKG already returns a ~3-day series,
    | so the full published series is stored and the display is grouped into up
    | to `forecast_days` calendar days.
    |
    */
    'forecast_days' => 3,

    /*
    |--------------------------------------------------------------------------
    | Manual sync rate limit
    |--------------------------------------------------------------------------
    */
    'sync_rate' => [
        'max_attempts' => (int) env('WINDY_SYNC_MAX_ATTEMPTS', 6),
        'decay_minutes' => (int) env('WINDY_SYNC_DECAY_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Read cache
    |--------------------------------------------------------------------------
    |
    | Public read caching (seconds). Keeps BMKG traffic and DB reads bounded.
    |
    */
    'cache_ttl_seconds' => (int) env('WINDY_CACHE_TTL', 1800),

    /*
    |--------------------------------------------------------------------------
    | Risk thresholds (INITIAL / NOT VALIDATED)
    |--------------------------------------------------------------------------
    |
    | Owner decision D-13: these are placeholder policy numbers pending official
    | validation. Rain is expressed in mm per 3 hours (owner decision O-14a),
    | matching the BMKG `tp` field's 3-hourly interval.
    |
    */
    'thresholds' => [
        'rain_waspada_mm' => 10.0,
        'rain_siaga_mm' => 20.0,
        'wind_waspada_kmh' => 25.0,
        'wind_siaga_kmh' => 40.0,
        // Wave (metres). Boundaries ALIGNED WITH BMKG's own published wave
        // categories. VERIFIED 2026-10-15 against 15,375 real observations from
        // ALL 375 sea areas: BMKG's category boundaries fall at 1.25 m and 2.5 m
        // (Tenang 0.1-0.4, Rendah 0.5-1.2, Sedang 1.3-2.4, Tinggi 2.5-3.9,
        // Sangat Tinggi 4.0-4.5). The Waspada/Siaga cut points therefore match
        // BMKG exactly and are not arbitrary. Still a policy choice and editable
        // at runtime (see WeatherSettingService).
        'wave_waspada_m' => 1.25,
        'wave_siaga_m' => 2.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | BMKG maritime (wave) source
    |--------------------------------------------------------------------------
    |
    | VERIFIED (2026-10-15): BMKG publishes a file-based maritime API at
    | https://maritim.bmkg.go.id/marine2026-data/ . A sea-area forecast is a
    | single JSON document at `perairan/{KODE}.json` (and `pelabuhan/{KODE}.json`
    | for ports) carrying `wave_height` in metres, `wave_cat`, wind in KNOTS,
    | and surface current.
    |
    | The sea area is configured PER LOCATION (`locations[].marine_area_code`),
    | so wave data is scoped per location and never shared. The verified area
    | for Parepare is `P.AE.01` (Perairan Pinrang).
    |
    | Wave data is best-effort: if this source is unreachable the land forecast
    | still syncs and wave renders "Data tidak tersedia".
    |
    */
    'marine' => [
        'enabled' => (bool) env('WINDY_MARINE_ENABLED', true),
        'base_url' => env('WINDY_MARINE_BASE_URL', 'https://maritim.bmkg.go.id/marine2026-data'),
        'timeout' => (int) env('WINDY_MARINE_HTTP_TIMEOUT', 10),
        // Knots → km/h conversion factor (1 knot = 1.852 km/h).
        'knot_to_kmh' => 1.852,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribution
    |--------------------------------------------------------------------------
    |
    | Required source credit, always displayed next to the data.
    |
    */
    'attribution' => [
        'bmkg' => 'Sumber data: BMKG (Badan Meteorologi, Klimatologi, dan Geofisika).',
        'bmkg_marine' => 'Sumber data gelombang laut: BMKG (Prakiraan Cuaca Maritim).',
    ],

    /*
    |--------------------------------------------------------------------------
    | Windy.com embed (owner decision O-8)
    |--------------------------------------------------------------------------
    |
    | The legacy Windy.com iframe is retained. No `XFrameOptionsMode.ALLOWALL`
    | is used; the embed is allowed through a narrow, explicit CSP `frame-src`
    | directive instead.
    |
    */
    'embed' => [
        'enabled' => (bool) env('WINDY_EMBED_ENABLED', true),
        'url' => 'https://embed.windy.com/embed2.html',
        'allowed_frame_src' => 'https://embed.windy.com',
    ],

];
