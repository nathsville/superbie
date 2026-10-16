<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an optional profile photo path to users.
     *
     * Approved by the product owner (profile-page redesign): accounts may store
     * a real uploaded profile photo. The column is NULLABLE and non-destructive —
     * legacy rows (and accounts that never upload) simply have no photo and the
     * UI falls back to the initials avatar. The path is relative to the `public`
     * disk (storage/app/public); no fabricated/placeholder image is ever stored.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path', 255)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
