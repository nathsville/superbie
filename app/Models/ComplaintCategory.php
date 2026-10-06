<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'dinas_name',
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

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'category_id');
    }

    /**
     * A category can be mapped to many Dinas/Unit (MANY-TO-MANY).
     * This is the authoritative mapping (FINAL business rule); the legacy
     * `dinas_name` string is retained only for backward compatibility.
     */
    public function dinasUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            DinasUnit::class,
            'category_dinas_unit',
            'category_id',
            'dinas_unit_id'
        )->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * A category that has ever been used by a complaint MUST NOT be hard-deleted
     * (Prompt 6 §6 historical rule). Deactivating it is the correct mechanism.
     *
     * Enforced at the model layer so every code path is protected, not only the
     * dashboard. The DB FK (`complaints.category_id`, ON DELETE SET NULL) remains
     * as defensive behavior but must never be reached through normal app flow —
     * nulling a historical complaint's category would violate the frozen rule.
     */
    public function isUsedByComplaints(): bool
    {
        return $this->complaints()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (ComplaintCategory $category) {
            if ($category->complaints()->exists()) {
                throw new \RuntimeException(
                    'Kategori ini sudah digunakan pada laporan dan tidak dapat dihapus. Nonaktifkan sebagai gantinya.'
                );
            }
        });
    }
}
