<?php

namespace App\Enums;

/**
 * Official complaint status values.
 *
 * LC-2 lifecycle: 7 enum cases are RETAINED for historical hydration and audit
 * compatibility, but only 4 belong to the ACTIVE lifecycle and only 3
 * transitions are allowed:
 *
 *   submitted → in_progress → { resolved | rejected }
 *
 * Classification:
 *  - Active (non-terminal): submitted, in_progress
 *  - Final business outcome: resolved, rejected
 *  - Legacy (no longer reachable, history only): under_review,
 *    waiting_for_information, closed
 *
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
            self::Submitted             => 'bg-primary-100 text-primary-800',
            self::UnderReview           => 'bg-accent-100 text-accent-800',
            self::InProgress            => 'bg-violet-100 text-violet-800',
            self::WaitingForInformation => 'bg-orange-100 text-orange-800',
            self::Resolved              => 'bg-green-100 text-green-800',
            self::Rejected              => 'bg-red-100 text-red-800',
            self::Closed                => 'bg-surface-sunken text-ink-soft',
        };
    }

    /**
     * The ACTIVE transition matrix — the single source of truth for the
     * lifecycle graph. Exactly three transitions are allowed:
     *
     *   submitted   → in_progress
     *   in_progress → resolved, rejected
     *
     * Every other pair is forbidden. In particular:
     *  - no transition into or out of a legacy status (under_review,
     *    waiting_for_information, closed);
     *  - no outgoing transition from a final business outcome (resolved,
     *    rejected) — the manual close edge is retired;
     *  - no direct submitted → rejected (pre-validation rejection is handled by
     *    returning the complaint to the citizen, not by a status change).
     *
     * @return list<ComplaintStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted             => [self::InProgress],
            self::InProgress            => [self::Resolved, self::Rejected],
            // Final business outcomes and legacy statuses have no outgoing edge.
            self::Resolved,
            self::Rejected,
            self::UnderReview,
            self::WaitingForInformation,
            self::Closed                => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Whether this status has at least one outgoing transition in the ACTIVE
     * matrix. Derived from {@see allowedTransitions()} so the graph can never
     * drift from the single source of truth. True only for submitted and
     * in_progress.
     */
    public function hasOutgoingTransitions(): bool
    {
        return $this->allowedTransitions() !== [];
    }

    /**
     * The statuses that belong to the ACTIVE lifecycle (non-terminal).
     *
     * @return list<ComplaintStatus>
     */
    public static function activeCases(): array
    {
        return [self::Submitted, self::InProgress];
    }

    /**
     * The statuses that represent a FINAL business outcome.
     *
     * @return list<ComplaintStatus>
     */
    public static function finalOutcomeCases(): array
    {
        return [self::Resolved, self::Rejected];
    }

    /**
     * The statuses retained for history only — not reachable by any active
     * transition.
     *
     * @return list<ComplaintStatus>
     */
    public static function legacyCases(): array
    {
        return [self::UnderReview, self::WaitingForInformation, self::Closed];
    }

    /**
     * Whether this status is part of the ACTIVE lifecycle (submitted/in_progress).
     */
    public function isActiveLifecycle(): bool
    {
        return in_array($this, self::activeCases(), true);
    }

    /**
     * Whether this status is a FINAL business outcome (resolved/rejected).
     */
    public function isFinalOutcome(): bool
    {
        return in_array($this, self::finalOutcomeCases(), true);
    }

    /**
     * Whether this status is a LEGACY status retained for history only.
     */
    public function isLegacy(): bool
    {
        return in_array($this, self::legacyCases(), true);
    }

    /**
     * Whether this status is terminal.
     *
     * Terminal means "final business outcome" — equivalent to
     * {@see isFinalOutcome()}. It is deliberately NOT defined as
     * `allowedTransitions() === []`, because legacy statuses have no outgoing
     * transitions either but are NOT final business outcomes.
     */
    public function isTerminal(): bool
    {
        return $this->isFinalOutcome();
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

    /**
     * A SAFE, public-facing description of what this status means to the
     * reporter, used by the citizen "Timeline Progres Laporan".
     *
     * This is deliberately a STATIC, generic explanation derived from the status
     * itself — NOT copied from any internal operator note (`complaint_status_histories.note`),
     * which stays internal. It contains no staff identity, no PII, no case-specific
     * detail, and no fabricated dates, so it is always safe to render publicly.
     *
     * The real, case-specific explanation a citizen is entitled to (e.g. the
     * official rejection reason) is delivered ONLY through the separate
     * `public_response` channel — never inferred here.
     */
    public function publicDescription(): string
    {
        return match ($this) {
            self::Submitted             => 'Laporan Anda telah diterima dan tercatat di sistem.',
            self::UnderReview           => 'Laporan Anda sedang dalam tahap peninjauan.',
            self::InProgress            => 'Laporan Anda telah diverifikasi dan sedang ditindaklanjuti oleh instansi terkait.',
            self::WaitingForInformation => 'Laporan Anda menunggu kelengkapan berkas sebelum dapat diproses lebih lanjut.',
            self::Resolved              => 'Laporan Anda telah dinyatakan selesai.',
            self::Rejected              => 'Laporan Anda tidak dapat ditindaklanjuti.',
            self::Closed                => 'Laporan Anda telah ditutup.',
        };
    }

    /**
     * Null-safe public description for a RAW stored status value (string).
     *
     * Returns NULL for an unknown/legacy value so the timeline never fabricates a
     * description for a status the system does not recognise. Used by the citizen
     * timeline, which must render historical rows verbatim without inventing data.
     */
    public static function publicDescriptionFor(?string $value): ?string
    {
        return $value !== null ? self::tryFrom($value)?->publicDescription() : null;
    }
}
