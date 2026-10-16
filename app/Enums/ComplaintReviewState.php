<?php

namespace App\Enums;

/**
 * Complaint review/validation state (SEPARATE from ComplaintStatus).
 *
 * This is NOT a complaint lifecycle status. The official ComplaintStatus enum
 * stays frozen at exactly 7 cases; this enum only tracks where a complaint sits
 * in the Super Admin validation workflows.
 *
 * PHASE 2 — initial validation:
 *   pending_initial_validation : the complaint awaits Super Admin validation
 *                                (a brand-new citizen complaint OR a resubmission).
 *   returned_to_citizen        : the Super Admin sent it back to the reporter
 *                                to be corrected. It is NOT routed to any Operator
 *                                and the complaint status is NOT set to rejected.
 *   validated_for_operator     : approved by the Super Admin and routed to a
 *                                Dinas/Unit; the Dinas Operator may now handle it.
 *
 * PHASE 3 — completion validation gate:
 *   pending_completion_validation  : the Operator has SUBMITTED the work as done
 *                                    and it awaits Super Admin validation. The
 *                                    complaint status is NOT `resolved` yet.
 *   completion_returned_to_operator: the Super Admin returned the completion to
 *                                    the Operator to be corrected. The complaint
 *                                    is back in the Operator workflow (status is
 *                                    NOT rejected/closed).
 *
 * NULL (on `complaints.review_state`) is reserved for LEGACY complaints that
 * predate PHASE 2. NULL is treated as "already past the validation gate" so the
 * historical operator workflow keeps working — no fabricated validation history.
 *
 * Total: 5 states + NULL legacy.
 */
enum ComplaintReviewState: string
{
    case PendingInitialValidation = 'pending_initial_validation';
    case ReturnedToCitizen        = 'returned_to_citizen';
    case ValidatedForOperator     = 'validated_for_operator';

    // PHASE 3 — Completion Validation Gate (additive; no new ComplaintStatus).
    case PendingCompletionValidation  = 'pending_completion_validation';
    case CompletionReturnedToOperator = 'completion_returned_to_operator';

    /**
     * Public-facing label (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingInitialValidation     => 'Menunggu Validasi',
            self::ReturnedToCitizen            => 'Perlu Diperbaiki',
            self::ValidatedForOperator         => 'Divalidasi',
            self::PendingCompletionValidation  => 'Menunggu Validasi Penyelesaian',
            self::CompletionReturnedToOperator => 'Penyelesaian Dikembalikan',
        };
    }

    public function tailwindBadge(): string
    {
        return match ($this) {
            self::PendingInitialValidation     => 'bg-accent-100 text-accent-800',
            self::ReturnedToCitizen            => 'bg-orange-100 text-orange-800',
            self::ValidatedForOperator         => 'bg-emerald-100 text-emerald-800',
            self::PendingCompletionValidation  => 'bg-accent-100 text-accent-800',
            self::CompletionReturnedToOperator => 'bg-orange-100 text-orange-800',
        };
    }

    /**
     * Whether this state sits in the Super Admin INITIAL-validation queue.
     *
     * Deliberately true ONLY for `pending_initial_validation`: the completion
     * queue is a separate surface and must not be mixed with the initial queue.
     */
    public function isAwaitingValidation(): bool
    {
        return $this === self::PendingInitialValidation;
    }

    /**
     * Whether this state blocks the complaint from reaching an Operator.
     *
     * Only the two PRE-routing states block the Operator: a complaint awaiting
     * initial validation, or one returned to the citizen, is not yet/again in the
     * Operator's hands. Once the complaint has been validated for the Operator,
     * the completion states (submitted / returned) keep it VISIBLE to the
     * Operator so the work can continue.
     */
    public function blocksOperator(): bool
    {
        return in_array($this, [self::PendingInitialValidation, self::ReturnedToCitizen], true);
    }
}
