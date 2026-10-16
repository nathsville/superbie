<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * PHASE 4 — Bagian 2 — RATING ANALYTICS (D-4.1 .. D-4.15, FROZEN).
 *
 * Read-only AGGREGATE analytics over `complaint_ratings` JOIN `complaints`.
 * This service computes ONLY the frozen metrics and invents no new business rule
 * (no ranking, leaderboard, SLA, weighted average, sentiment, AI or export).
 *
 * Scope (D-4.1 / D-4.9):
 *   - Admin / Super Admin : GLOBAL (may filter by any Dinas/Unit or all).
 *   - Operator            : OWN Dinas/Unit ONLY — the scope is FORCED from
 *                           `users.dinas_unit_id` server-side; any client-supplied
 *                           `dinas_unit_id` is IGNORED (never trusted).
 *   - Masyarakat / Guest  : no route reaches this service (403 / login redirect).
 *
 * Time basis (D-4.10): every RATING metric is filtered by
 * `complaint_ratings.created_at`. The `resolved_without_rating` metric does NOT
 * come from a rating row (there is none), so it is a SEPARATE current-state
 * coverage aggregate (D-4.5, OPTION A, FINAL): complaints whose CURRENT status
 * is `resolved` AND which have no `complaint_ratings` row. It is deliberately
 * NOT filtered by the rating period — changing the rating period must never
 * change this figure. It keeps only the DIMENSION filters (Dinas/Unit, Kategori,
 * Kecamatan, Kelurahan) + the Operator scope, which are semantically meaningful
 * for a complaint.
 *
 * Privacy (D-4.8): the payload is aggregate-only. No reporter name / NIK / phone /
 * address / individual rating record is ever selected or returned.
 *
 * Cache (D-4.15): reuses the existing dashboard cache convention (TTL 60s). The
 * existing dashboards are cached under a single FILTERLESS key, so a filtered
 * analytics block cannot share it. A dedicated key namespace
 * `dashboard:rating:{scope}:{filter-hash}:{version}` is therefore used, and a
 * version counter (mirroring the existing operator-cache version) allows a single
 * write to invalidate ALL rating-analytics keys at once — the cache store has no
 * tags. `ComplaintRatingObserver` bumps the version the moment a rating is created.
 */
class RatingAnalyticsService
{
    /** Same TTL as every other dashboard cache block (D-4.15). */
    public const CACHE_TTL_SECONDS = DashboardCacheService::CACHE_TTL_SECONDS;

    /** Period presets (D-4.6 = E). */
    public const PERIOD_OPTIONS = [
        '6m'     => '6 Bulan Terakhir',
        '12m'    => '12 Bulan Terakhir',
        'year'   => 'Tahun Berjalan',
        'custom' => 'Rentang Kustom',
    ];

    /**
     * Version counter for the Rating Analytics cache namespace (D-4.15).
     *
     * Owned by {@see DashboardCacheService} so a single write there (e.g. a
     * complaint / master-data change) invalidates rating analytics too; this
     * service reads the same key so rating submissions invalidate their own
     * namespace consistently with the existing operator-cache version pattern.
     */
    private const CACHE_VERSION_KEY = DashboardCacheService::RATING_CACHE_VERSION_KEY;

