<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PROMPT TAHAP 2 — Help Desk / Pusat Bantuan persistence (additive only).
     *
     * The Help Desk is a SUPPORT-TICKET system that is deliberately SEPARATE
     * from citizen complaints. It never mutates a complaint's status, category,
     * routing, assignment or notes; a related complaint is only a CONTEXT
     * reference.
     *
     * Two tables:
     *   help_tickets          — the ticket header (owner, subject, category,
     *                           opening description, optional complaint link,
     *                           status, resolution timestamp).
     *   help_ticket_messages  — the append-only written conversation.
     *
     * Design notes:
     *   - `related_complaint_id` uses ON DELETE SET NULL: the 5-year complaint
     *     retention purge deletes complaints, and a help ticket must SURVIVE that
     *     purge (its reference simply becomes NULL). A RESTRICT FK would break the
     *     existing, frozen retention mechanism, so it is deliberately avoided.
     *   - `user_id` (owner / sender) uses RESTRICT: accounts are deactivated, not
     *     hard-deleted, so historical tickets keep their author.
     *   - No backfill, no data rewrite, reversible.
     */
    public function up(): void
    {
        Schema::create('help_tickets', function (Blueprint $table) {
            $table->id();
            // Server-generated, unique, non-guessable reference (HD-YYYYMMDD-XXXX).
            $table->string('reference_code', 32)->unique();
            // Ticket owner (the citizen who created it).
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('subject', 180);
            // Allowlist value (App\Enums\HelpDeskCategory); stored as a string so
            // historical rows stay readable even if the enum grows.
            $table->string('category', 40);
            $table->text('description');
            // OPTIONAL context reference to the owner's own complaint. NULL when
            // the ticket is not tied to a report. SET NULL on complaint purge.
            $table->foreignId('related_complaint_id')
                ->nullable()
                ->constrained('complaints')
                ->nullOnDelete();
            // Allowlist value (App\Enums\HelpTicketStatus). Default = `open`.
            $table->string('status', 30)->default('open');
            // Set when the ticket becomes `resolved`; cleared if it is reopened.
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index('category');
        });

        Schema::create('help_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_ticket_id')
                ->constrained('help_tickets')
                ->restrictOnDelete();
            // Sender — always derived server-side from the authenticated user,
            // never from request input.
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['help_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_ticket_messages');
        Schema::dropIfExists('help_tickets');
    }
};
