<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 2 — Super Admin initial validation workflow.
     *
     * Adds a SEPARATE, isolated "review state" that is deliberately NOT part of
     * the frozen ComplaintStatus lifecycle (which stays at exactly 7 statuses).
     * It distinguishes:
     *   - pending_initial_validation : waiting for Super Admin (new / resubmitted)
     *   - returned_to_citizen        : sent back to the reporter to be corrected
     *   - validated_for_operator     : approved and routed to a Dinas/Operator
     *
     * Backward compatibility (documented, conservative):
     *   - `review_state` is NULLABLE with NO non-null default. Every complaint
     *     that already existed before PHASE 2 keeps `review_state = NULL`.
     *   - NULL means "legacy complaint created before the initial-validation
     *     gate existed". No validation history is fabricated for old rows and
     *     their operator visibility is preserved exactly as before.
     *   - New complaints always receive an EXPLICIT state from the application.
     *
     * `review_return_reason` stores the Super Admin's return note. It is a
     * workflow message intended for the reporter (never an internal-only note),
     * so it lives on the complaint row and is deleted with the complaint by the
     * existing 5-year retention purge (no separate table to clean up).
     *
     * Additive and reversible. No data is dropped, rewritten or backfilled.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('review_state', 40)
                ->nullable()
                ->default(null)
                ->after('status');

            $table->text('review_return_reason')
                ->nullable()
                ->after('review_state');

            $table->timestamp('review_state_updated_at')
                ->nullable()
                ->after('review_return_reason');

            // Deterministic initial-validation queue ordering (oldest waiting first).
            $table->index(['review_state', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['review_state', 'submitted_at']);
            $table->dropColumn([
                'review_state',
                'review_return_reason',
                'review_state_updated_at',
            ]);
        });
    }
};
