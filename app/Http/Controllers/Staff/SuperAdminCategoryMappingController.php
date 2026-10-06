<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SyncCategoryMappingRequest;
use App\Models\AuditLog;
use App\Models\ComplaintCategory;
use App\Models\DinasUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — Category ↔ Dinas/Unit mapping management (Prompt 5C / 6 Bagian 2).
 *
 * MANY-TO-MANY: one category → many Dinas/Unit, one Dinas/Unit → many categories.
 * Only Super Admin may manage mapping (route middleware + Form Request authorize()).
 *
 * Historical safety: changing/removing mappings NEVER touches complaints —
 * the complaint's ACTUAL destination (`complaints.dinas_unit_id`) is stored
 * separately and is never derived from this pivot at read time.
 */
class SuperAdminCategoryMappingController extends Controller
{
    public function edit(ComplaintCategory $category): View
    {
        $category->load('dinasUnits');

        // Inactive Dinas/Unit remain listed so existing mappings are preserved
        // and can be inspected (Prompt 6 §11/§22). Active units first.
        $allDinasUnits = DinasUnit::orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $mappedIds = $category->dinasUnits->pluck('id')->all();

        return view('super-admin.categories.mapping', compact('category', 'allDinasUnits', 'mappedIds'));
    }

    public function update(SyncCategoryMappingRequest $request, ComplaintCategory $category): RedirectResponse
    {
        $ids = $request->normalizedIds();

        $oldIds = $category->dinasUnits()->pluck('dinas_units.id')->map(fn ($id) => (int) $id)->all();

        $added   = array_values(array_diff($ids, $oldIds));
        $removed = array_values(array_diff($oldIds, $ids));

        DB::transaction(function () use ($request, $category, $ids, $oldIds, $added, $removed) {
            // sync() removes pivot rows not present and adds new ones. It NEVER
            // touches complaints (actual destination is stored on the complaint).
            $category->dinasUnits()->sync($ids);

            $this->audit($request, 'category_mapping.updated', $category, [
                'category_name' => $category->name,
                'old_dinas_ids' => $oldIds,
                'new_dinas_ids' => $ids,
            ]);

            // Granular audit entries (Prompt 6 §25) — added / removed units.
            foreach ($added as $dinasId) {
                $this->audit($request, 'category_mapping.dinas_added', $category, [
                    'category_name' => $category->name,
                    'dinas_unit_id' => $dinasId,
                    'dinas_unit_name' => DinasUnit::whereKey($dinasId)->value('name'),
                ]);
            }

            foreach ($removed as $dinasId) {
                $this->audit($request, 'category_mapping.dinas_removed', $category, [
                    'category_name' => $category->name,
                    'dinas_unit_id' => $dinasId,
                    'dinas_unit_name' => DinasUnit::whereKey($dinasId)->value('name'),
                ]);
            }
        });

        return redirect()
            ->route('super-admin.categories.mapping.edit', $category)
            ->with('success', 'Mapping Dinas/Unit untuk kategori "' . $category->name . '" berhasil diperbarui.');
    }

    private function audit(SyncCategoryMappingRequest $request, string $action, ComplaintCategory $category, array $metadata): void
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
