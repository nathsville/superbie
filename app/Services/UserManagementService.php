<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * UserManagementService — Super Admin user management domain rules (Prompt 9).
 *
 * Keeps the "last active super admin" integrity rule in ONE place so it cannot
 * be bypassed by a controller, a Blade button, or a hand-crafted HTTP request.
 *
 * SECURITY / INTEGRITY RULE (Prompt 9 §18–§21):
 *   The application must never lose every active Super Admin account.
 *   Therefore an ACTIVE `super_admin` who is the last active Super Admin
 *   MUST NOT be deactivated and MUST NOT be downgraded to another role.
 *   The rule is evaluated against live DB state inside a transaction, so it
 *   also protects a Super Admin editing their own account (self-lockout).
 */
class UserManagementService
{
    /**
     * Roles that may be assigned / filtered through User Management.
     * Delegates to the UserRole enum (single source of truth) so the historical
     * `petugas` role can never be offered or assigned.
     *
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        return \App\Enums\UserRole::values();
    }

    /**
     * Would applying ($newRole, $newIsActive) to $user leave ZERO active
     * Super Admins? If so the operation must be rejected.
     *
     * CONCURRENCY (Prompt 16): pass `$lock = true` to run the check with a row
     * lock. The authoritative check MUST be performed with `$lock = true` from
     * INSIDE the same DB transaction that performs the mutation, immediately
     * before the write. This closes a Time-Of-Check-to-Time-Of-Use window in
     * which two concurrent mutations could each observe "2 active super admins"
     * and both proceed, leaving the system with ZERO active Super Admins.
     *
     * Locking strategy: `SELECT ... FOR UPDATE` on the WHOLE set of active Super
     * Admins. Two concurrent mutations therefore try to lock the SAME rows and
     * serialize on the database; the second, once unblocked, re-reads the latest
     * committed state and correctly rejects. `orderBy('id')` fixes the lock
     * acquisition order to avoid deadlocks.
     */
    public function wouldRemoveLastActiveSuperAdmin(
        User $user,
        string $newRole,
        bool $newIsActive,
        bool $lock = false,
    ): bool {
        if ($lock) {
            // Read the target's CURRENT persisted state (not the possibly-stale
            // in-memory model). If it is not an ACTIVE Super Admin right now,
            // removing it cannot lower the active-super-admin count, so no lock
            // is needed (this keeps the common user-edit path lock-free).
            $target = User::query()->whereKey($user->getKey())->first();

            if ($target === null || $target->role !== 'super_admin' || ! $target->is_active) {
                return false; // not an active super admin → no invariant at risk
            }

            if ($newRole === 'super_admin' && $newIsActive) {
                return false; // remains an active super admin → always safe
            }

            // Lock the WHOLE set of active Super Admins (ordered for a stable
            // acquisition order → no deadlock), then evaluate. Two concurrent
            // removals serialize on these rows; the loser re-reads committed
            // state and is correctly rejected.
            $activeSuperAdminIds = User::query()
                ->where('role', 'super_admin')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            return count(array_diff($activeSuperAdminIds, [$user->getKey()])) === 0;
        }

        // Only relevant if the target is CURRENTLY an active super admin being
        // deactivated and/or downgraded away from super_admin.
        if ($user->role !== 'super_admin' || ! $user->is_active) {
            return false;
        }

        $stillSuperAdmin = ($newRole === 'super_admin');
        if ($stillSuperAdmin && $newIsActive) {
            return false; // remains an active super admin → always safe
        }

        // Count OTHER active super admins (excluding this account).
        $otherActiveSuperAdmins = User::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->count();

        return $otherActiveSuperAdmins === 0;
    }

    /**
     * Human-readable, safe error message for a rejected integrity operation.
     */
    public function lastSuperAdminMessage(): string
    {
        return 'Setidaknya satu Super Admin aktif harus tetap tersedia. Operasi ini ditolak.';
    }

    /**
     * Paginated + filtered user query for the index screen.
     *
     * Server-side search (name/email), role filter, and active/inactive filter.
     * Never loads the whole table into PHP for filtering.
     *
     * @param  array{search?:?string,role?:?string,status?:?string}  $filters
     */
    public function listQuery(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = User::query()->orderBy('name');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $role = (string) ($filters['role'] ?? '');
        if ($role !== '' && in_array($role, $this->assignableRoles(), true)) {
            $query->where('role', $role);
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->paginate(20)->withQueryString();
    }
}
