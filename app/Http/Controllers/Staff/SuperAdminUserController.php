<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreUserRequest;
use App\Http\Requests\SuperAdmin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\DinasUnit;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Super Admin — User Management (Prompt 9).
 *
 * Scope: list/search/filter users, create, edit, activate/deactivate.
 * Authorization: route middleware `role:super_admin` + Form Request `authorize()`.
 * Audit log: project convention `subject_type` = plain string ('user').
 *
 * Safety rules:
 *   - NO hard-delete of users (accounts are deactivated, preserving history).
 *   - NO administrator password management flow (not an approved business rule).
 *   - The "last active super admin" integrity rule is enforced server-side in
 *     UserManagementService, inside a transaction.
 */
class SuperAdminUserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $users,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->get('search'),
            'role'   => $request->get('role'),
            'status' => $request->get('status'),
        ];

        $users = $this->users->listQuery($filters);
        $roles = $this->users->assignableRoles();

        return view('super-admin.users.index', compact('users', 'roles', 'filters'));
    }

    public function create(): View
    {
        return view('super-admin.users.create', [
            'roles'      => $this->users->assignableRoles(),
            'dinasUnits' => DinasUnit::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($request, $validated) {
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'role'      => $validated['role'],
                'is_active' => $validated['is_active'] ?? true,
                // Only Operators carry a Dinas/Unit scope (Prompt 15 — BDR-1 = 1a).
                'dinas_unit_id' => $validated['role'] === 'operator'
                    ? ($validated['dinas_unit_id'] ?? null)
                    : null,
            ]);

            // NOTE: password is NEVER written to the audit log (Prompt 9 §24).
            $this->audit($request, 'user.created', $user, [
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role,
                'is_active' => $user->is_active,
                'dinas_unit_id' => $user->dinas_unit_id,
            ]);

            return $user;
        });

        return redirect()
            ->route('super-admin.users.index')
            ->with('success', 'Pengguna "' . $user->name . '" berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return view('super-admin.users.edit', [
            'user'       => $user,
            'roles'      => $this->users->assignableRoles(),
            'dinasUnits' => DinasUnit::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $newRole     = $validated['role'];
        $newIsActive = $validated['is_active'] ?? $user->is_active;

        $oldRole     = $user->role;
        $oldIsActive = $user->is_active;
        $oldDinasUnitId = $user->dinas_unit_id;

        // Only Operators carry a Dinas/Unit scope; other roles are cleared.
        $newDinasUnitId = $newRole === 'operator'
            ? ($validated['dinas_unit_id'] ?? null)
            : null;

        $violatesLastSuperAdmin = false;

        DB::transaction(function () use ($request, $validated, $user, $oldRole, $oldIsActive, $oldDinasUnitId, $newRole, $newIsActive, $newDinasUnitId, &$violatesLastSuperAdmin) {
            // ── Last active super admin protection (server-side, §18–§21) ─────
            // Prompt 16: the authoritative check runs INSIDE the transaction with
            // a row lock, immediately before the mutation, so two concurrent
            // mutations cannot both observe "2 active super admins" and each
            // proceed — which would leave ZERO active Super Admins (TOCTOU).
            if ($this->users->wouldRemoveLastActiveSuperAdmin($user, $newRole, $newIsActive, lock: true)) {
                $violatesLastSuperAdmin = true;
                return; // abort: no write performed → transaction commits a no-op
            }

            // Password is intentionally NOT updated here (no admin password flow).
            $user->update([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'role'      => $newRole,
                'is_active' => $newIsActive,
                'dinas_unit_id' => $newDinasUnitId,
            ]);

            $this->audit($request, 'user.updated', $user, [
                'name'  => $user->name,
                'email' => $user->email,
            ]);

            if ($oldRole !== $newRole) {
                $this->audit($request, 'user.role_changed', $user, [
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                ]);
            }

            if ((int) $oldDinasUnitId !== (int) $newDinasUnitId) {
                $this->audit($request, 'user.dinas_unit_changed', $user, [
                    'old_dinas_unit_id' => $oldDinasUnitId,
                    'new_dinas_unit_id' => $newDinasUnitId,
                ]);
            }

            if ($oldIsActive !== $newIsActive) {
                $this->audit(
                    $request,
                    $newIsActive ? 'user.activated' : 'user.deactivated',
                    $user,
                    ['is_active' => $newIsActive]
                );
            }
        });

        if ($violatesLastSuperAdmin) {
            return back()
                ->withInput()
                ->withErrors(['role' => $this->users->lastSuperAdminMessage()]);
        }

        return redirect()
            ->route('super-admin.users.index')
            ->with('success', 'Pengguna "' . $user->name . '" berhasil diperbarui.');
    }

    /**
     * Activate / deactivate a user (no role change).
     *
     * Deactivation is the mechanism to revoke access; accounts are never
     * hard-deleted. The last active super admin cannot be deactivated.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $newState = ! $user->is_active;

        $violatesLastSuperAdmin = false;

        DB::transaction(function () use ($request, $user, $newState, &$violatesLastSuperAdmin) {
            // ── Last active super admin protection (server-side, §18–§19) ─────
            // Prompt 16: authoritative check INSIDE the transaction with a row
            // lock, immediately before the mutation (closes the TOCTOU window).
            if ($this->users->wouldRemoveLastActiveSuperAdmin($user, $user->role, $newState, lock: true)) {
                $violatesLastSuperAdmin = true;
                return;
            }

            $user->update(['is_active' => $newState]);

            $this->audit(
                $request,
                $newState ? 'user.activated' : 'user.deactivated',
                $user,
                ['is_active' => $newState]
            );
        });

        if ($violatesLastSuperAdmin) {
            return redirect()
                ->route('super-admin.users.index')
                ->withErrors(['user' => $this->users->lastSuperAdminMessage()]);
        }

        return redirect()
            ->route('super-admin.users.index')
            ->with('success', 'Pengguna "' . $user->name . '" berhasil '
                . ($newState ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Append-only audit entry. Never records secrets (password/token/etc.).
     */
    private function audit(Request $request, string $action, User $user, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'user',
            'subject_id'   => $user->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
