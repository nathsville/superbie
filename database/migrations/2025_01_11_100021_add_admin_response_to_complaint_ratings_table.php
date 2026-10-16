<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * STAGE 3 — Official Super Admin reply to a citizen's written review.
     *
     * ADDITIVE ONLY: one nullable TEXT column on the EXISTING `complaint_ratings`
     * table. The rating row — and therefore the citizen review (`review_text`) —
     * stays exactly as it is: it is still created ONCE per complaint and remains
     * IMMUTABLE. No new table, no new model, no second insert path.
     *
     * Why this column and not a new table: the official reply is a 1:1, at most
     * one-per-review artifact. `complaint_ratings` already models "one row per
     * complaint" (DB UNIQUE on `complaint_id`) and already carries the optional
     * citizen review. A single nullable column keeps the schema minimal and lets
     * the reply ride on the same lifecycle/ownership rules for free.
     *
     * `NULL` = the review has no official reply yet. A reply, once written, is
     * CREATE-ONCE and IMMUTABLE: the application enforces this with a locked,
     * conditional `UPDATE ... WHERE admin_response IS NULL` inside a transaction
     * (the column stays nullable so historical/legacy ratings are untouched and
     * no backfill is required).
     *
     * The reply NEVER changes `rating`, `review_text`, the complaint status, the
     * KPI, or the rating analytics. It is free text stored as-is and MUST be
     * rendered through Blade escaping.
     *
     * Reversible: `down()` only drops this column.
     */
    public function up(): void
    {
        Schema::table('complaint_ratings', function (Blueprint $table) {
            // Official Super Admin reply to the citizen review. NULL = not yet
            // answered. Stored as-is; never interpreted as markup.
            $table->text('admin_response')->nullable()->after('review_text');
        });
    }

    public function down(): void
    {
        Schema::table('complaint_ratings', function (Blueprint $table) {
            $table->dropColumn('admin_response');
        });
    }
};
