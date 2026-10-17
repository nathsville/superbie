<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveMarketItemRequest;
use App\Models\AuditLog;
use App\Models\MarketItem;
use App\Models\MarketItemCategory;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — Market item (barang) CRUD (PHASE S / SIPHP module).
 *
 * The official item list is staff-entered (never seeded). Historical safety:
 * an item referenced by any price row MUST NOT be hard-deleted (model layer
 * also guards this); deactivate it instead.
 */
class SuperAdminMarketItemController extends Controller
{
    public function index(Request $request): View
    {
        $query = MarketItem::query()->with('category')->withCount('prices');

        if ($search = QueryInput::trimmed($request->get('search'))) {
            $query->where('market_items.name', 'like', '%' . $search . '%');
        }

        // A crafted array (`?category_id[]=x`) must be ignored, not cast inline.
        if ($categoryId = QueryInput::int($request->get('category_id'))) {
            $query->where('market_items.category_id', $categoryId);
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->orderBy('market_items.name')->paginate(20)->withQueryString();
        $categories = MarketItemCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('super-admin.siphp.items.index', [
            'items'      => $items,
            'categories' => $categories,
            'search'     => QueryInput::trimmed($request->get('search')) ?? '',
            'categoryId' => QueryInput::int($request->get('category_id')),
            'status'     => QueryInput::string($request->get('status')) ?? '',
        ]);
    }

    public function create(): View
    {
        $categories = MarketItemCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('super-admin.siphp.items.create', compact('categories'));
    }

    public function store(SaveMarketItemRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $item = DB::transaction(function () use ($request, $validated) {
            $item = MarketItem::create([
                'category_id' => $validated['category_id'],
                'name'        => $validated['name'],
                'unit'        => $validated['unit'] ?? null,
                'is_active'   => $validated['is_active'] ?? true,
            ]);

            $this->audit($request, 'market_item.created', $item, [
                'name'      => $item->name,
                'is_active' => $item->is_active,
            ]);

            return $item;
        });

        return redirect()
            ->route('super-admin.market-items.index')
            ->with('success', 'Barang "' . $item->name . '" berhasil dibuat.');
    }

    public function edit(MarketItem $item): View
    {
        $item->loadCount('prices');
        $categories = MarketItemCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('super-admin.siphp.items.edit', compact('item', 'categories'));
    }

    public function update(SaveMarketItemRequest $request, MarketItem $item): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $item) {
            $item->update([
                'category_id' => $validated['category_id'],
                'name'        => $validated['name'],
                'unit'        => $validated['unit'] ?? null,
                'is_active'   => $validated['is_active'] ?? $item->is_active,
            ]);

            $this->audit($request, 'market_item.updated', $item, [
                'name'      => $item->name,
                'is_active' => $item->is_active,
            ]);
        });

        return redirect()
            ->route('super-admin.market-items.index')
            ->with('success', 'Barang "' . $item->name . '" berhasil diperbarui.');
    }

    /**
     * Hard-delete an item that has NO price rows. Items with price history are
     * protected (historical integrity).
     */
    public function destroy(Request $request, MarketItem $item): RedirectResponse
    {
        if ($item->prices()->exists()) {
            return redirect()
                ->route('super-admin.market-items.index')
                ->withErrors([
                    'item' => 'Barang "' . $item->name . '" sudah memiliki data harga dan tidak dapat dihapus. Nonaktifkan sebagai gantinya.',
                ]);
        }

        $name = $item->name;

        DB::transaction(function () use ($request, $item, $name) {
            $this->audit($request, 'market_item.deleted', $item, ['name' => $name]);

            $item->delete();
        });

        return redirect()
            ->route('super-admin.market-items.index')
            ->with('success', 'Barang "' . $name . '" berhasil dihapus.');
    }

    private function audit(Request $request, string $action, MarketItem $item, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'market_item',
            'subject_id'   => $item->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
