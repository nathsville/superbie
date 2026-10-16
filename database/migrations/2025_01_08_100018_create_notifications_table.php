<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE N4 — In-app NOTIFICATION persistence (additive only).
     *
     * The application stores notifications in its OWN table (NOT the Laravel
     * polymorphic `notifications` shape) because the business design requires:
     *   - an explicit recipient column (`recipient_user_id`) for owner-scoped
     *     queries and IDOR-safe access;
     *   - a complaint anchor (`complaint_id`) so a notification follows the
     *     complaint through its lifecycle (and is purged with it);
     *   - a business milestone `type`;
     *   - a CANONICAL business-event anchor (`source_event_id` = the id of the
     *     canonical `audit_logs` row for that business action) used for
     *     idempotency;
     *   - full read/unread state (`read_at`).
     *
     * IDEMPOTENCY (the core guarantee):
     *   UNIQUE (recipient_user_id, complaint_id, type, source_event_id)
     *   One business action × one recipient = exactly one logical notification.
     *   Retrying the same business action re-uses the same canonical audit row,
     *   so the unique index makes a duplicate insert impossible at the DATABASE
     *   layer — not merely an application-level check.
     *
     * FK behaviour mirrors the other complaint children: RESTRICT on both
     * parents. Users are deactivated (never hard-deleted) in this project, and
     * the 5-year retention purge deletes notification rows explicitly BEFORE the
     * parent complaint — no new retention mechanism is introduced.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Recipient — the account that owns this notification. Never
            // nullable: a notification always has exactly one recipient.
            $table->foreignId('recipient_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Complaint anchor — every notification in this milestone model
            // refers to exactly one complaint. RESTRICT keeps referential
            // integrity; the retention purge removes children first.
            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->restrictOnDelete();

            // Business milestone (e.g. complaint_submitted, completion_validated).
            $table->string('type', 64);

            // Rendered, citizen/operator-safe text. NEVER internal metadata.
            $table->string('title', 180);
            $table->text('body');

            // Minimal renderer hints only (reference_code, milestone, status).
            // NEVER PII, internal notes, URLs or secrets.
            $table->json('data')->nullable();

            // read/unread: NULL = unread.
            $table->timestamp('read_at')->nullable();

            // Canonical business-event anchor (audit_logs.id of the action that
            // produced this milestone). Stable across retries of the SAME action.
            $table->unsignedBigInteger('source_event_id');

            $table->timestamps();

            // Idempotency key — one business action × one recipient.
            $table->unique(
                ['recipient_user_id', 'complaint_id', 'type', 'source_event_id'],
                'notifications_idempotency_unique'
            );

            // Unread count + unread listing (recipient_user_id + read_at).
            $table->index(['recipient_user_id', 'read_at'], 'notifications_recipient_read_idx');

            // Newest-first listing / dropdown (recipient_user_id + created_at + id).
            $table->index(['recipient_user_id', 'created_at', 'id'], 'notifications_recipient_recent_idx');
        });
    }

    /**
     * Reversible: dropping the table restores the previous schema exactly. The
     * table is new and holds only derived notification data, so rollback is safe.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
