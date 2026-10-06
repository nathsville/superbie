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
        'dinas_unit_id',
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

    /**
     * ACTUAL Dinas/Unit destination chosen by the Operator (Prompt 5C).
     * Stored (not dynamic) so history is stable against mapping changes.
     * May be null (not yet routed, or master data removed → set null).
     */
    public function dinasUnit(): BelongsTo
    {
        return $this->belongsTo(DinasUnit::class, 'dinas_unit_id');
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

    /**
     * Restrict the query to complaints VISIBLE to an Operator (Prompt 15 §4).
     *
     * FINAL, approved visibility rule (BDR-1 = 1a, BDR-2 = 2c hybrid):
     *   An Operator sees a Complaint iff:
     *     operator.dinas_unit_id IS NOT NULL
     *     AND (
     *          complaint.dinas_unit_id = operator.dinas_unit_id
     *          OR (complaint.dinas_unit_id IS NULL
     *              AND the complaint's category is mapped to operator.dinas_unit_id
     *              via `category_dinas_unit`)
     *     )
     *
     * An Operator WITHOUT a unit is DENIED everything — no global fallback.
     */
    public function scopeVisibleToOperator($query, User $operator)
    {
        $unitId = $operator->dinas_unit_id;

        if ($unitId === null) {
            // No unit → no scope at all (deny). Never a global fallback.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($unitId) {
            $q->where('complaints.dinas_unit_id', $unitId)
              ->orWhere(function ($q2) use ($unitId) {
                  $q2->whereNull('complaints.dinas_unit_id')
                     ->whereHas('category', function ($categoryQuery) use ($unitId) {
                         $categoryQuery->whereHas('dinasUnits', function ($dinasQuery) use ($unitId) {
                             $dinasQuery->where('dinas_units.id', $unitId);
                         });
                     });
              });
        });
    }

    /**
     * Per-instance guard mirroring {@see scopeVisibleToOperator()} exactly.
     *
     * Reuses the scope so the list query and the per-record authorization can
     * never diverge (single source of truth for the visibility rule).
     */
    public function isVisibleToOperator(User $operator): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->visibleToOperator($operator)
            ->exists();
    }
}
