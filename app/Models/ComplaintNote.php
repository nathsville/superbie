<?php

namespace App\Models;

use App\Enums\NoteVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintNote extends Model
{
    public $timestamps = false;

    // Append-only in normal UI
    protected $fillable = [
        'complaint_id',
        'author_id',
        'visibility',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'visibility' => NoteVisibility::class,
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isInternal(): bool
    {
        return $this->visibility === NoteVisibility::Internal;
    }

    public function isPublicResponse(): bool
    {
        return $this->visibility === NoteVisibility::PublicResponse;
    }
}
