<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveKecamatanRequest;
use App\Models\AuditLog;
use App\Models\Kecamatan;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — Kecamatan master data CRUD.
 *
 * Authorization: route middleware `role:super_admin` + request `authorize()`.
 * Audit log: project convention `subject_type` = plain string.
 *
 * Historical safety: a Kecamatan still owning Kelurahan or referenced by any
 * complaint MUST NOT be hard-deleted. The model layer guards this; the
 * controller presents a friendly redirect instead of a 500.
 */
class SuperAdminKecamatanController extends Controller
{
    public function index(Request $request): View
    {
        $query = Kecamatan::query()->withCount('kelurahan', 'complaints');

        if ($search = QueryInput::trimmed($request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        $kecamatan = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('super-admin.kecamatan.index', [
            'kecamatan' => $kecamatan,
            'search'    => QueryInput::trimmed($request->get('search')) ?? '',
        ]);
    }

    public function create(): View
    {
        return view('super-admin.kecamatan.create');
    }

    public function store(SaveKecamatanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $kecamatan = DB::transaction(function () use ($request, $validated) {
            $kecamatan = Kecamatan::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
            ]);

            $this->audit($request, 'kecamatan.created', $kecamatan, [
                'name' => $kecamatan->name,
                'code' => $kecamatan->code,
            ]);

            return $kecamatan;
        });

        return redirect()
            ->route('super-admin.kecamatan.index')
            ->with('success', 'Kecamatan "' . $kecamatan->name . '" berhasil dibuat.');
    }

    public function edit(Kecamatan $kecamatan): View
    {
        $kecamatan->loadCount('kelurahan', 'complaints');

        return view('super-admin.kecamatan.edit', compact('kecamatan'));
    }

    public function update(SaveKecamatanRequest $request, Kecamatan $kecamatan): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $kecamatan) {
            $kecamatan->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
            ]);

            $this->audit($request, 'kecamatan.updated', $kecamatan, [
                'name' => $kecamatan->name,
                'code' => $kecamatan->code,
            ]);
        });

        return redirect()
            ->route('super-admin.kecamatan.index')
            ->with('success', 'Kecamatan "' . $kecamatan->name . '" berhasil diperbarui.');
    }

    /**
     * Hard-delete a Kecamatan.
     *
     * Blocked when the Kecamatan still owns Kelurahan or is referenced by any
     * complaint (historical integrity). The model layer also guards this.
     */
    public function destroy(Request $request, Kecamatan $kecamatan): RedirectResponse
    {
        if ($kecamatan->kelurahan()->exists()) {
            return redirect()
                ->route('super-admin.kecamatan.index')
                ->withErrors([
                    'kecamatan' => 'Kecamatan "' . $kecamatan->name . '" masih memiliki Kelurahan dan tidak dapat dihapus. Tangani Kelurahan terlebih dahulu.',
                ]);
        }

        if ($kecamatan->complaints()->exists()) {
            return redirect()
                ->route('super-admin.kecamatan.index')
                ->withErrors([
                    'kecamatan' => 'Kecamatan "' . $kecamatan->name . '" sudah digunakan pada laporan dan tidak dapat dihapus.',
                ]);
        }

        $name = $kecamatan->name;

        DB::transaction(function () use ($request, $kecamatan, $name) {
            $this->audit($request, 'kecamatan.deleted', $kecamatan, ['name' => $name]);

            $kecamatan->delete();
        });

        return redirect()
            ->route('super-admin.kecamatan.index')
            ->with('success', 'Kecamatan "' . $name . '" berhasil dihapus.');
    }

    private function audit(Request $request, string $action, Kecamatan $kecamatan, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'kecamatan',
            'subject_id'   => $kecamatan->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
