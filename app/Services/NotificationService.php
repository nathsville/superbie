<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * PHASE N4 — In-app notification service.
 *
 * RESPONSIBILITIES (and ONLY these):
 *   - resolve the recipients for a business milestone;
 *   - enforce the recipient rules (active only, never the actor, never
 *     Admin/Petugas, never `assigned_to`);
 *   - build a safe title/body/data payload;
 *   - insert one idempotent notification per recipient.
 *
 * It NEVER mutates the complaint, NEVER opens a transaction of its own (the
 * caller owns the business transaction so the notification commits atomically
 * with the business action), and NEVER sends email/queue/broadcast.
 *
 * Emission contract (see the milestone matrix):
 *   emit(Complaint $complaint, string $milestone, int $sourceEventId, ?User $actor)
 *
 * `$sourceEventId` MUST be the id of the canonical `audit_logs` row created by
 * the SAME business action. It is the idempotency anchor: retrying the same
 * action re-uses the same canonical audit row, so the UNIQUE
 * (recipient_user_id, complaint_id, type, source_event_id) index makes a
 * duplicate notification impossible at the database layer.
 */
class NotificationService
{
    // ─── Milestones (frozen N3/N4 matrix) ─────────────────────────────────────

    public const M1  = 'complaint_submitted';
    public const M2  = 'initial_validation_approved';
    public const M3  = 'initial_validation_returned';
    public const M4  = 'complaint_resubmitted';
    public const M5  = 'completion_submitted';
    public const M6  = 'completion_returned';
    public const M7  = 'completion_validated';
    public const M8  = 'status_in_progress';
    public const M9  = 'status_waiting_for_information';
    public const M10 = 'status_rejected';
    public const M11 = 'status_closed';
    public const M12 = 'rating_submitted';

    /**
     * Recipient "audiences" a milestone may target.
     */
    private const AUDIENCE_CITIZEN     = 'citizen';
    private const AUDIENCE_OPERATOR    = 'operator';
    private const AUDIENCE_SUPER_ADMIN = 'super_admin';

    /**
     * Milestone → recipient audiences (frozen decision matrix).
     *
     * @var array<string, list<string>>
     */
    private const RECIPIENTS = [
        self::M1  => [self::AUDIENCE_SUPER_ADMIN],
        self::M2  => [self::AUDIENCE_OPERATOR, self::AUDIENCE_CITIZEN],
        self::M3  => [self::AUDIENCE_CITIZEN],
        self::M4  => [self::AUDIENCE_SUPER_ADMIN],
        self::M5  => [self::AUDIENCE_SUPER_ADMIN],
        self::M6  => [self::AUDIENCE_OPERATOR],
        self::M7  => [self::AUDIENCE_CITIZEN, self::AUDIENCE_OPERATOR],
        self::M8  => [self::AUDIENCE_CITIZEN],
        self::M9  => [self::AUDIENCE_CITIZEN],
        self::M10 => [self::AUDIENCE_CITIZEN],
        self::M11 => [self::AUDIENCE_CITIZEN, self::AUDIENCE_OPERATOR],
        self::M12 => [self::AUDIENCE_OPERATOR, self::AUDIENCE_SUPER_ADMIN],
    ];

    /**
     * Milestone → [title, body-template]. Bodies are deliberately GENERIC and
     * contain NO internal reason, internal note, or PII — the recipient is
     * directed to the complaint detail where the information they are entitled
     * to is shown.
     *
     * @var array<string, array{0:string,1:string}>
     */
    private const COPY = [
        self::M1  => ['Laporan Diajukan', 'Laporan %s telah diajukan dan menunggu validasi awal.'],
        self::M2  => ['Laporan Diteruskan ke Dinas', 'Laporan %s telah divalidasi dan diteruskan ke Dinas/Unit terkait.'],
        self::M3  => ['Laporan Dikembalikan untuk Diperbaiki', 'Laporan %s dikembalikan kepada Anda untuk diperbaiki.'],
        self::M4  => ['Laporan Dikirim Ulang', 'Laporan %s telah dikirim ulang dan menunggu validasi.'],
        self::M5  => ['Pengajuan Penyelesaian Menunggu Validasi', 'Penyelesaian laporan %s telah diajukan dan menunggu validasi.'],
        self::M6  => ['Penyelesaian Dikembalikan ke Operator', 'Penyelesaian laporan %s dikembalikan untuk diperbaiki.'],
        self::M7  => ['Laporan Selesai', 'Laporan %s telah selesai.'],
        self::M8  => ['Laporan Sedang Diproses', 'Laporan %s sedang diproses.'],
        self::M9  => ['Laporan Menunggu Informasi', 'Laporan %s menunggu informasi.'],
        self::M10 => ['Laporan Ditolak', 'Laporan %s ditolak.'],
        self::M11 => ['Laporan Ditutup', 'Laporan %s telah ditutup.'],
        self::M12 => ['Rating Laporan Diterima', 'Rating kepuasan diterima untuk laporan %s.'],
    ];

