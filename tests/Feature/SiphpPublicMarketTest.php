<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketItemCategory;
use App\Models\MarketPrice;
use App\Models\MarketStore;
use App\Models\User;
use App\Services\PublicMarketCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * PHASE S — public read-only market surface (`/siphp`; D-S3, D-S6; prd F-020).
 *
 * Acceptance criteria under test:
 *   · the public reads market prices + the retail directory with NO login;
 *   · only the LATEST price per item is shown, together with its date;
 *   · deactivated market/item/store disappear from the surface (history is
 *     never touched by the toggle);
 *   · with no data the page states "Data harga belum tersedia" — no synthetic
 *     numbers are ever rendered;
 *   · the change indicator only compares two REAL consecutive recordings and
 *     prints "—" when there is nothing to compare against;
 *   · the trend chart plots stored rows only (windowed, no interpolation);
 *   · `siphp.enabled=false` takes the whole surface offline (404).
 */
class SiphpPublicMarketTest extends TestCase
{
    use RefreshDatabase;

    // ─── Fixtures (created directly — nothing is seeded or invented) ─────────

    private function makeMarket(array $overrides = []): Market
    {
        static $seq = 0;
        $n = ++$seq;

        return Market::create(array_merge([
            'name'      => 'Pasar Uji ' . $n,
            'slug'      => 'pasar-uji-' . $n,
            'address'   => 'Jl. Uji No. ' . $n,
            'is_active' => true,
        ], $overrides));
    }

    private function makeCategory(array $overrides = []): MarketItemCategory
    {
        static $seq = 0;
        $n = ++$seq;

        return MarketItemCategory::create(array_merge([
            'name'       => 'Kategori Uji ' . $n,
            'slug'       => 'kategori-uji-' . $n,
            'sort_order' => $n,
        ], $overrides));
    }

    private function makeItem(MarketItemCategory $category, array $overrides = []): MarketItem
    {
        static $seq = 0;
        $n = ++$seq;

        return MarketItem::create(array_merge([
            'category_id' => $category->id,
            'name'        => 'Barang Uji ' . $n,
            'unit'        => 'kg',
            'is_active'   => true,
        ], $overrides));
    }

    private function makePrice(Market $market, MarketItem $item, string $date, int $amount, ?User $by = null): MarketPrice
    {
        return MarketPrice::create([
            'market_id'      => $market->id,
            'item_id'        => $item->id,
            'effective_date' => $date,
            'price'          => $amount,
            'recorded_by'    => $by?->id,
        ]);
    }

    private function makeStore(array $overrides = []): MarketStore
    {
        static $seq = 0;
        $n = ++$seq;

        return MarketStore::create(array_merge([
            'name'          => 'Toko Uji ' . $n,
            'type'          => 'Sembako',
            'address'       => 'Jl. Toko No. ' . $n,
            'phone'         => '08120000000',
            'opening_hours' => '07.00 – 21.00 WITA',
            'is_active'     => true,
        ], $overrides));
    }

    // =========================================================================
    // PUBLIC READ — LATEST PRICE + DATE
    // =========================================================================

    public function test_public_page_shows_latest_price_per_item_without_login(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi', 'slug' => 'lakessi']);
        $category = $this->makeCategory();
        $item = $this->makeItem($category, ['name' => 'Beras Premium']);

        $recorder = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->makePrice($market, $item, '2026-10-01', 12000, $recorder);
        $this->makePrice($market, $item, '2026-10-15', 13500, $recorder);

        $response = $this->get(route('siphp.index'));

        $response->assertOk();
        $response->assertSee('Lakessi');
        $response->assertSee('Beras Premium');
        $response->assertSee('Rp 13.500');
        $response->assertDontSee('Rp 12.000');
        $response->assertSee(Carbon::parse('2026-10-15')->translatedFormat('d M Y'));
    }

    public function test_market_detail_page_lists_every_item_with_older_rows_dated(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi', 'slug' => 'lakessi']);
        $category = $this->makeCategory();
        $fresh = $this->makeItem($category, ['name' => 'Beras']);
        $stale = $this->makeItem($category, ['name' => 'Gula']);

        $this->makePrice($market, $fresh, '2026-10-15', 13500);
        $this->makePrice($market, $stale, '2026-10-10', 17000);

        $response = $this->get(route('siphp.market', 'lakessi'));

        $response->assertOk();
        $response->assertSee('Rp 13.500');
        $response->assertSee('Rp 17.000');
        $response->assertSee('per ' . Carbon::parse('2026-10-10')->translatedFormat('d M Y'));
        // The row matching the market's newest date carries no repeated date.
        $response->assertDontSee('per ' . Carbon::parse('2026-10-15')->translatedFormat('d M Y'));

        // Unknown slug is a 404, never a guess.
        $this->get(route('siphp.market', 'slug-tidak-dikenal'))->assertNotFound();
    }

    // =========================================================================
    // HONEST EMPTY STATES — NO SYNTHETIC NUMBERS
    // =========================================================================

