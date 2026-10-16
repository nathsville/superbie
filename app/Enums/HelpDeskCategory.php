<?php

namespace App\Enums;

/**
 * PROMPT TAHAP 2 — Help Desk ticket categories.
 *
 * The allowlist is EXPLICIT and is the single source of truth for validation
 * (StoreHelpTicketRequest) and for the UI (create form + Super Admin filter).
 *
 * These categories were NOT defined in the project documentation before this
 * phase; the list below is the documented initial scope (prd.md / schema.md).
 */
enum HelpDeskCategory: string
{
    case AccountLogin        = 'account_login';
    case AppUsage            = 'app_usage';
    case ComplaintSubmission = 'complaint_submission';
    case ComplaintTracking   = 'complaint_tracking';
    case TechnicalOther      = 'technical_other';

    public function label(): string
    {
        return match ($this) {
            self::AccountLogin        => 'Akun dan Login',
            self::AppUsage            => 'Penggunaan Aplikasi',
            self::ComplaintSubmission => 'Pengiriman Laporan',
            self::ComplaintTracking   => 'Status atau Pelacakan Laporan',
            self::TechnicalOther      => 'Kendala Teknis Lainnya',
        };
    }

    /**
     * All category values — the validation allowlist.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $category) => $category->value, self::cases());
    }
}
