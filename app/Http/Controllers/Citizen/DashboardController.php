<?php

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Citizen dashboard — shows summary of own complaints only.
     * Complaints from other users are NEVER included.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // All queries scoped to reporter_id = authenticated user
        $totalLaporan    = Complaint::forReporter($user->id)->count();
        $laporanProses   = Complaint::forReporter($user->id)->whereIn('status', ['submitted', 'under_review', 'in_progress', 'waiting_for_information'])->count();
        $laporanSelesai  = Complaint::forReporter($user->id)->byStatus('resolved')->count();
        $laporanDitolak  = Complaint::forReporter($user->id)->byStatus('rejected')->count();

        $laporanTerbaru  = Complaint::forReporter($user->id)
            ->with('category')
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        return view('citizen.dashboard', compact(
            'user',
            'totalLaporan',
            'laporanProses',
            'laporanSelesai',
            'laporanDitolak',
            'laporanTerbaru',
        ));
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
