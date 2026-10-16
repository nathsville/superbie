<?php

namespace App\Services\Weather;

use App\Enums\WeatherRiskStatus;

/**
 * RiskEvaluationService — computes the early-warning status from parameters.
 *
 * HONESTY CONTRACT (governance):
 *   - The status is COMPUTED from whatever parameters are actually available.
 *     A parameter that is NULL is reported as `missing` and NEVER contributes a
 *     status. Missing data is surfaced, not silently defaulted to "Normal".
 *   - Parameters evaluated, each from a VERIFIED BMKG source:
 *       RAIN  — `tp`, mm per 3 hours (land forecast; owner decision O-14a)
 *       WIND  — `ws`, km/jam (land forecast)
 *       WAVE  — `wave_height`, metres (maritime forecast; owner decision O-14b,
 *               now available via the verified BMKG maritime API)
 */
class RiskEvaluationService
{
    public function __construct(
        private readonly WeatherSettingService $settings,
    ) {}

    /**
     * Evaluate a single parameter set.
     *
     * @return array{
     *     status: ?WeatherRiskStatus,
     *     evaluated: array<string, array{value:float, status:WeatherRiskStatus, unit:string}>,
     *     missing: list<string>,
     *     basis: list<string>
     * }
     */
    public function evaluate(?float $rainMm, ?float $windKmh, ?float $waveM = null): array
    {
        $thresholds = $this->settings->thresholds();

        $evaluated = [];
        $missing = [];

        if ($rainMm === null) {
            $missing[] = 'rain';
        } else {
            $evaluated['rain'] = [
                'value' => $rainMm,
                'unit' => 'mm/3 jam',
                'status' => $this->classify(
                    $rainMm,
                    $thresholds['rain_waspada_mm'],
                    $thresholds['rain_siaga_mm'],
                ),
            ];
        }

        if ($windKmh === null) {
            $missing[] = 'wind';
        } else {
            $evaluated['wind'] = [
                'value' => $windKmh,
                'unit' => 'km/jam',
                'status' => $this->classify(
                    $windKmh,
                    $thresholds['wind_waspada_kmh'],
                    $thresholds['wind_siaga_kmh'],
                ),
            ];
        }

        if ($waveM === null) {
            $missing[] = 'wave';
        } else {
            $evaluated['wave'] = [
                'value' => $waveM,
                'unit' => 'm',
                'status' => $this->classify(
                    $waveM,
                    $thresholds['wave_waspada_m'],
                    $thresholds['wave_siaga_m'],
                ),
            ];
        }

        $status = WeatherRiskStatus::mostSevere(
            array_map(static fn (array $row): WeatherRiskStatus => $row['status'], $evaluated)
        );

        return [
            'status' => $status,
            'evaluated' => $evaluated,
            'missing' => $missing,
            'basis' => array_keys($evaluated),
        ];
    }

    /**
     * Classify a single value against a Waspada/Siaga threshold pair.
     * `>= siaga` → Siaga; `>= waspada` → Waspada; otherwise Normal.
     */
    private function classify(float $value, float $waspada, float $siaga): WeatherRiskStatus
    {
        if ($value >= $siaga) {
            return WeatherRiskStatus::Siaga;
        }

        if ($value >= $waspada) {
            return WeatherRiskStatus::Waspada;
        }

        return WeatherRiskStatus::Normal;
    }
}
