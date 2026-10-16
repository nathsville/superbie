<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Enums\ComplaintStatus;
use App\Services\DashboardCacheService;
use App\Services\RatingAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperatorDashboardController extends Controller
{
    /**
     * Operator dashboard — operational overview of all complaints.
     * Metrics are cached with instant refresh support.
     */
    public function index(
        Request $request,
        DashboardCacheService $cacheService,
        RatingAnalyticsService $ratingAnalyticsService,
    ): View {
        $user = $request->user();
        $refresh = $request->boolean('refresh');

        $data = $cacheService->getOperatorData($user, $refresh);

        // PHASE 4 — Bagian 2 — Rating Analytics (D-4.1 / D-4.9). An Operator is
        // FORCED to their own Dinas/Unit server-side; a Super Admin viewing this
        // surface keeps the GLOBAL scope (mirrors the existing hybrid rule).
        $ratingAnalytics = $ratingAnalyticsService->getRatingAnalytics(
            $user,
            $request->only(['period', 'start_date', 'end_date', 'dinas_unit_id', 'category_id', 'kecamatan_id', 'kelurahan_id']),
            $refresh,
        );

        return view('operator.dashboard', array_merge([
            'user' => $user,
            'ratingAnalytics' => $ratingAnalytics,
        ], $data));
    }
}
