<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateWeatherSettingRequest;
use App\Models\AuditLog;
use App\Models\WaveForecast;
use App\Models\WeatherForecast;
use App\Models\WeatherLocation;
use App\Models\WeatherSource;
use App\Services\Weather\WeatherLocationRegistry;
use App\Services\Weather\WeatherSettingService;
use App\Services\Weather\WeatherSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — WINDY weather configuration + manual sync.
 *
 * Scope (owner decisions):
 *   - Manual sync (O-7 default) + scheduled sync (D-16).
 *   - MULTI-LOCATION: the panel lists every configured location and its stored
 *     data counts; adding a location is a config change, not a code change.
 *   - Thresholds are managed through `app_settings` (O-16) via
 *     WeatherSettingService, with defaults from config/weather.php.
 *
 * Authorization: route middleware `role:super_admin` + Form Request authorize().
 * Audit log: project convention `subject_type` = plain string ('weather_setting').
 */
class SuperAdminWeatherController extends Controller
{
    public function __construct(
        private readonly WeatherSettingService $settings,
        private readonly WeatherSyncService $sync,
        private readonly WeatherLocationRegistry $registry,
    ) {}

    public function index(): View
    {
        $source = WeatherSource::query()->where('code', 'bmkg')->first();

        $forecastCount = $source !== null
            ? WeatherForecast::query()->where('weather_source_id', $source->id)->count()
            : 0;

        $lastSyncedAt = $source !== null
            ? WeatherForecast::query()->where('weather_source_id', $source->id)->max('issued_at')
            : null;

        $waveCount = $source !== null
            ? WaveForecast::query()->where('weather_source_id', $source->id)->count()
            : 0;

        // Per-location rows for the admin table.
        $locations = [];
        foreach ($this->registry->all() as $definition) {
            $model = WeatherLocation::query()->where('key', $definition['key'])->first();

            $landCount = $source !== null && $model !== null
                ? WeatherForecast::query()
                    ->where('weather_source_id', $source->id)
                    ->where('weather_location_id', $model->id)
                    ->count()
                : 0;

            $waveForLocation = $source !== null && $definition['marine_area_code'] !== null
                ? WaveForecast::query()
                    ->where('weather_source_id', $source->id)
                    ->where('area_code', $definition['marine_area_code'])
                    ->count()
                : 0;

            $locations[] = [
                'key' => $definition['key'],
                'name' => $definition['name'],
                'is_default' => $definition['key'] === $this->registry->defaultKey(),
                'adm4' => $definition['adm4'],
                'marine_area_code' => $definition['marine_area_code'],
                'city' => $model?->city,
                'district' => $model?->district,
                'village' => $model?->village,
                'land_count' => $landCount,
                'wave_count' => $waveForLocation,
            ];
        }

        return view('super-admin.weather.index', [
            'settings' => $this->settings->managed(),
            'sourceEnabled' => $source?->is_enabled,
            'forecastCount' => $forecastCount,
            'lastSyncedAt' => $lastSyncedAt,
            'waveCount' => $waveCount,
            'locations' => $locations,
            'forecastDays' => (int) config('weather.forecast_days'),
        ]);
    }

    /**
     * Run a manual BMKG sync. Nothing is scheduled — this is the only trigger.
     */
    public function sync(): RedirectResponse
    {
        $result = $this->sync->sync();

        if (! $result['ok']) {
            return redirect()
                ->route('super-admin.weather.index')
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('super-admin.weather.index')
            ->with('success', $result['message']);
    }

    public function updateSetting(UpdateWeatherSettingRequest $request, string $setting): RedirectResponse
    {
        abort_unless($this->settings->isManaged($setting), 404);

        $value = (float) $request->validated()['value'];
        $before = $this->settings->effectiveValue($setting);

        DB::transaction(function () use ($request, $setting, $value, $before): void {
            $this->settings->set($setting, $value);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'weather_setting.updated',
                'subject_type' => 'weather_setting',
                'subject_id' => null,
                'metadata' => [
                    'key' => $setting,
                    'old_value' => $before,
                    'new_value' => $value,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('super-admin.weather.index')
            ->with('success', 'Ambang cuaca berhasil diperbarui.');
    }
}
