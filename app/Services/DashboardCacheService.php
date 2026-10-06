<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

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
                'laporanProses'  => Complaint::forReporter($user->id)->whereIn('status', ['submitted', 'under_review', 'in_progress', 'waiting_for_information'])->count(),
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
                ];
            }

            return [
                'totalLaporan'    => $this->operatorScopedQuery($user)->count(),
                'belumDitugaskan' => $this->operatorScopedQuery($user)
                    ->whereNull('assigned_to')
                    ->whereNotIn('status', ['resolved', 'rejected', 'closed'])
                    ->count(),
                'perluTindakan'   => $this->operatorScopedQuery($user)
                    ->whereIn('status', ['submitted', 'under_review'])
                    ->count(),
                'selesaiHariIni'  => $this->operatorScopedQuery($user)->byStatus('resolved')
                    ->whereDate('resolved_at', today())
                    ->count(),
                'statusBreakdown' => $statusBreakdown,
                'cachedAt'        => now()->toIso8601String(),
            ];
        });

        return array_merge($metrics, [
            'laporanTerbaru'  => $this->operatorScopedQuery($user)
                ->with(['reporter', 'category', 'assignee'])
                ->orderByDesc('submitted_at')
                ->limit(10)
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
            $statusBreakdown = [];
            foreach (ComplaintStatus::cases() as $status) {
                $statusBreakdown[$status->value] = [
                    'label' => $status->label(),
                    'badge' => $status->tailwindBadge(),
                    'count' => Complaint::byStatus($status->value)->count(),
                ];
            }

            return [
                'totalLaporan'     => Complaint::count(),
                'laporanHariIni'   => Complaint::whereDate('submitted_at', today())->count(),
                'laporanAktif'     => Complaint::whereNotIn('status', ['resolved', 'rejected', 'closed'])->count(),
                'laporanSelesai'   => Complaint::byStatus('resolved')->count(),
                'auditHariIni'     => AuditLog::whereDate('created_at', today())->count(),
                'statusBreakdown'  => $statusBreakdown,
                'cachedAt'         => now()->toIso8601String(),
            ];
        });

        return array_merge($metrics, [
            'laporanTerbaru'   => Complaint::with(['reporter', 'category', 'assignee'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get(),
            'laporanPerhatian' => Complaint::with(['reporter', 'category', 'assignee'])
                ->whereIn('status', ['submitted', 'under_review', 'in_progress', 'waiting_for_information'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get(),
            'auditTerbaru'     => AuditLog::with('actor')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
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
            $statusBreakdown = [];
            foreach (ComplaintStatus::cases() as $status) {
                $statusBreakdown[$status->value] = [
                    'label' => $status->label(),
                    'badge' => $status->tailwindBadge(),
                    'count' => Complaint::byStatus($status->value)->count(),
                ];
            }

            $roleBreakdown = User::selectRaw('role, count(*) as total')
                ->groupBy('role')
                ->pluck('total', 'role')
                ->toArray();

            return [
                'totalLaporan'    => Complaint::count(),
                'totalPengguna'   => User::count(),
                'laporanAktif'    => Complaint::whereNotIn('status', ['resolved', 'rejected', 'closed'])->count(),
                'laporanSelesai'  => Complaint::byStatus('resolved')->count(),
                'auditHariIni'    => AuditLog::whereDate('created_at', today())->count(),
                'penggunaBaru'    => User::whereDate('created_at', today())->count(),
                'roleBreakdown'   => $roleBreakdown,
                'statusBreakdown' => $statusBreakdown,
                'cachedAt'        => now()->toIso8601String(),
            ];
        });

        return array_merge($metrics, [
            'laporanTerbaru'  => Complaint::with(['reporter', 'category', 'assignee'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get(),
            'auditTerbaru'    => AuditLog::with('actor')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Invalidate specific citizen cache.
     */
    public function clearCitizenCache(?int $userId): void
    {
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
        Cache::forget('dashboard:admin');
        Cache::forget('dashboard:superadmin');
    }

    /**
     * Invalidate all dashboard caches.
     */
    public function clearAll(): void
    {
        $this->bumpOperatorCacheVersion();
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
}
