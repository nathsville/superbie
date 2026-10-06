<?php

namespace App\Observers;

use App\Models\User;
use App\Services\DashboardCacheService;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    public function __construct(
        protected DashboardCacheService $cacheService
    ) {}

    public function saved(User $user): void
    {
        Cache::forget('dashboard:superadmin');
        // A user's Dinas/Unit (operator scope) or role change can alter what the
        // Operator dashboard shows → invalidate all scoped operator caches.
        $this->cacheService->bumpOperatorCacheVersion();
    }

    public function deleted(User $user): void
    {
        Cache::forget('dashboard:superadmin');
        $this->cacheService->bumpOperatorCacheVersion();
    }
}
