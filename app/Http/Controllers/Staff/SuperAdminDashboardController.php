<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use App\Enums\ComplaintStatus;
use App\Services\DashboardCacheService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    /**
     * Super Admin dashboard — full system overview.
     * Metrics are cached with instant refresh support.
     */
    public function index(Request $request, DashboardCacheService $cacheService): View
    {
        $user = $request->user();
        $refresh = $request->boolean('refresh');

        $data = $cacheService->getSuperAdminData($refresh);

        return view('super-admin.dashboard', array_merge([
            'user' => $user,
        ], $data));
    }
}
