<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Super Admin — Create user validation (Prompt 9).
 *
 * Only Super Admin may create accounts (route middleware `role:super_admin`
 * enforces this too; authorization is never UI-only).
 *
 * Field set is limited to what the EXISTING `users` schema/model supports and
 * what the administrative flow needs: name, email, password, role, is_active.
 * No new column is invented. `nik`/`phone_number`/`address` are citizen identity
 * fields managed through registration/profile, not through this admin flow.
 *
 * Role is validated against the UserRole enum (source of truth): only the four
 * active roles are accepted. `petugas` and any unknown value are rejected
 * server-side — a frontend dropdown is never trusted.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role'     => ['required', 'string', Rule::in(UserRole::values())],
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
            'name.required'     => 'Nama pengguna wajib diisi.',
            'name.max'          => 'Nama pengguna maksimal 120 karakter.',
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'email.unique'      => 'Email sudah digunakan oleh akun lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'role.required'     => 'Role wajib dipilih.',
            'role.in'           => 'Role yang dipilih tidak valid.',
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
