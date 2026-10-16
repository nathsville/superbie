<?php

namespace App\Enums;

/**
 * PROMPT TAHAP 2 — Help Desk ticket lifecycle.
 *
 * These statuses were NOT defined in the project documentation before this
 * phase; the graph below is the documented design (prd.md / schema.md).
 *
 * Explicit transition matrix (the single source of truth):
 *
 *   open             → in_progress | waiting_for_user | resolved | closed
 *   in_progress      → waiting_for_user | resolved | closed
 *   waiting_for_user → in_progress | resolved | closed
 *   resolved         → in_progress (reopen) | closed
 *   closed           → (terminal — no outgoing transition)
 *
 * Authority:
 *   - Super Admin performs every transition above through the explicit status
 *     action.
 *   - A citizen performs ONLY the `resolved → in_progress` reopen edge, through
 *     the dedicated reopen action (which also posts the reopen message).
 *
 * Conversation rules (see allowsCitizenReply / allowsStaffReply):
 *   - A plain reply is allowed while the ticket is open, in_progress or
 *     waiting_for_user.
 *   - `resolved` and `closed` never accept a plain reply: a `resolved` ticket
 *     may only be REOPENED (validated transition) and a `closed` ticket is
 *     terminal. This prevents a closed ticket from silently receiving messages.
 */
enum HelpTicketStatus: string
{
    case Open           = 'open';
    case InProgress     = 'in_progress';
    case WaitingForUser = 'waiting_for_user';
    case Resolved       = 'resolved';
    case Closed         = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open           => 'Terbuka',
            self::InProgress     => 'Sedang Ditangani',
            self::WaitingForUser => 'Menunggu Jawaban Anda',
            self::Resolved       => 'Selesai',
            self::Closed         => 'Ditutup',
        };
    }

    public function tailwindBadge(): string
    {
        return match ($this) {
            self::Open           => 'bg-primary-100 text-primary-800',
            self::InProgress     => 'bg-violet-100 text-violet-800',
            self::WaitingForUser => 'bg-orange-100 text-orange-800',
            self::Resolved       => 'bg-green-100 text-green-800',
            self::Closed         => 'bg-surface-sunken text-ink-soft',
        };
    }

    /**
     * The allowed transitions OUT of this status.
     *
     * @return list<HelpTicketStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open           => [self::InProgress, self::WaitingForUser, self::Resolved, self::Closed],
            self::InProgress     => [self::WaitingForUser, self::Resolved, self::Closed],
            self::WaitingForUser => [self::InProgress, self::Resolved, self::Closed],
            self::Resolved       => [self::InProgress, self::Closed],
            self::Closed         => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Whether a CITIZEN may post a plain reply in this status.
     */
    public function allowsCitizenReply(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::WaitingForUser], true);
    }

    /**
     * Whether a SUPER ADMIN may post an official reply in this status.
     */
    public function allowsStaffReply(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::WaitingForUser], true);
    }

    /**
     * Whether a CITIZEN may reopen the ticket (only from `resolved`).
     */
    public function allowsCitizenReopen(): bool
    {
        return $this === self::Resolved;
    }

    /**
     * The status a ticket moves to after a CITIZEN reply, or NULL when the reply
     * leaves the status unchanged. A reply on `waiting_for_user` hands the ticket
     * back to the handler (`in_progress`).
     */
    public function nextOnCitizenReply(): ?self
    {
        return $this === self::WaitingForUser ? self::InProgress : null;
    }

    /**
     * The status a ticket moves to after a STAFF reply, or NULL when the reply
     * leaves the status unchanged. The first official reply on an `open` ticket
     * begins handling (`in_progress`).
     */
    public function nextOnStaffReply(): ?self
    {
        return $this === self::Open ? self::InProgress : null;
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
