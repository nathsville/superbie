<?php

namespace App\Http\Requests\SuperAdmin;

use App\Services\AppSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Super Admin — update a managed application setting (Prompt 11).
 *
 * Authorization: Super Admin only (route middleware enforces this too).
 *
 * Validation MIRRORS the existing runtime consumer
 * (App\Http\Requests\Citizen\StoreComplaintRequest), which treats a value as
 * valid only when it is numeric and greater than zero:
 *   - `daily_report_limit` → integer, min 1.
 *
 * No upper bound is invented: the consumer defines none, so neither do we.
 *
 * The setting key is taken from the route and MUST be a registered managed key;
 * unknown/arbitrary keys are rejected before any write (IDOR / arbitrary-key
 * protection).
 */
class UpdateAppSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Nilai konfigurasi wajib diisi.',
            'value.integer'  => 'Nilai konfigurasi harus berupa bilangan bulat.',
            'value.min'      => 'Nilai konfigurasi minimal 1.',
        ];
    }

    /**
     * Reject any setting key that is not a registered managed key. This blocks
     * arbitrary configuration creation even via a hand-crafted request.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $key = (string) $this->route('setting');

            if (! app(AppSettingService::class)->isManaged($key)) {
                $validator->errors()->add('value', 'Konfigurasi tidak dikenal atau tidak dapat dikelola.');
            }
        });
    }
}
