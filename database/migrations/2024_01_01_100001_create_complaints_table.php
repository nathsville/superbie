<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 32)->unique();
            // tracking_secret_hash: nullable (alternate verification may be used later - TODO: Define requirement)
            $table->string('tracking_secret_hash', 255)->nullable();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('complaint_categories')
                ->nullOnDelete();
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('reporter_id')
                ->constrained('users')
                ->restrictOnDelete();
            // Contact snapshot fields - requiredness: TODO: Define requirement
            $table->string('reporter_email', 255)->nullable();
            $table->string('reporter_phone', 30)->nullable();
            $table->string('title', 180);
            $table->text('description');
            // location_text: human-readable only; GIS out of scope for MVP
            $table->string('location_text', 255)->nullable();
            // Provisional statuses - confirm with service owner before production
            // Allowed: submitted, under_review, in_progress, waiting_for_information, resolved, rejected, closed
            $table->string('status', 40)->default('submitted')->index();
            $table->timestamp('public_updated_at')->nullable();
            $table->timestamp('submitted_at')->useCurrent()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Composite indexes for dashboard/list queries
            $table->index(['status', 'submitted_at']);
            $table->index(['category_id', 'submitted_at']);
            $table->index(['reporter_id', 'submitted_at']);
            $table->index(['assigned_to', 'status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
