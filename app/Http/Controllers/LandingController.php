<?php

namespace App\Http\Controllers;

use App\Models\ComplaintCategory;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The public front door ("/").
 *
 * The page makes one promise — a report is read by the unit that handles it —
 * and it proves it with the app's own data: the category → Dinas/Unit mapping
 * that already exists in `category_dinas_unit`. Nothing on this page is
 * invented for the design: the category names, the units that receive them,
 * and the Kecamatan/Kelurahan totals all come from the database, so the page
 * cannot drift away from what the service actually does.
 *
 * The figures are reported from the rows that are rendered (active categories
 * only, distinct units counted by normalised name) rather than from a stored
 * total, so they stay true when a Super Admin edits the mapping.
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        $categories = ComplaintCategory::query()
            ->active()
            // Alphabetical: the list is read as an index, so a citizen looking
            // for "sampah" scans names, not the admin's sort order.
            ->reorder('name')
            ->with('dinasUnits:id,name')
            ->get(['id', 'name'])
            ->map(fn (ComplaintCategory $category): array => [
                'name' => (string) $category->name,
                'units' => $category->dinasUnits->pluck('name')->all(),
            ])
            ->values()
            ->all();

        // One unit is stored under several spellings ("Dinas Pekerjaan Umum dan
        // Penataan Ruang (PUPR)" and "DINAS PEKERJAAN UMUM DAN PENATAAN RUANG"),
        // so the count is of distinct units by normalised name, not of rows.
        $unitCount = collect($categories)
            ->flatMap(fn (array $category): array => $category['units'])
            ->map(fn (string $name): string => Str::upper(Str::ascii($name)))
            ->unique()
            ->count();

        return view('public.landing', [
            'categories' => $categories,
            'unitCount' => $unitCount,
            'kecamatanCount' => Kecamatan::query()->count(),
            'kelurahanCount' => Kelurahan::query()->count(),
        ]);
    }
}
