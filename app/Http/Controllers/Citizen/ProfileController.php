<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the profile edit form for the authenticated citizen.
     */
    public function edit(Request $request): View
    {
        return view('citizen.profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update citizen profile information and/or password.
     */
    public function update(Request $request, ProfilePhotoService $photos): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone_number' => [
                'required', 'string', 'max:20',
                'regex:/^(\+62|62|0)8[0-9]{7,13}$/',
                Rule::unique('users', 'phone_number')->ignore($user->id),
            ],
            'address' => ['required', 'string'],
            'avatar' => [
                'nullable', 'file', 'image',
                'mimes:'.implode(',', ProfilePhotoService::MIMES),
                'max:'.ProfilePhotoService::MAX_KILOBYTES,
            ],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 120 karakter.',
            'phone_number.required' => 'Nomor HP wajib diisi.',
            'phone_number.regex' => 'Format nomor HP tidak valid. Gunakan format Indonesia (contoh: 08xxxxxxxxxx atau +62xxxxxxxxxx).',
            'phone_number.unique' => 'Nomor HP sudah terdaftar pada akun lain.',
            'address.required' => 'Alamat wajib diisi.',
            'avatar.image' => 'Berkas foto harus berupa gambar.',
            'avatar.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'avatar.max' => 'Ukuran foto maksimal 2 MB.',
            'current_password.required_with' => 'Masukkan kata sandi saat ini untuk mengubah kata sandi baru.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $passwordChanged = false;

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors([
                    'current_password' => 'Kata sandi saat ini yang Anda masukkan salah.',
                ])->withInput();
            }

            $user->password = Hash::make($request->password);
            $passwordChanged = true;
        }

        $user->name = $request->name;
        $user->phone_number = $this->normalizePhone($request->phone_number);
        $user->address = trim($request->address);

        if ($request->hasFile('avatar')) {
            $photos->store($user, $request->file('avatar'));
        }

        // NIK is intentionally NOT updated here: it is immutable after account
        // creation (FINAL business rule). Even if a client submits a "nik" field,
        // it is ignored — and the User model also rejects NIK mutation.
        $user->save();

        // Security (F-18-06): after a password change, invalidate every OTHER
        // authenticated session for this user so a stolen/older session cannot
        // outlive the credential rotation. The current session is intentionally
        // kept alive (logoutOtherDevices re-hashes and updates this session's
        // stored password hash via the auth.session middleware). Login/remember
        // behavior is unchanged.
        if ($passwordChanged) {
            Auth::logoutOtherDevices($request->password);
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'profile_updated',
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'updated_fields' => array_values(array_filter([
                    $request->filled('name') ? 'name' : null,
                    $request->filled('phone_number') ? 'phone_number' : null,
                    $request->filled('address') ? 'address' : null,
                    $request->hasFile('avatar') ? 'avatar' : null,
                    $passwordChanged ? 'password' : null,
                ])),
            ],
        ]);

        return back()->with('success', 'Profil Anda berhasil diperbarui.');
    }

    /**
     * Normalize an Indonesian phone number to canonical local form (08…).
     * See RegisteredUserController::normalizePhone for the accepted inputs.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        }

        return $digits;
    }
}
