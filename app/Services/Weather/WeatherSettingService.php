<?php

namespace App\Services\Weather;

use App\Models\AppSetting;

/**
 * WeatherSettingService — WINDY configuration backed by `app_settings`.
 *
 * Owner decision O-16: WINDY configuration reuses the EXISTING `app_settings`
 * table (via the `AppSetting` model), not a new storage mechanism.
 *
 * This is deliberately a SEPARATE service from `App\Services\AppSettingService`.
 * That service has a frozen, single-key managed registry whose exact contents
 * are asserted by `SuperAdminConfigurationTest`; extending it here would couple
 * an unrelated feature to a frozen contract. Reusing the same TABLE + model
 * satisfies O-16 without touching that contract.
 *
 * Only keys in `MANAGED` may be read or written. Defaults come from
 * `config/weather.php` (the single source of truth) — never invented here.
 */
class WeatherSettingService
{
    /**
     * Managed WINDY setting keys → config path holding the frozen default.
     *
     * @var array<string, array{label:string, description:string, default_config:string}>
     */
    private const MANAGED = [
        'weather_rain_waspada_mm' => [
            'label' => 'Ambang Hujan Waspada (mm/3 jam)',
            'description' => 'Ambang curah hujan untuk status Waspada, dalam mm per 3 jam.',
            'default_config' => 'weather.thresholds.rain_waspada_mm',
        ],
        'weather_rain_siaga_mm' => [
            'label' => 'Ambang Hujan Siaga (mm/3 jam)',
            'description' => 'Ambang curah hujan untuk status Siaga, dalam mm per 3 jam.',
            'default_config' => 'weather.thresholds.rain_siaga_mm',
        ],
        'weather_wind_waspada_kmh' => [
            'label' => 'Ambang Angin Waspada (km/jam)',
            'description' => 'Ambang kecepatan angin untuk status Waspada, dalam km/jam.',
            'default_config' => 'weather.thresholds.wind_waspada_kmh',
        ],
        'weather_wind_siaga_kmh' => [
            'label' => 'Ambang Angin Siaga (km/jam)',
            'description' => 'Ambang kecepatan angin untuk status Siaga, dalam km/jam.',
            'default_config' => 'weather.thresholds.wind_siaga_kmh',
        ],
        'weather_wave_waspada_m' => [
            'label' => 'Ambang Gelombang Waspada (m)',
            'description' => 'Ambang tinggi gelombang laut untuk status Waspada, dalam meter.',
            'default_config' => 'weather.thresholds.wave_waspada_m',
        ],
        'weather_wave_siaga_m' => [
            'label' => 'Ambang Gelombang Siaga (m)',
            'description' => 'Ambang tinggi gelombang laut untuk status Siaga, dalam meter.',
            'default_config' => 'weather.thresholds.wave_siaga_m',
        ],
    ];

    /** @return list<string> */
    public function managedKeys(): array
    {
        return array_keys(self::MANAGED);
    }

    public function isManaged(string $key): bool
    {
        return array_key_exists($key, self::MANAGED);
    }

    /**
     * All managed settings with their current effective state.
     *
     * @return list<array{key:string,label:string,description:string,default:float,override:?string,is_overridden:bool,effective:float}>
     */
    public function managed(): array
    {
        $rows = AppSetting::query()
            ->whereIn('key', $this->managedKeys())
            ->get()
            ->keyBy('key');

        $result = [];

        foreach (self::MANAGED as $key => $definition) {
            $row = $rows->get($key);

            $result[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'default' => $this->defaultValue($key),
                'override' => $row?->value,
                'is_overridden' => $row !== null,
                'effective' => $this->effectiveValue($key),
            ];
        }

        return $result;
    }

    /**
     * The effective numeric threshold. A numeric, non-negative override wins;
     * otherwise the frozen default from config/weather.php applies.
     */
    public function effectiveValue(string $key): float
    {
        $configured = AppSetting::get($key);

        if ($configured !== null && is_numeric($configured) && (float) $configured >= 0) {
            return (float) $configured;
        }

        return $this->defaultValue($key);
    }

    public function defaultValue(string $key): float
    {
        $this->assertManaged($key);

        return (float) config(self::MANAGED[$key]['default_config']);
    }

    /**
     * Effective thresholds, keyed for the risk evaluator.
     *
     * @return array{rain_waspada_mm:float, rain_siaga_mm:float, wind_waspada_kmh:float, wind_siaga_kmh:float, wave_waspada_m:float, wave_siaga_m:float}
     */
    public function thresholds(): array
    {
        return [
            'rain_waspada_mm' => $this->effectiveValue('weather_rain_waspada_mm'),
            'rain_siaga_mm' => $this->effectiveValue('weather_rain_siaga_mm'),
            'wind_waspada_kmh' => $this->effectiveValue('weather_wind_waspada_kmh'),
            'wind_siaga_kmh' => $this->effectiveValue('weather_wind_siaga_kmh'),
            'wave_waspada_m' => $this->effectiveValue('weather_wave_waspada_m'),
            'wave_siaga_m' => $this->effectiveValue('weather_wave_siaga_m'),
        ];
    }

    /**
     * Persist a managed setting. Rejects unmanaged keys so arbitrary
     * configuration can never be created through this path.
     */
    public function set(string $key, float $value): AppSetting
    {
        $this->assertManaged($key);

        return AppSetting::set($key, $value, self::MANAGED[$key]['description']);
    }

    private function assertManaged(string $key): void
    {
        if (! $this->isManaged($key)) {
            throw new \InvalidArgumentException("Weather setting key is not managed: {$key}");
        }
    }
}
