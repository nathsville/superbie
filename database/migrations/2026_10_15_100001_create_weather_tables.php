<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| WINDY weather tables
|--------------------------------------------------------------------------
|
| Design notes (see WINDY_DATA_MAPPING.md):
|
|   - `weather_sources`     — source metadata (label, attribution, enabled).
|   - `weather_locations`   — the monitored location(s) and BMKG adm4 code.
|   - `weather_forecasts`   — one row per 3-hourly forecast slot.
|
| There is deliberately NO `weather_observations` table: the chosen BMKG
| `prakiraan-cuaca` source returns a forecast series, not observations, and no
| synthetic observation is ever written. "Current conditions" shown to users
| are derived from the forecast slot nearest to now (labelled as such).
|
| Columns marked [V] were verified against a real BMKG response (2026-10-15).
|
| Timestamps are stored in WITA (Asia/Makassar) — the application timezone.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 150);
            $table->boolean('is_enabled')->default(true);
            $table->string('attribution_text', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('weather_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('adm4_code', 30)->nullable()->index();
            $table->string('province', 100)->nullable();   // [V] lokasi.provinsi
            $table->string('city', 100)->nullable();       // [V] lokasi.kotkab
            $table->string('district', 100)->nullable();   // [V] lokasi.kecamatan
            $table->string('village', 100)->nullable();    // [V] lokasi.desa
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('weather_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weather_location_id')
                ->constrained('weather_locations')->cascadeOnDelete();
            $table->foreignId('weather_source_id')
                ->constrained('weather_sources')->cascadeOnDelete();

            // When the source published this series (sync time) and the slot time.
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('forecast_at');              // [V] local_datetime (WITA)

            $table->decimal('temperature_c', 5, 2)->nullable();   // [V] t
            $table->unsignedTinyInteger('humidity_pct')->nullable(); // [V] hu
            $table->decimal('wind_speed_kmh', 6, 2)->nullable();  // [V] ws
            $table->string('wind_dir', 30)->nullable();           // [V] wd (cardinal)
            $table->unsignedSmallInteger('wind_dir_deg')->nullable(); // [V] wd_deg
            $table->string('wind_dir_to', 30)->nullable();        // [V] wd_to
            // Rain in mm per 3 hours (owner decision O-14a). [V] `tp`. NULL when
            // the source omits it — NULL is never coerced to 0.
            $table->decimal('rain_mm', 6, 2)->nullable();
            $table->unsignedTinyInteger('cloud_cover_pct')->nullable(); // [V] tcc
            $table->unsignedSmallInteger('weather_code')->nullable();   // [V] weather
            $table->string('weather_desc', 150)->nullable();            // [V] weather_desc
            $table->string('visibility_text', 50)->nullable();          // [V] vs_text
            $table->timestamp('analysis_date')->nullable();             // [V] analysis_date

            $table->timestamps();

            $table->unique(
                ['weather_location_id', 'weather_source_id', 'forecast_at'],
                'weather_forecasts_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_forecasts');
        Schema::dropIfExists('weather_locations');
        Schema::dropIfExists('weather_sources');
    }
};
