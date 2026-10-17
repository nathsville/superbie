<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Retail store directory entry — PHASE S (native SIPHP market module).
 *
 * Managed by Super Admin only; publicly readable at /siphp. A store may
 * optionally belong to one market (a market removal sets the link to NULL —
 * the store itself is not deleted). `photo_path` points to the PUBLIC disk
 * (D-S7) with a generated filename and server-side MIME/size validation.
 */
class MarketStore extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'name',
        'type',
        'address',
        'phone',
        'opening_hours',
        'photo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Public URL of the store photo, or null when none was uploaded. Nothing
     * is fabricated — the UI falls back to a neutral icon instead.
     */
    public function photoUrl(): ?string
    {
        if (! is_string($this->photo_path) || $this->photo_path === '') {
            return null;
        }

        return Storage::disk((string) config('siphp.store_photo.disk'))->url($this->photo_path);
    }
}
