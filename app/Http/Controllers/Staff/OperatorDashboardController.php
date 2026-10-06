<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Enums\ComplaintStatus;
use App\Services\DashboardCacheService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperatorDashboardController extends Controller
{
    /**
     * Operator dashboard — operational overview of all complaints.
     * Metrics are cached with instant refresh support.
     */
    public function index(Request $request, DashboardCacheService $cacheService): View
    {
        $user = $request->user();
        $refresh = $request->boolean('refresh');

        $data = $cacheService->getOperatorData($user, $refresh);

        return view('operator.dashboard', array_merge([
            'user' => $user,
        ], $data));
    }
}
