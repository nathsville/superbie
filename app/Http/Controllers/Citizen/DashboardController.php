<?php

namespace App\Http\Controllers\Citizen;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
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
     *
     * Optional read-only filters (search by reference/title, status allowlist,
     * category allowlist) are applied on top of the OWN-complaints scope. With
     * no query string the behaviour is unchanged: every own complaint, newest
     * first. The reporter scope is NEVER widened by any filter.
     */
    public function history(Request $request): View
    {
        $user = $request->user();

        $query = Complaint::forReporter($user->id)
            ->with(['category', 'kecamatan', 'kelurahan']);

        // ── Search (reference_code, title) — bounded, parameterised LIKE ──────
        $search = $request->get('q');
        if (is_string($search) && trim($search) !== '') {
            $search = mb_substr(trim($search), 0, 200);
            $query->where(function ($qb) use ($search) {
                $qb->where('reference_code', 'like', "%{$search}%")
                   ->orWhere('title', 'like', "%{$search}%");
            });
        }

        // ── Status filter (enum allowlist; unknown value ignored) ─────────────
        $status = $request->get('status');
        if (is_string($status) && ComplaintStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        // ── Category filter (integer id; unknown value ignored) ───────────────
        $category = $request->get('category');
        if (is_string($category) && ctype_digit($category)) {
            $query->where('category_id', (int) $category);
        }

        $laporan = $query->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('citizen.history', [
            'user'       => $user,
            'laporan'    => $laporan,
            'categories' => ComplaintCategory::active()->get(),
            'statuses'   => ComplaintStatus::cases(),
            'filters'    => [
                'q'        => $request->get('q'),
                'status'   => $status,
                'category' => $category,
            ],
        ]);
    }
}