    /**
     * Build the frozen Rating Analytics payload for a viewer + filter input.
     *
     * @param  array<string,mixed>  $input  Raw request input (period, start_date,
     *                                      end_date, dinas_unit_id, category_id,
     *                                      kecamatan_id, kelurahan_id).
     */
    public function getRatingAnalytics(User $viewer, array $input = [], bool $refresh = false): array
    {
        $scope   = $this->resolveScope($viewer, $input);
        $period  = $this->resolvePeriod(
            (string) ($input['period'] ?? '6m'),
            $input['start_date'] ?? null,
            $input['end_date'] ?? null,
        );
        $filters = $this->resolveFilters($input, $scope);

        $cacheKey = $this->cacheKey($scope, $period, $filters);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->compute($viewer, $scope, $period, $filters),
        );
    }

    /**
     * The scoped + filtered cache key (public for tests / invalidation proofs).
     */
    public function cacheKeyFor(User $viewer, array $input = []): string
    {
        $scope   = $this->resolveScope($viewer, $input);
        $period  = $this->resolvePeriod(
            (string) ($input['period'] ?? '6m'),
            $input['start_date'] ?? null,
            $input['end_date'] ?? null,
        );
        $filters = $this->resolveFilters($input, $scope);

        return $this->cacheKey($scope, $period, $filters);
    }

    /**
     * Invalidate EVERY rating-analytics cache entry at once.
     *
     * Called when a rating is created (D-4.15) and whenever a complaint / master
     * record that the analytics depends on changes (via DashboardCacheService).
     */
    public function clearCache(): void
    {
        Cache::put(self::CACHE_VERSION_KEY, $this->cacheVersion() + 1);
    }

    // ─── Scope ────────────────────────────────────────────────────────────────

    /**
     * @return array{isGlobal: bool, dinasUnitId: ?int, denied: bool}
     */
    private function resolveScope(User $viewer, array $input): array
    {
        // Admin + Super Admin are GLOBAL (D-4.1) and may filter by Dinas/Unit.
        if ($viewer->isAdmin() || $viewer->isSuperAdmin()) {
            return [
                'isGlobal'    => true,
                'dinasUnitId' => $this->validId($input['dinas_unit_id'] ?? null, 'dinas_units'),
                'denied'      => false,
            ];
        }

        // Operator: scope is FORCED from the authenticated account (D-4.9). A
        // client-supplied `dinas_unit_id` is deliberately ignored — the browser
        // is never trusted. An Operator without a unit is DENIED (no global
        // fallback), mirroring the existing operator dashboard behaviour.
        $own = $viewer->dinas_unit_id !== null ? (int) $viewer->dinas_unit_id : null;

        return [
            'isGlobal'    => false,
            'dinasUnitId' => $own,
            'denied'      => $own === null,
        ];
    }

    // ─── Period (D-4.6 = E) ───────────────────────────────────────────────────

    /**
     * @return array{key: string, label: string, start: Carbon, end: Carbon, months: list<array{ym: string, label: string}>}
     */
    private function resolvePeriod(string $period, mixed $startDate, mixed $endDate): array
    {
        if (! array_key_exists($period, self::PERIOD_OPTIONS)) {
            $period = '6m';
        }

        if ($period === 'custom') {
            $start = $this->parseDate($startDate);
            $end   = $this->parseDate($endDate);

            // start <= end, both valid, and never unbounded (both bounds present).
            if ($start !== null && $end !== null && $start->lte($end)) {
                return $this->customWindow($start, $end);
            }

            // Invalid custom input → safe default (never an unbounded query).
            $period = '6m';
        }

        return match ($period) {
            '12m'   => $this->monthWindow(12),
            'year'  => $this->yearWindow(),
            default => $this->monthWindow(6),
        };
    }

    /**
     * Last N CALENDAR months ending with the current month (consistent with the
     * existing dashboard trend convention — NOT an arbitrary rolling N×30 days).
     */
    private function monthWindow(int $months): array
    {
        $start  = now()->startOfMonth()->subMonths($months - 1);
        $end    = now()->endOfDay();
        $key    = $months === 12 ? '12m' : '6m';

        $list = [];
        for ($i = 0; $i < $months; $i++) {
            $month  = now()->startOfMonth()->subMonths($months - 1 - $i);
            $list[] = [
                'ym'    => $month->format('Y-m'),
                'label' => $month->translatedFormat('M Y'),
            ];
        }

        return [
            'key'    => $key,
            'label'  => self::PERIOD_OPTIONS[$key],
            'start'  => $start,
            'end'    => $end,
            'months' => $list,
        ];
    }

    /**
     * 1 January of the current year through today (application timezone).
     */
    private function yearWindow(): array
    {
        $start = now()->startOfYear();
        $end   = now()->endOfDay();

        $list   = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $list[] = [
                'ym'    => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('M Y'),
            ];
            $cursor->addMonthNoOverflow();
        }

        return [
            'key'    => 'year',
            'label'  => self::PERIOD_OPTIONS['year'],
            'start'  => $start,
            'end'    => $end,
            'months' => $list,
        ];
    }

    /**
     * Explicit [start, end] range (inclusive), bounded on both sides.
     */
    private function customWindow(Carbon $start, Carbon $end): array
    {
        $list   = [];
        $cursor = $start->copy()->startOfMonth();
        $last   = $end->copy()->startOfMonth();
        while ($cursor->lte($last)) {
            $list[] = [
                'ym'    => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('M Y'),
            ];
            $cursor->addMonthNoOverflow();
        }

        return [
            'key'    => 'custom',
            'label'  => self::PERIOD_OPTIONS['custom'],
            'start'  => $start->copy()->startOfDay(),
            'end'    => $end->copy()->endOfDay(),
            'months' => $list,
        ];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Filters (D-4.7 = C) ──────────────────────────────────────────────────

    /**
     * Server-side filter validation. Invalid values are DROPPED (never trusted,
     * never producing an unbounded or incorrect query). The Kelurahan is bounded
     * by the selected Kecamatan — the pair invariant is enforced here.
     *
     * @return array{category_id: ?int, kecamatan_id: ?int, kelurahan_id: ?int}
     */
    private function resolveFilters(array $input, array $scope): array
    {
        $kecamatanId = $this->validId($input['kecamatan_id'] ?? null, 'kecamatan');

        $kelurahanId = null;
        $candidate   = $this->validId($input['kelurahan_id'] ?? null, 'kelurahan');

        if ($candidate !== null) {
            if ($kecamatanId === null) {
                $kelurahanId = $candidate;
            } else {
                // A Kelurahan MUST belong to the selected Kecamatan.
                $belongs     = Kelurahan::whereKey($candidate)
                    ->where('kecamatan_id', $kecamatanId)
                    ->exists();
                $kelurahanId = $belongs ? $candidate : null;
            }
        }

        return [
            'category_id'  => $this->validId($input['category_id'] ?? null, 'complaint_categories'),
            'kecamatan_id' => $kecamatanId,
            'kelurahan_id' => $kelurahanId,
        ];
    }

    private function validId(mixed $value, string $table): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return DB::table($table)->where('id', $id)->exists() ? $id : null;
    }

    // ─── Aggregation (D-4.2, D-4.3, D-4.4, D-4.5, D-4.11) ─────────────────────

    /**
     * The single scoped rating base query (aggregate SQL only, no N+1).
     *
     * NOTE (D-4.11): there is deliberately NO complaint-status filter here — a
     * rating that was validly given while `resolved` is STILL counted after the
     * complaint later becomes `closed`.
     */
    private function ratingBase(array $scope, array $period, array $filters)
    {
        $query = DB::table('complaint_ratings as r')
            ->join('complaints as c', 'c.id', '=', 'r.complaint_id')
            ->whereBetween('r.created_at', [$period['start'], $period['end']]);

        if ($scope['dinasUnitId'] !== null) {
            $query->where('c.dinas_unit_id', $scope['dinasUnitId']);
        }
        if ($filters['category_id'] !== null) {
            $query->where('c.category_id', $filters['category_id']);
        }
        if ($filters['kecamatan_id'] !== null) {
            $query->where('c.kecamatan_id', $filters['kecamatan_id']);
        }
        if ($filters['kelurahan_id'] !== null) {
            $query->where('c.kelurahan_id', $filters['kelurahan_id']);
        }

        return $query;
    }

    private function compute(User $viewer, array $scope, array $period, array $filters): array
    {
        if ($scope['denied']) {
            return $this->emptyPayload($viewer, $scope, $period, $filters);
        }

        // 1) Totals + distribution + satisfaction in ONE aggregate query.
        $summary = $this->ratingBase($scope, $period, $filters)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(r.rating) as average')
            ->selectRaw('SUM(CASE WHEN r.rating >= 4 THEN 1 ELSE 0 END) as satisfied')
            ->first();

        $total     = (int) ($summary->total ?? 0);
        $satisfied = (int) ($summary->satisfied ?? 0);

        $average          = $summary->average !== null ? round((float) $summary->average, 2) : null;
        $satisfactionRate = $total > 0 ? round(($satisfied / $total) * 100, 1) : null;

        // 2) Distribution 1–5 (explicit buckets, one GROUP BY).
        $distributionRows = $this->ratingBase($scope, $period, $filters)
            ->select('r.rating')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('r.rating')
            ->pluck('total', 'rating');

        $distribution = [];
        for ($score = 1; $score <= 5; $score++) {
            $distribution[$score] = (int) ($distributionRows[$score] ?? 0);
        }

        // 3) Monthly trend (basis: complaint_ratings.created_at).
        $trendRows = $this->ratingBase($scope, $period, $filters)
            ->selectRaw("DATE_FORMAT(r.created_at, '%Y-%m') as ym, COUNT(*) as total, AVG(r.rating) as average")
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $trend = [];
        foreach ($period['months'] as $month) {
            $row = $trendRows->get($month['ym']);
            $trend[] = [
                'label'   => $month['label'],
                'ym'      => $month['ym'],
                'count'   => $row ? (int) $row->total : 0,
                // NULL (not 0) for a month with no rating — never "0 star".
                'average' => $row && $row->average !== null ? round((float) $row->average, 2) : null,
            ];
        }

        return [
            'scope'                => [
                'isGlobal'    => $scope['isGlobal'],
                'dinasUnitId' => $scope['dinasUnitId'],
            ],
            'period'               => $this->periodPayload($period, $filters),
            'filters'              => $this->filterPayload($filters, $scope),
            'hasData'              => $total > 0,
            'totalRatings'         => $total,
            'averageRating'        => $average,
            'satisfactionRate'     => $satisfactionRate,
            'satisfiedCount'       => $satisfied,
            'distribution'         => $distribution,
            'resolvedWithoutRating' => $this->resolvedWithoutRatingCount($scope, $filters),
            'byDinas'              => $this->breakdownByDinas($scope, $period, $filters),
            'byCategory'           => $this->breakdownByCategory($scope, $period, $filters),
            'trend'                => $trend,
            'options'              => $this->filterOptions($scope, $filters),
        ];
    }

    /**
     * D-4.5 (OPTION A, FINAL) — current-state coverage aggregate:
     * complaints whose CURRENT status is `resolved` AND which have NO
     * `complaint_ratings` row.
     *
     * This metric is NOT a rating metric: it has no rating row, so it is NOT
     * filtered by the rating period (`complaint_ratings.created_at`) and NOT by
     * `complaints.resolved_at`. Changing the rating period must never change this
     * value. Only the DIMENSION filters (Dinas/Unit, Kategori, Kecamatan,
     * Kelurahan) + the Operator scope apply, because those are semantically
     * meaningful for a complaint.
     *
     * It is an honest separate coverage/backlog count — NOT a negative rating,
     * NOT a dissatisfaction signal, and NEVER part of the satisfaction
     * denominator. `rejected` / `closed` are NOT counted (no `closed_without_rating`).
     */
    private function resolvedWithoutRatingCount(array $scope, array $filters): int
    {
        // Set-based NOT EXISTS (no per-row loop, no N+1).
        $query = DB::table('complaints as c')
            ->where('c.status', ComplaintStatus::Resolved->value)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw('1'))
                    ->from('complaint_ratings as r')
                    ->whereColumn('r.complaint_id', 'c.id');
            });

        if ($scope['dinasUnitId'] !== null) {
            $query->where('c.dinas_unit_id', $scope['dinasUnitId']);
        }
        if ($filters['category_id'] !== null) {
            $query->where('c.category_id', $filters['category_id']);
        }
        if ($filters['kecamatan_id'] !== null) {
            $query->where('c.kecamatan_id', $filters['kecamatan_id']);
        }
        if ($filters['kelurahan_id'] !== null) {
            $query->where('c.kelurahan_id', $filters['kelurahan_id']);
        }

        return (int) $query->count();
    }

    /**
     * D-4.2 #5 / AB — per Dinas/Unit: count, average, satisfaction (ONE GROUP BY).
     */
    private function breakdownByDinas(array $scope, array $period, array $filters): array
    {
        $rows = $this->ratingBase($scope, $period, $filters)
            ->select('c.dinas_unit_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(r.rating) as average')
            ->selectRaw('SUM(CASE WHEN r.rating >= 4 THEN 1 ELSE 0 END) as satisfied')
            ->groupBy('c.dinas_unit_id')
            ->get();

        $names = DinasUnit::whereIn('id', $rows->pluck('dinas_unit_id')->filter()->all())
            ->pluck('name', 'id');

        return $this->shapeBreakdown(
            $rows,
            fn ($row) => $row->dinas_unit_id !== null
                ? ($names[$row->dinas_unit_id] ?? 'Dinas/Unit Terhapus')
                : 'Belum ada Dinas/Unit',
        );
    }

    /**
     * D-4.2 #6 / AA — per category: count, average, satisfaction (ONE GROUP BY).
     */
    private function breakdownByCategory(array $scope, array $period, array $filters): array
    {
        $rows = $this->ratingBase($scope, $period, $filters)
            ->select('c.category_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(r.rating) as average')
            ->selectRaw('SUM(CASE WHEN r.rating >= 4 THEN 1 ELSE 0 END) as satisfied')
            ->groupBy('c.category_id')
            ->get();

        $names = ComplaintCategory::whereIn('id', $rows->pluck('category_id')->filter()->all())
            ->pluck('name', 'id');

        return $this->shapeBreakdown(
            $rows,
            fn ($row) => $row->category_id !== null
                ? ($names[$row->category_id] ?? 'Kategori Terhapus')
                : 'Tanpa Kategori',
        );
    }

    /**
     * @param  \Illuminate\Support\Collection  $rows
     * @return list<array{name: string, count: int, average: float, satisfactionRate: float}>
     */
    private function shapeBreakdown($rows, callable $nameResolver): array
    {
        $out = $rows->map(function ($row) use ($nameResolver) {
            $total = (int) $row->total;

            return [
                'name'             => $nameResolver($row),
                'count'            => $total,
                'average'          => $row->average !== null ? round((float) $row->average, 2) : null,
                'satisfactionRate' => $total > 0
                    ? round(((int) $row->satisfied / $total) * 100, 1)
                    : null,
            ];
        })->all();

        // Ordering by real volume is a presentation aid, not a "ranking policy".
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($out);
    }

    // ─── Filter option lists (master data) ────────────────────────────────────

    /**
     * Option lists for the filter form. Operators receive ONLY their own
     * Dinas/Unit — never a cross-Dinas comparison list (D-4.9 / R).
     *
     * CACHE-SAFE PAYLOAD: the lists are returned as PLAIN ARRAYS of
     * `['id' => int, 'name' => string]` (plus `kecamatan_id` for Kelurahan),
     * NEVER Eloquent models / Collections. The whole payload is stored with
     * `Cache::remember()` and the production store is `database`, whose
     * `unserialize()` runs with `allowed_classes => false`
     * (`config/cache.php` — `serializable_classes`). A cached model/Collection
     * would therefore come back as `__PHP_Incomplete_Class` on a cache HIT and
     * crash the view (`$category->id` on a string). Scalars round-trip safely,
     * so the option lists must be scalars too. This also keeps the payload
     * shape identical to {@see emptyPayload()}, which already returns arrays.
     */
    private function filterOptions(array $scope, array $filters): array
    {
        // Only ACTIVE Dinas/Unit are offered as GLOBAL filter options
        // (consistent with `DinasUnit::scopeActive()`): a deactivated row must
        // never remain a selectable filter value. An Operator is instead scoped
        // to their own unit regardless of its active flag — the scope is FORCED
        // server-side and never trusted from the client.
        if ($scope['isGlobal']) {
            $dinasUnits = DinasUnit::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            $dinasUnits = DinasUnit::query()
                ->whereKey($scope['dinasUnitId'])
                ->get(['id', 'name']);
        }

        $kecamatan = Kecamatan::query()->orderBy('name')->get(['id', 'name']);

        $kelurahan = Kelurahan::query()
            ->when($filters['kecamatan_id'], fn ($q, $v) => $q->where('kecamatan_id', $v))
            ->orderBy('name')
            ->get(['id', 'kecamatan_id', 'name']);

        $categories = ComplaintCategory::query()->orderBy('name')->get(['id', 'name']);

        return [
            'dinasUnits' => $dinasUnits
                ->map(fn ($unit) => ['id' => (int) $unit->id, 'name' => $unit->name])
                ->values()
                ->all(),
            'categories' => $categories
                ->map(fn ($category) => ['id' => (int) $category->id, 'name' => $category->name])
                ->values()
                ->all(),
            'kecamatan'  => $kecamatan
                ->map(fn ($kecamatan) => ['id' => (int) $kecamatan->id, 'name' => $kecamatan->name])
                ->values()
                ->all(),
            'kelurahan'  => $kelurahan
                ->map(fn ($kelurahan) => [
                    'id'           => (int) $kelurahan->id,
                    'kecamatan_id' => (int) $kelurahan->kecamatan_id,
                    'name'         => $kelurahan->name,
                ])
                ->values()
                ->all(),
        ];
    }

    private function emptyPayload(User $viewer, array $scope, array $period, array $filters): array
    {
        return [
            'scope'                => [
                'isGlobal'    => $scope['isGlobal'],
                'dinasUnitId' => $scope['dinasUnitId'],
            ],
            'period'               => $this->periodPayload($period, $filters),
            'filters'              => $this->filterPayload($filters, $scope),
            'hasData'              => false,
            'totalRatings'         => 0,
            'averageRating'        => null,
            'satisfactionRate'     => null,
            'satisfiedCount'       => 0,
            'distribution'         => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'resolvedWithoutRating' => 0,
            'byDinas'              => [],
            'byCategory'           => [],
            'trend'                => array_map(
                fn ($m) => ['label' => $m['label'], 'ym' => $m['ym'], 'count' => 0, 'average' => null],
                $period['months'],
            ),
            'options'              => [
                'dinasUnits' => [],
                'categories' => [],
                'kecamatan'  => [],
                'kelurahan'  => [],
            ],
        ];
    }

    // ─── Cache ────────────────────────────────────────────────────────────────

    private function cacheKey(array $scope, array $period, array $filters): string
    {
        $scopeKey  = $this->scopeKey($scope);
        $signature = implode('|', [
            $period['key'],
            $period['start']->toIso8601String(),
            $period['end']->toIso8601String(),
            (string) ($filters['category_id'] ?? ''),
            (string) ($filters['kecamatan_id'] ?? ''),
            (string) ($filters['kelurahan_id'] ?? ''),
        ]);

        return 'dashboard:rating:' . $scopeKey . ':' . md5($signature) . ':' . $this->cacheVersion();
    }

    private function scopeKey(array $scope): string
    {
        if ($scope['isGlobal']) {
            return 'global';
        }

        return 'unit:' . ($scope['dinasUnitId'] ?? 'none');
    }

    private function cacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    // ─── Small payload helpers (form repopulation) ────────────────────────────

    private function periodPayload(array $period, array $filters): array
    {
        return [
            'key'        => $period['key'],
            'label'      => $period['label'],
            'start'      => $period['start']->toDateString(),
            'end'        => $period['end']->toDateString(),
            'isCustom'   => $period['key'] === 'custom',
        ];
    }

    private function filterPayload(array $filters, array $scope): array
    {
        return [
            'dinas_unit_id' => $scope['dinasUnitId'],
            'category_id'   => $filters['category_id'],
            'kecamatan_id'  => $filters['kecamatan_id'],
            'kelurahan_id'  => $filters['kelurahan_id'],
        ];
    }
}
