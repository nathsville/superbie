<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super Admin — Category ↔ Dinas/Unit mapping sync (Prompt 6, Bagian 2).
 *
 * Server-side guarantees (Prompt 6 §9):
 *   - Only Super Admin may change mapping.
 *   - Every submitted Dinas/Unit id MUST exist (exists rule).
 *   - The category is resolved via route model binding (must exist).
 *   - Duplicate ids are collapsed before sync (the pivot also has a UNIQUE pair).
 *   - Only the bound category's pivot rows are affected (IDOR-safe); complaints
 *     are NEVER touched.
 */
class SyncCategoryMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'dinas_unit_ids'   => ['nullable', 'array'],
            'dinas_unit_ids.*' => ['integer', 'exists:dinas_units,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'dinas_unit_ids.array'   => 'Format pemilihan Dinas/Unit tidak valid.',
            'dinas_unit_ids.*.integer' => 'ID Dinas/Unit tidak valid.',
            'dinas_unit_ids.*.exists'  => 'Dinas/Unit yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * Normalized, de-duplicated list of selected Dinas/Unit ids.
     *
     * @return array<int,int>
     */
    public function normalizedIds(): array
    {
        return collect($this->validated('dinas_unit_ids') ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
