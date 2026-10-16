<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add OPTIONAL geographic coordinates to complaints (Prompt 4/5).
     *
     *   - latitude  : DECIMAL(10,7) NULL
     *   - longitude : DECIMAL(10,7) NULL
     *
     * BOTH ARE NULLABLE — deliberately. Coordinates are OPTIONAL: a complaint
     * may be submitted with NO geographic point at all (Kecamatan/Kelurahan
     * remain the REQUIRED administrative location). Existing complaints predate
     * this feature and MUST NOT be broken: legacy rows keep latitude = NULL and
     * longitude = NULL and are NEVER back-filled.
     *
     * DECIMAL(10,7) keeps 3 integer digits + 7 fractional digits, which covers
     * the full valid latitude (-90..90) and longitude (-180..180) range at the
     * precision required for report pins.
     *
     * NOT added on purpose (Prompt 4/5 explicitly forbids them):
     *   - no GIS POINT / geometry column
     *   - no spatial index
     *   - no geofence / boundary data
     *
     * `location_text` and the administrative columns are LEFT UNTOUCHED.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('kelurahan_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    /**
     * Reversible: dropping the two columns restores the previous schema exactly.
     * No data migration/backfill is involved, so rollback is safe.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
