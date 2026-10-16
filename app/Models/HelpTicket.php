<?php

namespace App\Models;

use App\Enums\HelpDeskCategory;
use App\Enums\HelpTicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PROMPT TAHAP 2 — Help Desk ticket.
 *
 * A support ticket is SEPARATE from a complaint: it never mutates complaint
 * status/category/routing/notes. `related_complaint_id` is only a context
 * reference and is NULLed automatically if the referenced complaint is purged.
 *
 * Owner-scoped access is enforced server-side in the controllers; every citizen
 * query starts from `User::helpTickets()` / an explicit `user_id` predicate so a
 * hand-crafted id can never reach another user's ticket (IDOR closed).
 */
class HelpTicket extends Model
{
    protected $fillable = [
        'reference_code',
        'user_id',
        'subject',
        'category',
        'description',
        'related_complaint_id',
        'status',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'category'    => HelpDeskCategory::class,
            'status'      => HelpTicketStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** The citizen who owns the ticket. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The append-only conversation, oldest first (deterministic: created_at, id).
     */
    public function messages(): HasMany
    {
        return $this->hasMany(HelpTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * OPTIONAL context reference to the owner's own complaint. Only a reference:
     * no complaint data is mutated, and the relation is NULL after a purge.
     */
    public function relatedComplaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'related_complaint_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ─── Behaviour helpers (single source of truth for the UI + controllers) ──

    public function allowsCitizenReply(): bool
    {
        return $this->status->allowsCitizenReply();
    }

    public function allowsCitizenReopen(): bool
    {
        return $this->status->allowsCitizenReopen();
    }

    public function allowsStaffReply(): bool
    {
        return $this->status->allowsStaffReply();
    }
}
