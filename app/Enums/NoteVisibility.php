<?php

namespace App\Enums;

/**
 * Visibility options for complaint notes.
 */
enum NoteVisibility: string
{
    case Internal       = 'internal';
    case PublicResponse = 'public_response';

    public function label(): string
    {
        return match ($this) {
            self::Internal       => 'Catatan Internal',
            self::PublicResponse => 'Respons Publik',
        };
    }
}
