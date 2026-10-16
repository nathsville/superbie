<?php

namespace App\Observers;

use App\Models\DinasUnit;
use App\Services\DashboardCacheService;

/**
 * Invalidates the staff dashboard caches when master Dinas/Unit data changes.
 *
 * Dinas/Unit names are denormalised into the cached staff dashboards (admin,
 * operator, super admin). Without this, a rename or deactivation could stay
 * stale for up to the 60s cache TTL (audit §9). Mirrors the existing
 * ComplaintObserver / UserObserver pattern.
 */
class DinasUnitObserver
{
    public function __construct(
        protected DashboardCacheService $cacheService
    ) {}

    public function saved(DinasUnit $dinasUnit): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }

    public function deleted(DinasUnit $dinasUnit): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }
}
