<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 1 — Operator ↔ Dinas/Unit is ONE-TO-ONE.
     *
     * FINAL, approved business rule:
     *   - Each Operator belongs to EXACTLY ONE Dinas/Unit (`users.dinas_unit_id`).
     *   - Each Dinas/Unit has AT MOST ONE Operator for its operational ownership.
     *
     * This migration adds the missing DATABASE guarantee for the second half of
     * that rule: a UNIQUE index on `users.dinas_unit_id`.
     *
     * Why a unique index is safe & correct:
     *   - MySQL treats every NULL as distinct, so the many non-operator rows
     *     (role != operator → dinas_unit_id forced NULL at the model layer) and
     *     any number of Operators WITHOUT a unit never collide.
     *   - Only two rows that BOTH point at the same non-null Dinas/Unit conflict,
     *     which is exactly the state this rule forbids.
     *
     * NON-DESTRUCTIVE:
     *   - No column added/dropped, no data touched, no table recreated.
     *   - The pre-existing FK (`users_dinas_unit_id_foreign`, ON DELETE SET NULL)
     *     is left intact; a UNIQUE index on the same column is an additional,
     *     independent index.
     *   - If a database already contained two Operators on one Dinas/Unit the
     *     index creation would fail loudly rather than silently delete rows —
     *     the Super Admin must resolve the mapping explicitly (no guessed
     *     backfill, per the Phase 1 audit rule).
     *
     * `complaints.assigned_to` is a DIFFERENT concept and is NOT touched here.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('dinas_unit_id', 'users_dinas_unit_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_dinas_unit_id_unique');
        });
    }
};
