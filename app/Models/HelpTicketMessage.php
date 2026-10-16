<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PROMPT TAHAP 2 — Help Desk ticket message.
 *
 * Append-only: there is deliberately NO update/delete endpoint. The sender is
 * always the authenticated user (never read from the request), so a citizen can
 * never impersonate staff by forging `user_id`/`sender_id`.
 */
class HelpTicketMessage extends Model
{
    protected $fillable = [
        'help_ticket_id',
        'user_id',
        'body',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpTicket::class, 'help_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the message was written by staff (Super Admin). Derived from the
     * sender's ROLE at render time — never from a stored/forged field.
     */
    public function isFromStaff(): bool
    {
        return $this->user?->isStaff() ?? false;
    }
}
