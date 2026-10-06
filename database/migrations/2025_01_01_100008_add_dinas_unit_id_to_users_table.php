<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the Operator's Dinas/Unit membership (Prompt 15 — BDR-1 = 1a).
     *
     * FINAL, approved business rule:
     *   - Each Operator belongs to EXACTLY ONE Dinas/Unit via `users.dinas_unit_id`.
     *   - An Operator with NO unit is DENIED (sees nothing) — no global fallback.
     *   - This column is the basis of the hybrid operator scope: a complaint is
     *     visible to an Operator when the complaint is routed to the SAME unit,
     *     or when it is still unrouted (NULL destination) AND its category is
     *     mapped to that unit via `category_dinas_unit`.
     *
     * Column:
     *   - nullable: non-operator accounts have no unit. Existing operators keep
     *     NULL (denied) until a Super Admin explicitly assigns a unit — we do NOT
     *     blanket-assign units to existing rows.
     *   - FK -> dinas_units.id, ON DELETE SET NULL: removing a master Dinas/Unit
     *     must NEVER delete the user account (no cascade-delete of users). The
     *     account simply loses scope until reassigned.
     *
     * `assigned_to` (complaint assignment) is a DIFFERENT concept and is left
     * untouched by this migration.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('dinas_unit_id')
                ->nullable()
                ->after('role')
                ->constrained('dinas_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dinas_unit_id');
        });
    }
};
