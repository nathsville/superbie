<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * AppSettingService — Super Admin Configuration Management (Prompt 11).
 *
 * SCOPE / CONSTRAINTS:
 *   - Exposes ONLY configuration keys that have a PROVEN runtime consumer in
 *     this codebase. Currently that is exactly one key: `daily_report_limit`,
 *     consumed by App\Http\Requests\Citizen\StoreComplaintRequest.
 *   - No arbitrary key creation, no delete, no .env / infrastructure editing.
 *   - The default value is read from the existing single source of truth
 *     (`config/business_rules.php`), never invented here.
 *   - Validation/effective-value logic MIRRORS the existing consumer so the UI
 *     can never persist a value the consumer would reject.
 */
class AppSettingService
{
    /**
     * Registry of settings that may be managed through the Super Admin UI.
     *
     * Every entry must be backed by a real consumer + a real default:
     *   key              → AppSetting key (fixed, never client-supplied)
     *   label/description→ factual UI copy
     *   type             → how the consumer interprets the stored value
     *   default_config   → config path holding the frozen default
     *   consumer         → the class that actually reads this value
     *
     * @var array<string, array{label:string, description:string, type:string, default_config:string, consumer:string}>
     */
    private const MANAGED = [
        'daily_report_limit' => [
            'label'          => 'Batas Laporan Harian',
            'description'    => 'Jumlah maksimum laporan yang dapat dikirim oleh satu pengguna dalam satu hari kalender.',
            'type'           => 'integer',
            'default_config' => 'business_rules.daily_report_limit',
            'consumer'       => \App\Http\Requests\Citizen\StoreComplaintRequest::class,
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
     * Managed settings with their real current state (override vs default).
     *
     * @return list<array{key:string,label:string,description:string,type:string,default:int,override:?string,is_overridden:bool,effective:int,updated_at:?\Illuminate\Support\Carbon}>
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
                'key'           => $key,
                'label'         => $definition['label'],
                'description'   => $definition['description'],
                'type'          => $definition['type'],
                'default'       => $this->defaultValue($key),
                'override'      => $row?->value,
                'is_overridden' => $row !== null,
                'effective'     => $this->effectiveValue($key),
                'updated_at'    => $row?->updated_at,
            ];
        }

        return $result;
    }

    /**
     * The effective value the application will actually use, mirroring the
     * consumer's own rule: a numeric, positive override wins; otherwise the
     * frozen default from config/business_rules.php applies.
     */
    public function effectiveValue(string $key): int
    {
        $configured = AppSetting::get($key);

        if ($configured !== null && is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        return $this->defaultValue($key);
    }

    /** The raw stored override value (or null when never overridden). */
    public function storedValue(string $key): ?string
    {
        $value = AppSetting::get($key);

        return $value === null ? null : (string) $value;
    }

    /** The frozen default from config/business_rules.php (single source of truth). */
    public function defaultValue(string $key): int
    {
        $this->assertManaged($key);

        return (int) config(self::MANAGED[$key]['default_config']);
    }

    /**
     * Persist a managed setting. Rejects any unmanaged key so arbitrary
     * configuration can never be created through this path.
     */
    public function set(string $key, int $value): AppSetting
    {
        $this->assertManaged($key);

        return AppSetting::set($key, $value, self::MANAGED[$key]['description']);
    }

    private function assertManaged(string $key): void
    {
        if (! $this->isManaged($key)) {
            throw new \InvalidArgumentException("Setting key is not managed: {$key}");
        }
    }
}
