<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A monitored location and its BMKG administrative-area (adm4) code.
 *
 * Multi-location support: each location carries a stable `key` (used in URLs
 * and cache keys) and a `marine_area_code` linking it to its maritime (wave)
 * sea area, so land and wave data stay scoped per location.
 *
 * @property int $id
 * @property string|null $key
 * @property string $name
 * @property string|null $adm4_code
 * @property string|null $marine_area_code
 * @property string|null $latitude
 * @property string|null $longitude
 * @property bool $is_active
 */
class WeatherLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'adm4_code',
        'marine_area_code',
        'province',
        'city',
        'district',
        'village',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Public route binding uses the stable `key` slug (e.g. /cuaca/parepare).
     */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return HasMany<WeatherForecast, $this>
     */
    public function forecasts(): HasMany
    {
        return $this->hasMany(WeatherForecast::class);
    }

    /**
     * @return HasMany<WaveForecast, $this>
     */
    public function waveForecasts(): HasMany
    {
        return $this->hasMany(WaveForecast::class, 'area_code', 'marine_area_code');
    }
}
