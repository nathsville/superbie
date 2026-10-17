<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super Admin — Market item (barang) validation (PHASE S / SIPHP module).
 *
 * The item must belong to an existing category. `name` has no uniqueness rule:
 * the frozen schema (schema.md §4.14) indexes it but does not declare UNIQUE.
 */
class SaveMarketItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:market_item_categories,id'],
            'name'        => ['required', 'string', 'max:150'],
            'unit'        => ['nullable', 'string', 'max:50'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori barang wajib dipilih.',
            'category_id.exists'   => 'Kategori barang yang dipilih tidak ditemukan.',
            'name.required'        => 'Nama barang wajib diisi.',
            'name.max'             => 'Nama barang maksimal 150 karakter.',
            'unit.max'             => 'Satuan maksimal 50 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'      => trim((string) $this->input('name')),
            'unit'      => $this->filled('unit') ? trim((string) $this->input('unit')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
