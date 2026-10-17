<?php

/*
|--------------------------------------------------------------------------
| SIPHP Market Module Configuration (PHASE S)
|--------------------------------------------------------------------------
|
| Configuration for the NATIVE SIPHP market module (market price information
| + retail directory) — owner decisions D-S1…D-S7, see SIPHP_INTEGRATION_PLAN.md.
|
| All market DATA is entered manually by staff in the Super Admin panel. This
| file holds CONFIGURATION only; no value here is ever presented as a price or
| a measurement. Public pages read only from the database.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Feature switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the public `/siphp` surface returns 404 (never a
    | half-rendered page). Panel routes for Super Admin remain available.
    |
    */
    'enabled' => (bool) env('SIPHP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Store photo upload (D-S7)
    |--------------------------------------------------------------------------
    |
    | Store photos are PUBLIC by design (retail directory). They are written to
    | the `public` disk under a generated filename; the original filename is
    | never used as a path. `max_kilobytes` is a TECHNICAL bound (not a business
    | rule) and can be tuned per host via env.
    |
    */
    'store_photo' => [
        'disk' => 'public',
        'directory' => 'siphp/stores',
        'max_kilobytes' => (int) env('SIPHP_STORE_PHOTO_MAX_KB', 4096),
        'mimes' => ['jpg', 'jpeg', 'png'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public source note (D-S2)
    |--------------------------------------------------------------------------
    |
    | Shown next to market data to make the manual-entry sourcing explicit.
    | Final wording is decided at phase S3; this default is the approved draft.
    |
    */
    'source_note' => env('SIPHP_SOURCE_NOTE', 'Data harga pasar diinput manual oleh petugas.'),

];
