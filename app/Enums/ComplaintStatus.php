<?php

namespace App\Enums;

/**
 * Official complaint status values — finalized per Prompt 3B business decision.
 *
 * 7 statuses, 11 allowed transitions, 1 terminal (closed).
 * No reopen workflow. Reopening is not supported.
 *
 * Authority to change status:
 *  - Operator: YES
 *  - Super Admin: YES
 *  - Admin: NO (monitoring only)
 *  - Masyarakat: NO
 */
enum ComplaintStatus: string
{
    case Submitted            = 'submitted';
    case UnderReview          = 'under_review';
    case InProgress           = 'in_progress';
    case WaitingForInformation = 'waiting_for_information';
    case Resolved             = 'resolved';
    case Rejected             = 'rejected';
    case Closed               = 'closed';

    /**
     * Official public-facing labels (Bahasa Indonesia).
     */
    public function label(): string
    {
        return match ($this) {
            self::Submitted             => 'Diajukan',
            self::UnderReview           => 'Sedang Ditinjau',
            self::InProgress            => 'Sedang Diproses',
            self::WaitingForInformation => 'Menunggu Informasi',
            self::Resolved              => 'Selesai',
            self::Rejected              => 'Ditolak',
            self::Closed                => 'Ditutup',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted             => 'blue',
            self::UnderReview           => 'yellow',
            self::InProgress            => 'indigo',
            self::WaitingForInformation => 'orange',
            self::Resolved              => 'green',
            self::Rejected              => 'red',
            self::Closed                => 'gray',
        };
    }

    public function tailwindBadge(): string
    {
        return match ($this) {
            self::Submitted             => 'bg-blue-100 text-blue-800',
            self::UnderReview           => 'bg-yellow-100 text-yellow-800',
            self::InProgress            => 'bg-indigo-100 text-indigo-800',
            self::WaitingForInformation => 'bg-orange-100 text-orange-800',
            self::Resolved              => 'bg-green-100 text-green-800',
            self::Rejected              => 'bg-red-100 text-red-800',
            self::Closed                => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Official allowed transitions — 11 transitions total.
     *
     * submitted             → under_review
     * under_review          → in_progress, waiting_for_information, rejected
     * in_progress           → waiting_for_information, resolved, rejected
     * waiting_for_information → in_progress, resolved
     *   NOTE: waiting_for_information → rejected is FORBIDDEN
     * resolved              → closed
     * rejected              → closed
     * closed                → [] (terminal — no transitions out)
     *
     * Explicitly forbidden (representative list):
     *  - submitted → rejected
     *  - waiting_for_information → rejected
     *  - resolved → anything except closed
     *  - rejected → anything except closed
     *  - closed → anything
     *
     * No reopen workflow is supported.
     *
     * @return list<ComplaintStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted             => [self::UnderReview],
            self::UnderReview           => [self::InProgress, self::WaitingForInformation, self::Rejected],
            self::InProgress            => [self::WaitingForInformation, self::Resolved, self::Rejected],
            self::WaitingForInformation => [self::InProgress, self::Resolved],
            self::Resolved              => [self::Closed],
            self::Rejected              => [self::Closed],
            self::Closed                => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Whether this status is terminal (no transitions out).
     */
    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Whether a public response is required when transitioning TO this status.
     *
     * resolved: public response REQUIRED
     * rejected: public response REQUIRED
     */
    public function requiresPublicResponse(): bool
    {
        return in_array($this, [self::Resolved, self::Rejected], true);
    }

    /**
     * Whether a rejection reason is required when transitioning TO this status.
     *
     * rejected: rejection reason REQUIRED
     */
    public function requiresRejectionReason(): bool
    {
        return $this === self::Rejected;
    }
}
