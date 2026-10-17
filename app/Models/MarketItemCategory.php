<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item category master (barang pokok / subsidi / penting) — PHASE S (native
 * SIPHP market module). Managed by Super Admin only.
 *
 * Seeded with the three observed categories only (ERD_SIPHP_PAREPARE.md);
 * further categories are data, never invented in code.
 *
 * A category that still owns items MUST NOT be hard-deleted — see
 * {@see isInUse()}.
 */
class MarketItemCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(MarketItem::class, 'category_id');
    }

    // ─── Historical-integrity guard ───────────────────────────────────────────

    public function isInUse(): bool
    {
        return $this->items()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (MarketItemCategory $category) {
            if ($category->items()->exists()) {
                throw new \RuntimeException(
                    'Kategori barang ini masih memiliki barang dan tidak dapat dihapus.'
                );
            }
        });
    }
}
