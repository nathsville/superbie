<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kecamatan master data (FINAL business rule).
 *
 * Source of truth = BPS. No `is_active` (location master has no lifecycle) and
 * no GIS fields. A Kecamatan owns many Kelurahan and may be referenced by many
 * complaints (as their structured location).
 *
 * Historical integrity: a Kecamatan that is still referenced (by Kelurahan or
 * by complaints) MUST NOT be hard-deleted — see {@see isInUse()}. The deletion
 * guard is enforced at the model layer so every code path is protected, not only
 * the Super Admin dashboard.
 */
class Kecamatan extends Model
{
    use HasFactory;

    protected $table = 'kecamatan';

    protected $fillable = [
        'name',
        'code',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function kelurahan(): HasMany
    {
        return $this->hasMany(Kelurahan::class, 'kecamatan_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'kecamatan_id');
    }

    // ─── Historical-integrity guard ───────────────────────────────────────────

    /**
     * A Kecamatan is "in use" when it still owns Kelurahan OR is referenced by
     * any complaint. Such a record must not be hard-deleted.
     */
    public function isInUse(): bool
    {
        return $this->kelurahan()->exists() || $this->complaints()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (Kecamatan $kecamatan) {
            if ($kecamatan->kelurahan()->exists()) {
                throw new \RuntimeException(
                    'Kecamatan ini masih memiliki Kelurahan dan tidak dapat dihapus. Hapus/ pindahkan Kelurahan terlebih dahulu.'
                );
            }

            if ($kecamatan->complaints()->exists()) {
                throw new \RuntimeException(
                    'Kecamatan ini sudah digunakan pada laporan dan tidak dapat dihapus.'
                );
            }
        });
    }
}
