<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveMarketPricesRequest;
use App\Models\AuditLog;
use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketItemCategory;
use App\Models\MarketPrice;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin — Manual market price entry (PHASE S / SIPHP module).
 *
 * One submission covers ALL active items of one market for one date (D-S6:
 * unique market × item × date). Re-submitting the same date UPDATES the row
 * (idempotent correction); an empty price input leaves the row unchanged — a
 * price is never coerced to zero. Only Super Admin may write (route middleware
 * + request `authorize()`); the public surface is read-only.
 */
class SuperAdminMarketPriceController extends Controller
{
    public function index(): View
    {
        $markets = Market::query()
            ->withCount('prices')
            ->withMax('prices as last_price_date', 'effective_date')
            ->orderBy('name')
            ->get();

        $activeItemsCount = MarketItem::where('is_active', true)->count();

        $recent = MarketPrice::query()
            ->with(['market', 'item'])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('super-admin.siphp.prices.index', compact('markets', 'activeItemsCount', 'recent'));
    }

    /**
     * Bulk entry page for one market + one date (defaults to today, WITA).
     */
    public function catat(Request $request, Market $market): View
    {
        $date = $this->parseDate(QueryInput::trimmed($request->get('date')));

        $items = MarketItem::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = MarketItemCategory::orderBy('sort_order')->orderBy('name')->get();

        $existing = MarketPrice::query()
            ->where('market_id', $market->id)
            ->whereDate('effective_date', $date)
            ->get()
            ->keyBy('item_id');

        return view('super-admin.siphp.prices.catat', compact('market', 'date', 'items', 'categories', 'existing'));
    }

    public function store(SaveMarketPricesRequest $request, Market $market): RedirectResponse
    {
        $validated = $request->validated();
        $date = $validated['effective_date'];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $market, $date, $validated, &$created, &$updated, &$skipped) {
            $activeItemIds = MarketItem::where('is_active', true)->pluck('id')->all();

            foreach ($validated['rows'] as $row) {
                $itemId = (int) $row['item_id'];
                $price = $row['price'] ?? null;

                // Inactive items and empty inputs are skipped — nothing is
                // written and no default value is invented.
                if (! in_array($itemId, $activeItemIds, true) || $price === null || $price === '') {
                    $skipped++;
                    continue;
                }

                $model = MarketPrice::updateOrCreate(
                    [
                        'market_id'      => $market->id,
                        'item_id'        => $itemId,
                        'effective_date' => $date,
                    ],
                    [
                        'price'       => $price,
                        'recorded_by' => $request->user()->id,
                    ],
                );

                $model->wasRecentlyCreated ? $created++ : $updated++;
            }

            $this->audit($request, 'market_prices.recorded', [
                'market'  => $market->name,
                'date'    => $date,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ], $market->id);
        });

        $message = ($created + $updated) > 0
            ? sprintf('Harga tersimpan untuk %s (%s): %d baru, %d diperbarui.', $market->name, $date, $created, $updated)
            : 'Tidak ada harga yang diisi — tidak ada perubahan.';

        return redirect()
            ->route('super-admin.market-prices.catat', ['market' => $market->id, 'date' => $date])
            ->with('success', $message);
    }

    /**
     * Delete one price row (same-day correction aid). Every deletion is
     * audited; the price history of other rows is untouched.
     */
    public function destroy(Request $request, MarketPrice $price): RedirectResponse
    {
        $marketId = $price->market_id;
        $date = $price->effective_date?->toDateString();

        DB::transaction(function () use ($request, $price) {
            $this->audit($request, 'market_prices.deleted', [
                'market' => $price->market?->name,
                'item'   => $price->item?->name,
                'date'   => $price->effective_date?->toDateString(),
                'price'  => (string) $price->price,
            ], $price->id);

            $price->delete();
        });

        return redirect()
            ->route('super-admin.market-prices.catat', array_filter(['market' => $marketId, 'date' => $date]))
            ->with('success', 'Satu baris harga berhasil dihapus.');
    }

    /**
     * Defensive date parsing: malformed input falls back to today instead of
     * raising a 500. Output is always `Y-m-d` (WITA calendar day).
     */
    private function parseDate(?string $raw): string
    {
        if ($raw !== null && Carbon::hasFormat($raw, 'Y-m-d')) {
            return $raw;
        }

        return now()->toDateString();
    }

    private function audit(Request $request, string $action, array $metadata, ?int $subjectId = null): void
    {
        AuditLog::create([
            'actor_id'     => $request->user()->id,
            'action'       => $action,
            'subject_type' => 'market_price',
            'subject_id'   => $subjectId,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => $metadata,
        ]);
    }
}
