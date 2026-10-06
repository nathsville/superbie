<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Enums\ComplaintStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

use App\Services\DashboardCacheService;

/**
 * Admin dashboard — MONITORING ONLY.
 * Admin has NO management capabilities; no create/update/delete routes exist.
 * Metrics are cached with instant refresh support.
 */
class AdminDashboardController extends Controller
{
    public function index(Request $request, DashboardCacheService $cacheService): View
    {
        $user = $request->user();
        $refresh = $request->boolean('refresh');

        $data = $cacheService->getAdminData($refresh);

        return view('admin.dashboard', array_merge([
            'user' => $user,
        ], $data));
    }

    public function complaints(Request $request): View
    {
        $query = Complaint::with(['reporter', 'category', 'assignee']);

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
            'sedang_diproses'=> Complaint::whereIn('status', ['under_review', 'in_progress', 'waiting_for_information'])->count(),
            'belum_ditugaskan' => Complaint::whereNull('assigned_to')
                                         ->whereNotIn('status', ['resolved', 'rejected', 'closed'])
                                         ->count(),
        ];

        return view('admin.complaints', compact('laporan', 'categories', 'kpi'));
    }

    public function auditLog(Request $request): View
    {
        $logs = AuditLog::with('actor')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log', compact('logs'));
    }
}
