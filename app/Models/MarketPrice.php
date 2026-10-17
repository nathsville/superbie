<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual market price row — PHASE S (native SIPHP market module).
 *
 * One row per (market × item × effective_date) (D-S6), unique at the DB
 * layer (`market_prices_slot_unique`). Written ONLY by Super Admin through
 * the panel; the public page reads the latest row per item. The app never
 * fills a missing price with a default/synthetic value.
 */
class MarketPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'item_id',
        'price',
        'effective_date',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'item_id');
    }

    /** Staff member who entered the row (audit trail). */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
