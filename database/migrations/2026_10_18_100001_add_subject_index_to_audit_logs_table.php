<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit PROMPT_28 / S-05 — the retention purge deletes audit rows per
 * (subject_type, subject_id) for each purged complaint; without this index the
 * append-only table was scanned in full for every purge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_subject_index');
        });
    }
};