    public function test_public_page_never_fabricates_data(): void
    {
        $this->makeMarket(['name' => 'Lakessi']);

        $response = $this->get(route('siphp.index'));

        $response->assertOk();
        $response->assertSee('Data harga belum tersedia.');
        $response->assertSee('Direktori toko belum tersedia.');
        $response->assertDontSee('Rp ');
    }

    // =========================================================================
    // DEACTIVATED RECORDS DISAPPEAR — HISTORY UNTOUCHED
    // =========================================================================

    public function test_deactivated_records_disappear_from_the_public_surface(): void
    {
        $hiddenMarket = $this->makeMarket(['name' => 'Sumpang Minangae', 'is_active' => false]);
        $category = $this->makeCategory();
        $hiddenItem = $this->makeItem($category, ['name' => 'Barang Tersembunyi', 'is_active' => false]);
        $hiddenStore = $this->makeStore(['name' => 'Toko Tersembunyi', 'is_active' => false]);

        $this->makePrice($hiddenMarket, $hiddenItem, '2026-10-15', 9000);
        $this->makePrice($hiddenMarket, $this->makeItem($category, ['name' => 'Barang Pasar Mati']), '2026-10-15', 8000);

        $response = $this->get(route('siphp.index'));

        $response->assertOk();
        $response->assertDontSee('Sumpang Minangae');
        $response->assertDontSee('Barang Tersembunyi');
        $response->assertDontSee('Barang Pasar Mati');
        $response->assertDontSee('Toko Tersembunyi');
        $response->assertDontSee('Rp ');

        // The deactivated market's detail page is a 404, not a leak.
        $this->get(route('siphp.market', $hiddenMarket->slug))->assertNotFound();

        // The history itself is untouched by the toggle.
        $this->assertDatabaseHas('market_prices', ['market_id' => $hiddenMarket->id, 'price' => 9000]);
        $this->assertDatabaseHas('market_stores', ['id' => $hiddenStore->id]);
    }

    public function test_active_store_of_a_deactivated_market_is_hidden(): void
    {
        $inactiveMarket = $this->makeMarket(['name' => 'Pasar Nonaktif', 'is_active' => false]);
        $activeMarket = $this->makeMarket(['name' => 'Pasar Aktif']);

        $this->makeStore(['name' => 'Toko Pasar Nonaktif', 'market_id' => $inactiveMarket->id]);
        $this->makeStore(['name' => 'Toko Pasar Aktif', 'market_id' => $activeMarket->id]);
        $this->makeStore(['name' => 'Toko Tanpa Pasar', 'market_id' => null]);

        $response = $this->get(route('siphp.index'));

        $response->assertOk();
        $response->assertSee('Toko Pasar Aktif');
        $response->assertSee('Toko Tanpa Pasar');
        $response->assertDontSee('Toko Pasar Nonaktif');
        $response->assertDontSee('Pasar Nonaktif');
    }

    public function test_active_store_is_listed_with_its_market(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi']);
        $category = $this->makeCategory();
        $item = $this->makeItem($category, ['name' => 'Beras']);
        $this->makePrice($market, $item, '2026-10-15', 13000);

        $this->makeStore(['name' => 'Toko Sinar Jaya', 'market_id' => $market->id]);

        $response = $this->get(route('siphp.index'));

        $response->assertOk();
        $response->assertSee('Beras');
        $response->assertSee('Toko Sinar Jaya');
        $response->assertSee('Pasar Lakessi');
    }

    // =========================================================================
    // PUBLIC CACHE (audit PROMPT_28 — cached aggregates + version invalidation)
    // =========================================================================

    public function test_public_cache_is_versioned_and_bumped_in_one_write(): void
    {
        $cache = app(PublicMarketCacheService::class);

        $calls = 0;
        $compute = function () use (&$calls) {
            return ++$calls;
        };

        $this->assertSame(1, $cache->remember('probe', $compute));
        $this->assertSame(1, $cache->remember('probe', $compute));
        $this->assertSame(1, $calls, 'second read must be served from the cache');

        $cache->bumpVersion();

        $this->assertSame(2, $cache->remember('probe', $compute));
        $this->assertSame(2, $calls, 'a version bump must invalidate every entry');
    }

    public function test_public_index_is_cached_and_follows_new_recordings(): void
    {
        $cache = app(PublicMarketCacheService::class);

        $market   = $this->makeMarket(['name' => 'Lakessi']);
        $category = $this->makeCategory();
        $item     = $this->makeItem($category, ['name' => 'Beras Baru']);

        // Warm the cache while no price exists yet.
        $this->get(route('siphp.index'))
            ->assertOk()
            ->assertSee('Data harga belum tersedia.');

        $this->assertTrue(
            Cache::has($cache->key('index')),
            'the public index result must be cached'
        );

        // An Eloquent write bumps the version -> the next view is fresh.
        $this->makePrice($market, $item, '2026-10-16', 14000);

        $this->get(route('siphp.index'))
            ->assertOk()
            ->assertSee('Beras Baru');
    }

    // =========================================================================
    // FEATURE SWITCH
    // =========================================================================

    public function test_surface_returns_404_when_disabled(): void
    {
        config(['siphp.enabled' => false]);

        $market = $this->makeMarket();

        $this->get(route('siphp.index'))->assertNotFound();
        $this->get(route('siphp.market', $market->slug))->assertNotFound();
    }

