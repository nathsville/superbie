<?php

namespace App\Observers;

use App\Services\PublicMarketCacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates the public market cache when any record that feeds `/siphp`
 * changes: markets, item categories, items, recorded prices and stores.
 *
 * Registered for those five models in AppServiceProvider. The public surface
 * accepts the same 60s staleness bound as the staff dashboards; this observer
 * keeps that bound tight on every Eloquent write (audit PROMPT_28).
 */
class MarketDataObserver
{
    public function __construct(
        protected PublicMarketCacheService $cacheService
    ) {}

    public function saved(Model $model): void
    {
        $this->cacheService->bumpVersion();
    }

    public function deleted(Model $model): void
    {
        $this->cacheService->bumpVersion();
    }
}
