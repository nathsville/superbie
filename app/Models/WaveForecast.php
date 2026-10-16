<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One marine forecast slot (significant wave height + supporting fields).
 *
 * VERIFIED SOURCE (2026-10-15): BMKG maritime API `perairan/{KODE}.json`.
 * Values are stored verbatim from the source; NULL means "not provided".
 * Wind speeds are kept in KNOTS (the marine feed's native unit).
 *
 * @property int $id
 * @property int $weather_source_id
 * @property string $area_code
 * @property string|null $area_name
 * @property \Illuminate\Support\Carbon $forecast_at
 * @property string|null $wave_height_m
 * @property string|null $wave_cat
 * @property string|null $wind_speed_kt
 */
class WaveForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'weather_source_id',
        'area_code',
        'area_name',
        'issued_at',
        'valid_from',
        'valid_to',
        'forecast_at',
        'wave_height_m',
        'wave_cat',
        'wind_from',
        'wind_speed_kt',
        'wind_gust_kt',
        'current_to',
        'current_speed_kt',
        'weather_desc',
        'temp_avg_c',
        'rh_avg_pct',
        'visibility_km',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'forecast_at' => 'datetime',
            'wave_height_m' => 'decimal:2',
            'wind_speed_kt' => 'decimal:2',
            'wind_gust_kt' => 'decimal:2',
            'current_speed_kt' => 'decimal:2',
            'temp_avg_c' => 'integer',
            'rh_avg_pct' => 'integer',
            'visibility_km' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WeatherSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(WeatherSource::class, 'weather_source_id');
    }
}
