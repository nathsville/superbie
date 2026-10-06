<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\DinasUnit;
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
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : null,
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
