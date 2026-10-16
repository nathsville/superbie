<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelurahan master data (FINAL business rule).
     *
     * A Kelurahan belongs to exactly ONE Kecamatan (Kecamatan 1 ── N Kelurahan).
     * Structure only — no official rows are seeded (BPS dataset not yet provided).
     *
     * Deliberately NO `is_active` and NO GIS fields.
     *
     * FK behavior: RESTRICT. A Kecamatan that still owns Kelurahan must not be
     * deleted silently — the application layer blocks this first (model guard)
     * and the DB constraint is the defensive backstop.
     */
    public function up(): void
    {
        Schema::create('kelurahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kecamatan_id')
                ->constrained('kecamatan')
                ->restrictOnDelete();
            $table->string('name', 150);
            // Optional code (e.g. a BPS area code). Unique when present.
            $table->string('code', 50)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelurahan');
    }
};
