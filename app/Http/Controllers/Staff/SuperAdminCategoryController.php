<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveComplaintCategoryRequest;
use App\Models\AuditLog;
use App\Models\ComplaintCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Super Admin — Category master data management (Prompt 6, Bagian 1).
 *
 * Scope (Bagian 1):
 *   - list, create, edit/update, activate, deactivate
 *   - hard-delete ONLY when never used by a complaint (historical safety)
 *
 * Authorization: route middleware `role:super_admin` + request `authorize()`.
 * Audit log: project convention `subject_type` = plain string.
 *
 * Historical safety (frozen rules):
 *   - Deactivating a category NEVER touches existing complaints.
 *   - Changing/deleting master data NEVER reassigns complaints.
 *   - `dinas_name` is a legacy label, NOT a routing source of truth; routing
 *     uses the `category_dinas_unit` many-to-many mapping.
 */
class SuperAdminCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = ComplaintCategory::query()->withCount('complaints', 'dinasUnits');

        if ($search = trim((string) $request->get('search'))) {
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

        $categories = $query->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();

        return view('super-admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('super-admin.categories.create');
    }

    public function store(SaveComplaintCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $category = DB::transaction(function () use ($request, $validated) {
            $category = ComplaintCategory::create([
                'name'        => $validated['name'],
                'slug'        => $this->uniqueSlug($validated['name']),
                'description' => $validated['description'] ?? null,
                'dinas_name'  => $validated['dinas_name'] ?? null,
                'is_active'   => $validated['is_active'] ?? true,
                'sort_order'  => $validated['sort_order'] ?? 0,
            ]);

            $this->audit($request, 'complaint_category.created', $category, [
                'name'      => $category->name,
                'is_active' => $category->is_active,
            ]);

            return $category;
        });

        return redirect()
            ->route('super-admin.categories.index')
            ->with('success', 'Kategori "' . $category->name . '" berhasil dibuat.');
    }

    public function edit(ComplaintCategory $category): View
    {
        $category->loadCount('complaints');

        return view('super-admin.categories.edit', compact('category'));
    }

    public function update(SaveComplaintCategoryRequest $request, ComplaintCategory $category): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $category) {
            // NOTE: `slug` is a stable key and is NOT changed on update, so any
            // external references to it remain valid. Only presentation fields
            // (name/description/label/sort) and the active flag are updated.
            $category->update([
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'dinas_name'  => $validated['dinas_name'] ?? null,
                'is_active'   => $validated['is_active'] ?? $category->is_active,
                'sort_order'  => $validated['sort_order'] ?? 0,
            ]);

            $this->audit($request, 'complaint_category.updated', $category, [
                'name'      => $category->name,
                'is_active' => $category->is_active,
            ]);
        });

        return redirect()
            ->route('super-admin.categories.index')
            ->with('success', 'Kategori "' . $category->name . '" berhasil diperbarui.');
    }

    /**
     * Activate / deactivate a category.
     *
     * Deactivation is the mechanism to stop a category from being used for NEW
     * complaints. It NEVER modifies existing complaints (historical safety).
     */
    public function toggleActive(Request $request, ComplaintCategory $category): RedirectResponse
    {
        $newState = ! $category->is_active;

        DB::transaction(function () use ($request, $category, $newState) {
            $category->update(['is_active' => $newState]);

            $this->audit(
                $request,
                $newState ? 'complaint_category.activated' : 'complaint_category.deactivated',
                $category,
                ['name' => $category->name, 'is_active' => $newState]
            );
        });

        return redirect()
            ->route('super-admin.categories.index')
            ->with('success', 'Kategori "' . $category->name . '" berhasil '
                . ($newState ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Hard-delete a category.
     *
     * A category that has ever been used by a complaint MUST NOT be deleted
     * (Prompt 6 §6) — deactivate it instead. The model layer also guards this;
     * here we present a friendly redirect. Only UNUSED categories may be deleted.
     */
    public function destroy(Request $request, ComplaintCategory $category): RedirectResponse
    {
        if ($category->isUsedByComplaints()) {
            return redirect()
                ->route('super-admin.categories.index')
                ->withErrors([
                    'category' => 'Kategori "' . $category->name . '" sudah digunakan pada laporan dan tidak dapat dihapus. Nonaktifkan sebagai gantinya agar tidak dipilih untuk laporan baru.',
                ]);
        }

        $name = $category->name;

        DB::transaction(function () use ($request, $category, $name) {
            // Remove category ↔ dinas mapping first (pivot only; never touches complaints).
            $category->dinasUnits()->detach();

            $this->audit($request, 'complaint_category.deleted', $category, ['name' => $name]);

            $category->delete();
        });

        return redirect()
            ->route('super-admin.categories.index')
            ->with('success', 'Kategori "' . $name . '" berhasil dihapus.');
    }

    /**
     * Generate a unique slug from the category name (derived, server-side).
     * The existing schema enforces UNIQUE(slug).
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 2;

        while (ComplaintCategory::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function audit(Request $request, string $action, ComplaintCategory $category, array $metadata): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'complaint_category',
            'subject_id'   => $category->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
