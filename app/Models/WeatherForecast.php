<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One forecast slot from a source for a location.
 *
 * IMPORTANT: every value here is a FORECAST value as published by the source.
 * NULL means "the source did not provide this field" and is NEVER coerced to 0
 * or any synthetic value. The display layer renders NULL as "Data tidak
 * tersedia".
 *
 * @property int $id
 * @property int $weather_location_id
 * @property int $weather_source_id
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon $forecast_at
 * @property string|null $temperature_c
 * @property int|null $humidity_pct
 * @property string|null $wind_speed_kmh
 * @property string|null $wind_dir
 * @property string|null $rain_mm
 * @property string|null $weather_desc
 */
class WeatherForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'weather_location_id',
        'weather_source_id',
        'issued_at',
        'forecast_at',
        'temperature_c',
        'humidity_pct',
        'wind_speed_kmh',
        'wind_dir',
        'wind_dir_deg',
        'wind_dir_to',
        'rain_mm',
        'cloud_cover_pct',
        'weather_code',
        'weather_desc',
        'visibility_text',
        'analysis_date',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'forecast_at' => 'datetime',
            'analysis_date' => 'datetime',
            'temperature_c' => 'decimal:2',
            'humidity_pct' => 'integer',
            'wind_speed_kmh' => 'decimal:2',
            'wind_dir_deg' => 'integer',
            'rain_mm' => 'decimal:2',
            'cloud_cover_pct' => 'integer',
            'weather_code' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WeatherLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WeatherLocation::class, 'weather_location_id');
    }

    /**
     * @return BelongsTo<WeatherSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(WeatherSource::class, 'weather_source_id');
    }
}
