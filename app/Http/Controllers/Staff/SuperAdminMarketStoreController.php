<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveMarketStoreRequest;
use App\Models\AuditLog;
use App\Models\Market;
use App\Models\MarketStore;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Super Admin — Retail store directory CRUD (PHASE S / SIPHP module).
 *
 * Photos are PUBLIC by design (D-S7) and stored on the configured public disk
 * under a generated filename; the previous file is removed when a photo is
 * replaced or the store is deleted — AFTER the commit (audit PROMPT_28 / S-02),
 * so no orphaned images accumulate and no committed row ever points at a file
 * that a rolled-back transaction removed. The original client filename is
 * never used as a path.
 */
class SuperAdminMarketStoreController extends Controller
{
    public function index(Request $request): View
    {
        $query = MarketStore::query()->with('market');

        if ($search = QueryInput::trimmed($request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('market_stores.name', 'like', '%' . $search . '%')
                  ->orWhere('market_stores.type', 'like', '%' . $search . '%');
            });
        }

        // A crafted array (`?market_id[]=x`) must be ignored, not cast inline.
        if ($marketId = QueryInput::int($request->get('market_id'))) {
            $query->where('market_stores.market_id', $marketId);
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $stores = $query->orderBy('market_stores.name')->paginate(20)->withQueryString();
        $markets = Market::orderBy('name')->get();

        return view('super-admin.siphp.stores.index', [
            'stores'   => $stores,
            'markets'  => $markets,
            'search'   => QueryInput::trimmed($request->get('search')) ?? '',
            'marketId' => QueryInput::int($request->get('market_id')),
            'status'   => QueryInput::string($request->get('status')) ?? '',
        ]);
    }

    public function create(): View
    {
        $markets = Market::orderBy('name')->get();

        return view('super-admin.siphp.stores.create', compact('markets'));
    }

    public function store(SaveMarketStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Audit PROMPT_28 (S-02): the photo is written BEFORE the transaction
        // and removed again if it rolls back — a committed row never points at
        // a missing file, and a failed create leaves no orphan on disk.
        $photoPath = $this->storePhoto($request->file('photo'));

        try {
            $store = DB::transaction(function () use ($request, $validated, $photoPath) {
                $store = MarketStore::create([
                    'market_id'     => $validated['market_id'] ?? null,
                    'name'          => $validated['name'],
                    'type'          => $validated['type'] ?? null,
                    'address'       => $validated['address'] ?? null,
                    'phone'         => $validated['phone'] ?? null,
                    'opening_hours' => $validated['opening_hours'] ?? null,
                    'photo_path'    => $photoPath,
                    'is_active'     => $validated['is_active'] ?? true,
                ]);

                $this->audit($request, 'market_store.created', $store, [
                    'name' => $store->name,
                ]);

                return $store;
            });
        } catch (\Throwable $e) {
            $this->deletePhoto($photoPath);
            throw $e;
        }

        return redirect()
            ->route('super-admin.market-stores.index')
            ->with('success', 'Toko "' . $store->name . '" berhasil dibuat.');
    }

    public function edit(MarketStore $store): View
    {
        $markets = Market::orderBy('name')->get();

        return view('super-admin.siphp.stores.edit', compact('store', 'markets'));
    }

    public function update(SaveMarketStoreRequest $request, MarketStore $store): RedirectResponse
    {
        $validated = $request->validated();

        // Audit PROMPT_28 (S-02): file writes stay OUTSIDE the transaction. A
        // fresh upload is written first and removed again if the transaction
        // rolls back; the replaced/removed file is deleted only AFTER the
        // commit — a committed row always points at a file that exists.
        $newPhotoPath = $this->storePhoto($request->file('photo'));

        $finalPhotoPath = $newPhotoPath
            ?? (($validated['remove_photo'] ?? false) ? null : $store->photo_path);
        $replacedPhotoPath = $finalPhotoPath === $store->photo_path ? null : $store->photo_path;

        try {
            DB::transaction(function () use ($request, $validated, $store, $finalPhotoPath) {
                $store->update([
                    'market_id'     => $validated['market_id'] ?? null,
                    'name'          => $validated['name'],
                    'type'          => $validated['type'] ?? null,
                    'address'       => $validated['address'] ?? null,
                    'phone'         => $validated['phone'] ?? null,
                    'opening_hours' => $validated['opening_hours'] ?? null,
                    'photo_path'    => $finalPhotoPath,
                    'is_active'     => $validated['is_active'] ?? $store->is_active,
                ]);

                $this->audit($request, 'market_store.updated', $store, [
                    'name' => $store->name,
                ]);
            });
        } catch (\Throwable $e) {
            $this->deletePhoto($newPhotoPath);
            throw $e;
        }

        // The replaced/removed file goes away only once the row actually moved.
        $this->deletePhoto($replacedPhotoPath);

        return redirect()
            ->route('super-admin.market-stores.index')
            ->with('success', 'Toko "' . $store->name . '" berhasil diperbarui.');
    }

    public function destroy(Request $request, MarketStore $store): RedirectResponse
    {
        $name = $store->name;
        $photoPath = $store->photo_path;

        DB::transaction(function () use ($request, $store, $name) {
            $this->audit($request, 'market_store.deleted', $store, ['name' => $name]);

            $store->delete();
        });

        // Audit PROMPT_28 (S-02): the photo is removed only after the commit
        // (retention-service pattern) — a rolled-back delete never leaves the
        // surviving row without its file.
        $this->deletePhoto($photoPath);

        return redirect()
            ->route('super-admin.market-stores.index')
            ->with('success', 'Toko "' . $name . '" berhasil dihapus.');
    }

    /**
     * Persist an uploaded store photo on the configured public disk. Returns
     * null when no file was uploaded.
     */
    private function storePhoto(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        return $file->store(
            (string) config('siphp.store_photo.directory'),
            (string) config('siphp.store_photo.disk'),
        );
    }

    private function deletePhoto(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk((string) config('siphp.store_photo.disk'))->delete($path);
        }
    }

    private function audit(Request $request, string $action, MarketStore $store, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'market_store',
            'subject_id'   => $store->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