    /**
     * Emit the milestone notification(s) for ONE business action.
     *
     * MUST be called INSIDE the caller's DB transaction, AFTER the canonical
     * audit row exists (its id is passed as `$sourceEventId`).
     *
     * @param  int  $sourceEventId  canonical audit_logs.id for this action
     * @param  User|null  $actor  the acting user (never notified about own action)
     */
    public function emit(Complaint $complaint, string $milestone, int $sourceEventId, ?User $actor = null): void
    {
        if (! isset(self::RECIPIENTS[$milestone])) {
            // Unknown milestone: never fabricate a notification.
            return;
        }

        [$title, $bodyTemplate] = self::COPY[$milestone];
        $body = sprintf($bodyTemplate, (string) $complaint->reference_code);

        $data = [
            'complaint_id'   => $complaint->id,
            'reference_code' => $complaint->reference_code,
            'milestone'      => $milestone,
            'status'         => $complaint->status?->value,
        ];

        foreach ($this->resolveRecipients($complaint, $milestone) as $recipient) {
            $this->deliver($recipient, $complaint, $milestone, $title, $body, $data, $sourceEventId, $actor);
        }
    }

    /**
     * Resolve the ACTIVE, eligible recipients for a milestone.
     *
     * Excludes: the actor (self-notification), Admin, Petugas, and any inactive
     * account. Operator routing ALWAYS uses `complaints.dinas_unit_id` →
     * `DinasUnit::operator()` (never `assigned_to`).
     *
     * @return Collection<int, User>
     */
    private function resolveRecipients(Complaint $complaint, string $milestone): Collection
    {
        $recipients = collect();

        foreach (self::RECIPIENTS[$milestone] as $audience) {
            match ($audience) {
                self::AUDIENCE_CITIZEN     => $this->addCitizen($recipients, $complaint),
                self::AUDIENCE_OPERATOR    => $this->addOperator($recipients, $complaint),
                self::AUDIENCE_SUPER_ADMIN => $this->addSuperAdmins($recipients),
                default                    => null,
            };
        }

        // De-duplicate by user id (a user can only be one role, but this keeps
        // the invariant explicit) and drop inactive accounts.
        return $recipients
            ->filter()
            ->unique(fn (User $u) => $u->id)
            ->filter(fn (User $u) => (bool) $u->is_active)
            ->values();
    }

    private function addCitizen(Collection $recipients, Complaint $complaint): void
    {
        if ($complaint->reporter !== null) {
            $recipients->push($complaint->reporter);
        }
    }

    /**
     * Operator recipient = the owner of the complaint's Dinas/Unit.
     *
     * G8: when the Dinas/Unit has NO Operator, the notification is SKIPPED and
     * the condition is logged. There is NEVER a fallback to another user and
     * `assigned_to` is NEVER consulted.
     */
    private function addOperator(Collection $recipients, Complaint $complaint): void
    {
        $operator = $complaint->dinasUnit?->operator;

        if ($operator === null) {
            Log::info('Notification skipped: Dinas/Unit has no Operator.', [
                'complaint_id'  => $complaint->id,
                'dinas_unit_id' => $complaint->dinas_unit_id,
            ]);

            return;
        }

        $recipients->push($operator);
    }

    /**
     * Every ACTIVE Super Admin receives the notification (G6). The actor is
     * excluded later in `deliver()`. One query for the whole set (never one
     * query per recipient).
     */
    private function addSuperAdmins(Collection $recipients): void
    {
        User::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->get()
            ->each(fn (User $u) => $recipients->push($u));
    }

    /**
     * Persist ONE notification for a recipient, idempotently.
     *
     * Rules enforced here:
     *   - never notify the actor (self-notification);
     *   - never create for an inactive recipient;
     *   - one business action × one recipient = one logical notification
     *     (application pre-check + UNIQUE database constraint).
     */
    private function deliver(
        User $recipient,
        Complaint $complaint,
        string $milestone,
        string $title,
        string $body,
        array $data,
        int $sourceEventId,
        ?User $actor,
    ): void {
        if ($actor !== null && (int) $recipient->id === (int) $actor->id) {
            return; // no self-notification
        }

        if (! $recipient->is_active) {
            return; // inactive recipients never receive NEW notifications
        }

        $key = [
            'recipient_user_id' => $recipient->id,
            'complaint_id'      => $complaint->id,
            'type'              => $milestone,
            'source_event_id'   => $sourceEventId,
        ];

        // Application-level pre-check (fast path).
        if (AppNotification::query()->where($key)->exists()) {
            return;
        }

        try {
            AppNotification::create($key + [
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
                'read_at' => null,
            ]);
        } catch (QueryException $e) {
            // The UNIQUE index is the final protection against a concurrent
            // duplicate. A duplicate-key violation here means the notification
            // already exists — swallow it (the logical notification is present).
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }
        }
    }

    /**
     * MySQL duplicate-key detection (SQLSTATE 23000 / errno 1062).
     */
    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000'
            && (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
