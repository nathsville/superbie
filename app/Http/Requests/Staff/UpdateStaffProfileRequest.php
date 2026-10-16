<?php

namespace App\Http\Requests\Staff;

use App\Services\ProfilePhotoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff self-service profile update (operator / admin / super_admin).
 *
 * SELF-SERVICE SCOPE ONLY. A staff member may edit their OWN non-privileged
 * contact fields (name, phone number), their profile photo, and their own
 * password. They may NEVER change their `role`, `dinas_unit_id`, `email`, or
 * `nik` here — those remain Super-Admin-only via User Management (Prompt 9/21)
 * and are additionally enforced at the model layer (User::booted) and by the
 * absence of any such input in the view. Authorization is never UI-only:
 * `authorize()` re-checks the authenticated account is an active staff member.
 */
class UpdateStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isStaff() && $user->is_active;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone_number' => [
                'nullable', 'string', 'max:20',
                'regex:/^(\+62|62|0)8[0-9]{7,13}$/',
                Rule::unique('users', 'phone_number')->ignore($userId),
            ],
            'avatar' => [
                'nullable', 'file', 'image',
                'mimes:'.implode(',', ProfilePhotoService::MIMES),
                'max:'.ProfilePhotoService::MAX_KILOBYTES,
            ],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 120 karakter.',
            'phone_number.regex' => 'Format nomor HP tidak valid. Gunakan format Indonesia (contoh: 08xxxxxxxxxx atau +62xxxxxxxxxx).',
            'phone_number.unique' => 'Nomor HP sudah terdaftar pada akun lain.',
            'avatar.image' => 'Berkas foto harus berupa gambar.',
            'avatar.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'avatar.max' => 'Ukuran foto maksimal 2 MB.',
            'current_password.required_with' => 'Masukkan kata sandi saat ini untuk mengubah kata sandi baru.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ];
    }
}
