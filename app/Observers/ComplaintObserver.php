<?php

namespace App\Observers;

use App\Models\Complaint;
use App\Services\DashboardCacheService;

class ComplaintObserver
{
    public function __construct(
        protected DashboardCacheService $cacheService
    ) {}

    public function saved(Complaint $complaint): void
    {
        $this->cacheService->clearCitizenCache($complaint->reporter_id);
        $this->cacheService->clearGlobalDashboardCaches();
    }

    public function deleted(Complaint $complaint): void
    {
        $this->cacheService->clearCitizenCache($complaint->reporter_id);
        $this->cacheService->clearGlobalDashboardCaches();
    }
}
