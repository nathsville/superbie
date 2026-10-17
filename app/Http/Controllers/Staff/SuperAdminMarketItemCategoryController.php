<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveMarketItemCategoryRequest;
use App\Models\AuditLog;
use App\Models\MarketItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Super Admin — Market item category master data CRUD (PHASE S / SIPHP).
 *
 * Categories (barang pokok / subsidi / penting) are seeded from observed facts
 * only; further rows are data. Historical safety: a category that still owns
 * items MUST NOT be hard-deleted (model layer also guards this).
 */
class SuperAdminMarketItemCategoryController extends Controller
{
    public function index(): View
    {
        $categories = MarketItemCategory::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('super-admin.siphp.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('super-admin.siphp.categories.create');
    }

    public function store(SaveMarketItemCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $category = DB::transaction(function () use ($request, $validated) {
            $category = MarketItemCategory::create([
                'name'       => $validated['name'],
                'slug'       => $this->uniqueSlug($validated['name']),
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            $this->audit($request, 'market_item_category.created', $category, [
                'name' => $category->name,
            ]);

            return $category;
        });

        return redirect()
            ->route('super-admin.market-categories.index')
            ->with('success', 'Kategori barang "' . $category->name . '" berhasil dibuat.');
    }

    public function edit(MarketItemCategory $category): View
    {
        $category->loadCount('items');

        return view('super-admin.siphp.categories.edit', compact('category'));
    }

    public function update(SaveMarketItemCategoryRequest $request, MarketItemCategory $category): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $category) {
            // `slug` is a stable key and is NOT changed on update.
            $category->update([
                'name'       => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            $this->audit($request, 'market_item_category.updated', $category, [
                'name' => $category->name,
            ]);
        });

        return redirect()
            ->route('super-admin.market-categories.index')
            ->with('success', 'Kategori barang "' . $category->name . '" berhasil diperbarui.');
    }

    /**
     * Hard-delete a category that still owns NO items. Categories with items
     * are protected (historical integrity).
     */
    public function destroy(Request $request, MarketItemCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return redirect()
                ->route('super-admin.market-categories.index')
                ->withErrors([
                    'category' => 'Kategori "' . $category->name . '" masih memiliki barang dan tidak dapat dihapus. Pindahkan barangnya terlebih dahulu.',
                ]);
        }

        $name = $category->name;

        DB::transaction(function () use ($request, $category, $name) {
            $this->audit($request, 'market_item_category.deleted', $category, ['name' => $name]);

            $category->delete();
        });

        return redirect()
            ->route('super-admin.market-categories.index')
            ->with('success', 'Kategori barang "' . $name . '" berhasil dihapus.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kategori-barang';
        $slug = $base;
        $i = 2;

        while (MarketItemCategory::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function audit(Request $request, string $action, MarketItemCategory $category, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'market_item_category',
            'subject_id'   => $category->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