    // =========================================================================
    // HONEST CHANGE INDICATOR — ONLY REAL CONSECUTIVE RECORDINGS
    // =========================================================================

    public function test_public_page_shows_change_against_the_previous_recording(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi', 'slug' => 'lakessi']);
        $category = $this->makeCategory();
        $naik = $this->makeItem($category, ['name' => 'Cabai Rawit']);
        $turun = $this->makeItem($category, ['name' => 'Gula Pasir']);
        $tetap = $this->makeItem($category, ['name' => 'Tepung Terigu']);

        $this->makePrice($market, $naik, '2026-10-14', 50000);
        $this->makePrice($market, $naik, '2026-10-17', 52000);

        $this->makePrice($market, $turun, '2026-10-14', 18000);
        $this->makePrice($market, $turun, '2026-10-17', 17500);

        $this->makePrice($market, $tetap, '2026-10-14', 12000);
        $this->makePrice($market, $tetap, '2026-10-17', 12000);

        $response = $this->get(route('siphp.index'));

        $response->assertOk();
        // ↑ 2.000 / 50.000 = 4,0% · ↓ 500 / 18.000 = 2,8% · equal rows say so.
        $response->assertSee('↑ Rp 2.000 (4,0%)');
        $response->assertSee('↓ Rp 500 (2,8%)');
        $response->assertSee('Tetap');
        $response->assertSee('dibanding pencatatan ' . Carbon::parse('2026-10-14')->translatedFormat('d M Y'));
    }

    public function test_single_recording_shows_no_change_indicator(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi', 'slug' => 'lakessi']);
        $category = $this->makeCategory();
        $item = $this->makeItem($category, ['name' => 'Beras Premium']);

        $this->makePrice($market, $item, '2026-10-17', 14500);

        $response = $this->get(route('siphp.market', 'lakessi'));

        $response->assertOk();
        $response->assertSee('Belum ada pencatatan sebelumnya');
        $response->assertDontSee('↑');
        $response->assertDontSee('↓');
        $response->assertDontSee('Tetap');
    }

    // =========================================================================
    // PUBLIC TREND CHART — RECORDED ROWS ONLY
    // =========================================================================

    public function test_market_detail_renders_trend_chart_from_recorded_rows_only(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi', 'slug' => 'lakessi']);
        $category = $this->makeCategory();
        $item = $this->makeItem($category, ['name' => 'Beras Premium']);

        $this->makePrice($market, $item, '2026-10-15', 12000);
        $this->makePrice($market, $item, '2026-10-16', 12500);
        $this->makePrice($market, $item, '2026-10-17', 13000);

        $response = $this->get(route('siphp.market', 'lakessi'));

        $response->assertOk();
        $response->assertSee('Tren harga');
        $response->assertSee('3 pencatatan');
        $response->assertSee('<polyline', false);
        $response->assertSee('aria-label="Tren harga Beras Premium di Lakessi"', false);
        // The axes carry the real stored window — nothing invented between.
        $response->assertSee('13.000');
        $response->assertSee('15 Okt');
        $response->assertSee('16 Okt');
        $response->assertSee('17 Okt');
    }

    public function test_trend_chart_follows_the_barang_selector_and_ignores_unknown_ids(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi', 'slug' => 'lakessi']);
        $category = $this->makeCategory();
        $first = $this->makeItem($category, ['name' => 'Aaa Beras']);
        $second = $this->makeItem($category, ['name' => 'Bbb Gula']);

        $this->makePrice($market, $first, '2026-10-16', 11000);
        $this->makePrice($market, $first, '2026-10-17', 11500);
        $this->makePrice($market, $second, '2026-10-16', 21000);
        $this->makePrice($market, $second, '2026-10-17', 21500);

        // An explicit selection wins.
        $this->get(route('siphp.market', ['market' => 'lakessi', 'barang' => $second->id]))
            ->assertOk()
            ->assertSee('aria-label="Tren harga Bbb Gula di Lakessi"', false);

        // An unknown ID falls back to the first chartable item — never a 404.
        $this->get(route('siphp.market', ['market' => 'lakessi', 'barang' => 999999]))
            ->assertOk()
            ->assertSee('aria-label="Tren harga Aaa Beras di Lakessi"', false);
    }

    public function test_chart_waits_for_two_recordings_and_hides_items_without_history(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi', 'slug' => 'lakessi']);
        $category = $this->makeCategory();
        $single = $this->makeItem($category, ['name' => 'Barang Sekali']);
        $never = $this->makeItem($category, ['name' => 'Barang Tanpa Harga']);

        $this->makePrice($market, $single, '2026-10-17', 9000);

        $response = $this->get(route('siphp.market', 'lakessi'));

        $response->assertOk();
        $response->assertSee('Grafik tampil setelah minimal dua pencatatan.');
        $response->assertSee('1 pencatatan');
        $response->assertDontSee('<polyline', false);
        // An item that was never priced is not offered in the chart selector.
        $response->assertDontSee('value="' . $never->id . '"', false);
    }
}
