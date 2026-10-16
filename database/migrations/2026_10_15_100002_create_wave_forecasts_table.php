<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| WINDY wave forecasts
|--------------------------------------------------------------------------
|
| Source: BMKG maritime API (VERIFIED 2026-10-15):
|   https://maritim.bmkg.go.id/marine2026-data/perairan/{KODE}.json
|
| One row per forecast slot per sea area. `wave_height_m` is the significant
| wave height in metres — the only wave figure BMKG publishes here — and is
| stored verbatim. Wind from the marine feed is in KNOTS and kept in knots
| (no unit mixing with the land feed's km/jam).
|
| Times in the source are UTC; they are stored converted to WITA
| (Asia/Makassar) to match owner decision O-15.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wave_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weather_source_id')
                ->constrained('weather_sources')->cascadeOnDelete();

            // BMKG sea-area code (e.g. P.AE.01 = Perairan Pinrang).
            $table->string('area_code', 30)->index();
            $table->string('area_name', 150)->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamp('forecast_at');

            // Significant wave height, metres (VERIFIED field `wave_height`).
            $table->decimal('wave_height_m', 5, 2)->nullable();
            $table->string('wave_cat', 50)->nullable();       // e.g. "Rendah"
            $table->string('wind_from', 50)->nullable();
            $table->decimal('wind_speed_kt', 6, 2)->nullable(); // knots
            $table->decimal('wind_gust_kt', 6, 2)->nullable();  // knots
            $table->string('current_to', 50)->nullable();
            $table->decimal('current_speed_kt', 6, 2)->nullable(); // knots
            $table->string('weather_desc', 150)->nullable();
            $table->unsignedSmallInteger('temp_avg_c')->nullable();
            $table->unsignedTinyInteger('rh_avg_pct')->nullable();
            $table->unsignedSmallInteger('visibility_km')->nullable();

            $table->timestamps();

            $table->unique(
                ['weather_source_id', 'area_code', 'forecast_at'],
                'wave_forecasts_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wave_forecasts');
    }
};
