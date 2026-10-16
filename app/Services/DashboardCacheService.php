<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardCacheService
{
    public const CACHE_TTL_SECONDS = 60;

    /**
     * Version counter for the Operator dashboard cache namespace (Prompt 15).
     *
     * The cache store is `database` (no tags), so per-unit invalidation is done
     * by bumping this version: every operator cache key embeds the current
     * version, so a single write invalidates ALL operator caches at once without
     * enumerating Dinas/Unit rows.
     */
    private const OPERATOR_CACHE_VERSION_KEY = 'dashboard:operator:version';

    /**
     * Version counter for the Rating Analytics cache namespace (PHASE 4 —
     * Bagian 2 — D-4.15).
     *
     * Rating analytics is a FILTERED block (period + Dinas/Kategori/Kecamatan/
     * Kelurahan), so it cannot share the single filterless `dashboard:admin` key
     * — it lives under its own `dashboard:rating:*` namespace (see
     * {@see \App\Services\RatingAnalyticsService}). The cache store has no tags,
     * so invalidation is done by bumping this version: every rating key embeds
     * it, so ONE write invalidates every rating cache. `ComplaintRatingObserver`
     * bumps it on rating creation; complaint / master-data changes bump it here
     * too (rating aggregates depend on complaint columns).
     */
    public const RATING_CACHE_VERSION_KEY = 'dashboard:rating:version';

    /**
     * Get or cache Citizen dashboard metrics.
     * Caches heavy aggregate queries and merges recent complaint models fresh.
     */
    public function getCitizenData(User $user, bool $refresh = false): array
    {
        $cacheKey = "dashboard:citizen:{$user->id}";

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $metrics = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user) {
            return [
                'totalLaporan'   => Complaint::forReporter($user->id)->count(),
                // "Diproses" = ACTIVE workflow only (submitted + in_progress).
                // LC-2C: legacy statuses (under_review / waiting_for_information)
                // are history-only and must NOT be counted as active processing.
                'laporanProses'  => Complaint::forReporter($user->id)->whereIn('status', self::ACTIVE_STATUS_VALUES)->count(),
                'laporanSelesai' => Complaint::forReporter($user->id)->byStatus('resolved')->count(),
                'laporanDitolak' => Complaint::forReporter($user->id)->byStatus('rejected')->count(),
                'cachedAt'       => now()->toIso8601String(),
            ];
        });

        return array_merge($metrics, [
            'laporanTerbaru' => Complaint::forReporter($user->id)
                ->with('category')
                ->orderByDesc('submitted_at')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Get or cache Operator dashboard metrics (Prompt 15 — scoped).
     *
     * Operators only see complaints within their Dinas/Unit (hybrid rule); a
     * Super Admin viewing this dashboard keeps the GLOBAL operational overview.
     * An Operator without a unit sees an honest empty dashboard (denied scope).
     */
    public function getOperatorData(User $user, bool $refresh = false): array
    {
        $cacheKey = $this->operatorCacheKey($user);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $metrics = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user) {
            $statusBreakdown = [];
            foreach (ComplaintStatus::cases() as $status) {
                $statusBreakdown[$status->value] = [
                    'label' => $status->label(),
                    'badge' => $status->tailwindBadge(),
                    'count' => $this->operatorScopedQuery($user)->byStatus($status->value)->count(),
                    // LC-2C: legacy statuses are history-only.
                    'legacy' => $status->isLegacy(),
                ];
            }

            return [
                'totalLaporan'    => $this->operatorScopedQuery($user)->count(),
                // PHASE 6C — Decision 3: the "belum ditugaskan" metric was
                // REDEFINED to "Belum Ditentukan Dinas/Unit" and now counts
                // active reports with NO routing destination (`dinas_unit_id IS
                // NULL`). The legacy `assigned_to IS NULL` definition is REMOVED
                // (assigned_to is a deprecated column, never written by runtime).
                'belumDinasUnit'  => $this->operatorScopedQuery($user)
                    ->whereNull('dinas_unit_id')
                    ->whereIn('status', self::ACTIVE_STATUS_VALUES)
                    ->count(),
                // "Perlu Tindakan" = awaiting review/initial validation, i.e. the
                // ACTIVE `submitted` status. LC-2C: the legacy `under_review`
                // status is no longer an actionable workflow stage.
                'perluTindakan'   => $this->operatorScopedQuery($user)
                    ->where('status', ComplaintStatus::Submitted->value)
                    ->count(),
                'selesaiHariIni'  => $this->operatorScopedQuery($user)->byStatus('resolved')
                    ->whereDate('resolved_at', today())
                    ->count(),
                'statusBreakdown' => $statusBreakdown,
                'aging'           => $this->agingBuckets($this->operatorScopedQuery($user)),
                'kategoriTeratas' => $this->categoryDistribution($this->operatorScopedQuery($user)),
                'trenBulanan'     => $this->monthlyTrend($this->operatorScopedQuery($user)),
                'cachedAt'        => now()->toIso8601String(),
            ];
        });

        return array_merge($metrics, [
            'laporanTerbaru'  => $this->operatorScopedQuery($user)
                ->with(['reporter', 'category', 'dinasUnit'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get(),
            'laporanPerhatian' => $this->activeBase($this->operatorScopedQuery($user))
                ->with(['reporter', 'category', 'dinasUnit'])
                ->whereNotNull('submitted_at')
                ->orderBy('submitted_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Base query applying the Operator visibility scope (Prompt 15 §4).
     * Super Admin is GLOBAL and therefore unscoped.
     */
    private function operatorScopedQuery(User $user)
    {
        $query = Complaint::query();

        if (! $user->isSuperAdmin()) {
            $query->visibleToOperator($user);
        }

        return $query;
    }

    /**
     * Current Operator cache version (defaults to 1 when unset).
     */
    private function operatorCacheVersion(): int
    {
        return (int) Cache::get(self::OPERATOR_CACHE_VERSION_KEY, 1);
    }

    /**
     * The scoped cache key for an Operator's dashboard (public for tests).
     */
    public function operatorCacheKey(User $user): string
    {
        $scopeKey = $user->isSuperAdmin()
            ? 'global'
            : 'unit:' . ($user->dinas_unit_id ?? 'none');

        return 'dashboard:operator:' . $scopeKey . ':' . $this->operatorCacheVersion();
    }

    /**
     * Get or cache Admin dashboard metrics.
     * Caches all heavy KPI metrics and status breakdowns.
     */
    public function getAdminData(bool $refresh = false): array
    {
        $cacheKey = 'dashboard:admin';

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $metrics = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () {
            // ONE `GROUP BY status` aggregation drives every status-derived
            // figure below (breakdown, flow funnel, aktif/selesai KPIs). This
            // replaces ~14 separate COUNT(*) queries with a single query and
            // produces identical business results (Prompt 2 — Phase 6).
            $statusCounts = $this->statusCounts(Complaint::query());

            $statusBreakdown = [];
            foreach (ComplaintStatus::cases() as $status) {
                $statusBreakdown[$status->value] = [
                    'label' => $status->label(),
                    'badge' => $status->tailwindBadge(),
                    'count' => $statusCounts[$status->value] ?? 0,
                    // LC-2C: legacy statuses are history-only; the view labels
                    // them "historis" so they are never read as active stages.
                    'legacy' => $status->isLegacy(),
                ];
            }

            // ONE real total, reused by the geographic card so it never issues
            // its own COUNT(*) (Prompt 5/5 — reuse the already-computed figure).
            $totalReports = Complaint::count();

            return [
                'totalLaporan'     => $totalReports,
                'laporanHariIni'   => Complaint::whereDate('submitted_at', today())->count(),
                'laporanAktif'     => $this->activeTotalFromCounts($statusCounts),
                'laporanSelesai'   => $statusCounts[ComplaintStatus::Resolved->value] ?? 0,
                'auditHariIni'     => AuditLog::whereDate('created_at', today())->count(),
                'statusBreakdown'  => $statusBreakdown,
                'aging'            => $this->agingBuckets(Complaint::query()),
                // NOTE: `kategoriTeratas` is intentionally NOT computed on the
                // Admin path — the Admin dashboard does not render it (audit
                // §8). The shared `categoryDistribution()` method is kept for
                // the Operator & Super Admin dashboards.
                'kategoriBacklog'  => $this->categoryBacklogDistribution(Complaint::query()),
                'kategoriMatrix'   => $this->categoryStatusMatrix(Complaint::query()),
                'statusFlow'       => $this->statusFlow($statusCounts),
                'kategoriPenyelesaian' => $this->categoryCompletion(Complaint::query()),
                'dinasStatusBreakdown' => $this->dinasStatusBreakdown(Complaint::query()),
                'dinasLongAging'   => $this->dinasLongAging(Complaint::query()),
                'trenBulanan'      => $this->monthlyTrend(Complaint::query()),
                // Geographic distribution (Prompt 5/5): administrative counts
                // (kecamatan/kelurahan) + geographic pins/heat points. Reuses
                // the already-computed `$totalReports` (no extra COUNT(*)).
                'geographicMap'    => $this->geographicMap($totalReports),
                // Geographic Analytics Layer 1 (Prompt 6/5): Top Kecamatan,
                // Top Kelurahan, backlog per Kelurahan, and the Kelurahan ×
                // Status matrix. Administrative (kecamatan/kelurahan) counts
                // only — coordinates are irrelevant here. Computed inside this
                // same cache closure (TTL 60s); adds no COUNT(*) of its own.
                'geographicAnalytics' => $this->geographicAnalytics(),
                // Geographic Analytics Layer 2A (Prompt 7/5 — Bagian 1/3):
                // Wilayah × Kategori. Administrative aggregation
                // (kelurahan_id + category_id) — coordinates are NOT used.
                'geographicLayer2A' => $this->geographicLayer2A(),
                // Geographic Analytics Layer 2B (Prompt 8/5 — Bagian 1/3):
                // operational wilayah analytics (oldest active case, average
                // active age, active-rate, active-load). One aggregate scan,
                // single evaluation timestamp, coordinates NOT used.
                'geographicLayer2B' => $this->geographicLayer2B(),
                'cachedAt'         => now()->toIso8601String(),
            ];
        });

        return array_merge($metrics, [
            // `dinasUnit` is eager-loaded so the monitoring rows never trigger
            // an N+1 (Prompt 2 — Phase 5). `assignee` is NOT loaded (PHASE 6C —
            // `assigned_to` is deprecated; the handling Operator is resolved
            // from `dinas_unit_id`).
            'laporanTerbaru'   => Complaint::with(['reporter', 'category', 'dinasUnit'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get(),
            'laporanPerhatian' => Complaint::with(['reporter', 'category', 'dinasUnit'])
                ->whereIn('status', self::ACTIVE_STATUS_VALUES)
                // ASC: the card is labelled "berumur tertinggi", so the
                // longest-active report must sit at the top (audit fix — the
                // previous DESC order put the newest report first).
                ->orderBy('submitted_at')
                ->limit(10)
                ->get(),
            'auditTerbaru'     => AuditLog::with('actor')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
            'attention'        => $this->attentionSummary(Complaint::query()),
        ]);
    }

    /**
     * Get or cache Super Admin dashboard metrics.
     * Caches system metrics, user counts, role & status breakdowns.
     */
    public function getSuperAdminData(bool $refresh = false): array
    {
        $cacheKey = 'dashboard:superadmin';

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $metrics = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () {
            // ONE `GROUP BY status` aggregation drives every status-derived
            // figure below (breakdown + aktif/selesai KPIs), mirroring the Admin
            // path. This replaces 7 separate COUNT(*) queries with a single query
            // and produces identical business results (Prompt 2 — Phase 6).
            $statusCounts = $this->statusCounts(Complaint::query());

            $statusBreakdown = [];
            foreach (ComplaintStatus::cases() as $status) {
                $statusBreakdown[$status->value] = [
                    'label' => $status->label(),
                    'badge' => $status->tailwindBadge(),
                    'count' => $statusCounts[$status->value] ?? 0,
                    // LC-2C: legacy statuses are history-only.
                    'legacy' => $status->isLegacy(),
                ];
            }

            $roleBreakdown = User::selectRaw('role, count(*) as total')
                ->groupBy('role')
                ->pluck('total', 'role')
                ->toArray();

            return [
                'totalLaporan'    => Complaint::count(),
                'totalPengguna'   => User::count(),
                'laporanAktif'    => $this->activeTotalFromCounts($statusCounts),
                'laporanSelesai'  => $statusCounts[ComplaintStatus::Resolved->value] ?? 0,
                'auditHariIni'    => AuditLog::whereDate('created_at', today())->count(),
                'penggunaBaru'    => User::whereDate('created_at', today())->count(),
                'roleBreakdown'   => $roleBreakdown,
                'statusBreakdown' => $statusBreakdown,
                'aging'           => $this->agingBuckets(Complaint::query()),
                'kategoriTeratas' => $this->categoryDistribution(Complaint::query()),
                'dinasDistribution' => $this->dinasDistribution(Complaint::query()),
                'trenBulanan'     => $this->monthlyTrend(Complaint::query()),
                'cachedAt'        => now()->toIso8601String(),
            ];
        });

        return array_merge($metrics, [
            'laporanTerbaru'  => Complaint::with(['reporter', 'category', 'dinasUnit'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get(),
            'auditTerbaru'    => AuditLog::with('actor')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
            'laporanPerhatian' => Complaint::with(['reporter', 'category', 'dinasUnit'])
                ->whereIn('status', self::ACTIVE_STATUS_VALUES)
                ->whereNotNull('submitted_at')
                ->orderBy('submitted_at')
                ->limit(8)
                ->get(),
            'attention'       => $this->attentionSummary(Complaint::query()),
        ]);
    }

    // ─── Derived analytics (Prompt 27) ────────────────────────────────────────
    //
    // Every value below is computed ONLY from columns that already exist on the
    // frozen schema (complaints.submitted_at / resolved_at / category_id /
    // dinas_unit_id, complaint_categories.name, dinas_units.name). Nothing here
    // is an SLA claim, a forecast, a growth %, or a fabricated figure.

    /**
     * The ACTIVE workflow statuses — the single source of truth for every
     * "active" KPI in this service. Mirrors {@see ComplaintStatus::activeCases()}
     * (submitted, in_progress).
     *
     * LC-2C: legacy statuses (under_review, waiting_for_information, closed) and
     * final business outcomes (resolved, rejected) are NOT active and must never
     * be counted as workflow-active. Kept as a constant (not a method) so a
     * regression test can assert it never drifts from the enum.
     */
    private const ACTIVE_STATUS_VALUES = ['submitted', 'in_progress'];

    /**
     * Restrict any scoped base query to ACTIVE complaints only.
     */
    private function activeBase($baseQuery)
    {
        return (clone $baseQuery)->whereIn('status', self::ACTIVE_STATUS_VALUES);
    }

    /**
     * ONE `GROUP BY status` aggregation, normalised to the frozen status enum.
     *
     * Returns `[statusValue => int]` for all seven statuses (0 when absent) so
     * every status-derived metric (breakdown, funnel flow, aktif/selesai KPIs)
     * can be derived from a single query instead of a COUNT(*) per status.
     * Results are identical to the previous per-status counts.
     *
     * @return array<string, int>
     */
    private function statusCounts($baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $counts = [];
        foreach (ComplaintStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($rows as $row) {
            // `status` is cast to ComplaintStatus by the model.
            $status = $row->status;
            $key    = $status instanceof ComplaintStatus ? $status->value : (string) $status;

            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * Active workflow total derived from a `statusCounts()` map.
     * Equivalent to `whereIn('status', ACTIVE_STATUS_VALUES)->count()`.
     * Legacy + final-outcome statuses are excluded (LC-2C).
     */
    private function activeTotalFromCounts(array $statusCounts): int
    {
        $total = 0;
        foreach (self::ACTIVE_STATUS_VALUES as $status) {
            $total += $statusCounts[$status] ?? 0;
        }

        return $total;
    }

    /**
     * Factual age buckets from `submitted_at` in ONE query.
     *
     * `$cutoffs` are ascending age edges in days; they produce len+1 buckets.
     * For [2, 4, 8] → [0–1 hari], [2–3 hari], [4–7 hari], [>7 hari].
     * For [2, 4]    → [0–1 hari], [2–3 hari], [>3 hari].
     *
     * The caller must already have restricted `$query` (active / non-null
     * `submitted_at`). Conditional aggregation keeps this to a single query
     * instead of a COUNT(*) per bucket — no SLA, just elapsed time.
     *
     * @param  list<int>  $cutoffs  ascending exclusive age edges, in days
     * @return list<int>  one count per bucket (same order as the derived buckets)
     */
    private function ageBucketCounts($query, array $cutoffs): array
    {
        $now      = now();
        $edges    = count($cutoffs);
        $buckets  = $edges + 1;
        $selects  = [];
        $bindings = [];

        for ($i = 0; $i < $buckets; $i++) {
            $conds = [];

            // Upper bound inherited from the previous edge (all but bucket 0).
            if ($i >= 1) {
                $conds[]    = 'submitted_at <= ?';
                $bindings[] = $now->copy()->subDays($cutoffs[$i - 1]);
            }

            // Lower bound: strictly older than this edge (all but the last).
            if ($i <= $edges - 1) {
                $conds[]    = 'submitted_at > ?';
                $bindings[] = $now->copy()->subDays($cutoffs[$i]);
            }

            $condition = $conds === [] ? '1 = 1' : implode(' AND ', $conds);
            $selects[] = 'SUM(CASE WHEN '.$condition.' THEN 1 ELSE 0 END) as bucket_'.$i;
        }

        $row = (clone $query)->selectRaw(implode(', ', $selects), $bindings)->first();

        $out = [];
        for ($i = 0; $i < $buckets; $i++) {
            $out[] = (int) ($row->{'bucket_'.$i} ?? 0);
        }

        return $out;
    }

    /**
     * Pair a list of bucket labels with their counts.
     *
     * @param  list<string>  $labels
     * @param  list<int>     $counts
     * @return list<array{label: string, count: int}>
     */
    private function labelBuckets(array $labels, array $counts): array
    {
        $out = [];
        foreach ($labels as $i => $label) {
            $out[] = ['label' => $label, 'count' => $counts[$i] ?? 0];
        }

        return $out;
    }

    /**
     * Umur Laporan (age) of ACTIVE complaints, bucketed from `submitted_at`.
     * This is factual age — NOT an SLA.
     *
     * @return array{buckets: list<array{label: string, count: int}>, total: int}
     */
    private function agingBuckets($baseQuery): array
    {
        $active = $this->activeBase($baseQuery)->whereNotNull('submitted_at');

        // One conditional-aggregation query produces all four buckets.
        $counts = $this->ageBucketCounts($active, [2, 4, 8]);

        $buckets = $this->labelBuckets(
            ['0–1 hari', '2–3 hari', '4–7 hari', '>7 hari'],
            $counts,
        );

        return [
            'buckets' => $buckets,
            'total'   => array_sum(array_column($buckets, 'count')),
        ];
    }

    /**
     * Status flow (funnel) for the admin "Bottleneck Status Laporan" card.
     * Real counts per ACTIVE workflow stage, plus "Selesai" for context. The
     * bottleneck is the ACTIVE stage holding the most reports. Shares are
     * factual proportions — no SLA, no forecast.
     *
     * LC-2C: the funnel shows ONLY the active lifecycle stages
     * ({@see ComplaintStatus::activeCases()} — submitted, in_progress) plus the
     * resolved outcome for context. Legacy stages (under_review,
     * waiting_for_information) are no longer reachable and are deliberately NOT
     * presented as active workflow stages.
     *
     * Counts are supplied by the shared `statusCounts()` aggregation so this
     * card no longer issues its own COUNT(*) queries (Prompt 2 — Phase 7:
     * the count / share / bottleneck output shape is unchanged).
     *
     * @param  array<string, int>  $counts  statusValue => count (from statusCounts())
     * @return array{stages: list<array{value: string, label: string, color: string, count: int, active: bool, share: float, share_label: string}>, active_total: int, flow_total: int, bottleneck: string|null}
     */
    private function statusFlow(array $counts): array
    {
        // Active workflow stages + the resolved outcome for context. Derived from
        // the enum helpers so the stage list can never drift from the lifecycle.
        $stageStatuses = array_merge(
            ComplaintStatus::activeCases(),
            [ComplaintStatus::Resolved],
        );

        $stageCounts = [];
        foreach ($stageStatuses as $status) {
            $stageCounts[$status->value] = $counts[$status->value] ?? 0;
        }

        $activeTotal = 0;
        foreach ($stageStatuses as $status) {
            if ($status->isActiveLifecycle()) {
                $activeTotal += $stageCounts[$status->value];
            }
        }
        $flowTotal = $activeTotal + $stageCounts[ComplaintStatus::Resolved->value];

        $bottleneck = null;
        $maxActive  = 0;

        $stages = [];
        foreach ($stageStatuses as $status) {
            $count    = $stageCounts[$status->value];
            $isActive = $status->isActiveLifecycle();

            // Bottleneck = busiest ACTIVE stage (earliest stage wins on ties).
            if ($isActive && $count > $maxActive) {
                $maxActive  = $count;
                $bottleneck = $status->value;
            }

            $denom = $isActive ? $activeTotal : $flowTotal;
            $share = $denom > 0 ? round(($count / $denom) * 100, 1) : 0.0;

            $stages[] = [
                'value'       => $status->value,
                'label'       => $status->label(),
                'color'       => $status->color(),
                'count'       => $count,
                'active'      => $isActive,
                'share'       => $share,
                'share_label' => $isActive ? 'dari laporan aktif' : 'dari total laporan',
            ];
        }

        return [
            'stages'       => $stages,
            'active_total' => $activeTotal,
            'flow_total'   => $flowTotal,
            'bottleneck'   => $bottleneck,
        ];
    }

    /**
     * Category × status matrix for the admin bottleneck heatmap. Returns, per
     * category, the real complaint count for each ACTIVE lifecycle status plus
     * "resolved" for context. Rows are ordered by active backlog (highest first)
     * so the busiest categories surface at the top. Real counts only — no SLA,
     * no forecast.
     *
     * LC-2C: the columns are the active workflow statuses
     * ({@see ComplaintStatus::activeCases()}) plus resolved; legacy statuses are
     * not presented as active stages and are excluded from `active`.
     *
     * @return list<array{name: string, counts: array<string, int>, total: int, active: int}>
     */
    private function categoryStatusMatrix($baseQuery, int $limit = 6): array
    {
        $statuses = array_map(
            fn (ComplaintStatus $s) => $s->value,
            array_merge(ComplaintStatus::activeCases(), [ComplaintStatus::Resolved]),
        );

        $rows = (clone $baseQuery)
            ->whereNotNull('category_id')
            ->whereIn('status', $statuses)
            ->select('category_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('category_id', 'status')
            ->get();

        $names = ComplaintCategory::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        $categories = [];
        foreach ($rows as $row) {
            $key = $row->category_id;

            if (! isset($categories[$key])) {
                $categories[$key] = [
                    'name'   => $names[$key] ?? 'Tanpa Kategori',
                    'counts' => array_fill_keys($statuses, 0),
                    'total'  => 0,
                    'active' => 0,
                ];
            }

            $value = (int) $row->total;
            $categories[$key]['counts'][$row->status->value] = $value;
            $categories[$key]['total'] += $value;

            if ($row->status instanceof ComplaintStatus && $row->status->isActiveLifecycle()) {
                $categories[$key]['active'] += $value;
            }
        }

        $out = array_values($categories);
        usort($out, fn ($a, $b) => ($b['active'] <=> $a['active']) ?: ($b['total'] <=> $a['total']));

        return array_slice($out, 0, $limit);
    }

    /**
     * Per-category completion ratio for the admin "Persentase Penyelesaian per
     * Kategori" card. Completion = resolved / total (mirrors the dashboard-wide
     * "Tingkat Penyelesaian" KPI). Rows are sorted by completion ASCENDING so
     * the most lagging sectors surface first. Real counts only — no SLA.
     *
     * @return list<array{name: string, total: int, resolved: int, percent: float, tier: string}>
     */
    private function categoryCompletion($baseQuery, int $limit = 6): array
    {
        $rows = (clone $baseQuery)
            ->whereNotNull('category_id')
            ->select(
                'category_id',
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count"),
            )
            ->groupBy('category_id')
            ->get();

        $names = ComplaintCategory::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        $out = [];
        foreach ($rows as $row) {
            $total = (int) $row->total;
            if ($total === 0) {
                continue;
            }

            $resolved = (int) $row->resolved_count;
            $percent  = round(($resolved / $total) * 100, 1);

            $out[] = [
                'name'     => $names[$row->category_id] ?? 'Tanpa Kategori',
                'total'    => $total,
                'resolved' => $resolved,
                'percent'  => $percent,
                'tier'     => $percent > 70 ? 'high' : ($percent >= 40 ? 'mid' : 'low'),
            ];
        }

        usort($out, fn ($a, $b) => ($a['percent'] <=> $b['percent']) ?: ($b['total'] <=> $a['total']));

        return array_slice($out, 0, $limit);
    }

    /**
     * Per-category backlog: total complaints vs the still-active pile, plus the
     * resolved / rejected / legacy split used by the hover tooltip. Categories
     * are sorted by active backlog descending.
     *
     * LC-2C: the three LC-2 categories are kept distinct so the tooltip figures
     * always add up to `total` and a legacy row can never be read as a rejection:
     *  - active   = submitted + in_progress (the active workflow)
     *  - resolved = resolved (successful outcome)
     *  - rejected = rejected (rejection outcome — NOT merged with legacy)
     *  - legacy   = under_review + waiting_for_information + closed (history only)
     *
     * Real counts only — no SLA, no forecast, no fabricated figure.
     *
     * @return list<array{name: string, total: int, active: int, resolved: int, rejected: int, legacy: int}>
     */
    private function categoryBacklogDistribution($baseQuery, int $limit = 6): array
    {
        $rows = (clone $baseQuery)
            ->whereNotNull('category_id')
            ->select(
                'category_id',
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN status IN ('submitted','in_progress') THEN 1 ELSE 0 END) as active_count"),
                DB::raw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count"),
                DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw("SUM(CASE WHEN status IN ('under_review','waiting_for_information','closed') THEN 1 ELSE 0 END) as legacy_count"),
            )
            ->groupBy('category_id')
            ->get();

        $names = ComplaintCategory::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        $out = $rows->map(fn ($row) => [
            'name'     => $names[$row->category_id] ?? 'Tanpa Kategori',
            'total'    => (int) $row->total,
            'active'   => (int) $row->active_count,
            'resolved' => (int) $row->resolved_count,
            'rejected' => (int) $row->rejected_count,
            'legacy'   => (int) $row->legacy_count,
        ])->all();

        usort($out, fn ($a, $b) => ($b['active'] <=> $a['active']) ?: ($b['total'] <=> $a['total']));

        return array_slice($out, 0, $limit);
    }

    /**
     * Top categories by complaint count (real category names only).
     *
     * @return list<array{name: string, count: int}>
     */
    private function categoryDistribution($baseQuery, int $limit = 5): array
    {
        $rows = (clone $baseQuery)
            ->whereNotNull('category_id')
            ->select('category_id', DB::raw('count(*) as total'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $names = ComplaintCategory::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'name'  => $names[$row->category_id] ?? 'Tanpa Kategori',
            'count' => (int) $row->total,
        ])->all();
    }

    /**
     * Dinas/Unit with the most ACTIVE reports older than 7 days (factual age
     * from `submitted_at`, same ">7 hari" threshold as `agingBuckets()`).
     * Rows are sorted by long-age load descending. The tooltip payload carries
     * the oldest such report per unit (reference code + category + age).
     * Real counts only — no SLA, no forecast.
     *
     * @return list<array{name: string, count: int, oldest: array{reference_code: string, category: string, age_days: int|null}|null}>
     */
    private function dinasLongAging($baseQuery, int $limit = 6): array
    {
        // Same ">7 hari" cutoff used by agingBuckets() so both cards agree.
        $cutoff = now()->subDays(8);

        $rows = $this->activeBase($baseQuery)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $cutoff)
            ->with('category:id,name')
            ->orderBy('submitted_at')
            ->get(['id', 'reference_code', 'dinas_unit_id', 'category_id', 'submitted_at']);

        $names = DinasUnit::whereIn('id', $rows->pluck('dinas_unit_id')->filter())->pluck('name', 'id');

        $units = [];
        foreach ($rows as $row) {
            $key = $row->dinas_unit_id ?? 'none';

            if (! isset($units[$key])) {
                $units[$key] = [
                    'name'   => $row->dinas_unit_id
                        ? ($names[$row->dinas_unit_id] ?? 'Dinas/Unit Terhapus')
                        : 'Belum ada Dinas/Unit',
                    'count'  => 0,
                    'oldest' => null,
                ];
            }

            $units[$key]['count']++;

            // Ordered oldest-first → the first report seen per unit is its oldest.
            if ($units[$key]['oldest'] === null) {
                $units[$key]['oldest'] = [
                    'reference_code' => $row->reference_code,
                    'category'       => $row->category?->name ?? 'Tanpa Kategori',
                    'age_days'       => $row->submitted_at ? (int) $row->submitted_at->diffInDays(now()) : null,
                ];
            }
        }

        $out = array_values($units);
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($out, 0, $limit);
    }

    /**
     * ACTIVE complaint load per Dinas/Unit, broken down by the ACTIVE lifecycle
     * statuses ({@see ComplaintStatus::activeCases()} — submitted / in_progress)
     * for the admin "Beban Dinas/Unit × Status" stacked bar chart.
     *
     * LC-2C: legacy statuses are NOT active workflow and are excluded, so the
     * chart only ever shows work that is still in the active lifecycle.
     *
     * Rows are sorted by active load (backlog) descending — highest first. Only
     * real counts are returned; no SLA, no forecast, no fabricated figure.
     *
     * @return list<array{name: string, total: int, statuses: array<string, int>}>
     */
    private function dinasStatusBreakdown($baseQuery, int $limit = 6): array
    {
        $activeStatuses = array_map(
            fn (ComplaintStatus $s) => $s->value,
            ComplaintStatus::activeCases(),
        );

        $rows = (clone $baseQuery)
            ->whereIn('status', $activeStatuses)
            ->select('dinas_unit_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('dinas_unit_id', 'status')
            ->get();

        $names = DinasUnit::whereIn('id', $rows->pluck('dinas_unit_id')->filter())->pluck('name', 'id');

        $units = [];
        foreach ($rows as $row) {
            $key = $row->dinas_unit_id ?? 'none';

            if (! isset($units[$key])) {
                $units[$key] = [
                    'name'     => $row->dinas_unit_id
                        ? ($names[$row->dinas_unit_id] ?? 'Dinas/Unit Terhapus')
                        : 'Belum ada Dinas/Unit',
                    'statuses' => array_fill_keys($activeStatuses, 0),
                    'total'    => 0,
                ];
            }

            $units[$key]['statuses'][$row->status->value] = (int) $row->total;
            $units[$key]['total'] += (int) $row->total;
        }

        usort($units, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice(array_values($units), 0, $limit);
    }

    /**
     * Complaint load per Dinas/Unit (real Dinas/Unit names only).
     * Unrouted complaints (dinas_unit_id NULL) are grouped honestly.
     *
     * @return list<array{name: string, count: int}>
     */
    private function dinasDistribution($baseQuery, int $limit = 6): array
    {
        $rows = (clone $baseQuery)
            ->select('dinas_unit_id', DB::raw('count(*) as total'))
            ->groupBy('dinas_unit_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $names = DinasUnit::whereIn('id', $rows->pluck('dinas_unit_id')->filter())->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'name'  => $row->dinas_unit_id
                ? ($names[$row->dinas_unit_id] ?? 'Dinas/Unit Terhapus')
                : 'Belum ada Dinas/Unit',
            'count' => (int) $row->total,
        ])->all();
    }

    /**
     * Shared monthly trend window (single source of truth for the dashboard's
     * month range + labels). The last N calendar months ending with the current
     * month, oldest first. `monthlyTrend()` (inflow/resolution) and
     * `regionalTrend()` (Prompt 7/5 Bagian 2) both consume this so the two
     * trends can never drift to different periods.
     *
     * @return array{start: \Illuminate\Support\Carbon, months: list<array{ym: string, label: string}>}
     */
    private function trendPeriod(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $list = [];
        for ($i = 0; $i < $months; $i++) {
            $month  = now()->startOfMonth()->subMonths($months - 1 - $i);
            $list[] = [
                'ym'    => $month->format('Y-m'),
                'label' => $month->translatedFormat('M Y'),
            ];
        }

        return ['start' => $start, 'months' => $list];
    }

    /**
     * Monthly inflow (submitted_at) vs resolution (resolved_at) over the last
     * N calendar months, plus the Net Gap (inflow − resolution) and the
     * cumulative backlog (running total of the net gap, i.e. how much the
     * unfinished pile has accumulated over the displayed window). Pure
     * aggregation over real timestamps; no fabricated trend, no SLA, no
     * forecast — `backlog` is a running balance of two real counts.
     *
     * @return list<array{label: string, inflow: int, resolved: int, gap: int, backlog: int}>
     */
    private function monthlyTrend($baseQuery, int $months = 6): array
    {
        $period = $this->trendPeriod($months);
        $start  = $period['start'];

        $inflow = (clone $baseQuery)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(submitted_at, '%Y-%m') as ym, count(*) as total")
            ->groupBy('ym')
            ->get()
            ->pluck('total', 'ym');

        $resolved = (clone $baseQuery)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(resolved_at, '%Y-%m') as ym, count(*) as total")
            ->groupBy('ym')
            ->get()
            ->pluck('total', 'ym');

        $series  = [];
        $running = 0;

        foreach ($period['months'] as $month) {
            $ym  = $month['ym'];
            $in  = (int) ($inflow[$ym] ?? 0);
            $out = (int) ($resolved[$ym] ?? 0);

            // Cumulative unfinished pile: running total of (masuk − selesai)
            // across the displayed window. Factual arithmetic, not a forecast.
            $running += ($in - $out);

            $series[] = [
                'label'    => $month['label'],
                'inflow'   => $in,
                'resolved' => $out,
                'gap'      => $in - $out,
                'backlog'  => $running,
            ];
        }

        return $series;
    }

    /**
     * Attention summary for the admin "Perlu Perhatian" banner and the
     * "Laporan Belum Ditentukan Dinas/Unit" KPI card (real data only).
     *
     * PHASE 6C — Decision 3: the former `belumDitugaskan` metric
     * (`assigned_to IS NULL`) was REDEFINED to "Belum Ditentukan Dinas/Unit"
     * and now counts active reports with NO routing destination
     * (`dinas_unit_id IS NULL`). Because `assigned_to` is deprecated (never
     * written by runtime), the old and new definitions collapse into ONE
     * semantic concept, so the duplicate keys are consolidated into
     * `belumDinasUnit*` only.
     *
     * The pile is split by factual age derived from `submitted_at` — this is
     * NOT an SLA claim, just the time elapsed since submission.
     *
     * @return array{belumDinasUnit: int, belumDinasUnitTotal: int, belumDinasUnitBuckets: list<array{label: string, count: int}>, laporanTertua: Complaint|null}
     */
    private function attentionSummary($baseQuery): array
    {
        $active = $this->activeBase($baseQuery);

        // Active reports with no routing destination yet.
        $noDinas = (clone $active)
            ->whereNull('dinas_unit_id')
            ->whereNotNull('submitted_at');

        // Age buckets (0–1 / 2–3 / >3 hari) — one query.
        $dinasBuckets = $this->labelBuckets(['0–1 hari', '2–3 hari', '>3 hari'], $this->ageBucketCounts($noDinas, [2, 4]));

        return [
            'belumDinasUnit'           => (clone $active)->whereNull('dinas_unit_id')->count(),
            'belumDinasUnitTotal'      => array_sum(array_column($dinasBuckets, 'count')),
            'belumDinasUnitBuckets'    => $dinasBuckets,
            'laporanTertua'            => (clone $active)
                ->whereNotNull('submitted_at')
                ->with('category')
                ->orderBy('submitted_at')
                ->first(),
        ];
    }

    /**
     * Geographic distribution of complaints for the Admin/Super Admin
     * "Peta Wilayah Aduan" card (Prompt 5/5).
     *
     * DATA SOURCES (strict):
     *  - Administrative counts come from `complaints.kecamatan_id` /
     *    `complaints.kelurahan_id` (GROUP BY). A complaint without coordinates
     *    STILL counts administratively.
     *  - Pins / heat points come ONLY from complaints carrying a VALID
     *    `latitude` + `longitude` PAIR. `location_text` is NEVER a coordinate
     *    source.
     *
     * This runs INSIDE the existing dashboard `Cache::remember` closure (TTL
     * 60s) and reuses the already-computed `$totalReports`, so it adds no extra
     * COUNT(*) and is invalidated by the existing
     * {@see clearGlobalDashboardCaches()} observer wiring.
     *
     * ~3 additional queries on a cache miss (points, byKecamatan, byKelurahan);
     * no N+1 — names are resolved with single `pluck`/eager-load lookups.
     *
     * Only display-safe fields are returned (no NIK / phone / address / email /
     * notes / password). The map itself is rendered read-only.
     *
     * @return array{totalReports: int, reportsWithCoordinates: int, reportsWithoutCoordinates: int, points: list<array<string, mixed>>, byKecamatan: list<array{name: string, total: int}>, byKelurahan: list<array{name: string, kecamatan: string, total: int}>}
     */
    private function geographicMap(int $totalReports): array
    {
        // ── Geographic points: ONLY valid lat+lng PAIRS ───────────────────────
        // LEFT JOINs keep each complaint as ONE row (no fan-out) and resolve the
        // category / kecamatan / kelurahan names in the same query — no N+1.
        $points = Complaint::query()
            ->leftJoin('complaint_categories', 'complaint_categories.id', '=', 'complaints.category_id')
            ->leftJoin('kecamatan', 'kecamatan.id', '=', 'complaints.kecamatan_id')
            ->leftJoin('kelurahan', 'kelurahan.id', '=', 'complaints.kelurahan_id')
            ->whereNotNull('complaints.latitude')
            ->whereNotNull('complaints.longitude')
            ->orderBy('complaints.id')
            ->get([
                'complaints.id',
                'complaints.reference_code',
                'complaints.latitude',
                'complaints.longitude',
                'complaints.status',
                'complaint_categories.name as category_name',
                'kecamatan.name as kecamatan_name',
                'kelurahan.name as kelurahan_name',
            ])
            ->map(function ($row) {
                $status = $row->status instanceof ComplaintStatus
                    ? $row->status
                    : ComplaintStatus::tryFrom((string) $row->status);

                return [
                    'id'             => (int) $row->id,
                    'complaint_number' => $row->reference_code,
                    'latitude'       => (float) $row->latitude,
                    'longitude'      => (float) $row->longitude,
                    'status'         => $status?->value,
                    'status_label'   => $status?->label() ?? 'Tidak Diketahui',
                    'category'       => $row->category_name ?? 'Tanpa Kategori',
                    'kecamatan'      => $row->kecamatan_name,
                    'kelurahan'      => $row->kelurahan_name,
                ];
            })
            ->values()
            ->all();

        $reportsWithCoordinates = count($points);
        $reportsWithoutCoordinates = max(0, $totalReports - $reportsWithCoordinates);

        return [
            'totalReports'              => $totalReports,
            'reportsWithCoordinates'    => $reportsWithCoordinates,
            'reportsWithoutCoordinates' => $reportsWithoutCoordinates,
            'points'                    => $points,
            'byKecamatan'               => $this->geographicKecamatanCounts(),
            'byKelurahan'               => $this->geographicKelurahanCounts(),
        ];
    }

    /**
     * Complaint count per Kecamatan (administrative aggregation).
     *
     * Complaints with a NULL `kecamatan_id` (legacy rows) are grouped honestly
     * under "Belum Ditentukan" rather than dropped.
     *
     * @return list<array{name: string, total: int}>
     */
    private function geographicKecamatanCounts(): array
    {
        $rows = Complaint::query()
            ->select('kecamatan_id', DB::raw('count(*) as total'))
            ->groupBy('kecamatan_id')
            ->get();

        $names = Kecamatan::whereIn('id', $rows->pluck('kecamatan_id')->filter())
            ->pluck('name', 'id');

        $out = $rows->map(fn ($row) => [
            'name'  => $row->kecamatan_id
                ? ($names[$row->kecamatan_id] ?? 'Kecamatan Terhapus')
                : 'Belum Ditentukan',
            'total' => (int) $row->total,
        ])->all();

        return $this->sortGeographicRows($out);
    }

    /**
     * Complaint count per Kelurahan, each carrying its parent Kecamatan name
     * (administrative aggregation). NULL `kelurahan_id` rows are grouped under
     * "Belum Ditentukan".
     *
     * @return list<array{name: string, kecamatan: string, total: int}>
     */
    private function geographicKelurahanCounts(): array
    {
        $rows = Complaint::query()
            ->select('kelurahan_id', DB::raw('count(*) as total'))
            ->groupBy('kelurahan_id')
            ->get();

        $kelurahan = Kelurahan::with('kecamatan:id,name')
            ->whereIn('id', $rows->pluck('kelurahan_id')->filter())
            ->get()
            ->keyBy('id');

        $out = $rows->map(function ($row) use ($kelurahan) {
            $model = $row->kelurahan_id ? $kelurahan->get($row->kelurahan_id) : null;

            return [
                'name'      => $model?->name ?? ($row->kelurahan_id ? 'Kelurahan Terhapus' : 'Belum Ditentukan'),
                'kecamatan' => $model?->kecamatan?->name ?? 'Belum Ditentukan',
                'total'     => (int) $row->total,
            ];
        })->all();

        return $this->sortGeographicRows($out);
    }

    /**
     * Deterministic ordering for the administrative aggregates: highest count
     * first, then name ASC (stable across requests / DB engines).
     *
     * @param  list<array{name: string, total: int}>  $rows
     * @return list<array{name: string, total: int}>
     */
    private function sortGeographicRows(array $rows): array
    {
        return array_values(collect($rows)
            ->sortBy([
                ['total', 'desc'],
                ['name', 'asc'],
            ])
            ->all());
    }

    // ─── Geographic Analytics Layer 1 (Prompt 6/5) ────────────────────────────
    //
    // Four read-only administrative rankings for the Admin dashboard:
    //   1. Top Kecamatan        — complaints per kecamatan_id
    //   2. Top Kelurahan        — complaints per kelurahan_id
    //   3. Backlog by Kelurahan — ACTIVE workflow complaints per kelurahan
    //   4. Status by Kelurahan  — Kelurahan × status matrix (all 7 statuses)
    //
    // ALL counts come from the administrative columns `kecamatan_id` /
    // `kelurahan_id`. Coordinates are NOT involved — structured location and
    // geographic coordinates are two DIFFERENT dimensions and are never mixed.
    //
    // Rows whose `kecamatan_id` / `kelurahan_id` is NULL, or points at a missing
    // master row, are NEVER promoted to an "official" wilayah: they are excluded
    // from these rankings. (The Prompt 5/5 map card already surfaces them
    // honestly as "Belum Ditentukan".) No fabricated wilayah, no dummy data.
    //
    // "Backlog" reuses the project's single source of truth for active statuses
    // ({@see self::ACTIVE_STATUS_VALUES}); the status matrix columns iterate
    // {@see ComplaintStatus::cases()} so no status list is hard-coded here.
    //
    // Query budget: 2 aggregation scans + 2 batched name lookups (no N+1). The
    // Kelurahan aggregation is ONE `GROUP BY kelurahan_id, status` scan that
    // feeds the Top Kelurahan ranking, the backlog ranking AND the status matrix
    // — never a query per kelurahan or per (kelurahan, status).

    /**
     * Assemble the Layer-1 geographic analytics payload. See the section note
     * above for the exact semantics and query budget.
     *
     * @return array{topKecamatan: list<array{name: string, total: int}>, topKelurahan: list<array{name: string, kecamatan: string, total: int}>, backlogByKelurahan: list<array{name: string, kecamatan: string, backlog: int}>, statusByKelurahan: list<array<string, mixed>>}
     */
    private function geographicAnalytics(int $limit = 5): array
    {
        // ONE Kelurahan × status scan, shared by three of the four analytics.
        $kelurahan = $this->kelurahanStatusAggregates();

        return [
            'topKecamatan'       => $this->topKecamatan($limit),
            'topKelurahan'       => $this->topKelurahan($kelurahan, $limit),
            'backlogByKelurahan' => $this->topKelurahanBacklog($kelurahan, $limit),
            'statusByKelurahan'  => $this->kelurahanStatusMatrix($kelurahan, $limit),
        ];
    }

    /**
     * Complaints per Kecamatan (administrative), highest first, ties broken by
     * Kecamatan name ASC for a deterministic order. Only VALID master Kecamatan
     * are ranked; NULL / missing ids are excluded (never an official wilayah).
     *
     * @return list<array{name: string, total: int}>
     */
    private function topKecamatan(int $limit): array
    {
        $rows = Complaint::query()
            ->whereNotNull('kecamatan_id')
            ->select('kecamatan_id', DB::raw('count(*) as total'))
            ->groupBy('kecamatan_id')
            ->get();

        // Single batched lookup for the master names (no N+1).
        $names = Kecamatan::whereIn('id', $rows->pluck('kecamatan_id')->unique()->filter())
            ->pluck('name', 'id');

        $out = [];
        foreach ($rows as $row) {
            $name = $names[$row->kecamatan_id] ?? null;

            // Master row missing → not an official Kecamatan; skip entirely.
            if ($name === null) {
                continue;
            }

            $out[] = ['name' => $name, 'total' => (int) $row->total];
        }

        $out = collect($out)
            ->sortBy([
                ['total', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $limit);
    }

    /**
     * ONE `GROUP BY kelurahan_id, status` scan → a per-Kelurahan aggregate keyed
     * by kelurahan id. Each entry carries the resolved Kelurahan/Kecamatan name,
     * the per-status counts, the grand total, and the ACTIVE (backlog) total.
     *
     * Rows whose `kelurahan_id` is NULL or does not resolve to a master row are
     * dropped: they are not an official wilayah (Prompt 6/5 §V).
     *
     * @return array<int, array{name: string, kecamatan: string, counts: array<string, int>, total: int, backlog: int}>
     */
    private function kelurahanStatusAggregates(): array
    {
        $rows = Complaint::query()
            ->whereNotNull('kelurahan_id')
            ->select('kelurahan_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('kelurahan_id', 'status')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Single batched eager-load for the master names (no N+1).
        $master = Kelurahan::with('kecamatan:id,name')
            ->whereIn('id', $rows->pluck('kelurahan_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        $statusValues = array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::cases());

        $aggregates = [];
        foreach ($rows as $row) {
            $model = $master->get($row->kelurahan_id);

            // Missing master row → not an official Kelurahan; skip entirely.
            if ($model === null) {
                continue;
            }

            $id = (int) $row->kelurahan_id;

            if (! isset($aggregates[$id])) {
                $aggregates[$id] = [
                    'name'      => $model->name,
                    'kecamatan' => $model->kecamatan?->name ?? 'Belum Ditentukan',
                    'counts'    => array_fill_keys($statusValues, 0),
                    'total'     => 0,
                    'backlog'   => 0,
                ];
            }

            $statusValue = $row->status instanceof ComplaintStatus
                ? $row->status->value
                : (string) $row->status;
            $count = (int) $row->total;

            if (array_key_exists($statusValue, $aggregates[$id]['counts'])) {
                $aggregates[$id]['counts'][$statusValue] = $count;
            }
            $aggregates[$id]['total'] += $count;

            // Backlog = still-active reports — single source of truth shared with
            // every other backlog figure in the project (LC-2C: active workflow
            // only; legacy + final outcomes excluded).
            if (in_array($statusValue, self::ACTIVE_STATUS_VALUES, true)) {
                $aggregates[$id]['backlog'] += $count;
            }
        }

        return $aggregates;
    }

    /**
     * Top Kelurahan by TOTAL reports. Ordering: total DESC, then Kecamatan name
     * ASC, then Kelurahan name ASC (deterministic). Consumes the shared
     * Kelurahan aggregate — issues no query of its own.
     *
     * @param  array<int, array{name: string, kecamatan: string, counts: array<string, int>, total: int, backlog: int}>  $kelurahan
     * @return list<array{name: string, kecamatan: string, total: int}>
     */
    private function topKelurahan(array $kelurahan, int $limit): array
    {
        $out = collect($kelurahan)
            ->map(fn ($row) => [
                'name'      => $row['name'],
                'kecamatan' => $row['kecamatan'],
                'total'     => $row['total'],
            ])
            ->sortBy([
                ['total', 'desc'],
                ['kecamatan', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $limit);
    }

    /**
     * Top Kelurahan by BACKLOG (active/non-terminal reports only). Ordering:
     * backlog DESC, then Kecamatan ASC, then Kelurahan ASC. Deliberately a
     * DIFFERENT metric from {@see topKelurahan()} (total vs active) so the two
     * cards never blur together. Consumes the shared Kelurahan aggregate.
     *
     * @param  array<int, array{name: string, kecamatan: string, counts: array<string, int>, total: int, backlog: int}>  $kelurahan
     * @return list<array{name: string, kecamatan: string, backlog: int}>
     */
    private function topKelurahanBacklog(array $kelurahan, int $limit): array
    {
        $out = collect($kelurahan)
            ->map(fn ($row) => [
                'name'      => $row['name'],
                'kecamatan' => $row['kecamatan'],
                'backlog'   => $row['backlog'],
            ])
            ->sortBy([
                ['backlog', 'desc'],
                ['kecamatan', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $limit);
    }

    /**
     * Kelurahan × Status matrix: the real count of each of the seven official
     * statuses per Kelurahan, plus the row total. Columns iterate
     * {@see ComplaintStatus::cases()} (single source of truth) and every status
     * is always present — a zero is a real zero, never a missing column.
     *
     * Top 5 wilayah, ordered by total DESC, then Kecamatan ASC, then Kelurahan
     * ASC. Consumes the shared Kelurahan aggregate — no query of its own.
     *
     * @param  array<int, array{name: string, kecamatan: string, counts: array<string, int>, total: int, backlog: int}>  $kelurahan
     * @return list<array<string, mixed>>
     */
    private function kelurahanStatusMatrix(array $kelurahan, int $limit): array
    {
        $statusValues = array_map(fn (ComplaintStatus $s) => $s->value, ComplaintStatus::cases());

        $rows = collect($kelurahan)
            ->map(function ($row) use ($statusValues) {
                $entry = [
                    'name'      => $row['name'],
                    'kecamatan' => $row['kecamatan'],
                    'total'     => $row['total'],
                ];

                foreach ($statusValues as $value) {
                    $entry[$value] = (int) ($row['counts'][$value] ?? 0);
                }

                return $entry;
            })
            ->sortBy([
                ['total', 'desc'],
                ['kecamatan', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($rows, 0, $limit);
    }

    // ─── Geographic Analytics Layer 2A (Prompt 7/5 — Bagian 1/3) ─────────────
    //
    // "Kategori masalah apa yang paling banyak terjadi di wilayah tertentu?"
    // Administrative aggregation over `complaints.kelurahan_id` +
    // `complaints.category_id`. Coordinates are NOT involved (structured
    // location and geographic coordinates are different dimensions).
    //
    // Query budget: ONE `GROUP BY kelurahan_id, category_id` scan + TWO batched
    // master-name lookups (Kelurahan+parent, ComplaintCategory). No query per
    // kelurahan, no query per category, no N+1. Rows whose kelurahan or category
    // is NULL / orphaned (master row missing) are dropped — they are never shown
    // as an official wilayah/category and no placeholder label is invented.

    /**
     * Top-10 Kelurahan × Kategori combinations for the Admin dashboard.
     *
     * Deterministic ordering: total DESC, then Kelurahan name ASC, then Category
     * name ASC. Only complaints carrying a VALID `kelurahan_id` AND a VALID
     * `category_id` are counted; NULL / orphaned master rows are excluded.
     *
     * Also carries `regionalTrend` (Prompt 7/5 — Bagian 2): the monthly inflow
     * trend for the Top-5 Kelurahan by window total, and `completionByKelurahan`
     * (Bagian 3): the Top-5 Kelurahan by completion rate (resolved + rejected).
     *
     * @return array{kelurahanCategory: list<array{kelurahan: string, kecamatan: string, category: string, total: int}>, regionalTrend: array{labels: list<string>, series: list<array{name: string, kecamatan: string, total: int, data: list<int>}>}, completionByKelurahan: list<array{kelurahan: string, kecamatan: string, total: int, completed: int, completionRate: float}>}
     */
    private function geographicLayer2A(int $limit = 10): array
    {
        return [
            'kelurahanCategory'    => $this->kelurahanCategoryAggregates($limit),
            'regionalTrend'        => $this->regionalTrend(),
            'completionByKelurahan' => $this->completionByKelurahan(),
        ];
    }

    /**
     * Completion rate per Kelurahan (Prompt 7/5 — Bagian 3/3).
     *
     * Answers "which wilayah completes the most reports?" for the TOP 5
     * Kelurahan. Aggregated over ALL valid complaints in the wilayah (NOT limited
     * to the trend window).
     *
     * BUSINESS RULE (frozen): completed = resolved + rejected. `closed` is a
     * SEPARATE terminal status and is NOT counted as completed. The status values
     * come from {@see ComplaintStatus} (single source of truth), never a
     * hard-coded list.
     *
     * QUERY BUDGET (constant, no N+1): ONE `GROUP BY kelurahan_id` scan with
     * conditional aggregation (total / resolved / rejected) + ONE batched
     * Kelurahan name lookup. Kelurahan with total = 0 are skipped (never a
     * division). NULL / orphaned kelurahan are excluded — no placeholder label.
     *
     * Ordering (deterministic): completionRate DESC → total DESC → name ASC
     * (id ASC as a final internal tie-breaker).
     *
     * @return list<array{kelurahan: string, kecamatan: string, total: int, completed: int, completionRate: float}>
     */
    private function completionByKelurahan(int $topN = 5): array
    {
        $resolved = ComplaintStatus::Resolved->value;
        $rejected = ComplaintStatus::Rejected->value;

        // ONE scan; conditional aggregation produces total + per-status counts.
        $rows = Complaint::query()
            ->whereNotNull('kelurahan_id')
            ->select('kelurahan_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved_count", [$resolved])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count", [$rejected])
            ->groupBy('kelurahan_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // ONE batched lookup (no query per kelurahan).
        $master = Kelurahan::with('kecamatan:id,name')
            ->whereIn('id', $rows->pluck('kelurahan_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $total = (int) $row->total;

            // Never divide by zero; a wilayah with no reports is not ranked.
            if ($total === 0) {
                continue;
            }

            $model = $master->get($row->kelurahan_id);
            if ($model === null) {
                continue; // orphan → never an official wilayah
            }

            // completed = resolved + rejected (closed is NOT completed).
            $completed = (int) $row->resolved_count + (int) $row->rejected_count;

            $out[] = [
                'kelurahan'      => $model->name,
                'kecamatan'      => $model->kecamatan?->name ?? '',
                'total'          => $total,
                'completed'      => $completed,
                'completionRate' => round(($completed / $total) * 100, 1),
            ];
        }

        $out = collect($out)
            ->sortBy([
                ['completionRate', 'desc'],
                ['total', 'desc'],
                ['kelurahan', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $topN);
    }

    /**
     * Monthly trend of complaint inflow per Kelurahan (Prompt 7/5 — Bagian 2).
     *
     * Answers "is the report volume in a kelurahan rising or falling over time?"
     * for the TOP 5 Kelurahan by TOTAL reports across the whole trend window.
     *
     * SOURCE: `complaints.submitted_at` ONLY (the report's arrival time — never
     * created_at / updated_at / resolved_at / closed_at). Region comes from
     * `complaints.kelurahan_id`; NULL / orphaned kelurahan are excluded and no
     * placeholder wilayah is invented. Coordinates are irrelevant here.
     *
     * PERIOD: reuses {@see trendPeriod()} — the SAME window/labels as the
     * dashboard's `monthlyTrend()`, so the geographic trend can never drift to a
     * different period than the main trend.
     *
     * QUERY BUDGET (constant, no N+1): ONE `GROUP BY kelurahan_id, ym` scan over
     * the window + ONE batched Kelurahan name lookup. Zero-filling happens in
     * memory. Top 5 is decided ONCE from the window total and stays fixed for
     * every month (never a different Top 5 per month).
     *
     * @return array{labels: list<string>, series: list<array{name: string, kecamatan: string, total: int, data: list<int>}>}
     */
    private function regionalTrend(int $topN = 5): array
    {
        $period = $this->trendPeriod();
        $labels = array_column($period['months'], 'label');
        $ymList = array_column($period['months'], 'ym');

        $rows = Complaint::query()
            ->whereNotNull('kelurahan_id')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $period['start'])
            ->selectRaw("kelurahan_id, DATE_FORMAT(submitted_at, '%Y-%m') as ym, count(*) as total")
            ->groupBy('kelurahan_id', 'ym')
            ->get();

        if ($rows->isEmpty()) {
            // No valid geographic data in the window → honest empty structure.
            return ['labels' => [], 'series' => []];
        }

        // Accumulate per-kelurahan totals (window) + per-month buckets in memory.
        $totals  = [];
        $buckets = [];
        foreach ($rows as $row) {
            $id = (int) $row->kelurahan_id;
            $ym = (string) $row->ym;

            // Ignore months outside the window (defensive; SQL already filters).
            if (! in_array($ym, $ymList, true)) {
                continue;
            }

            $count = (int) $row->total;
            $totals[$id]  = ($totals[$id] ?? 0) + $count;
            $buckets[$id][$ym] = $count;
        }

        if ($totals === []) {
            return ['labels' => [], 'series' => []];
        }

        // Top-N by window total; ties broken by Kelurahan name ASC. Resolved via
        // ONE batched lookup (no query per kelurahan).
        $master = Kelurahan::with('kecamatan:id,name')
            ->whereIn('id', array_keys($totals))
            ->get()
            ->keyBy('id');

        // Drop orphaned ids (no master row) before ranking.
        $candidates = [];
        foreach ($totals as $id => $total) {
            $model = $master->get($id);
            if ($model === null) {
                continue; // orphan → never an official wilayah
            }
            $candidates[] = [
                'id'        => $id,
                'name'      => $model->name,
                'kecamatan' => $model->kecamatan?->name ?? '',
                'total'     => $total,
            ];
        }

        if ($candidates === []) {
            return ['labels' => [], 'series' => []];
        }

        $candidates = collect($candidates)
            ->sortBy([
                ['total', 'desc'],
                ['name', 'asc'],
                ['id', 'asc'], // final internal tie-breaker for full determinism
            ])
            ->values()
            ->all();

        $series = [];
        foreach (array_slice($candidates, 0, $topN) as $row) {
            $data = [];
            foreach ($ymList as $ym) {
                // Zero-fill: a month without reports is a real 0, never null and
                // never omitted — every series matches the label count exactly.
                $data[] = (int) ($buckets[$row['id']][$ym] ?? 0);
            }

            $series[] = [
                'name'      => $row['name'],
                'kecamatan' => $row['kecamatan'],
                'total'     => $row['total'],
                'data'      => $data,
            ];
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * ONE `GROUP BY kelurahan_id, category_id` scan → Top-N (kelurahan, category)
     * pairs by complaint count. Master names are resolved with batched lookups
     * (no N+1). Rows that do not resolve to a real Kelurahan AND a real Category
     * are dropped entirely (never an official wilayah/category).
     *
     * @return list<array{kelurahan: string, kecamatan: string, category: string, total: int}>
     */
    private function kelurahanCategoryAggregates(int $limit): array
    {
        $rows = Complaint::query()
            ->whereNotNull('kelurahan_id')
            ->whereNotNull('category_id')
            ->select('kelurahan_id', 'category_id', DB::raw('count(*) as total'))
            ->groupBy('kelurahan_id', 'category_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Two batched lookups — never one query per kelurahan / per category.
        $kelurahan = Kelurahan::with('kecamatan:id,name')
            ->whereIn('id', $rows->pluck('kelurahan_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        $categories = ComplaintCategory::whereIn('id', $rows->pluck('category_id')->unique()->filter())
            ->pluck('name', 'id');

        $out = [];
        foreach ($rows as $row) {
            $kelurahanModel = $kelurahan->get($row->kelurahan_id);
            $categoryName   = $categories[$row->category_id] ?? null;

            // Orphaned kelurahan OR orphaned category → not an official row.
            if ($kelurahanModel === null || $categoryName === null) {
                continue;
            }

            $out[] = [
                'kelurahan' => $kelurahanModel->name,
                'kecamatan' => $kelurahanModel->kecamatan?->name ?? '',
                'category'  => $categoryName,
                'total'     => (int) $row->total,
            ];
        }

        $out = collect($out)
            ->sortBy([
                ['total', 'desc'],
                ['kelurahan', 'asc'],
                ['category', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $limit);
    }

    // ─── Geographic Analytics Layer 2B (Prompt 8/5 — Bagian 1/3) ─────────────
    //
    // Operational wilayah analytics that COMPLEMENT Layer 1 / Layer 2A:
    //   1. Wilayah dengan Kasus Tertua   — oldest ACTIVE case per kelurahan
    //   2. Rata-rata Umur Laporan Aktif  — mean active age per kelurahan
    //   3. Wilayah dengan Proporsi Aktif — active / total per kelurahan
    //   4. Wilayah dengan Beban Aktif    — active count per kelurahan
    //
    // These are ANALYTICAL AGE metrics — NOT SLA / breach / overdue. No SLA is
    // frozen in this project, so no such claim is ever derived or displayed.
    //
    // ACTIVE = the active lifecycle statuses ({@see self::ACTIVE_STATUS_VALUES}
    // — submitted, in_progress); legacy + final outcomes are NOT active. The
    // status list is the project's single source of truth (shared with Layer 1 /
    // Layer 2A) — never re-invented.
    //
    // QUERY BUDGET (constant, no N+1): ONE `GROUP BY kelurahan_id` scan with
    // conditional aggregation (total, active, dated-active, oldest active age,
    // Σ active age) + ONE batched Kelurahan/Kecamatan name lookup. ALL FOUR
    // metrics reuse that single dataset. A SINGLE evaluation timestamp is
    // captured once per cache miss (never now() per row).
    //
    // Only kelurahan with a VALID master row AND a VALID Kecamatan parent are
    // official wilayah here; NULL / orphaned ids are excluded (no placeholder).
    //
    // Metric 4 shares the SAME active-count aggregate as Layer 1's backlog card
    // (both derive from `complaints` grouped by kelurahan) but is a SEPARATE
    // metric with its own payload — Layer 1 is FROZEN and untouched.

    /**
     * Assemble the Layer-2B payload (four operational wilayah metrics).
     *
     * @return array{oldestCasesByKelurahan: list<array{kelurahan: string, kecamatan: string, oldestAgeDays: int, activeCount: int}>, averageAgeByKelurahan: list<array{kelurahan: string, kecamatan: string, averageAgeDays: float, activeCount: int}>, activeRateByKelurahan: list<array{kelurahan: string, kecamatan: string, total: int, active: int, activeRate: float}>, activeLoadByKelurahan: list<array{kelurahan: string, kecamatan: string, activeCount: int, totalCount: int}>}
     */
    private function geographicLayer2B(int $topN = 5): array
    {
        $empty = [
            'oldestCasesByKelurahan' => [],
            'averageAgeByKelurahan'  => [],
            'activeRateByKelurahan'  => [],
            'activeLoadByKelurahan'  => [],
        ];

        // ONE evaluation timestamp for the whole cache miss.
        $evaluationTime = now();

        // ACTIVE = the active lifecycle statuses (submitted, in_progress) from the
        // single source of truth. LC-2C: legacy + final-outcome statuses are NOT
        // active and are excluded from every metric below. The placeholder count
        // is derived from the constant so the SQL can never drift from the list.
        $activeStatuses = self::ACTIVE_STATUS_VALUES;
        $inPlaceholders = implode(', ', array_fill(0, count($activeStatuses), '?'));

        // ONE aggregate scan feeding all four metrics. DATEDIFF is the same
        // MySQL date primitive already used by the dashboard (monthlyTrend uses
        // DATE_FORMAT); ages are whole days and never negative.
        $rows = Complaint::query()
            ->whereNotNull('kelurahan_id')
            ->select('kelurahan_id')
            ->selectRaw('count(*) as total_count')
            ->selectRaw(
                "SUM(CASE WHEN status IN ({$inPlaceholders}) THEN 1 ELSE 0 END) as active_count",
                $activeStatuses,
            )
            ->selectRaw(
                "SUM(CASE WHEN status IN ({$inPlaceholders}) AND submitted_at IS NOT NULL THEN 1 ELSE 0 END) as active_dated_count",
                $activeStatuses,
            )
            ->selectRaw(
                "DATEDIFF(?, MIN(CASE WHEN status IN ({$inPlaceholders}) AND submitted_at IS NOT NULL THEN submitted_at END)) as oldest_active_age_days",
                array_merge([$evaluationTime], $activeStatuses),
            )
            ->selectRaw(
                "SUM(CASE WHEN status IN ({$inPlaceholders}) AND submitted_at IS NOT NULL THEN GREATEST(DATEDIFF(?, submitted_at), 0) ELSE 0 END) as active_age_days_sum",
                array_merge($activeStatuses, [$evaluationTime]),
            )
            ->groupBy('kelurahan_id')
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        // ONE batched lookup (never Kelurahan::find() inside a loop).
        $master = Kelurahan::with('kecamatan:id,name')
            ->whereIn('id', $rows->pluck('kelurahan_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        $dataset = [];
        foreach ($rows as $row) {
            $model = $master->get($row->kelurahan_id);

            // Valid master Kelurahan AND valid Kecamatan parent only.
            if ($model === null || $model->kecamatan === null) {
                continue;
            }

            $oldestRaw = $row->oldest_active_age_days;

            $dataset[] = [
                'kelurahan' => $model->name,
                'kecamatan' => $model->kecamatan->name,
                'total'     => (int) $row->total_count,
                'active'    => (int) $row->active_count,
                // Count of active cases that actually carry a submitted_at
                // (the correct denominator for the average-age metric).
                'activeDated' => (int) $row->active_dated_count,
                // Whole days since the oldest active case; clamped at 0 so an
                // abnormal future timestamp can never yield a negative age.
                'oldestAgeDays' => $oldestRaw === null ? null : max(0, (int) $oldestRaw),
                'ageSum'        => max(0, (int) $row->active_age_days_sum),
            ];
        }

        if ($dataset === []) {
            return $empty;
        }

        return [
            'oldestCasesByKelurahan' => $this->oldestCasesByKelurahan($dataset, $topN),
            'averageAgeByKelurahan'  => $this->averageAgeByKelurahan($dataset, $topN),
            'activeRateByKelurahan'  => $this->activeRateByKelurahan($dataset, $topN),
            'activeLoadByKelurahan'  => $this->activeLoadByKelurahan($dataset, $topN),
        ];
    }

    /**
     * Metric 1 — Wilayah dengan Kasus Tertua. Ranks kelurahan by the age (days)
     * of their OLDEST active complaint. Only kelurahan with at least one active
     * case that has a usable `submitted_at` are ranked.
     *
     * Ordering: oldestAgeDays DESC → activeCount DESC → kelurahan ASC.
     *
     * @param  list<array<string, mixed>>  $dataset
     * @return list<array{kelurahan: string, kecamatan: string, oldestAgeDays: int, activeCount: int}>
     */
    private function oldestCasesByKelurahan(array $dataset, int $topN): array
    {
        $out = [];
        foreach ($dataset as $row) {
            if ($row['active'] <= 0 || $row['oldestAgeDays'] === null) {
                continue;
            }

            $out[] = [
                'kelurahan'     => $row['kelurahan'],
                'kecamatan'     => $row['kecamatan'],
                'oldestAgeDays' => $row['oldestAgeDays'],
                'activeCount'   => $row['active'],
            ];
        }

        $out = collect($out)
            ->sortBy([
                ['oldestAgeDays', 'desc'],
                ['activeCount', 'desc'],
                ['kelurahan', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $topN);
    }

    /**
     * Metric 2 — Rata-rata Umur Laporan Aktif per Wilayah. Mean age (days) of
     * the active complaints of each kelurahan. Denominator = active complaints
     * that carry a `submitted_at` (never the total active count, so a NULL
     * timestamp can never skew the mean). Rounded to 1 decimal.
     *
     * Ordering: averageAgeDays DESC → activeCount DESC → kelurahan ASC.
     *
     * @param  list<array<string, mixed>>  $dataset
     * @return list<array{kelurahan: string, kecamatan: string, averageAgeDays: float, activeCount: int}>
     */
    private function averageAgeByKelurahan(array $dataset, int $topN): array
    {
        $out = [];
        foreach ($dataset as $row) {
            // No dated active case → the average is undefined (never divide).
            if ($row['activeDated'] <= 0) {
                continue;
            }

            $out[] = [
                'kelurahan'      => $row['kelurahan'],
                'kecamatan'      => $row['kecamatan'],
                'averageAgeDays' => round($row['ageSum'] / $row['activeDated'], 1),
                'activeCount'    => $row['active'],
            ];
        }

        $out = collect($out)
            ->sortBy([
                ['averageAgeDays', 'desc'],
                ['activeCount', 'desc'],
                ['kelurahan', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $topN);
    }

    /**
     * Metric 3 — Wilayah dengan Proporsi Kasus Aktif Tinggi. active / total as a
     * percentage (1 decimal). DISTINCT from Layer 1's "Backlog Tertinggi" card:
     * Layer 1 ranks by the raw active COUNT, this ranks by the active PROPORTION
     * of the wilayah's total. A wilayah with total = 0 is never ranked (no
     * division, no fabricated 0%).
     *
     * Ordering: activeRate DESC → active DESC → total DESC → kelurahan ASC.
     *
     * @param  list<array<string, mixed>>  $dataset
     * @return list<array{kelurahan: string, kecamatan: string, total: int, active: int, activeRate: float}>
     */
    private function activeRateByKelurahan(array $dataset, int $topN): array
    {
        $out = [];
        foreach ($dataset as $row) {
            if ($row['total'] <= 0) {
                continue; // no denominator → not ranked
            }

            $out[] = [
                'kelurahan'  => $row['kelurahan'],
                'kecamatan'  => $row['kecamatan'],
                'total'      => $row['total'],
                'active'     => $row['active'],
                'activeRate' => round(($row['active'] / $row['total']) * 100, 1),
            ];
        }

        $out = collect($out)
            ->sortBy([
                ['activeRate', 'desc'],
                ['active', 'desc'],
                ['total', 'desc'],
                ['kelurahan', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $topN);
    }

    /**
     * Metric 4 — Wilayah dengan Beban Kasus Aktif Terbesar. Raw active count per
     * kelurahan (plus the wilayah total as context). A wilayah with no active
     * case is not a "load" row and is excluded.
     *
     * Ordering: activeCount DESC → totalCount DESC → kelurahan ASC.
     *
     * @param  list<array<string, mixed>>  $dataset
     * @return list<array{kelurahan: string, kecamatan: string, activeCount: int, totalCount: int}>
     */
    private function activeLoadByKelurahan(array $dataset, int $topN): array
    {
        $out = [];
        foreach ($dataset as $row) {
            if ($row['active'] <= 0) {
                continue;
            }

            $out[] = [
                'kelurahan'   => $row['kelurahan'],
                'kecamatan'   => $row['kecamatan'],
                'activeCount' => $row['active'],
                'totalCount'  => $row['total'],
            ];
        }

        $out = collect($out)
            ->sortBy([
                ['activeCount', 'desc'],
                ['totalCount', 'desc'],
                ['kelurahan', 'asc'],
            ])
            ->values()
            ->all();

        return array_slice($out, 0, $topN);
    }

    /**
     * Invalidate specific citizen cache.
     */
    public function clearCitizenCache(?int $userId): void    {
        if ($userId) {
            Cache::forget("dashboard:citizen:{$userId}");
        }
    }

    /**
     * Invalidate global staff dashboard caches (operator, admin, super admin).
     *
     * Operator caches are keyed by (scope, version); bumping the version
     * invalidates ALL of them in one write (the cache store has no tags).
     */
    public function clearGlobalDashboardCaches(): void
    {
        $this->bumpOperatorCacheVersion();
        $this->bumpRatingCacheVersion();
        Cache::forget('dashboard:admin');
        Cache::forget('dashboard:superadmin');
    }

    /**
     * Invalidate all dashboard caches.
     */
    public function clearAll(): void
    {
        $this->bumpOperatorCacheVersion();
        $this->bumpRatingCacheVersion();
        Cache::forget('dashboard:admin');
        Cache::forget('dashboard:superadmin');
    }

    /**
     * Bump the Operator cache version, invalidating every scoped operator cache.
     */
    public function bumpOperatorCacheVersion(): void
    {
        Cache::put(self::OPERATOR_CACHE_VERSION_KEY, $this->operatorCacheVersion() + 1);
    }

    /**
     * Bump the Rating Analytics cache version, invalidating every rating cache
     * (PHASE 4 — Bagian 2 — D-4.15). Rating aggregates join `complaints` and the
     * location/category/dinas master data, so any complaint or master-data change
     * (which calls `clearGlobalDashboardCaches()`) refreshes rating analytics too.
     */
    public function bumpRatingCacheVersion(): void
    {
        Cache::put(self::RATING_CACHE_VERSION_KEY, (int) Cache::get(self::RATING_CACHE_VERSION_KEY, 1) + 1);
    }
}
