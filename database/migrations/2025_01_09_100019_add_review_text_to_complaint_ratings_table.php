<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 3 — Bagian 2 — written review ("ulasan tertulis") attached to the
     * citizen satisfaction RATING.
     *
     * ADDITIVE ONLY: one nullable TEXT column on the EXISTING `complaint_ratings`
     * table. The rating row — and therefore its review — is still created ONCE
     * per complaint and stays IMMUTABLE. No new table, no new route, no second
     * insert path.
     *
     * The review is OPTIONAL: NULL means "a rating without a written review".
     * Because it lives on the SAME row as the rating, it automatically inherits
     * the rating's ownership/eligibility rules (owner-only, only while the
     * complaint status is `resolved`) — the review can never be created for
     * another user or another complaint, and never for a non-`resolved` complaint.
     *
     * No backfill is performed: historical ratings (if any) keep `review_text`
     * NULL, which reads as "no written review". Reversible: dropping the column
     * restores the previous schema exactly.
     *
     * MODERATION is deliberately NOT implemented here. `prd.md` §4 keeps the
     * moderation policy as `TODO: Define requirement`; the text is stored as-is
     * and rendered through Blade's default escaping (no HTML is ever trusted).
     */
    public function up(): void
    {
        Schema::table('complaint_ratings', function (Blueprint $table) {
            // OPTIONAL free text. NULL = the reporter gave a rating but wrote no
            // review. Stored as-is; never interpreted as markup.
            $table->text('review_text')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('complaint_ratings', function (Blueprint $table) {
            $table->dropColumn('review_text');
        });
    }
};
