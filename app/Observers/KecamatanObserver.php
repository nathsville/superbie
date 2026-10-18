<?php

namespace App\Observers;

use App\Models\Kecamatan;
use App\Services\DashboardCacheService;

/**
 * Invalidates the staff dashboard caches when master Kecamatan data changes.
 *
 * Kecamatan names are denormalised into the cached staff dashboards (admin,
 * operator, super admin) and the rating analytics. Without this, a rename
 * could stay stale for up to the 60s cache TTL (audit PROMPT_28). Mirrors the
 * ComplaintCategoryObserver / DinasUnitObserver pattern.
 */
class KecamatanObserver
{
    public function __construct(
        protected DashboardCacheService $cacheService
    ) {}

    public function saved(Kecamatan $kecamatan): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }

    public function deleted(Kecamatan $kecamatan): void
    {
        $this->cacheService->clearGlobalDashboardCaches();
    }
}
