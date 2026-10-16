<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kecamatan master data (FINAL business rule — Prompt "Master Data Lokasi").
     *
     * Source of truth = BPS. This migration creates the STRUCTURE ONLY; no
     * official Kecamatan rows are seeded here (the official dataset is not yet
     * provided — inventing names is forbidden).
     *
     * Deliberately NO `is_active` (FINAL rule: location master has no
     * active/inactive lifecycle) and NO GIS fields (latitude/longitude/polygon/
     * geometry/postal_code) — GIS is out of scope.
     */
    public function up(): void
    {
        Schema::create('kecamatan', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // Optional code (e.g. a BPS area code). Unique when present; the
            // format is NOT invented here — it is supplied with the official data.
            $table->string('code', 50)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kecamatan');
    }
};
