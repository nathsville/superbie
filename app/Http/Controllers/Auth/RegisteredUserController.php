<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $nikLength = (int) config('business_rules.identity.nik_length');

        $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'nik'      => ['required', 'digits:' . $nikLength, 'unique:users,nik'],
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^(\+62|62|0)8[0-9]{7,13}$/', 'unique:users,phone_number'],
            'address'  => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'nik.required'   => 'NIK wajib diisi.',
            'nik.digits'     => "NIK harus terdiri dari {$nikLength} digit angka.",
            'nik.unique'     => 'NIK sudah terdaftar.',
            'phone_number.required' => 'Nomor HP wajib diisi.',
            'phone_number.regex'    => 'Format nomor HP tidak valid. Gunakan format Indonesia (contoh: 08xxxxxxxxxx atau +62xxxxxxxxxx).',
            'phone_number.unique'   => 'Nomor HP sudah terdaftar.',
            'address.required'      => 'Alamat wajib diisi.',
        ]);

        $user = User::create([
            'name'     => trim($request->name),
            'email'    => strtolower(trim($request->email)),
            'nik'      => $request->nik,
            'phone_number' => $this->normalizePhone($request->phone_number),
            'address'  => trim($request->address),
            'password' => Hash::make($request->password),
            'role'     => 'masyarakat', // Registration always creates masyarakat role
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('citizen.dashboard');
    }

    /**
     * Normalize an Indonesian phone number to a canonical local form (08…).
     * Accepted inputs: 08xxxxxxxxxx, 628xxxxxxxxxx, +628xxxxxxxxxx.
     * This guarantees a locally- and internationally-written number representing
     * the same line cannot create duplicate identity (FINAL business rule).
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
