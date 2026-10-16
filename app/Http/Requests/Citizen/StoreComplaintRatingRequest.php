<?php

namespace App\Http\Requests\Citizen;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 3 — Bagian 2 — Citizen satisfaction rating submission (1–5 stars),
 * with an OPTIONAL written review.
 *
 * Deliberately accepts ONLY `rating` and the optional `review_text`. The
 * complaint is resolved from route model binding and ownership is validated
 * server-side against `complaints.reporter_id` — `complaint_id`/`user_id` are
 * NEVER read from the request body as a source of ownership (that would be an
 * IDOR).
 */
class StoreComplaintRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'masyarakat';
    }

    public function rules(): array
    {
        return [
            // 1–5 stars. Integer only: rejects 0, 6, negatives, decimals and
            // arbitrary strings. `required` rejects a missing/NULL rating.
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            // OPTIONAL written review. NULL/absent is valid (a rating without a
            // review). Bound length matches the other free-text fields (5000).
            // No HTML is trusted — the value is stored as-is and rendered through
            // Blade escaping. MODERATION is NOT implemented (prd.md §4 TODO).
            'review_text' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Pilih rating kepuasan Anda.',
            'rating.integer'  => 'Rating harus berupa angka 1 sampai 5.',
            'rating.min'      => 'Rating minimal 1 bintang.',
            'rating.max'      => 'Rating maksimal 5 bintang.',
            'review_text.string' => 'Ulasan harus berupa teks.',
            'review_text.max'    => 'Ulasan maksimal 5000 karakter.',
        ];
    }
}
