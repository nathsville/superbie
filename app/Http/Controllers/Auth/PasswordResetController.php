<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Password reset controller.
 * Note: Requires email provider to be configured.
 * TODO: Define requirement — email provider for production.
 */
class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Use generic response to prevent user enumeration
        return back()->with('status', 'Jika email terdaftar, instruksi reset password telah dikirimkan.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password'       => \Illuminate\Support\Facades\Hash::make($request->password),
                    'remember_token' => \Illuminate\Support\Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PasswordReset) {
            return redirect()->route('login')
                ->with('status', 'Password berhasil direset. Silakan login.');
        }

        // Audit PROMPT_28 (S-01): `__($status)` would surface `passwords.user`
        // ("We can't find a user with that email address.") for unknown
        // emails — an enumeration oracle. The throttled state is safe to
        // state (rate limit, not identity); every other failure collapses
        // into one generic message (decision recorded in prd.md, 2026-10-18).
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'Terlalu banyak percobaan. Coba lagi sebentar lagi.']);
        }

        return back()->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa.']);
    }
}
