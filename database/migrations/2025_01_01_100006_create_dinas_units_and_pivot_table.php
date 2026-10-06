<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Category ↔ Dinas/Unit is MANY-TO-MANY (FINAL business rule).
     *
     * Adds:
     *   - dinas_units           : entity identity + active flag
     *   - category_dinas_unit   : pivot (category_id, dinas_unit_id) unique pair
     *
     * NON-DESTRUCTIVE: the legacy `complaint_categories.dinas_name` string is
     * left intact (no data loss). It is no longer the authoritative mapping;
     * the pivot table is. Official Dinas/Unit records are NOT seeded here —
     * the official list is not yet provided (Prompt 5B §F, §U.2).
     */
    public function up(): void
    {
        Schema::create('dinas_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->nullable()->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_dinas_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('complaint_categories')
                ->cascadeOnDelete();
            $table->foreignId('dinas_unit_id')
                ->constrained('dinas_units')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'dinas_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_dinas_unit');
        Schema::dropIfExists('dinas_units');
    }
};
