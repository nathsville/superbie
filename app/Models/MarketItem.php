<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item (barang) priced in the market module — PHASE S (native SIPHP market
 * module). Managed by Super Admin only; the official list is staff-entered
 * and never seeded.
 *
 * Historical integrity: an item that still has price rows MUST NOT be
 * hard-deleted — see {@see isInUse()}. Deactivate it instead.
 */
class MarketItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketItemCategory::class, 'category_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MarketPrice::class, 'item_id');
    }

    // ─── Historical-integrity guard ───────────────────────────────────────────

    public function isInUse(): bool
    {
        return $this->prices()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (MarketItem $item) {
            if ($item->prices()->exists()) {
                throw new \RuntimeException(
                    'Barang ini sudah memiliki data harga dan tidak dapat dihapus. Nonaktifkan barang sebagai gantinya.'
                );
            }
        });
    }
}
