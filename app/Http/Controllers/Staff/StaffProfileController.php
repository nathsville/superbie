<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateStaffProfileRequest;
use App\Models\AuditLog;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Staff self-service profile (operator / admin / super_admin).
 *
 * A staff member may edit their OWN non-privileged contact fields (name, phone
 * number), their profile photo, and their own password. Privileged fields —
 * `role`, `dinas_unit_id`, `email`, and `nik` — are NEVER editable here: they
 * remain Super-Admin-only via User Management (Prompt 9/21) and are additionally
 * enforced at the model layer (User::booted). Every value rendered is the
 * authenticated user's own stored data; nothing is fabricated.
 */
class StaffProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        // Dinas/Unit is meaningful for operators only (Prompt 15 — one Operator
        // → exactly one Dinas/Unit). Resolved lazily so the page never issues an
        // unnecessary query for admin/super_admin.
        $dinasUnit = $user->isOperator() ? $user->dinasUnit : null;

        return view('staff.profile.show', [
            'user'      => $user,
            'dinasUnit' => $dinasUnit,
        ]);
    }

    /**
     * Update the authenticated staff member's own profile.
     *
     * Scope is intentionally narrow (name, phone_number, avatar, password).
     * `role`/`dinas_unit_id`/`email`/`nik` are not read from the request at all,
     * so even a hand-crafted payload cannot escalate privileges from here.
     */
    public function update(UpdateStaffProfileRequest $request, ProfilePhotoService $photos): RedirectResponse
    {
        $user = $request->user();

        $passwordChanged = false;

        if ($request->filled('password')) {
            if (! Hash::check((string) $request->current_password, $user->password)) {
                return back()->withErrors([
                    'current_password' => 'Kata sandi saat ini yang Anda masukkan salah.',
                ])->withInput();
            }

            $user->password = Hash::make((string) $request->password);
            $passwordChanged = true;
        }

        $user->name = $request->string('name')->trim()->value();

        if ($request->filled('phone_number')) {
            $user->phone_number = $this->normalizePhone((string) $request->phone_number);
        }

        if ($request->hasFile('avatar')) {
            $photos->store($user, $request->file('avatar'));
        }

        $user->save();

        // Security (F-18-06): after a password change, invalidate every OTHER
        // authenticated session for this user. The current session is kept alive
        // (AuthenticateSession re-hashes this session's stored hash).
        if ($passwordChanged) {
            Auth::logoutOtherDevices((string) $request->password);
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
                    $request->hasFile('avatar') ? 'avatar' : null,
                    $passwordChanged ? 'password' : null,
                ])),
            ],
        ]);

        return back()->with('success', 'Profil Anda berhasil diperbarui.');
    }

    /**
     * Normalize an Indonesian phone number to canonical local form (08…).
     * Mirrors Citizen\ProfileController::normalizePhone.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with((string) $digits, '62')) {
            $digits = '0'.substr((string) $digits, 2);
        }

        return (string) $digits;
    }
}
