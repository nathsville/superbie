<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketPrice;
use App\Models\MarketStore;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Public, read-only market information (PHASE S / SIPHP — owner decision D-S3).
 *
 * NO login required and NO write path: every row rendered here was entered
 * manually by Super Admin inside SuperBie (D-S2). There is no scraping, no
 * iframe and no runtime sync with the legacy siphp-parepare.com site (D-S1).
 *
 * Public rules are strict (prd F-020):
 *   · show the LATEST price per item and its date — never fabricate a value;
 *   · the change indicator compares against the PREVIOUS RECORDED DATE only —
 *     with a single recording the surface prints "—" instead of a delta;
 *   · the trend chart plots recorded rows only (last window), never smoothing
 *     or forecasting;
 *   · an absent price is stated as "Data harga belum tersedia";
 *   · deactivated markets/items/stores disappear from this surface without
 *     touching their recorded history.
 */
class PublicMarketController extends Controller
{
    /**
     * How many of the most recent recordings feed the public trend chart.
     * Bounded so a long-running series can never bloat the page.
     */
    private const TREND_WINDOW = 30;

    /**
     * `/siphp` — all active markets with their latest prices + retail directory.
     */
    public function index(): View
    {
        abort_unless((bool) config('siphp.enabled'), 404);

        $markets = Market::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $marketIds = $markets->pluck('id');

        $pricesByMarket = $this->latestPriceRows($marketIds)
            ->groupBy('market_id');

        // Keyed "market_id|item_id" → the row before the latest one. A missing
        // key means the item has a single recording; the view then prints "—".
        $previousPrices = $this->previousPriceRows($marketIds);

        // Audit PROMPT_28 (Rendah): a store whose market is DEACTIVATED must
        // not surface here — consistent with the active-only market list above.
        // D-S4a: a store may be listed without a market; those stay visible.
        $stores = MarketStore::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('market_id')
                    ->orWhereHas('market', fn ($market) => $market->where('is_active', true));
            })
            ->with('market')
            ->orderBy('name')
            ->get();

        return view('public.siphp', compact('markets', 'pricesByMarket', 'previousPrices', 'stores'));
    }

    /**
     * `/siphp/pasar/{market}` — one ACTIVE market resolved by slug: the full
     * latest-price list (grouped by category), the trend chart and the stores.
     */
    public function show(Request $request, string $market): View
    {
        abort_unless((bool) config('siphp.enabled'), 404);

        $market = Market::query()
            ->where('slug', $market)
            ->where('is_active', true)
            ->firstOrFail();

        $prices = $this->latestPriceRows(collect([$market->id]));
        $previousPrices = $this->previousPriceRows(collect([$market->id]));

        $stores = MarketStore::query()
            ->where('is_active', true)
            ->where('market_id', $market->id)
            ->orderBy('name')
            ->get();

        $trend = $this->trendData($request, $market);

        return view('public.siphp-market', compact('market', 'prices', 'previousPrices', 'stores', 'trend'));
    }

    /**
     * Latest price row per (market × item) in one SQL round trip.
     *
     * The MAX is taken per item, not per market: a plain "latest date for the
     * market" would hide items that were last priced on an earlier day. Rows
     * of deactivated items are dropped (hidden from the public list without
     * erasing the history), and rows are ordered by category sort order then
     * item name so the page reads in the staff's own hierarchy.
     */
    private function latestPriceRows(Collection $marketIds): Collection
    {
        if ($marketIds->isEmpty()) {
            return collect();
        }

        $latest = DB::table('market_prices')
            ->selectRaw('market_id, item_id, MAX(effective_date) as max_date')
            ->whereIn('market_id', $marketIds)
            ->groupBy('market_id', 'item_id');

        return MarketPrice::query()
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('market_prices.market_id', '=', 'latest.market_id')
                    ->on('market_prices.item_id', '=', 'latest.item_id')
                    ->on('market_prices.effective_date', '=', 'latest.max_date');
            })
            ->with(['item.category'])
            ->select(['market_prices.*'])
            ->get()
            ->filter(fn (MarketPrice $price) => $price->item !== null && $price->item->is_active)
            ->sortBy(fn (MarketPrice $price) => sprintf(
                '%06d|%s',
                (int) ($price->item->category?->sort_order ?? 999999),
                $price->item->name
            ))
            ->values();
    }

    /**
     * The recorded row BEFORE the latest one, per (market × item) — the only
     * honest comparison base for the public change indicator. One SQL round
     * trip, built on the same MAX-per-item idea as {@see latestPriceRows()};
     * an item with a single recording simply has no key here.
     */
    private function previousPriceRows(Collection $marketIds): Collection
    {
        if ($marketIds->isEmpty()) {
            return collect();
        }

        $latest = DB::table('market_prices')
            ->selectRaw('market_id, item_id, MAX(effective_date) as max_date')
            ->whereIn('market_id', $marketIds)
            ->groupBy('market_id', 'item_id');

        $previous = DB::table('market_prices')
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('market_prices.market_id', '=', 'latest.market_id')
                    ->on('market_prices.item_id', '=', 'latest.item_id')
                    ->whereColumn('market_prices.effective_date', '<', 'latest.max_date');
            })
            ->selectRaw('market_prices.market_id, market_prices.item_id, MAX(market_prices.effective_date) as previous_date')
            ->groupBy('market_prices.market_id', 'market_prices.item_id');

        return MarketPrice::query()
            ->joinSub($previous, 'previous', function ($join) {
                $join->on('market_prices.market_id', '=', 'previous.market_id')
                    ->on('market_prices.item_id', '=', 'previous.item_id')
                    ->on('market_prices.effective_date', '=', 'previous.previous_date');
            })
            ->select(['market_prices.*'])
            ->get()
            ->keyBy(fn (MarketPrice $price) => $price->market_id . '|' . $price->item_id);
    }

    /**
     * Data for the public trend chart on one market page.
     *
     * `barang` carries the item ID (public items have no slug yet): an unknown
     * or deactivated ID silently falls back to the first chartable item — this
     * is a read-only convenience selector, never a data leak. Items that were
     * never priced in this market are not offered at all.
     *
     * @return array{
     *     items: Collection<int, MarketItem>,
     *     selected: ?MarketItem,
     *     points: list<array{value: float, label: string}>,
     *     total: int,
     *     earliest: ?\Illuminate\Support\Carbon,
     *     latest: ?\Illuminate\Support\Carbon
     * }
     */
    private function trendData(Request $request, Market $market): array
    {
        $items = MarketItem::query()
            ->where('is_active', true)
            ->with('category')
            ->whereExists(function ($query) use ($market) {
                $query->selectRaw('1')
                    ->from('market_prices')
                    ->whereColumn('market_prices.item_id', 'market_items.id')
                    ->where('market_prices.market_id', $market->id);
            })
            ->get()
            ->sortBy(fn (MarketItem $item) => sprintf(
                '%06d|%s',
                (int) ($item->category?->sort_order ?? 999999),
                $item->name
            ))
            ->values();

        $selected = null;
        $requested = $request->integer('barang');
        if ($requested > 0) {
            $selected = $items->firstWhere('id', $requested);
        }
        $selected ??= $items->first();

        if ($selected === null) {
            return [
                'items'    => $items,
                'selected' => null,
                'points'   => [],
                'total'    => 0,
                'earliest' => null,
                'latest'   => null,
            ];
        }

        // Newest first for the LIMIT (the window), reversed for the chart.
        $prices = MarketPrice::query()
            ->where('market_id', $market->id)
            ->where('item_id', $selected->id)
            ->orderByDesc('effective_date')
            ->limit(self::TREND_WINDOW)
            ->get();

        return [
            'items'    => $items,
            'selected' => $selected,
            'points'   => $prices
                ->reverse()
                ->values()
                ->map(fn (MarketPrice $price) => [
                    'value' => (float) $price->price,
                    'label' => $price->effective_date->translatedFormat('d M'),
                ])
                ->all(),
            'total'    => MarketPrice::query()
                ->where('market_id', $market->id)
                ->where('item_id', $selected->id)
                ->count(),
            'earliest' => $prices->last()?->effective_date,
            'latest'   => $prices->first()?->effective_date,
        ];
    }
}
