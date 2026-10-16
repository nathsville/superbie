<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use App\Enums\ComplaintStatus;
use App\Services\DashboardCacheService;
use App\Services\RatingAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    /**
     * Super Admin dashboard — full system overview.
     * Metrics are cached with instant refresh support.
     */
    public function index(
        Request $request,
        DashboardCacheService $cacheService,
        RatingAnalyticsService $ratingAnalyticsService,
    ): View {
        $user = $request->user();
        $refresh = $request->boolean('refresh');

        $data = $cacheService->getSuperAdminData($refresh);

        // PHASE 4 — Bagian 2 — Rating Analytics (D-4.1: Super Admin = GLOBAL).
        $ratingAnalytics = $ratingAnalyticsService->getRatingAnalytics(
            $user,
            $request->only(['period', 'start_date', 'end_date', 'dinas_unit_id', 'category_id', 'kecamatan_id', 'kelurahan_id']),
            $refresh,
        );

        return view('super-admin.dashboard', array_merge([
            'user' => $user,
            'ratingAnalytics' => $ratingAnalytics,
        ], $data));
    }
}
