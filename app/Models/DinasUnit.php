<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DinasUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * A Dinas/Unit can be mapped to many categories (MANY-TO-MANY).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ComplaintCategory::class,
            'category_dinas_unit',
            'dinas_unit_id',
            'category_id'
        )->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Complaints whose ACTUAL destination is this Dinas/Unit (Prompt 5C).
     * Historical: complaints keep pointing here even if this unit is deactivated.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'dinas_unit_id');
    }

    /**
     * The single Operator account that owns this Dinas/Unit (PHASE 1 — 1:1).
     *
     * One Dinas/Unit has AT MOST ONE Operator. The relationship is constrained
     * to `role = operator` so a stray non-operator row can never be surfaced as
     * "the operator". NULL means the unit currently has no operator.
     *
     * Backed by `users.dinas_unit_id` (FK, ON DELETE SET NULL) and enforced by
     * the UNIQUE index `users_dinas_unit_id_unique` plus the model-layer guard.
     */
    public function operator(): HasOne
    {
        return $this->hasOne(User::class, 'dinas_unit_id')->where('role', 'operator');
    }

    /**
     * Operator accounts historically linked to this Dinas/Unit.
     *
     * DEPRECATED by the PHASE 1 1:1 rule: a Dinas/Unit may now have at most one
     * Operator. Kept as a convenience alias for the (0..1) set so existing
     * read-only call sites remain valid; new code should use {@see operator()}.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'dinas_unit_id');
    }

    /**
     * A Dinas/Unit that has ever been used as a complaint's ACTUAL destination
     * MUST NOT be hard-deleted (Prompt 5C.1). Super Admin must DEACTIVATE it
     * instead. Historical complaints keep their stored destination intact.
     *
     * Enforced at the model layer so every code path is protected — not only
     * the dashboard. The DB FK (`ON DELETE SET NULL`) remains as defensive
     * behavior but is never reached through normal application flow.
     */
    public function isUsedByComplaints(): bool
    {
        return $this->complaints()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (DinasUnit $dinasUnit) {
            if ($dinasUnit->complaints()->exists()) {
                throw new \RuntimeException(
                    'Dinas/Unit ini sudah digunakan pada laporan dan tidak dapat dihapus. Nonaktifkan sebagai gantinya.'
                );
            }
        });
    }
}
