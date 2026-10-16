<?php

namespace App\Enums;

/*
|--------------------------------------------------------------------------
| WeatherRiskStatus
|--------------------------------------------------------------------------
|
| The three-level early-warning status derived from weather parameters. The
| value is COMPUTED (never stored as a measurement) by RiskEvaluationService
| from the current parameters and the configured thresholds.
|
| Ordering matters: `Siaga` is more severe than `Waspada`, which is more
| severe than `Normal`.
|
*/
enum WeatherRiskStatus: string
{
    case Normal = 'NORMAL';
    case Waspada = 'WASPADA';
    case Siaga = 'SIAGA';

    /**
     * Severity rank, used to pick the most severe status across parameters.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Normal => 0,
            self::Waspada => 1,
            self::Siaga => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Waspada => 'Waspada',
            self::Siaga => 'Siaga',
        };
    }

    /**
     * Tailwind badge classes used by the public weather view.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Normal => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Waspada => 'bg-accent-50 text-accent-700 ring-accent-600/20',
            self::Siaga => 'bg-red-50 text-red-700 ring-red-600/20',
        };
    }

    /**
     * Neutral, agency-free guidance text (owner decision O-9: no agency-specific
     * recommendation text). These are generic, non-authoritative descriptors of
     * the status level — not an official instruction.
     */
    public function guidance(): string
    {
        return match ($this) {
            self::Normal => 'Kondisi dalam rentang normal berdasarkan ambang yang dikonfigurasi.',
            self::Waspada => 'Perlu peningkatan kewaspadaan berdasarkan ambang yang dikonfigurasi.',
            self::Siaga => 'Perlu kewaspadaan tinggi berdasarkan ambang yang dikonfigurasi.',
        };
    }

    /**
     * Pick the most severe status from a list of statuses (nulls ignored).
     *
     * @param  array<int, self|null>  $statuses
     */
    public static function mostSevere(array $statuses): ?self
    {
        $severe = null;

        foreach ($statuses as $status) {
            if (! $status instanceof self) {
                continue;
            }

            if ($severe === null || $status->rank() > $severe->rank()) {
                $severe = $status;
            }
        }

        return $severe;
    }
}
