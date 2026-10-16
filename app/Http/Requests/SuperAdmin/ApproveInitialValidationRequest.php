<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 2 — Super Admin approves initial validation.
 *
 * The Super Admin may correct the category and MUST choose a valid, active
 * Dinas/Unit that is mapped to the FINAL category. Cross-field business rules
 * (mapping, active, has-operator) are enforced in the controller inside the
 * approval transaction so they are atomic with the writes.
 */
class ApproveInitialValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'category_id'   => ['required', 'integer', 'exists:complaint_categories,id'],
            'dinas_unit_id' => ['required', 'integer', 'exists:dinas_units,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'   => 'Kategori final wajib ditentukan.',
            'category_id.exists'     => 'Kategori yang dipilih tidak valid.',
            'dinas_unit_id.required' => 'Dinas/Unit tujuan wajib dipilih.',
            'dinas_unit_id.exists'   => 'Dinas/Unit yang dipilih tidak ditemukan.',
        ];
    }
}
