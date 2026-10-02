<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 120 karakter.',
            'current_password.required_with' => 'Masukkan kata sandi saat ini untuk mengubah kata sandi baru.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors([
                    'current_password' => 'Kata sandi saat ini yang Anda masukkan salah.',
                ])->withInput();
            }

            $user->password = Hash::make($request->password);
        }

        $user->name = $request->name;
        $user->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'profile_updated',
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'updated_fields' => array_keys($request->only(['name', 'password'])),
            ],
        ]);

        return back()->with('success', 'Profil Anda berhasil diperbarui.');
    }
}
