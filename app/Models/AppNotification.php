<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE N4 — In-app notification.
 *
 * The class is named `AppNotification` to avoid colliding with the framework's
 * `Illuminate\Notifications\Notification` (the project does NOT use Laravel's
 * polymorphic database notification channel — see NotificationService). The
 * TABLE is `notifications` (frozen decision G10).
 *
 * Owner-scoped by construction: every query in the UI starts from the
 * authenticated user's own `appNotifications()` relation, so a user can never
 * read, mark, or delete another user's notification (IDOR closed).
 */
class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'recipient_user_id',
        'complaint_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
        'source_event_id',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }

    // ─── Read state ───────────────────────────────────────────────────────────

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<AppNotification>  $query
     */
    public function scopeUnread($query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * @param  Builder<AppNotification>  $query
     */
    public function scopeForRecipient($query, int $userId): void
    {
        $query->where('recipient_user_id', $userId);
    }

    // ─── Render-time target resolution ────────────────────────────────────────

    /**
     * Resolve the safe destination URL for this notification AT RENDER TIME,
     * based on the RECIPIENT's role + the current complaint state.
     *
     * Never stored as a source of truth, and never points a user at data they
     * are not allowed to see: every branch re-checks the recipient's own access
     * (ownership for citizens, Dinas scope for operators) and falls back to a
     * safe listing page when the direct target is no longer valid.
     */
    public function targetUrl(?User $viewer = null): ?string
    {
        $viewer ??= auth()->user();

        if ($viewer === null || $this->complaint_id === null) {
            return null;
        }

        // Only the owner may follow the link; guards against a stale/forged row.
        if ((int) $this->recipient_user_id !== (int) $viewer->id) {
            return null;
        }

        $complaint = $this->complaint;
        if ($complaint === null) {
            return null;
        }

        if ($viewer->isSuperAdmin()) {
            return $this->superAdminTargetUrl($complaint);
        }

        if ($viewer->isOperator()) {
            // Respect the Operator's Dinas scope; never leak a cross-unit target.
            return $complaint->isVisibleToOperator($viewer)
                ? route('operator.complaint.show', $complaint)
                : route('operator.complaint.index');
        }

        if ($viewer->isMasyarakat()) {
            // Citizens may only ever reach their OWN complaint.
            return $complaint->reporter_id === $viewer->id
                ? route('citizen.complaint.show', $complaint)
                : route('citizen.history');
        }

        // Admin has no notification centre; safe fallback.
        return route('dashboard');
    }

    /**
     * Super Admin target: prefer the actionable queue detail (G5) while the
     * complaint is still in that queue, otherwise fall back to the global
     * monitoring detail. Never 404s.
     */
    private function superAdminTargetUrl(Complaint $complaint): string
    {
        if (in_array($this->type, [NotificationService::M1, NotificationService::M4], true)
            && $complaint->isAwaitingInitialValidation()) {
            return route('super-admin.validation.show', $complaint);
        }

        if ($this->type === NotificationService::M5 && $complaint->isPendingCompletionValidation()) {
            return route('super-admin.completion-validation.show', $complaint);
        }

        return route('super-admin.complaints.show', $complaint);
    }
}
