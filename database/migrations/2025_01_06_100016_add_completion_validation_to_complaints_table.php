<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 3 — Completion Validation Gate (foundation, additive only).
     *
     * The Operator submits a finished complaint for Super Admin validation.
     * While it awaits validation the complaint status stays `in_progress`; the
     * gate is expressed by `review_state` (see ComplaintReviewState). The two
     * columns below support that workflow WITHOUT introducing a new complaint
     * status and WITHOUT touching the frozen ComplaintStatus matrix.
     *
     *   - completion_submitted_at : when the Operator SUBMITTED the completion
     *                               for validation. NULL before submission and
     *                               reset on approval / return.
     *   - completion_return_reason: the Super Admin's reason for returning the
     *                               completion to the Operator. INTERNAL — shown
     *                               to the Operator/staff, NEVER to the reporter.
     *                               Deliberately separate from
     *                               `review_return_reason` (which is the
     *                               return-to-CITIZEN message).
     *
     * Backward compatibility (documented, conservative):
     *   - BOTH columns are NULLABLE with NO non-null default. Every complaint
     *     that already existed keeps `completion_submitted_at = NULL` and
     *     `completion_return_reason = NULL`.
     *   - NO backfill, NO data rewrite, NO column rename/drop, NO FK change,
     *     NO new table. Purely additive and reversible.
     *   - Legacy rows (`review_state IS NULL`) stay valid and untouched.
     *
     * Retention: both columns live on the `complaints` row, so the existing
     * 5-year retention purge deletes them automatically with the complaint —
     * no new retention mechanism is required.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->timestamp('completion_submitted_at')
                ->nullable()
                ->after('review_state_updated_at');

            $table->text('completion_return_reason')
                ->nullable()
                ->after('completion_submitted_at');
        });
    }

    /**
     * Reversible: dropping the two columns restores the previous schema exactly.
     * No data migration/backfill is involved, so rollback is safe. No extra
     * index was created by this migration, so none needs to be dropped.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn([
                'completion_submitted_at',
                'completion_return_reason',
            ]);
        });
    }
};
