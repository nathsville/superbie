<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelurahan master data (FINAL business rule).
 *
 * A Kelurahan belongs to exactly ONE Kecamatan. No `is_active` and no GIS
 * fields. The server-side invariant "the chosen Kelurahan must belong to the
 * chosen Kecamatan" is enforced in validation (see the complaint Form Request
 * and the Operator location update), never trusted from the frontend.
 *
 * Historical integrity: a Kelurahan referenced by any complaint MUST NOT be
 * hard-deleted — see {@see isInUse()}.
 */
class Kelurahan extends Model
{
    use HasFactory;

    protected $table = 'kelurahan';

    protected $fillable = [
        'kecamatan_id',
        'name',
        'code',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'kelurahan_id');
    }

    // ─── Historical-integrity guard ───────────────────────────────────────────

    public function isInUse(): bool
    {
        return $this->complaints()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (Kelurahan $kelurahan) {
            if ($kelurahan->complaints()->exists()) {
                throw new \RuntimeException(
                    'Kelurahan ini sudah digunakan pada laporan dan tidak dapat dihapus.'
                );
            }
        });
    }
}
