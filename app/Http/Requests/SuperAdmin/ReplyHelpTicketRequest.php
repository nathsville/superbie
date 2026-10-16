<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PROMPT TAHAP 2 — Super Admin official reply on a Help Desk ticket.
 *
 * Only `super_admin` may reply. The sender is resolved from the authenticated
 * user; no sender field is read from the request.
 */
class ReplyHelpTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Isi balasan wajib diisi.',
            'body.min' => 'Isi balasan tidak boleh kosong.',
            'body.max' => 'Isi balasan maksimal 5000 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }
}
