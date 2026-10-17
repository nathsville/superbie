<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Market (pasar) master data — PHASE S (native SIPHP market module).
 *
 * Managed by Super Admin only; publicly readable at /siphp (no login). A
 * market owns price rows and store rows.
 *
 * Historical integrity: a market that still has price rows MUST NOT be
 * hard-deleted — see {@see isInUse()}. Deactivate it instead. The guard is
 * enforced at the model layer so every code path is protected, not only the
 * Super Admin dashboard.
 */
class Market extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function prices(): HasMany
    {
        return $this->hasMany(MarketPrice::class, 'market_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(MarketStore::class, 'market_id');
    }

    // ─── Historical-integrity guard ───────────────────────────────────────────

    /**
     * A market is "in use" when it still has price history. Such a record
     * must not be hard-deleted (price history is the module's data trail).
     */
    public function isInUse(): bool
    {
        return $this->prices()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (Market $market) {
            if ($market->prices()->exists()) {
                throw new \RuntimeException(
                    'Pasar ini sudah memiliki data harga dan tidak dapat dihapus. Nonaktifkan pasar sebagai gantinya.'
                );
            }
        });
    }
}
