<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Enums\ComplaintStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin dashboard — MONITORING ONLY.
 * Admin has NO management capabilities; no create/update/delete routes exist.
 */
class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Monitoring metrics — real DB counts only, no fake numbers
        $totalLaporan   = Complaint::count();
        $laporanAktif   = Complaint::whereNotIn('status', ['resolved', 'rejected', 'closed'])->count();
        $laporanSelesai = Complaint::byStatus('resolved')->count();
        $auditHariIni   = AuditLog::whereDate('created_at', today())->count();

        $statusBreakdown = [];
        foreach (ComplaintStatus::cases() as $status) {
            $statusBreakdown[$status->value] = [
                'label' => $status->label(),
                'badge' => $status->tailwindBadge(),
                'count' => Complaint::byStatus($status->value)->count(),
            ];
        }

        $laporanTerbaru = Complaint::with(['reporter', 'category'])
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'user',
            'totalLaporan',
            'laporanAktif',
            'laporanSelesai',
            'auditHariIni',
            'statusBreakdown',
            'laporanTerbaru',
        ));
    }

    public function complaints(Request $request): View
    {
        $query = Complaint::with(['reporter', 'category', 'assignee'])
            ->orderByDesc('submitted_at');

        // Allowlisted filter parameters only
        if ($request->filled('status') && in_array($request->status, array_column(ComplaintStatus::cases(), 'value'))) {
            $query->byStatus($request->status);
        }

        $laporan = $query->paginate(20)->withQueryString();

        return view('admin.complaints', compact('laporan'));
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
