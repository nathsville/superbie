<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveDinasUnitRequest;
use App\Models\AuditLog;
use App\Models\DinasUnit;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — Dinas/Unit master data CRUD (Prompt 5C).
 *
 * Authorization: route middleware `role:super_admin` + request `authorize()`.
 * Audit log: uses the project convention `subject_type` = plain string.
 */
class SuperAdminDinasUnitController extends Controller
{
    public function index(Request $request): View
    {
        // PHASE 1 — eager-load the single owning Operator so the "Operator"
        // column renders without an N+1 query per row.
        $query = DinasUnit::query()->withCount('categories', 'complaints')->with('operator');

        // Raw input is coerced defensively: a crafted array (`?search[]=x`) must
        // be ignored, never cast inline (that raises "Array to string conversion").
        $search = QueryInput::trimmed($request->get('search')) ?? '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        // Only the two recognised statuses narrow the list; anything else (including
        // a crafted array) is ignored.
        $statusFilter = QueryInput::string($request->get('status')) ?? '';
        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $dinasUnits = $query->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();

        // The header reports the GRAND total (independent of filters), so an
        // active search/filter never mislabels the master-data size.
        $totalUnits = DinasUnit::count();

        return view('super-admin.dinas.index', compact('dinasUnits', 'totalUnits', 'search', 'statusFilter'));
    }

    public function create(): View
    {
        return view('super-admin.dinas.create');
    }

    public function store(SaveDinasUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $dinasUnit = DB::transaction(function () use ($request, $validated) {
            $dinasUnit = DinasUnit::create([
                'name'        => $validated['name'],
                'code'        => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active'   => $validated['is_active'] ?? true,
                'sort_order'  => $validated['sort_order'] ?? 0,
            ]);

            $this->audit($request, 'dinas_unit.created', $dinasUnit, [
                'name' => $dinasUnit->name,
                'code' => $dinasUnit->code,
            ]);

            return $dinasUnit;
        });

        return redirect()
            ->route('super-admin.dinas.index')
            ->with('success', 'Dinas/Unit "' . $dinasUnit->name . '" berhasil dibuat.');
    }

    public function edit(DinasUnit $dinasUnit): View
    {
        return view('super-admin.dinas.edit', compact('dinasUnit'));
    }

    public function update(SaveDinasUnitRequest $request, DinasUnit $dinasUnit): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $dinasUnit) {
            $dinasUnit->update([
                'name'        => $validated['name'],
                'code'        => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active'   => $validated['is_active'] ?? $dinasUnit->is_active,
                'sort_order'  => $validated['sort_order'] ?? 0,
            ]);

            $this->audit($request, 'dinas_unit.updated', $dinasUnit, [
                'name'      => $dinasUnit->name,
                'code'      => $dinasUnit->code,
                'is_active' => $dinasUnit->is_active,
            ]);
        });

        return redirect()
            ->route('super-admin.dinas.index')
            ->with('success', 'Dinas/Unit "' . $dinasUnit->name . '" berhasil diperbarui.');
    }

    /**
     * Hard-delete a Dinas/Unit.
     *
     * A Dinas/Unit that has ever been used as a complaint's ACTUAL destination
     * MUST NOT be deleted (Prompt 5C.1) — the Super Admin must deactivate it.
     * The model layer also guards this; here we present a friendly redirect.
     */
    public function destroy(Request $request, DinasUnit $dinasUnit): RedirectResponse
    {
        if ($dinasUnit->isUsedByComplaints()) {
            return redirect()
                ->route('super-admin.dinas.index')
                ->withErrors([
                    'dinas_unit' => 'Dinas/Unit "' . $dinasUnit->name . '" sudah digunakan pada laporan dan tidak dapat dihapus. Nonaktifkan sebagai gantinya agar tidak dipilih untuk penugasan baru.',
                ]);
        }

        DB::transaction(function () use ($request, $dinasUnit) {
            // Remove category mapping first (pivot only; never touches complaints).
            $dinasUnit->categories()->detach();

            $this->audit($request, 'dinas_unit.deleted', $dinasUnit, [
                'name' => $dinasUnit->name,
                'code' => $dinasUnit->code,
            ]);

            $dinasUnit->delete();
        });

        return redirect()
            ->route('super-admin.dinas.index')
            ->with('success', 'Dinas/Unit "' . $dinasUnit->name . '" berhasil dihapus.');
    }

    private function audit(Request $request, string $action, DinasUnit $dinasUnit, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'dinas_unit',
            'subject_id'   => $dinasUnit->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
