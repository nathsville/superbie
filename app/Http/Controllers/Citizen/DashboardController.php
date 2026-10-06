<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\View\View;

use App\Services\DashboardCacheService;

class DashboardController extends Controller
{
    /**
     * Citizen dashboard — shows summary of own complaints only.
     * Complaints from other users are NEVER included.
     * Metrics are cached with instant refresh support.
     */
    public function index(Request $request, DashboardCacheService $cacheService): View
    {
        $user = $request->user();
        $refresh = $request->boolean('refresh');

        $data = $cacheService->getCitizenData($user, $refresh);

        return view('citizen.dashboard', array_merge([
            'user' => $user,
        ], $data));
    }

    /**
     * Full complaint history list — scoped to own complaints only.
     */
    public function history(Request $request): View
    {
        $user = $request->user();

        $laporan = Complaint::forReporter($user->id)
            ->with('category')
            ->orderByDesc('submitted_at')
            ->paginate(15);

        return view('citizen.history', compact('user', 'laporan'));
    }
}
