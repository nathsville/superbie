<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add community identity fields to users (FINAL business rule):
     *   - nik          : 16 digits, unique, immutable after creation
     *   - phone_number : local/international, unique, editable
     *   - address      : free text (no business max length)
     *
     * Columns are added NULLABLE to remain non-destructive for existing rows.
     * Business rule requires them for all accounts; enforcement of "required"
     * is applied at the registration layer (server-side), so NO fake/placeholder
     * NIK is ever generated for legacy users (Prompt 5B §U.3).
     *
     * Unique indexes: MySQL permits multiple NULLs under a UNIQUE index, so
     * existing rows (NULL) are safe while new values remain unique.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('nik', 16)->nullable()->unique()->after('email');
            $table->string('phone_number', 20)->nullable()->unique()->after('nik');
            $table->text('address')->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropUnique(['phone_number']);
            $table->dropColumn(['nik', 'phone_number', 'address']);
        });
    }
};
