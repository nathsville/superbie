<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * STAGE 3 — Official Super Admin reply to a citizen's written review.
 *
 * Deliberately accepts ONLY `admin_response`. The complaint is resolved from
 * route model binding and the rating is resolved from that complaint
 * server-side — `rating_id`, `complaint_id`, `user_id` and any identity field
 * are NEVER read from the request body (that would allow forging the responder
 * or the object relationship).
 *
 * The reply must be a non-blank string of at most 5000 characters (the same
 * bound used by the citizen review). Surrounding whitespace is trimmed before
 * validation so a whitespace-only body is rejected as missing; the trimmed
 * value is what gets persisted.
 */
class StoreReviewResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'admin_response' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_response.required' => 'Balasan resmi wajib diisi.',
            'admin_response.string'   => 'Balasan resmi harus berupa teks.',
            'admin_response.max'      => 'Balasan resmi maksimal 5000 karakter.',
        ];
    }

    /**
     * Trim surrounding whitespace so a body made only of spaces fails the
     * `required` rule instead of being stored as blank content.
     *
     * Only SCALAR input is coerced. A crafted array (`admin_response[]=x`) is
     * left untouched so the `string` rule rejects it with a 422 — casting an
     * array inline (`(string) $input`) would raise "Array to string conversion"
     * (HTTP 500).
     */
    protected function prepareForValidation(): void
    {
        $response = $this->input('admin_response');

        $this->merge([
            'admin_response' => is_scalar($response) ? trim((string) $response) : $response,
        ]);
    }
}
