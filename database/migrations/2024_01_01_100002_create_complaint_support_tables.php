<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attachment metadata only — binaries stored in private disk
        // Allowed extensions/size/count: TODO: Define requirement
        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->restrictOnDelete(); // preserve until retention policy defined
            $table->string('disk', 50)->default('private');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        // Append-only status transition history
        Schema::create('complaint_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('note', 1000)->nullable();
            // No updated_at — history is append-only
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['complaint_id', 'created_at']);
        });

        // Internal notes and public responses
        Schema::create('complaint_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->restrictOnDelete();
            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // visibility: 'internal' (never public) or 'public_response'
            $table->string('visibility', 24)->default('internal');
            $table->text('body');
            // No updated_at — notes are append-only in normal UI
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['complaint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_notes');
        Schema::dropIfExists('complaint_status_histories');
        Schema::dropIfExists('complaint_attachments');
    }
};
