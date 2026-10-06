<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateAppSettingRequest;
use App\Models\AuditLog;
use App\Services\AppSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — Configuration Management (Prompt 11).
 *
 * Exposes ONLY the configuration keys with a proven runtime consumer
 * (currently `daily_report_limit`). No arbitrary key creation, no delete, and
 * no .env / infrastructure editing.
 *
 * Authorization: route middleware `role:super_admin` + Form Request authorize().
 * Audit log: project convention `subject_type` = plain string ('app_setting').
 */
class SuperAdminConfigController extends Controller
{
    public function __construct(
        private readonly AppSettingService $settings,
    ) {}

    public function index(): View
    {
        return view('super-admin.config.index', [
            'settings' => $this->settings->managed(),
        ]);
    }

    public function edit(string $setting): View
    {
        abort_unless($this->settings->isManaged($setting), 404);

        return view('super-admin.config.edit', [
            'key'         => $setting,
            'setting'     => $this->managedSetting($setting),
        ]);
    }

    public function update(UpdateAppSettingRequest $request, string $setting): RedirectResponse
    {
        abort_unless($this->settings->isManaged($setting), 404);

        $value = (int) $request->validated()['value'];
        $before = $this->settings->effectiveValue($setting);

        DB::transaction(function () use ($request, $setting, $value, $before) {
            $this->settings->set($setting, $value);

            // Audit the administrative change. Only the key and the non-sensitive
            // numeric values are recorded — never credentials or secrets.
            AuditLog::create([
                'actor_id'     => $request->user()->id,
                'action'       => 'app_setting.updated',
                'subject_type' => 'app_setting',
                'subject_id'   => null,
                'metadata'     => [
                    'key'       => $setting,
                    'old_value' => $before,
                    'new_value' => $value,
                ],
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'created_at'   => now(),
            ]);
        });

        return redirect()
            ->route('super-admin.config.index')
            ->with('success', 'Konfigurasi "' . $setting . '" berhasil diperbarui.');
    }

    /**
     * @return array{key:string,label:string,description:string,type:string,default:int,override:?string,is_overridden:bool,effective:int,updated_at:?\Illuminate\Support\Carbon}
     */
    private function managedSetting(string $key): array
    {
        foreach ($this->settings->managed() as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        abort(404);
    }
}
