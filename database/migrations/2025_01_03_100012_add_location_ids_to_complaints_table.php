<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add STRUCTURED location to complaints (FINAL business rule).
     *
     *   - kecamatan_id : FK -> kecamatan.id
     *   - kelurahan_id : FK -> kelurahan.id
     *
     * BOTH ARE NULLABLE — deliberately. Existing complaints predate this
     * feature and MUST NOT be broken. Legacy rows keep kecamatan_id = NULL and
     * kelurahan_id = NULL; they are NEVER back-filled (guessing a wilayah from
     * `location_text` is forbidden). The REQUIRED rule applies to NEW complaints
     * only, and is enforced in the Form Request, not at the column level.
     *
     * `location_text` is LEFT UNTOUCHED (kept as the free-form detail/additional
     * location). Nothing here modifies or removes it.
     *
     * ON DELETE: RESTRICT. A Kecamatan/Kelurahan that is referenced by a
     * complaint must never be hard-deleted (that would silently strip the
     * complaint of its location). The application layer blocks such deletion
     * first (model guard + controller guard); the DB constraint is the
     * defensive backstop. Complaints are NEVER cascade-deleted.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('kecamatan_id')
                ->nullable()
                ->after('location_text')
                ->constrained('kecamatan')
                ->restrictOnDelete();

            $table->foreignId('kelurahan_id')
                ->nullable()
                ->after('kecamatan_id')
                ->constrained('kelurahan')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelurahan_id');
            $table->dropConstrainedForeignId('kecamatan_id');
        });
    }
};
