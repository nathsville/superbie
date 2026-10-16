<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\DinasUnit;
use App\Support\QueryInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDinasUnitRequest extends FormRequest
{
    /**
     * Only Super Admin may manage Dinas/Unit master data.
     * (Route middleware enforces this too; authorization is never UI-only.)
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        // The route may bind an existing DinasUnit for updates.
        $dinasUnit = $this->route('dinas_unit');
        $dinasUnitId = $dinasUnit instanceof DinasUnit ? $dinasUnit->id : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('dinas_units', 'code')->ignore($dinasUnitId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama Dinas/Unit wajib diisi.',
            'name.max' => 'Nama Dinas/Unit maksimal 150 karakter.',
            'code.max' => 'Kode maksimal 50 karakter.',
            'code.unique' => 'Kode Dinas/Unit sudah digunakan.',
            'description.max' => 'Deskripsi maksimal 500 karakter.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Coerce `name`/`code` to trimmed strings ONLY when the input is scalar.
        // A crafted array (`name[]=x`, `code[]=y`) is left untouched so the
        // `string` rule rejects it with a 422 — never cast inline, which would
        // raise "Array to string conversion" (HTTP 500).
        $name = $this->input('name');
        $code = $this->input('code');

        // `is_active`: normalise a scalar to a real boolean; an array is left as
        // the array so the `boolean` rule rejects it (it must never be silently
        // coerced to `false`, which would create an inactive unit).
        $isActive = $this->input('is_active');
        if ($isActive === null) {
            $normalisedActive = false;               // absent → inactive (legacy default)
        } elseif (is_scalar($isActive)) {
            $normalisedActive = $this->boolean('is_active');
        } else {
            $normalisedActive = $isActive;
        }

        $this->merge([
            'name'      => is_scalar($name) ? trim((string) $name) : $name,
            'code'      => is_scalar($code) ? ($this->filled('code') ? trim((string) $code) : null) : $code,
            'is_active' => $normalisedActive,
        ]);
    }
}
