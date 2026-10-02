<?php

namespace App\Models;

use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code',
        'tracking_secret_hash',
        'category_id',
        'assigned_to',
        'reporter_id',
        'reporter_email',
        'reporter_phone',
        'title',
        'description',
        'location_text',
        'status',
        'public_updated_at',
        'submitted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status'           => ComplaintStatus::class,
            'submitted_at'     => 'datetime',
            'resolved_at'      => 'datetime',
            'public_updated_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'category_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ComplaintStatusHistory::class)->orderBy('created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ComplaintNote::class)->orderBy('created_at');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(ComplaintNote::class)
            ->where('visibility', 'internal')
            ->orderBy('created_at');
    }

    public function publicResponses(): HasMany
    {
        return $this->hasMany(ComplaintNote::class)
            ->where('visibility', 'public_response')
            ->orderBy('created_at');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForReporter($query, int $userId)
    {
        return $query->where('reporter_id', $userId);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
