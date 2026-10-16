<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Map configuration — SuperBie / Lapor Pak Wali (Prompt 4/5)
    |--------------------------------------------------------------------------
    |
    | Configuration for the OPTIONAL report-location map (Leaflet + OpenStreetMap).
    |
    | IMPORTANT: `default_center` is ONLY the initial view of an empty map. It is
    | NOT a report location, NOT a user location, and MUST NEVER be written into a
    | complaint. A report's coordinates are set ONLY by an explicit user action
    | (GPS detection or a map click) — never by this default.
    |
    */

    // Initial map view (a generic, city-level center). Overridable per env.
    'default_center' => [
        'lat' => (float) env('MAP_DEFAULT_LAT', -4.0135),
        'lng' => (float) env('MAP_DEFAULT_LNG', 119.6255),
    ],

    'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 13),

    // OpenStreetMap tile layer. Attribution is REQUIRED and must be shown.
    'tile' => [
        'url'         => env('MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        'max_zoom'    => (int) env('MAP_TILE_MAX_ZOOM', 19),
    ],

    /*
    |--------------------------------------------------------------------------
    | Boundary dataset — automatic Kecamatan/Kelurahan detection
    |--------------------------------------------------------------------------
    |
    | Bundled kelurahan polygons for Kota Parepare used to SUGGEST the
    | Kecamatan/Kelurahan when a citizen marks a point on the map or uses GPS.
    | The suggestion is UX-only: the administrative pair stays REQUIRED and is
    | always re-validated server-side.
    |
    | Source: cahyadsn/wilayah_boundaries (MIT License, (c) 2024 Cahya DSN),
    | "Data boundaries wilayah administrasi pemerintahan Indonesia sesuai
    | Kepmendagri No 300.2.2-2430 Tahun 2025". See resources/data/README.md.
    | Boundaries are INDICATIVE, not a definitive legal boundary.
    |
    */
    'boundaries' => [
        'file' => resource_path('data/parepare-kelurahan.geojson'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverse geocoding — OPTIONAL automatic "Lokasi / Alamat Detail" suggestion
    |--------------------------------------------------------------------------
    |
    | SCOPE / PRIVACY DEVIATION (owner-approved): the PRD lists "geocoding" as
    | out of scope and the map contract promised coordinates were "never sent
    | anywhere except the form". The owner explicitly approved using an external
    | reverse-geocoding service to PRE-FILL the optional free-text address.
    |
    | Because this transmits the citizen's coordinates to a THIRD PARTY, it is
    | gated by an EXPLICIT opt-in checkbox in the form — coordinates are never
    | sent unless the citizen ticks it. The result is only a SUGGESTION: the
    | field stays editable and the "only fill when empty" rule means a manual
    | address is never overwritten. Citizen forms only; staff views never call it.
    |
    | Provider: OpenStreetMap Nominatim. Its usage policy requires an identifying
    | HTTP Referer (sent automatically by the browser) and a contact email for
    | heavier use — set MAP_GEOCODE_EMAIL in production. Requests are one-shot
    | per chosen point (never on page load, never a background loop).
    |
    */
    'reverse_geocode' => [
        'enabled' => (bool) env('MAP_REVERSE_GEOCODE', true),
        'url'     => env('MAP_GEOCODE_URL', 'https://nominatim.openstreetmap.org/reverse'),
        'email'   => env('MAP_GEOCODE_EMAIL'),
        'locale'  => env('MAP_GEOCODE_LOCALE', 'id'),
        'attribution' => 'OpenStreetMap Nominatim',
    ],

];
