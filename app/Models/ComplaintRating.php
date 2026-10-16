<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 3 — Bagian 2 — Citizen satisfaction rating (1–5 stars).
 *
 * A rating is IMMUTABLE and create-once: one complaint has AT MOST one rating
 * (enforced by the `complaint_ratings.complaint_id` UNIQUE index — the primary
 * defense). There is deliberately NO update/delete path.
 *
 * The rater (`user_id`) is the complaint OWNER; the submission flow validates it
 * server-side against `complaints.reporter_id` (never a client-supplied owner).
 *
 * PHASE 3 — Bagian 2 (review): an OPTIONAL written review (`review_text`) rides
 * on the SAME immutable row as the rating. NULL = a rating with no written
 * review. It inherits every rating rule for free (owner-only, only while the
 * complaint is `resolved`, created once) and is never a separate insert path.
 * The text is stored as-is and MUST be rendered through Blade escaping.
 *
 * STAGE 3 (official reply): an OPTIONAL `admin_response` rides on the SAME row.
 * NULL = no official reply yet. It is written ONCE by a Super Admin and is
 * IMMUTABLE (no update/delete path); it never touches `rating`/`review_text`,
 * the complaint status, the KPI, or the rating analytics.
 */
class ComplaintRating extends Model
{
    protected $fillable = [
        'complaint_id',
        'user_id',
        'rating',
        // OPTIONAL written review. NULL = no review text was provided.
        'review_text',
        // STAGE 3 — OPTIONAL official Super Admin reply. NULL = not yet answered.
        'admin_response',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Whether this rating carries a non-empty written review. A review that is
     * only whitespace is treated as absent (the submission trims it).
     */
    public function hasReview(): bool
    {
        return $this->review_text !== null && trim((string) $this->review_text) !== '';
    }

    /**
     * STAGE 3 — whether this rating already carries a non-empty official Super
     * Admin reply. A reply that is only whitespace is treated as absent (the
     * submission trims it and stores NULL for a blank body).
     */
    public function hasAdminResponse(): bool
    {
        return $this->admin_response !== null && trim((string) $this->admin_response) !== '';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
