<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * TTL cache for the PUBLIC market surface (`/siphp`, PHASE S — audit PROMPT_28).
 *
 * The public pages compute latest/previous price aggregates on every request.
 * Results are cached for 60 seconds — the same staleness bound the staff
 * dashboards use — and every market-data write bumps a version counter, which
 * invalidates ALL cached entries in one write (the production cache store has
 * no tags). Same version-key pattern as RatingAnalyticsService / operator
 * caches.
 */
class PublicMarketCacheService
{
    /** Version counter — bump = invalidate every cached public-market entry. */
    public const VERSION_KEY = 'siphp:public:version';

    /** Matches the staff dashboard TTL: a staleness bound, not a promise. */
    public const TTL_SECONDS = 60;

    /**
     * Remember a computed value for this version of the data.
     */
    public function remember(string $name, Closure $callback): mixed
    {
        return Cache::remember($this->key($name), self::TTL_SECONDS, $callback);
    }

    /**
     * The version-carrying cache key (public for tests / invalidation proofs).
     */
    public function key(string $name): string
    {
        return 'siphp:public:v' . $this->version() . ':' . $name;
    }

    /**
     * Invalidate every cached entry in one atomic write.
     */
    public function bumpVersion(): void
    {
        Cache::put(self::VERSION_KEY, $this->version() + 1);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
