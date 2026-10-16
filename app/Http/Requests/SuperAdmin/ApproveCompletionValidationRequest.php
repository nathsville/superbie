<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 3 (Bagian 1C) — Super Admin APPROVES a completion validation.
 *
 * Approval carries NO user-supplied routing/status data: it performs the single
 * EXISTING domain transition `in_progress → resolved` and publishes the
 * previously-internal completion narrative as the citizen-facing public
 * response. The public-response text is NEVER typed here — it is taken from the
 * Operator's own internal completion note (see the controller). This request
 * therefore only enforces authorization (Super Admin only, server-side).
 */
class ApproveCompletionValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        // No request fields are trusted. The approval is a workflow decision,
        // not a form payload.
        return [];
    }
}
