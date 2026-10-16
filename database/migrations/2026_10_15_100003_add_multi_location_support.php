<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| WINDY multi-location support
|--------------------------------------------------------------------------
|
| Adds the columns needed to monitor MORE THAN ONE location:
|
|   - `weather_locations.key`               — stable slug used in URLs
|                                             (/cuaca/{key}), DB lookups and
|                                             cache keys. Nullable for legacy
|                                             rows; unique when set.
|   - `weather_locations.marine_area_code` — the BMKG sea-area code for THIS
|                                             location, so wave data is scoped
|                                             per location and never shared.
|
| `wave_forecasts` is intentionally NOT changed: a sea area is a physical
| thing (BMKG `P.AE.01` etc.), independent of which land location happens to
| map to it. Wave rows stay keyed by `area_code`, and a location is linked to
| its waves through `marine_area_code`. This avoids duplicating identical wave
| data when two locations share one sea area.
|
| OWNER DECISION: only Kota Parepare is configured; these columns carry the
| capability, not new locations.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weather_locations', function (Blueprint $table) {
            $table->string('key', 60)->nullable()->unique()->after('id');
            $table->string('marine_area_code', 30)->nullable()->after('adm4_code');
        });
    }

    public function down(): void
    {
        Schema::table('weather_locations', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'marine_area_code']);
        });
    }
};
