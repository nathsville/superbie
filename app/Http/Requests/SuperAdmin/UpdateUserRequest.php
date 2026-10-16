<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Super Admin — Update user validation (Prompt 9).
 *
 * Editable fields: name, email, role, is_active.
 *
 * Password is intentionally NOT an editable field here: administrator-driven
 * password management is not an approved business flow (Prompt 9 §16), so it is
 * neither accepted nor written by this request. The model also keeps `password`
 * out of any incidental mass assignment from this flow.
 *
 * Role is validated against the UserRole enum: only the four active roles are
 * accepted; `petugas`/unknown values are rejected server-side.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'name'  => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role'  => ['required', 'string', Rule::in(UserRole::values())],
            'is_active' => ['nullable', 'boolean'],
            // Prompt 15 — an Operator MUST belong to exactly one Dinas/Unit.
            // PHASE 1 — a Dinas/Unit may be owned by AT MOST ONE Operator. The
            // chosen unit must not already belong to a DIFFERENT Operator; the
            // row being edited is excluded so an idempotent save never conflicts
            // with itself. Scoped to `role = operator` rows (1:1 relationship).
            'dinas_unit_id' => [
                'nullable',
                'integer',
                'exists:dinas_units,id',
                Rule::requiredIf(fn () => $this->input('role') === 'operator'),
                Rule::unique('users', 'dinas_unit_id')
                    ->where(fn ($query) => $query->where('role', 'operator'))
                    ->ignore($userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Nama pengguna wajib diisi.',
            'name.max'       => 'Nama pengguna maksimal 120 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.unique'   => 'Email sudah digunakan oleh akun lain.',
            'role.required'  => 'Role wajib dipilih.',
            'role.in'        => 'Role yang dipilih tidak valid.',
            'dinas_unit_id.required' => 'Dinas/Unit wajib dipilih untuk akun Operator.',
            'dinas_unit_id.exists'   => 'Dinas/Unit yang dipilih tidak valid.',
            'dinas_unit_id.unique'   => 'Dinas/Unit ini sudah dimiliki oleh Operator lain.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // PHASE 1 — only an Operator may carry a Dinas/Unit. For every other
        // role the field is normalised to NULL here, so the 1:1 uniqueness rule
        // (scoped to `role = operator`) can never fire for a non-operator
        // payload. The model layer's own guard remains the single source of
        // truth for the "non-operators never keep a unit" invariant.
        $isOperator = $this->input('role') === 'operator';

        $this->merge([
            'name'      => trim((string) $this->input('name')),
            'email'     => strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
            'dinas_unit_id' => ($isOperator && $this->filled('dinas_unit_id'))
                ? $this->input('dinas_unit_id')
                : null,
        ]);
    }
}
