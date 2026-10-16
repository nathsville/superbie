<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 2 — Super Admin returns a complaint to the reporter for correction.
 *
 * A reason/instruction is MANDATORY: it is stored on the complaint as the
 * workflow message shown to the reporter (never an internal-only note) and is
 * audited with the review action.
 */
class ReturnInitialValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pengembalian wajib diisi agar pelapor mengetahui hal yang harus diperbaiki.',
            'reason.min'      => 'Alasan pengembalian minimal 10 karakter.',
            'reason.max'      => 'Alasan pengembalian maksimal 2000 karakter.',
        ];
    }
}
