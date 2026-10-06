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
            'dinas_unit_id' => [
                'nullable',
                'integer',
                'exists:dinas_units,id',
                Rule::requiredIf(fn () => $this->input('role') === 'operator'),
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'      => trim((string) $this->input('name')),
            'email'     => strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
            'dinas_unit_id' => $this->filled('dinas_unit_id') ? $this->input('dinas_unit_id') : null,
        ]);
    }
}
