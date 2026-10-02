<?php

namespace App\Enums;

/**
 * Provisional complaint status values.
 * These must be confirmed with the service owner before production.
 * TODO: Define requirement — official statuses, transitions, and who may perform each.
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

    public function label(): string
    {
        return match ($this) {
            self::Submitted             => 'Dikirim',
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
     * Allowed transitions per status.
     * TODO: Define requirement — confirm official workflow before production.
     *
     * @return list<ComplaintStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted             => [self::UnderReview, self::Rejected],
            self::UnderReview           => [self::InProgress, self::WaitingForInformation, self::Rejected],
            self::InProgress            => [self::WaitingForInformation, self::Resolved, self::Rejected],
            self::WaitingForInformation => [self::InProgress, self::Resolved, self::Rejected],
            self::Resolved              => [self::Closed],
            self::Rejected              => [self::Closed],
            self::Closed                => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
