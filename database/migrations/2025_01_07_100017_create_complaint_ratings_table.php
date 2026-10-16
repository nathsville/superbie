<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 3 — Bagian 2 — Citizen satisfaction RATING (additive only).
     *
     * The REPORTER (owner) of a complaint may give ONE satisfaction rating
     * (1–5 stars) — and ONLY while the complaint status is `resolved`. The
     * rating is stored separately from the complaint lifecycle and NEVER mutates
     * `status`, `review_state`, `resolved_at` or the completion columns.
     *
     * One complaint = at most ONE rating. The `complaint_id` UNIQUE index is the
     * PRIMARY defense (not application-only): it makes a double submission /
     * race impossible at the database layer.
     *
     * Ownership: `user_id` is the rater. It is validated server-side against the
     * complaint's real owner column (`complaints.reporter_id`) — the rating table
     * NEVER trusts a client-supplied owner.
     *
     * Retention: this is CHILD data of a complaint. It uses `ON DELETE RESTRICT`
     * (matching the other complaint children), so the existing 5-year retention
     * purge deletes rating rows explicitly, BEFORE the parent complaint — no new
     * retention mechanism is introduced.
     */
    public function up(): void
    {
        Schema::create('complaint_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->restrictOnDelete();

            // The rater — the complaint OWNER. Never nullable: a rating always
            // has an owner. RESTRICT matches the complaint children convention
            // (users are deactivated, never hard-deleted, in this project).
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Satisfaction score, 1–5 (server-side validated). Stored as a
            // small unsigned integer consistent with a fixed 1–5 scale.
            $table->unsignedTinyInteger('rating');

            $table->timestamps();

            // One complaint → at most one rating (database-level guarantee).
            $table->unique('complaint_id');
        });
    }

    /**
     * Reversible: dropping the table restores the previous schema exactly. No
     * data backfill is involved (the table is new), so rollback is safe.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_ratings');
    }
};
