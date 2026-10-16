<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weather data source (metadata only). In this slice the only row is `bmkg`.
 *
 * @property int $id
 * @property string $code
 * @property string $label
 * @property bool $is_enabled
 * @property string|null $attribution_text
 */
class WeatherSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'is_enabled',
        'attribution_text',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WeatherForecast, $this>
     */
    public function forecasts(): HasMany
    {
        return $this->hasMany(WeatherForecast::class);
    }
}
