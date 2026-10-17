<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\MarketItemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Super Admin — Market item category validation (PHASE S / SIPHP module).
 *
 * `slug` is derived server-side; `name` is UNIQUE in the frozen schema
 * (schema.md §4.13) and therefore validated here, ignoring the current row on
 * update.
 */
class SaveMarketItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof MarketItemCategory ? $category->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('market_item_categories', 'name')->ignore($categoryId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori barang wajib diisi.',
            'name.max'      => 'Nama kategori maksimal 100 karakter.',
            'name.unique'   => 'Nama kategori barang sudah digunakan.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
