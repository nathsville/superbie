<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveMarketRequest;
use App\Models\AuditLog;
use App\Models\Market;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Super Admin — Market (pasar) master data CRUD (PHASE S / SIPHP module).
 *
 * Authorization: route middleware `role:super_admin` + request `authorize()`.
 * Audit log: project convention `subject_type` = plain string.
 *
 * Historical safety: a market referenced by any price row MUST NOT be
 * hard-deleted (model layer guards this; the controller presents a friendly
 * redirect). Deactivate it instead. Deleting a market NEVER deletes its
 * stores — the FK simply clears their market link.
 */
class SuperAdminMarketController extends Controller
{
    public function index(Request $request): View
    {
        $query = Market::query()->withCount('prices', 'stores');

        if ($search = QueryInput::trimmed($request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $markets = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('super-admin.siphp.market.index', [
            'markets' => $markets,
            'search'  => QueryInput::trimmed($request->get('search')) ?? '',
            'status'  => QueryInput::string($request->get('status')) ?? '',
        ]);
    }

    public function create(): View
    {
        return view('super-admin.siphp.market.create');
    }

    public function store(SaveMarketRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $market = DB::transaction(function () use ($request, $validated) {
            $market = Market::create([
                'name'      => $validated['name'],
                'slug'      => $this->uniqueSlug($validated['name']),
                'address'   => $validated['address'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->audit($request, 'market.created', $market, [
                'name'      => $market->name,
                'is_active' => $market->is_active,
            ]);

            return $market;
        });

        return redirect()
            ->route('super-admin.markets.index')
            ->with('success', 'Pasar "' . $market->name . '" berhasil dibuat.');
    }

    public function edit(Market $market): View
    {
        $market->loadCount('prices', 'stores');

        return view('super-admin.siphp.market.edit', compact('market'));
    }

    public function update(SaveMarketRequest $request, Market $market): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $market) {
            // NOTE: `slug` is a stable public URL key and is NOT changed on
            // update, so existing `/siphp/pasar/{slug}` links remain valid.
            $market->update([
                'name'      => $validated['name'],
                'address'   => $validated['address'] ?? null,
                'is_active' => $validated['is_active'] ?? $market->is_active,
            ]);

            $this->audit($request, 'market.updated', $market, [
                'name'      => $market->name,
                'is_active' => $market->is_active,
            ]);
        });

        return redirect()
            ->route('super-admin.markets.index')
            ->with('success', 'Pasar "' . $market->name . '" berhasil diperbarui.');
    }

    /**
     * Activate / deactivate a market. Deactivation hides it from the public
     * `/siphp` surface and from new price entry; it NEVER modifies recorded
     * price history.
     */
    public function toggleActive(Request $request, Market $market): RedirectResponse
    {
        $newState = ! $market->is_active;

        DB::transaction(function () use ($request, $market, $newState) {
            $market->update(['is_active' => $newState]);

            $this->audit(
                $request,
                $newState ? 'market.activated' : 'market.deactivated',
                $market,
                ['name' => $market->name, 'is_active' => $newState]
            );
        });

        return redirect()
            ->route('super-admin.markets.index')
            ->with('success', 'Pasar "' . $market->name . '" berhasil '
                . ($newState ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Hard-delete a market.
     *
     * A market that already has price rows MUST NOT be deleted (historical
     * integrity). The model layer also guards this; here we present a friendly
     * redirect. Only UNUSED markets may be deleted.
     */
    public function destroy(Request $request, Market $market): RedirectResponse
    {
        if ($market->prices()->exists()) {
            return redirect()
                ->route('super-admin.markets.index')
                ->withErrors([
                    'market' => 'Pasar "' . $market->name . '" sudah memiliki data harga dan tidak dapat dihapus. Nonaktifkan sebagai gantinya.',
                ]);
        }

        $name = $market->name;

        DB::transaction(function () use ($request, $market, $name) {
            $this->audit($request, 'market.deleted', $market, ['name' => $name]);

            $market->delete();
        });

        return redirect()
            ->route('super-admin.markets.index')
            ->with('success', 'Pasar "' . $name . '" berhasil dihapus.');
    }

    /**
     * Generate a unique slug from the market name (derived, server-side).
     * The schema enforces UNIQUE(slug).
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'pasar';
        $slug = $base;
        $i = 2;

        while (Market::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function audit(Request $request, string $action, Market $market, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'market',
            'subject_id'   => $market->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
