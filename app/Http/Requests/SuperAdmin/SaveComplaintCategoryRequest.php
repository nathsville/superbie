<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\ComplaintCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Super Admin — Category master data validation (Prompt 6).
 *
 * Only Super Admin may manage Category master data.
 * (Route middleware enforces this too; authorization is never UI-only.)
 *
 * Field set mirrors the EXISTING `complaint_categories` schema — no field is
 * invented. `slug` is intentionally NOT accepted from the request: it is a
 * derived, unique key generated server-side from `name` so clients cannot
 * manipulate it.
 */
class SaveComplaintCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            // Legacy convenience label (Prompt 6 §10). NOT a routing source of
            // truth — routing uses the category_dinas_unit mapping. Retained so
            // existing displays keep working; optional.
            'dinas_name'  => ['nullable', 'string', 'max:150'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.max'      => 'Nama kategori maksimal 100 karakter.',
            'description.max' => 'Deskripsi maksimal 500 karakter.',
            'dinas_name.max'  => 'Label instansi maksimal 150 karakter.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'  => $this->boolean('is_active'),
            'name'       => trim((string) $this->input('name')),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'dinas_name' => $this->filled('dinas_name') ? trim((string) $this->input('dinas_name')) : null,
        ]);
    }
}
