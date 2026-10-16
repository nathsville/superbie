<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\HelpTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PROMPT TAHAP 2 — Super Admin status change on a Help Desk ticket.
 *
 * The target status must be a value of the `HelpTicketStatus` enum; the LEGALITY
 * of the transition (from the ticket's CURRENT status) is enforced in the
 * controller against the enum's explicit transition matrix.
 */
class UpdateHelpTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(static fn (HelpTicketStatus $s) => $s->value, HelpTicketStatus::cases())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status baru.',
            'status.in' => 'Status tiket yang dipilih tidak valid.',
        ];
    }
}
