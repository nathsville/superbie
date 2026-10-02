<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PetugasDashboardController extends Controller
{
    /**
     * Petugas dashboard — only complaints assigned to this petugas.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Scoped strictly to assigned_to = current petugas
        $totalDitugaskan = Complaint::assignedTo($user->id)->count();
        $perluDitangani  = Complaint::assignedTo($user->id)
            ->whereIn('status', ['submitted', 'under_review', 'in_progress', 'waiting_for_information'])
            ->count();
        $selesai         = Complaint::assignedTo($user->id)->byStatus('resolved')->count();

        $laporanTerbaru  = Complaint::assignedTo($user->id)
            ->with(['reporter', 'category'])
            ->whereIn('status', ['submitted', 'under_review', 'in_progress', 'waiting_for_information'])
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get();

        return view('petugas.dashboard', compact(
            'user',
            'totalDitugaskan',
            'perluDitangani',
            'selesai',
            'laporanTerbaru',
        ));
    }
}
