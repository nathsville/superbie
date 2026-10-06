<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the complaint's ACTUAL Dinas/Unit destination (Prompt 5C).
     *
     * Business rule:
     *   - The complaint's Category determines the *valid set* of Dinas/Unit.
     *   - The Operator chooses the ACTUAL destination from that set.
     *   - The actual destination is STORED on the complaint so historical data
     *     is stable against later mapping changes (no dynamic re-resolution).
     *
     * Column:
     *   - nullable: complaints without a destination yet remain valid.
     *   - FK -> dinas_units.id, ON DELETE SET NULL: deactivating/removing a
     *     master Dinas/Unit must NEVER delete the complaint. The stored name is
     *     preserved for display via the relation's null-safe fallback.
     *
     * `assigned_to` (Operator) is intentionally left untouched — it is a
     * different concept from the organizational destination.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('dinas_unit_id')
                ->nullable()
                ->after('category_id')
                ->constrained('dinas_units')
                ->nullOnDelete();

            $table->index(['dinas_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['dinas_unit_id', 'status']);
            $table->dropConstrainedForeignId('dinas_unit_id');
        });
    }
};
