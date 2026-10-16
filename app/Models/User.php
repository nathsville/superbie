<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'nik',
        'phone_number',
        'address',
        'avatar_path',
        'password',
        'role',
        'is_active',
        'dinas_unit_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ─── Boot: enforce FINAL business rules at the model layer ────────────────
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            // NIK, once set, cannot be changed through any code path (including
            // malicious HTTP payloads). Attempting to change it is rejected.
            if ($user->isDirty('nik') && $user->getOriginal('nik') !== null) {
                $user->nik = $user->getOriginal('nik');
            }
        });

        static::saving(function (User $user) {
            // D-2 / Prompt 15 invariant (schema.md §4.1):
            //   "Only meaningful for `operator`; forced NULL for every other role."
            //   One Operator → exactly one Dinas/Unit (`users.dinas_unit_id`).
            //
            // Enforced HERE, at the model layer, so that EVERY persistence path
            // is protected — the Super Admin User Management Form Requests, a
            // hand-crafted payload, a seeder, or any future endpoint — never
            // only the dashboard. This mirrors the NIK guard above and the
            // "enforced at the model layer" precedent used by DinasUnit.
            //
            // It does NOT require a unit for an Operator: an Operator without a
            // unit is a valid (but denied-scope) state, per Prompt 15 BDR-3 and
            // Prompt 21 §14 — no global fallback and no forced assignment.
            if ($user->role !== 'operator') {
                $user->dinas_unit_id = null;

                return;
            }

            // PHASE 1 — Dinas/Unit ↔ Operator is ONE-TO-ONE.
            // A Dinas/Unit may be owned by AT MOST ONE Operator. Reject (never
            // silently reassign) any save that would give a second Operator the
            // same unit. The UNIQUE index `users_dinas_unit_id_unique` is the
            // database backstop; this guard gives a clear, catchable error and
            // also protects direct model writes that bypass the Form Requests.
            // Excludes the row being saved so an idempotent update never
            // conflicts with itself.
            if ($user->dinas_unit_id !== null) {
                $conflict = static::query()
                    ->where('role', 'operator')
                    ->where('dinas_unit_id', $user->dinas_unit_id)
                    ->when($user->exists, fn ($q) => $q->whereKeyNot($user->getKey()))
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'dinas_unit_id' => 'Dinas/Unit ini sudah dimiliki oleh Operator lain. Lepaskan Operator tersebut terlebih dahulu sebelum menetapkan yang baru.',
                    ]);
                }
            }
        });
    }

    // ─── Role helpers ─────────────────────────────────────────────────────────

    public function isMasyarakat(): bool
    {
        return $this->role === 'masyarakat';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['operator', 'admin', 'super_admin'], true);
    }

    // ─── Profile photo ────────────────────────────────────────────────────────

    /**
     * Publicly resolvable URL for the uploaded profile photo, or NULL when the
     * account has none. The stored value is a relative path on the `public`
     * disk; nothing is fabricated — no photo means the UI shows initials.
     *
     * The URL is returned ROOT-RELATIVE (e.g. `/storage/avatars/1/x.jpg`) rather
     * than absolute. The `public` disk otherwise builds an absolute URL from
     * APP_URL, which in this deployment is a LAN/hotspot address that the
     * browser cannot reach — producing a broken image. A root-relative URL
     * always resolves against the origin actually serving the page.
     */
    public function avatarUrl(): ?string
    {
        $path = trim((string) ($this->avatar_path ?? ''));

        if ($path === '') {
            return null;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        $parts = parse_url($url);
        $relative = (string) ($parts['path'] ?? '');

        if ($relative === '') {
            return $url; // unexpected shape — keep the raw URL
        }

        if (isset($parts['query'])) {
            $relative .= '?' . $parts['query'];
        }

        return $relative;
    }

    /**
     * Single-letter initial used by the fallback avatar (never fabricated data —
     * derived purely from the account's own name).
     */
    public function initial(): string
    {
        return strtoupper(substr(trim((string) $this->name), 0, 1)) ?: '?';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The Dinas/Unit this account belongs to.
     *
     * Meaningful for Operators (Prompt 15 — BDR-1 = 1a): an Operator belongs to
     * EXACTLY ONE Dinas/Unit, which defines the complaints it may see/act on.
     * NULL means the Operator has no scope → DENIED (no global fallback).
     */
    public function dinasUnit(): BelongsTo
    {
        return $this->belongsTo(DinasUnit::class, 'dinas_unit_id');
    }

    /**
     * PHASE 1 — restrict a query to Operator accounts.
     */
    public function scopeOperator($query)
    {
        return $query->where('role', 'operator');
    }

    /**
     * PHASE 1 — the single Operator that owns a given Dinas/Unit, or NULL.
     *
     * Source of truth is `users.dinas_unit_id` (never `complaints.assigned_to`,
     * never a name, never a category mapping). Because the relationship is 1:1
     * this returns at most one account; the UNIQUE index guarantees it.
     */
    public static function operatorForDinas(int $dinasUnitId): ?self
    {
        return static::query()
            ->where('role', 'operator')
            ->where('dinas_unit_id', $dinasUnitId)
            ->first();
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    /**
     * PHASE 3 — Bagian 2 — satisfaction ratings this user (as a reporter) has
     * given. At most one per complaint.
     */
    public function complaintRatings(): HasMany
    {
        return $this->hasMany(ComplaintRating::class, 'user_id');
    }

    /**
     * @deprecated LEGACY (PHASE 6C — Decision 2). Relies on the deprecated
     * `complaints.assigned_to` column, which is never written by any active
     * workflow. Do NOT use for active assignment, routing, metrics, filters, or
     * authorization. Retained only for backward compatibility / historical
     * inspection.
     */
    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    /**
     * PHASE N4 — in-app notifications addressed to this account.
     *
     * Named `appNotifications()` (NOT `notifications()`) to avoid shadowing the
     * framework `Notifiable` trait's polymorphic `notifications()` relation,
     * which this project does not use. Every notification query in the UI is
     * owner-scoped through this relation.
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'recipient_user_id');
    }

    /**
     * PROMPT TAHAP 2 — Help Desk tickets owned by this citizen. Owner-scoped by
     * construction: the citizen UI always queries through this relation so a
     * citizen can never read another user's ticket.
     */
    public function helpTickets(): HasMany
    {
        return $this->hasMany(HelpTicket::class, 'user_id');
    }

    /**
     * PROMPT TAHAP 2 — Help Desk messages authored by this account.
     */
    public function helpTicketMessages(): HasMany
    {
        return $this->hasMany(HelpTicketMessage::class, 'user_id');
    }
}
