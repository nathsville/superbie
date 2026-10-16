<?php

namespace App\Http\Requests\SuperAdmin;

use App\Services\Weather\WeatherSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Super Admin — update a managed WINDY weather setting.
 *
 * Authorization: Super Admin only (route middleware enforces this too).
 *
 * The setting key is taken from the route and MUST be a registered managed key;
 * unknown/arbitrary keys are rejected before any write (arbitrary-key
 * protection).
 *
 * Validation mirrors the runtime consumer (RiskEvaluationService): a threshold
 * is a non-negative number. No upper bound is invented.
 */
class UpdateWeatherSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Nilai ambang wajib diisi.',
            'value.numeric' => 'Nilai ambang harus berupa angka.',
            'value.min' => 'Nilai ambang tidak boleh negatif.',
        ];
    }

    /**
     * Reject any setting key that is not a registered managed key.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $key = (string) $this->route('setting');

            if (! app(WeatherSettingService::class)->isManaged($key)) {
                $validator->errors()->add('value', 'Ambang cuaca tidak dikenal atau tidak dapat dikelola.');
            }
        });
    }
}
