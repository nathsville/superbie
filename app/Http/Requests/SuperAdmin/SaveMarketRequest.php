<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super Admin — Market (pasar) master data validation (PHASE S / SIPHP module).
 *
 * Only Super Admin may manage market master data. The route middleware
 * `role:super_admin` enforces this too; authorization is never UI-only.
 *
 * `slug` is a derived, server-generated key and is NOT accepted from the
 * request. No uniqueness rule is imposed on `name` — the frozen schema
 * (schema.md §4.12) declares UNIQUE only on `slug`.
 */
class SaveMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'address'   => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pasar wajib diisi.',
            'name.max'      => 'Nama pasar maksimal 150 karakter.',
            'address.max'   => 'Alamat maksimal 255 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'      => trim((string) $this->input('name')),
            'address'   => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
