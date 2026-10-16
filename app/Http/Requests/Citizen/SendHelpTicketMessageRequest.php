<?php

namespace App\Http\Requests\Citizen;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PROMPT TAHAP 2 — Citizen reply / reopen message.
 *
 * Used by BOTH the reply endpoint and the reopen endpoint. The sender is ALWAYS
 * the authenticated user (resolved in the controller); no sender/owner field is
 * ever read from the request, so a citizen cannot impersonate staff.
 */
class SendHelpTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'masyarakat';
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
            'body.required' => 'Isi pesan wajib diisi.',
            'body.min' => 'Isi pesan tidak boleh kosong.',
            'body.max' => 'Isi pesan maksimal 5000 karakter.',
        ];
    }

    /**
     * Trim surrounding whitespace and reject whitespace-only bodies. The `min:1`
     * rule alone would accept a single space, so the trimmed value is what gets
     * validated and persisted.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }
}
