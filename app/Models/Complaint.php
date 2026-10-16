<?php

namespace App\Models;

use App\Enums\ComplaintReviewState;
use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code',
        // DEPRECATED (PHASE 6C — Decision 4). `tracking_secret_hash` is a legacy
        // public-tracking column; there is no public tracking feature, so it is
        // no longer written. Retained ONLY for historical preservation.
        'tracking_secret_hash',
        'category_id',
        'dinas_unit_id',
        // DEPRECATED (PHASE 6C — Decision 2). `assigned_to` is a legacy
        // compatibility column retained ONLY for historical preservation. It is
        // NEVER written by any active workflow, is NOT a routing or assignment
        // source of truth (the handling Operator is resolved from
        // `dinas_unit_id`), and MUST NOT be used by new metrics/filters/UI.
        'assigned_to',
        'reporter_id',
        // `reporter_email` is a RETAINED snapshot (PHASE 6C — Decision 6).
        'reporter_email',
        // DEPRECATED (PHASE 6C — Decision 5): legacy column, no longer written.
        'reporter_phone',
        'title',
        'description',
        'location_text',
        'kecamatan_id',
        'kelurahan_id',
        // OPTIONAL geographic coordinates (Prompt 4/5). Both are nullable and
        // must be treated as a PAIR — never set one without the other.
        'latitude',
        'longitude',
        'status',
        // PHASE 2 — initial-validation review state (SEPARATE from status).
        'review_state',
        'review_return_reason',
        'review_state_updated_at',
        // PHASE 3 — completion-validation gate (SEPARATE from status). The
        // reason is INTERNAL (Operator/staff), never shown to the reporter.
        'completion_submitted_at',
        'completion_return_reason',
        // DEPRECATED (PHASE 6C — Decision 7). `public_updated_at` is a legacy
        // public-tracking timestamp; no active workflow writes it and there is
        // no public tracking. Retained ONLY for historical preservation.
        'public_updated_at',
        'submitted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status'           => ComplaintStatus::class,
            // PHASE 2 — review state is a nullable enum; NULL = legacy row.
            'review_state'     => ComplaintReviewState::class,
            'review_state_updated_at' => 'datetime',
            // PHASE 3 — completion submission timestamp (nullable; no cast for
            // `completion_return_reason`, it is a plain TEXT string).
            'completion_submitted_at' => 'datetime',
            'submitted_at'     => 'datetime',
            'resolved_at'      => 'datetime',
            'public_updated_at' => 'datetime',
            // Coordinates cast to a fixed 7-decimal string, matching the DB
            // precision (DECIMAL(10,7)) and keeping display deterministic.
            'latitude'         => 'decimal:7',
            'longitude'        => 'decimal:7',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * LEGACY / HISTORICAL relation (PHASE 6C — Decision 2).
     *
     * `complaints.assigned_to` is DEPRECATED: it is never written by any active
     * workflow and is NOT the routing/assignment source of truth (the handling
     * Operator is resolved from `dinas_unit_id` via `resolvedOperator()`).
     *
     * This relation is retained ONLY so historical rows created before the
     * deprecation can still be displayed as historical information. Do NOT use it
     * for active assignment, routing, metrics, filters, or authorization.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'category_id');
    }

    /**
     * ACTUAL Dinas/Unit destination chosen by the Operator (Prompt 5C).
     * Stored (not dynamic) so history is stable against mapping changes.
     * May be null (not yet routed, or master data removed → set null).
     */
    public function dinasUnit(): BelongsTo
    {
        return $this->belongsTo(DinasUnit::class, 'dinas_unit_id');
    }

    /**
     * Structured location — Kecamatan (FINAL business rule).
     *
     * NULL for legacy complaints created before structured location existed;
     * REQUIRED for new complaints (enforced in the Form Request).
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_id');
    }

    /**
     * Structured location — Kelurahan. Always belongs to {@see kecamatan()}.
     * NULL for legacy complaints.
     */
    public function kelurahan(): BelongsTo
    {
        return $this->belongsTo(Kelurahan::class, 'kelurahan_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ComplaintStatusHistory::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ComplaintNote::class)->orderBy('created_at');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(ComplaintNote::class)
            ->where('visibility', 'internal')
            ->orderBy('created_at');
    }

    public function publicResponses(): HasMany
    {
        return $this->hasMany(ComplaintNote::class)
            ->where('visibility', 'public_response')
            ->orderBy('created_at');
    }

    /**
     * PHASE 3 — Bagian 2 — the reporter's satisfaction rating (1–5). At most
     * ONE rating per complaint (DB UNIQUE on `complaint_ratings.complaint_id`).
     */
    public function rating(): HasOne
    {
        return $this->hasOne(ComplaintRating::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForReporter($query, int $userId)
    {
        return $query->where('reporter_id', $userId);
    }

    /**
     * @deprecated LEGACY (PHASE 6C — Decision 2). `assigned_to` is deprecated and
     * never written by runtime, so this scope is unused and must not be used for
     * active assignment/filtering. Retained only for backward compatibility.
     */
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * PHASE 2 — complaints awaiting Super Admin initial validation, oldest
     * waiting first (deterministic: `submitted_at` ASC, then `id` ASC).
     */
    public function scopePendingInitialValidation($query)
    {
        return $query->where('review_state', ComplaintReviewState::PendingInitialValidation->value)
            ->orderBy('submitted_at')
            ->orderBy('id');
    }

    /**
     * PHASE 2 — complaints returned to the reporter for correction.
     */
    public function scopeReturnedToCitizen($query)
    {
        return $query->where('review_state', ComplaintReviewState::ReturnedToCitizen->value);
    }

    /**
     * PHASE 3 / N6B-1 — the Super Admin COMPLETION VALIDATION QUEUE: complaints
     * whose completion the Operator has SUBMITTED and which still await Super
     * Admin validation, oldest waiting first (deterministic: `completion_submitted_at`
     * ASC, then `id` ASC).
     *
     * N6B-1 (FROZEN, Option A): queue eligibility requires BOTH
     *   - `review_state = pending_completion_validation`, AND
     *   - `status = in_progress`.
     *
     * The `status` condition is required because a frozen G7 direct resolve by a
     * Super Admin (`in_progress → resolved` through `operator.complaint.update-status`)
     * deliberately does NOT rewrite `review_state`. Such a row therefore keeps
     * `review_state = pending_completion_validation` while its `status` is no
     * longer `in_progress`; it must NOT surface as an actionable completion item.
     * The same applies to any other non-`in_progress` status. We do NOT mutate
     * `review_state` here — this is a read/query rule only.
     *
     * Deliberately excludes `completion_returned_to_operator` (back in the
     * OPERATOR's hands) and every pre-routing / validated / legacy state.
     */
    public function scopePendingCompletionValidation($query)
    {
        return $query->where('review_state', ComplaintReviewState::PendingCompletionValidation->value)
            ->where('status', ComplaintStatus::InProgress->value)
            ->orderBy('completion_submitted_at')
            ->orderBy('id');
    }

    /**
     * PHASE 3 — complaints whose completion the Super Admin returned to the
     * Operator for correction (still in the Operator workflow).
     */
    public function scopeCompletionReturnedToOperator($query)
    {
        return $query->where('review_state', ComplaintReviewState::CompletionReturnedToOperator->value);
    }

    /**
     * Restrict the query to complaints VISIBLE to an Operator (Prompt 15 §4).
     *
     * FINAL, approved visibility rule (BDR-1 = 1a, BDR-2 = 2c hybrid):
     *   An Operator sees a Complaint iff:
     *     operator.dinas_unit_id IS NOT NULL
     *     AND (
     *          complaint.dinas_unit_id = operator.dinas_unit_id
     *          OR (complaint.dinas_unit_id IS NULL
     *              AND the complaint's category is mapped to operator.dinas_unit_id
     *              via `category_dinas_unit`)
     *     )
     *
     * An Operator WITHOUT a unit is DENIED everything — no global fallback.
     *
     * PHASE 2 adds the initial-validation gate: an Operator may ONLY see a
     * complaint that has passed Super Admin validation. Complaints that are
     * `pending_initial_validation` or `returned_to_citizen` are excluded
     * server-side. LEGACY complaints (review_state IS NULL, created before the
     * gate existed) remain visible so the historical workflow is preserved —
     * see the migration/backward-compatibility note. This is applied in SQL, so
     * the list query and the per-record guard can never disagree.
     *
     * PHASE 3 keeps the Operator's own in-flight work VISIBLE: once a complaint
     * has been validated for the Operator, it stays reachable while the Operator
     * works it AND while its completion is `pending_completion_validation` (the
     * Super Admin is reviewing it) OR `completion_returned_to_operator` (it was
     * handed back to the Operator to be fixed). Those two completion states
     * NEVER block the Operator — only the two PRE-routing states do.
     */
    public function scopeVisibleToOperator($query, User $operator)
    {
        $unitId = $operator->dinas_unit_id;

        if ($unitId === null) {
            // No unit → no scope at all (deny). Never a global fallback.
            return $query->whereRaw('1 = 0');
        }

        // PHASE 2/3 gate — only complaints that have passed the initial gate are
        // reachable, plus the two completion states that belong to the Operator.
        // Legacy (NULL) rows stay visible for backward compatibility.
        $query->where(function ($q) {
            $q->whereIn('complaints.review_state', [
                    ComplaintReviewState::ValidatedForOperator->value,
                    ComplaintReviewState::PendingCompletionValidation->value,
                    ComplaintReviewState::CompletionReturnedToOperator->value,
                ])
              ->orWhereNull('complaints.review_state');
        });

        return $query->where(function ($q) use ($unitId) {
            $q->where('complaints.dinas_unit_id', $unitId)
              ->orWhere(function ($q2) use ($unitId) {
                  $q2->whereNull('complaints.dinas_unit_id')
                     ->whereHas('category', function ($categoryQuery) use ($unitId) {
                         $categoryQuery->whereHas('dinasUnits', function ($dinasQuery) use ($unitId) {
                             $dinasQuery->where('dinas_units.id', $unitId);
                         });
                     });
              });
        });
    }

    /**
     * Per-instance guard mirroring {@see scopeVisibleToOperator()} exactly.
     *
     * Reuses the scope so the list query and the per-record authorization can
     * never diverge (single source of truth for the visibility rule).
     */
    public function isVisibleToOperator(User $operator): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->visibleToOperator($operator)
            ->exists();
    }

    // ─── PHASE 2 — review-state helpers ───────────────────────────────────────

    /**
     * Whether the complaint is awaiting Super Admin initial validation.
     */
    public function isAwaitingInitialValidation(): bool
    {
        return $this->review_state === ComplaintReviewState::PendingInitialValidation;
    }

    /**
     * Whether the complaint has been returned to the reporter for correction.
     */
    public function isReturnedToCitizen(): bool
    {
        return $this->review_state === ComplaintReviewState::ReturnedToCitizen;
    }

    /**
     * Whether the complaint has been validated and routed to a Dinas/Operator.
     */
    public function isValidatedForOperator(): bool
    {
        return $this->review_state === ComplaintReviewState::ValidatedForOperator;
    }

    /**
     * PHASE 3 — whether the Operator has SUBMITTED the completion and it awaits
     * Super Admin validation (`review_state = pending_completion_validation`).
     */
    public function isPendingCompletionValidation(): bool
    {
        return $this->review_state === ComplaintReviewState::PendingCompletionValidation;
    }

    /**
     * PHASE 3 — whether the Super Admin returned the completion to the Operator
     * for correction (`review_state = completion_returned_to_operator`).
     */
    public function isCompletionReturnedToOperator(): bool
    {
        return $this->review_state === ComplaintReviewState::CompletionReturnedToOperator;
    }

    /**
     * PHASE 3 — whether the complaint sits anywhere in the completion gate:
     * submitted for validation OR returned to the Operator. Derived from
     * `review_state` ONLY — never inferred from `status` alone.
     */
    public function isCompletionPending(): bool
    {
        return $this->isPendingCompletionValidation()
            || $this->isCompletionReturnedToOperator();
    }

    /**
     * PHASE 3 — whether an Operator may SUBMIT this complaint's completion.
     *
     * This is a WORKFLOW predicate (it reads BOTH `status` and `review_state`,
     * unlike the pure state helpers above), mirroring the server-side gate in
     * `OperatorComplaintController::submitCompletion()` so the list/detail view
     * and the endpoint can never disagree.
     *
     * Requirements:
     *   - status is exactly `in_progress` (submitting NEVER changes the status);
     *   - review_state is `validated_for_operator` (normal case),
     *     `completion_returned_to_operator` (resubmission after a return), or
     *     NULL (legacy row, treated as already validated for the Operator).
     *
     * Rejected: `pending_completion_validation` (already submitted),
     * `pending_initial_validation` / `returned_to_citizen` (not yet the
     * Operator's), and any non-`in_progress` status.
     */
    public function canSubmitCompletion(): bool
    {
        if ($this->status !== ComplaintStatus::InProgress) {
            return false;
        }

        return $this->review_state === null
            || $this->review_state === ComplaintReviewState::ValidatedForOperator
            || $this->review_state === ComplaintReviewState::CompletionReturnedToOperator;
    }

    /**
     * PHASE 3 — Bagian 2 — whether the reporter may give a satisfaction rating.
     *
     * Single source of truth = `ComplaintStatus`. The frozen business decision:
     * a rating may ONLY be given while the complaint is `resolved`. Once it moves
     * to `closed` (or any other status) it is NOT ratable. `review_state` is
     * deliberately NOT consulted — `validated_for_operator` alone never implies
     * eligibility.
     */
    public function canBeRated(): bool
    {
        return $this->status === ComplaintStatus::Resolved;
    }

    /**
     * PHASE 2 — the Operator that will handle this complaint, resolved DYNAMICALLY
     * from its Dinas/Unit (`dinas_unit_id` → `DinasUnit::operator()`).
     *
     * This is the single source of truth for "who handles this complaint": the
     * complaint is NOT assigned a specific operator (`assigned_to` is a legacy
     * field and is never used as a routing mechanism in PHASE 2). Returns NULL
     * when the complaint has no Dinas/Unit, or the Dinas/Unit has no Operator.
     */
    public function resolvedOperator(): ?User
    {
        return $this->dinasUnit?->operator;
    }

    // ─── Citizen timeline (public, read-only projection) ──────────────────────

    /**
     * Build the PUBLIC "Timeline Progres Laporan" for this complaint.
     *
     * The timeline is a truthful, chronological projection of the REAL recorded
     * status events (`complaint_status_histories`), NOT a re-derivation from the
     * current status. It is ordered OLDEST FIRST — the same convention as the
     * existing operator/staff status history — so the flow reads top-to-bottom
     * from creation to the latest event, and the LAST entry represents the
     * current state.
     *
     * PRIVACY — each entry deliberately exposes ONLY:
     *   - the official status label (`ComplaintStatus::label()`),
     *   - a STATIC, safe public description derived from the status
     *     (`ComplaintStatus::publicDescription()`),
     *   - the event timestamp, and
     *   - presentational flags (initial / completion / current).
     *
     * The internal `note` column of `complaint_status_histories` (operational,
     * possibly case-specific, and — for rejections — containing an internal
     * reason) and the `changed_by` actor identity are NEVER included. The
     * official, case-specific explanation a citizen is entitled to is delivered
     * separately through the `public_response` channel.
     *
     * ROBUSTNESS — legacy complaints with missing or incomplete history are
     * rendered from whatever rows ACTUALLY exist; nothing is fabricated, and an
     * unknown/legacy status value is rendered verbatim with no invented
     * description (see `ComplaintStatus::publicDescriptionFor()`). When no history
     * exists at all, an EMPTY collection is returned so the view can show an
     * informative empty state.
     *
     * @return \Illuminate\Support\Collection<int, array{
     *     status:string,
     *     label:string,
     *     description:?string,
     *     occurred_at:\Illuminate\Support\Carbon|null,
     *     is_initial:bool,
     *     is_completion:bool,
     *     is_current:bool,
     * }>
     */
    public function timelineEntries(): \Illuminate\Support\Collection
    {
        $histories = $this->relationLoaded('statusHistories')
            ? $this->statusHistories
            : $this->statusHistories()->get();

        $lastIndex = $histories->count() - 1;

        return $histories->values()->map(function (ComplaintStatusHistory $history, int $index) use ($lastIndex) {
            $status = ComplaintStatus::tryFrom((string) $history->to_status);

            return [
                'status'      => $history->to_status,
                'label'       => $status?->label() ?? $history->to_status,
                'description' => ComplaintStatus::publicDescriptionFor($history->to_status),
                'occurred_at' => $history->created_at,
                // The very first recorded event (creation) has no predecessor.
                'is_initial'    => $history->from_status === null,
                // A final business outcome (resolved/rejected) marks completion.
                'is_completion' => $status?->isFinalOutcome() ?? false,
                // The most recent recorded event represents the current state.
                'is_current'    => $index === $lastIndex,
            ];
        });
    }
}
