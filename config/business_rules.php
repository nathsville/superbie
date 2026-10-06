<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business Rules — SuperBie / Lapor Pak Wali
    |--------------------------------------------------------------------------
    |
    | Single source of truth for frozen business rules. Values here reflect
    | final business decisions and MUST NOT be changed without an approved
    | business decision. Do not scatter these magic numbers across code.
    |
    */

    // ─── Daily report limit (FINAL: 5 complaints / user / calendar day) ───────
    // BUSINESS RULE — counted against complaints already created today.
    'daily_report_limit' => 5,

    // ─── Complaint submission rate limit (SECURITY / abuse protection) ────────
    // FINAL (Prompt 17): 5 submissions / 10 minutes / authenticated user.
    // This is an anti-abuse/security control, NOT a business rule, and is kept
    // SEPARATE from `daily_report_limit` above (two independent mechanisms).
    // Enforced by the named Laravel rate limiter `complaint-submission`
    // (registered in AppServiceProvider) applied to the complaint submission
    // route only. Exceeded → HTTP 429. Scope key = authenticated user id
    // (never the client IP). A submission with 0..N attachments counts as ONE.
    'complaint_submission_rate_limit'          => 5,
    'complaint_submission_rate_window_minutes' => 10,

    // ─── Attachment rules (FINAL) ─────────────────────────────────────────────
    // Optional; max 10 files per complaint; max 20 MB per file.
    // Accepted formats: JPG, JPEG, PNG, PDF, MP4. Malware scanning NOT required.
    'attachment' => [
        'max_files'    => 10,
        'max_kilobytes' => 20480, // 20 MB per file
        'mimes'        => ['jpg', 'jpeg', 'png', 'pdf', 'mp4'],
    ],

    // ─── Identity (FINAL) ─────────────────────────────────────────────────────
    // Sensitive community identity fields enforced at the account layer.
    'identity' => [
        'nik_length' => 16,
    ],

    // ─── Retention (FINAL: 5 years from submitted_at, then permanent deletion) ─
    // Retention is measured from complaints.submitted_at ("sejak laporan masuk").
    // Action after retention = permanent deletion (no archive, no soft-delete,
    // no grace period). Complaint-related audit logs are deleted too.
    'retention' => [
        'years' => 5,
        'reference_column' => 'submitted_at',
    ],

];
