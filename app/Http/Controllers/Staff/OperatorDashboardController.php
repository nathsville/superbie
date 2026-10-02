<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Enums\ComplaintStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperatorDashboardController extends Controller
{
    /**
     * Operator dashboard — operational overview of all complaints.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Operator sees all complaints for operational management
        $totalLaporan    = Complaint::count();
        $belumDitugaskan = Complaint::whereNull('assigned_to')
            ->whereNotIn('status', ['resolved', 'rejected', 'closed'])
            ->count();
        $perluTindakan   = Complaint::whereIn('status', ['submitted', 'under_review'])
            ->count();
        $selesaiHariIni  = Complaint::byStatus('resolved')
            ->whereDate('resolved_at', today())
            ->count();

        // Status breakdown counts
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

        return view('operator.dashboard', compact(
            'user',
            'totalLaporan',
            'belumDitugaskan',
            'perluTindakan',
            'selesaiHariIni',
            'statusBreakdown',
            'laporanTerbaru',
        ));
    }
}
