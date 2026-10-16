<?php

namespace App\Http\Requests\Citizen;

use App\Enums\HelpDeskCategory;
use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PROMPT TAHAP 2 — Create a Help Desk ticket (citizen only).
 *
 * Security boundaries enforced here (server-side, never UI-only):
 *   - Only `masyarakat` may create a ticket.
 *   - `category` is validated against the EXPLICIT enum allowlist.
 *   - `related_complaint_id` (optional) MUST belong to the authenticated citizen.
 *     A complaint owned by somebody else is rejected WITHOUT revealing whether it
 *     exists (the generic message is identical for "missing" and "not yours").
 *   - `user_id` / `status` are NEVER read from the request — they are set by the
 *     controller, so mass assignment cannot forge ownership or status.
 */
class StoreHelpTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'masyarakat';
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:180'],
            'category' => ['required', 'string', Rule::in(HelpDeskCategory::values())],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            // OPTIONAL context reference. Ownership is checked in withValidator()
            // so a foreign complaint id is rejected without leaking existence.
            'related_complaint_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Judul tiket wajib diisi.',
            'subject.min' => 'Judul tiket minimal 5 karakter.',
            'subject.max' => 'Judul tiket maksimal 180 karakter.',
            'category.required' => 'Pilih kategori bantuan.',
            'category.in' => 'Kategori bantuan yang dipilih tidak valid.',
            'description.required' => 'Deskripsi masalah wajib diisi.',
            'description.min' => 'Deskripsi masalah minimal 10 karakter.',
            'description.max' => 'Deskripsi masalah maksimal 5000 karakter.',
            'related_complaint_id.integer' => 'Referensi laporan tidak valid.',
        ];
    }

    /**
     * Normalise the optional complaint reference and verify OWNERSHIP.
     *
     * A blank value becomes NULL (no reference). A provided value must resolve to
     * a complaint whose `reporter_id` is the authenticated citizen. The failure
     * message is deliberately generic so a non-owned / non-existent id cannot be
     * probed for existence.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('related_complaint_id');
        if ($value === '' || $value === null) {
            $this->merge(['related_complaint_id' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $id = $this->input('related_complaint_id');

            if ($id === null) {
                return;
            }

            $owned = Complaint::whereKey($id)
                ->where('reporter_id', $this->user()->id)
                ->exists();

            if (! $owned) {
                // Same message for "does not exist" and "not yours" — no oracle.
                $validator->errors()->add(
                    'related_complaint_id',
                    'Laporan terkait tidak ditemukan atau bukan milik Anda.'
                );
            }
        });
    }
}
