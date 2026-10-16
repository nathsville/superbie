<?php

namespace App\Observers;

use App\Models\ComplaintCategory;
use App\Services\DashboardCacheService;

/**
 * Invalidates the staff dashboard caches when master Category data changes.
 *
 * Category names / active flags are denormalised into the cached staff
 * dashboards (admin, operator, super admin). Without this, a rename or
 * deactivation could stay stale for up to the 60s cache TTL (audit §9).
 * Mirrors the existing ComplaintObserver / UserObserver pattern.
 */
class ComplaintCategoryObserver
{
    public function __construct(
        protected DashboardCacheService $cacheService
    ) {}

    public function saved(ComplaintCategory $category): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }

    public function deleted(ComplaintCategory $category): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }
}
