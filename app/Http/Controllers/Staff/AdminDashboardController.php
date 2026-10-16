<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Enums\ComplaintStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

use App\Services\DashboardCacheService;
use App\Services\RatingAnalyticsService;

/**
 * Admin dashboard — MONITORING ONLY.
 * Admin has NO management capabilities; no create/update/delete routes exist.
 * Metrics are cached with instant refresh support.
 */
class AdminDashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardCacheService $cacheService,
        RatingAnalyticsService $ratingAnalyticsService,
    ): View {
        $user = $request->user();
        $refresh = $request->boolean('refresh');

        $data = $cacheService->getAdminData($refresh);

        // PHASE 4 — Bagian 2 — Rating Analytics (D-4.1: Admin = GLOBAL).
        // Filters are read from the query string and validated server-side.
        $ratingAnalytics = $ratingAnalyticsService->getRatingAnalytics(
            $user,
            $request->only(['period', 'start_date', 'end_date', 'dinas_unit_id', 'category_id', 'kecamatan_id', 'kelurahan_id']),
            $refresh,
        );

        return view('admin.dashboard', array_merge([
            'user' => $user,
            'ratingAnalytics' => $ratingAnalytics,
        ], $data));
    }

    public function complaints(Request $request): View
    {
        $query = Complaint::with(['reporter', 'category', 'dinasUnit']);

        // ── Search (reference_code, title, reporter name) ───────────────────
        if ($request->filled('q')) {
            $q = $request->string('q')->trim()->limit(200)->value();
            $query->where(function ($qb) use ($q) {
                $qb->where('reference_code', 'like', "%{$q}%")
                   ->orWhere('title', 'like', "%{$q}%")
                   ->orWhereHas('reporter', fn ($r) => $r->where('name', 'like', "%{$q}%"));
            });
        }

        // ── Status filter ─────────────────────────────────────────────────────
        if ($request->filled('status') && in_array($request->status, array_column(ComplaintStatus::cases(), 'value'))) {
            $query->byStatus($request->status);
        }

        // ── Category filter ───────────────────────────────────────────────────
        if ($request->filled('category') && is_numeric($request->category)) {
            $query->where('category_id', (int) $request->category);
        }

        // ── Routing filter (belum ditentukan Dinas/Unit) ──────────────────────
        // PHASE 6C — Decision 3: redefined from `assigned_to IS NULL`
        // ("belum ditugaskan") to `dinas_unit_id IS NULL`
        // ("belum ditentukan Dinas/Unit"). The query param name is kept stable
        // for the existing Attention Center link, but its SEMANTICS are now
        // Dinas/Unit-based (assigned_to is deprecated).
        if ($request->filled('assigned') && $request->assigned === 'unassigned') {
            $query->whereNull('dinas_unit_id')
                  ->whereNotIn('status', ['resolved', 'rejected', 'closed']);
        }

        // ── Sort ──────────────────────────────────────────────────────────────
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('submitted_at'),
            'status' => $query->orderBy('status')->orderByDesc('submitted_at'),
            default  => $query->orderByDesc('submitted_at'),
        };

        $laporan    = $query->paginate(20)->withQueryString();
        $categories = ComplaintCategory::active()->get();

        // ── KPI metrics (real database counts only) ───────────────────────────
        // NOTE: No SLA metric is computed here. SLA is not yet an approved
        // business rule (see rules.md §1.5 / prd.md), so no invented threshold
        // (e.g. "3 days") is derived or displayed (Prompt 8 / P0-3).
        $kpi = [
            'total'          => Complaint::count(),
            'baru'           => Complaint::byStatus('submitted')->count(),
            // LC-2C: "Sedang Diproses" = the ACTIVE `in_progress` stage only.
            // Legacy under_review / waiting_for_information are history-only and
            // must not be counted as active processing.
            'sedang_diproses'=> Complaint::byStatus('in_progress')->count(),
            // PHASE 6C — Decision 3: "Belum Ditentukan Dinas/Unit" is now based
            // on the routing destination (`dinas_unit_id IS NULL`), NOT the
            // deprecated `assigned_to` column. LC-2C: restricted to the ACTIVE
            // workflow so legacy rows are never counted as pending routing.
            'belum_dinas_unit' => Complaint::whereNull('dinas_unit_id')
                                         ->whereIn('status', ['submitted', 'in_progress'])
                                         ->count(),
        ];

        return view('admin.complaints', compact('laporan', 'categories', 'kpi'));
    }
}
