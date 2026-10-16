<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Profile photo storage (approved profile-page redesign).
 *
 * Stores an uploaded profile photo on the `public` disk under a per-user
 * folder and removes the previous file so no orphaned images accumulate. The
 * stored value is the RELATIVE path (users.avatar_path); the public URL is
 * resolved by User::avatarUrl(). Nothing is fabricated — an account without a
 * photo simply has a NULL avatar_path and the UI falls back to initials.
 */
class ProfilePhotoService
{
    /** Accepted image extensions for a profile photo. */
    public const MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    /** Max size in kilobytes (2 MB). */
    public const MAX_KILOBYTES = 2048;

    /**
     * Persist the uploaded photo for $user, deleting the previous one.
     * Returns the new relative path (already assigned to $user->avatar_path).
     */
    public function store(User $user, UploadedFile $file): string
    {
        $previous = $user->avatar_path;

        $path = $file->store('avatars/'.$user->id, 'public');

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $user->avatar_path = $path;

        return $path;
    }
}
