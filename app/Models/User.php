<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
