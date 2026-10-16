<?php

namespace App\Observers;

use App\Models\ComplaintRating;
use App\Services\DashboardCacheService;

/**
 * PHASE 4 — Bagian 2 — D-4.15 cache invalidation.
 *
 * A rating INSERT/UPDATE does NOT touch the parent `complaints` row, so the
 * existing `ComplaintObserver` never fires on rating submission and the rating
 * analytics block would go stale for up to its 60s TTL. This observer mirrors
 * the existing observer architecture (ComplaintObserver / UserObserver / …) and
 * bumps the rating cache version the moment a rating is created, invalidating
 * EVERY rating-analytics cache entry in one write (the cache store has no tags).
 *
 * `deleted` is covered defensively (the retention purge uses a mass delete that
 * does not fire model events, but it also deletes the parent complaint, which
 * already bumps the rating version via `clearGlobalDashboardCaches()`).
 */
class ComplaintRatingObserver
{
    public function __construct(
        protected DashboardCacheService $cacheService
    ) {}

    public function created(ComplaintRating $rating): void
    {
        $this->cacheService->bumpRatingCacheVersion();
    }

    public function deleted(ComplaintRating $rating): void
    {
        $this->cacheService->bumpRatingCacheVersion();
    }
}
