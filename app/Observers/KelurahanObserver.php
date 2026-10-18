<?php

namespace App\Observers;

use App\Models\Kelurahan;
use App\Services\DashboardCacheService;

/**
 * Invalidates the staff dashboard caches when master Kelurahan data changes.
 *
 * Kelurahan names are denormalised into the cached staff dashboards (admin,
 * operator, super admin) and the rating analytics. Without this, a rename or
 * a move between Kecamatan could stay stale for up to the 60s cache TTL
 * (audit PROMPT_28). Mirrors the ComplaintCategoryObserver / DinasUnitObserver
 * pattern.
 */
class KelurahanObserver
{
    public function __construct(
        protected DashboardCacheService $cacheService
    ) {}

    public function saved(Kelurahan $kelurahan): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }

    public function deleted(Kelurahan $kelurahan): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }
}
