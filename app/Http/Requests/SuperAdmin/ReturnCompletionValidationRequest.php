<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 3 (Bagian 1C) — Super Admin RETURNS a completion to the Operator.
 *
 * A reason/instruction is MANDATORY (min 10 characters, trimmed server-side):
 * it is stored in `complaints.completion_return_reason` — an INTERNAL message
 * shown to the Operator/staff, NEVER to the reporter. It is deliberately
 * separate from `review_return_reason` (the PHASE 2 return-to-citizen message).
 */
class ReturnCompletionValidationRequest extends FormRequest
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
            'reason.required' => 'Alasan pengembalian wajib diisi agar Operator mengetahui hal yang harus diperbaiki.',
            'reason.min'      => 'Alasan pengembalian minimal 10 karakter.',
            'reason.max'      => 'Alasan pengembalian maksimal 2000 karakter.',
        ];
    }
}
