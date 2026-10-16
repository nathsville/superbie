<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveKelurahanRequest;
use App\Models\AuditLog;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — Kelurahan master data CRUD.
 *
 * Authorization: route middleware `role:super_admin` + request `authorize()`.
 * Audit log: project convention `subject_type` = plain string.
 *
 * Historical safety: a Kelurahan referenced by any complaint MUST NOT be
 * hard-deleted. The model layer guards this; the controller presents a friendly
 * redirect instead of a 500.
 */
class SuperAdminKelurahanController extends Controller
{
    public function index(Request $request): View
    {
        $query = Kelurahan::query()->with('kecamatan')->withCount('complaints');

        if ($search = QueryInput::trimmed($request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('kelurahan.name', 'like', '%' . $search . '%')
                  ->orWhere('kelurahan.code', 'like', '%' . $search . '%');
            });
        }

        // A crafted array (`?kecamatan_id[]=x`) must be ignored, not cast inline.
        if ($kecamatanId = QueryInput::int($request->get('kecamatan_id'))) {
            $query->where('kelurahan.kecamatan_id', $kecamatanId);
        }

        $kelurahan = $query->orderBy('name')->paginate(20)->withQueryString();
        $kecamatanList = Kecamatan::orderBy('name')->get();

        return view('super-admin.kelurahan.index', [
            'kelurahan'     => $kelurahan,
            'kecamatanList' => $kecamatanList,
            'search'        => QueryInput::trimmed($request->get('search')) ?? '',
            'kecamatanId'   => QueryInput::int($request->get('kecamatan_id')),
        ]);
    }

    public function create(): View
    {
        $kecamatanList = Kecamatan::orderBy('name')->get();

        return view('super-admin.kelurahan.create', compact('kecamatanList'));
    }

    public function store(SaveKelurahanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $kelurahan = DB::transaction(function () use ($request, $validated) {
            $kelurahan = Kelurahan::create([
                'kecamatan_id' => $validated['kecamatan_id'],
                'name'         => $validated['name'],
                'code'         => $validated['code'] ?? null,
            ]);

            $this->audit($request, 'kelurahan.created', $kelurahan, [
                'name'         => $kelurahan->name,
                'code'         => $kelurahan->code,
                'kecamatan_id' => $kelurahan->kecamatan_id,
            ]);

            return $kelurahan;
        });

        return redirect()
            ->route('super-admin.kelurahan.index')
            ->with('success', 'Kelurahan "' . $kelurahan->name . '" berhasil dibuat.');
    }

    public function edit(Kelurahan $kelurahan): View
    {
        $kelurahan->loadCount('complaints');
        $kecamatanList = Kecamatan::orderBy('name')->get();

        return view('super-admin.kelurahan.edit', compact('kelurahan', 'kecamatanList'));
    }

    public function update(SaveKelurahanRequest $request, Kelurahan $kelurahan): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $kelurahan) {
            $kelurahan->update([
                'kecamatan_id' => $validated['kecamatan_id'],
                'name'         => $validated['name'],
                'code'         => $validated['code'] ?? null,
            ]);

            $this->audit($request, 'kelurahan.updated', $kelurahan, [
                'name'         => $kelurahan->name,
                'code'         => $kelurahan->code,
                'kecamatan_id' => $kelurahan->kecamatan_id,
            ]);
        });

        return redirect()
            ->route('super-admin.kelurahan.index')
            ->with('success', 'Kelurahan "' . $kelurahan->name . '" berhasil diperbarui.');
    }

    /**
     * Hard-delete a Kelurahan.
     *
     * Blocked when referenced by any complaint (historical integrity). The model
     * layer also guards this.
     */
    public function destroy(Request $request, Kelurahan $kelurahan): RedirectResponse
    {
        if ($kelurahan->complaints()->exists()) {
            return redirect()
                ->route('super-admin.kelurahan.index')
                ->withErrors([
                    'kelurahan' => 'Kelurahan "' . $kelurahan->name . '" sudah digunakan pada laporan dan tidak dapat dihapus.',
                ]);
        }

        $name = $kelurahan->name;

        DB::transaction(function () use ($request, $kelurahan, $name) {
            $this->audit($request, 'kelurahan.deleted', $kelurahan, ['name' => $name]);

            $kelurahan->delete();
        });

        return redirect()
            ->route('super-admin.kelurahan.index')
            ->with('success', 'Kelurahan "' . $name . '" berhasil dihapus.');
    }

    private function audit(Request $request, string $action, Kelurahan $kelurahan, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'kelurahan',
            'subject_id'   => $kelurahan->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
