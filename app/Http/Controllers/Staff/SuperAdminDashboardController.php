<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use App\Enums\ComplaintStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Full system overview
        $totalLaporan    = Complaint::count();
        $totalPengguna   = User::count();
        $laporanAktif    = Complaint::whereNotIn('status', ['resolved', 'rejected', 'closed'])->count();
        $laporanSelesai  = Complaint::byStatus('resolved')->count();
        $auditHariIni    = AuditLog::whereDate('created_at', today())->count();
        $penggunaBaru    = User::whereDate('created_at', today())->count();

        // Role breakdown
        $roleBreakdown = User::selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->toArray();

        // Status breakdown
        $statusBreakdown = [];
        foreach (ComplaintStatus::cases() as $status) {
            $statusBreakdown[$status->value] = [
                'label' => $status->label(),
                'badge' => $status->tailwindBadge(),
                'count' => Complaint::byStatus($status->value)->count(),
            ];
        }

        $laporanTerbaru = Complaint::with(['reporter', 'category', 'assignee'])
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get();

        $auditTerbaru = AuditLog::with('actor')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('super-admin.dashboard', compact(
            'user',
            'totalLaporan',
            'totalPengguna',
            'laporanAktif',
            'laporanSelesai',
            'auditHariIni',
            'penggunaBaru',
            'roleBreakdown',
            'statusBreakdown',
            'laporanTerbaru',
            'auditTerbaru',
        ));
    }
}
