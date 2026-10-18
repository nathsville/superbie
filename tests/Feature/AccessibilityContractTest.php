<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketItemCategory;
use App\Models\MarketPrice;
use App\Models\User;
use App\Services\Weather\WeatherSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PROMPT 28 — accessibility contract (remediation package).
 *
 * Locks the server-rendered half of the audit fixes so they cannot regress:
 *   · S-06 — a skip link on every sheet with a `#main-content` target
 *     (landing, cuaca, siphp, detail pasar, dashboard shell);
 *   · T-04 — profile actions never pair white text with `accent-500`
 *     (1.62:1); the shared fill is `accent-600` (white on #996300 ≈ 5.1:1,
 *     the pair design.md §2 measures);
 *   · S-07 — 13px zebra-row labels use `ink-soft` (AA on `surface-sunken`),
 *     never `ink-faint`;
 *   · S-10 — every Windy overlay button exposes `aria-pressed`;
 *   · S-11 — the toast close button is a 44px target with a hidden icon.
 *
 * The viewport-measured fixes (S-08 footer clamp floor, S-09 lg tab width)
 * are browser behaviour — verified by hand with a headless Chrome, not
 * asserted here (no JS runner exists; same boundary as
 * DashboardShellSidebarTest).
 */
class AccessibilityContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // ── S-06 · skip link on every sheet with a `#main-content` target ────────

    public function test_public_sheets_render_the_skip_link(): void
    {
        foreach ([route('home'), route('weather.index'), route('siphp.index')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Lewati ke konten utama')
                ->assertSee('href="#main-content"', false);
        }
    }

    public function test_market_detail_renders_the_skip_link(): void
    {
        $this->makeMarket(['slug' => 'lakessi']);

        $this->get(route('siphp.market', 'lakessi'))
            ->assertOk()
            ->assertSee('Lewati ke konten utama');
    }

    public function test_dashboard_shell_renders_the_skip_link(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertSee('Lewati ke konten utama')
            ->assertSee('id="main-content"', false);
    }

    // ── T-04 · profile actions use the measured accent fill ──────────────────

    public function test_profile_actions_never_put_white_text_on_accent_500(): void
    {
        $citizen = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $html = $this->actingAs($citizen)
            ->get(route('citizen.profile.edit'))
            ->assertOk()
            ->getContent();

        // The 1.62:1 violation is gone; the measured 5.1:1 pair renders on
        // both offenders — the card "Edit" button and the camera badge.
        $this->assertStringNotContainsString('text-white bg-accent-500', $html);
        $this->assertStringContainsString('bg-accent-600 hover:bg-accent-700', $html);
    }

    // ── S-07 · zebra-row labels clear AA on the sunken fill ──────────────────

    public function test_zebra_row_labels_use_ink_soft(): void
    {
        $market = $this->makeMarket(['slug' => 'lakessi']);
        $category = $this->makeCategory();
        $fresh = $this->makeItem($category, ['name' => 'Beras Premium']);
        $stale = $this->makeItem($category, ['name' => 'Gula Pasir']);

        $this->makePrice($market, $fresh, '2026-10-15', 13500);
        $this->makePrice($market, $stale, '2026-10-10', 17000);

        $index = $this->get(route('siphp.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('font-normal text-ink-faint', $index);
        $this->assertStringContainsString('text-[13px] font-normal text-ink-soft">kg</span>', $index);

        // The trend header ("per kg" on the white surface) deliberately keeps
        // `ink-faint` — the S-07 fix scopes to the sunken zebra rows, whose
        // unit chip and date line are the two offenders.
        $detail = $this->get(route('siphp.market', 'lakessi'))->assertOk()->getContent();
        $this->assertStringNotContainsString('block text-ink-faint">per ', $detail);
        $this->assertStringContainsString('block text-ink-soft">per ', $detail);
        $this->assertStringContainsString('text-[13px] font-normal text-ink-soft">kg</span>', $detail);
    }

    // ── S-10 · the Windy overlay rail exposes its pressed state ──────────────

    public function test_weather_overlay_buttons_expose_aria_pressed(): void
    {
        // The map panel renders with stored forecast data (same setup as
        // WindyWeatherTest::test_public_page_offers_windy_overlay_layers).
        $this->fakeBmkg([
            $this->slot('2026-10-15 09:00:00', 30.0, 70, 10.0, 'Selatan', 'Berawan', 0.0),
        ]);
        app(WeatherSyncService::class)->sync();

        $html = $this->get(route('weather.index'))->assertOk()->getContent();

        $this->assertSame(
            5,
            substr_count($html, ':aria-pressed="overlay ==='),
            'every Windy overlay button must expose aria-pressed'
        );
    }

    // ── S-11 · the toast close control is a real target ──────────────────────

    public function test_toast_close_button_is_a_labelled_44px_target(): void
    {
        $html = $this->withSession(['success' => 'Tersimpan.'])
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="Tutup notifikasi"', $html);
        $this->assertStringContainsString('-my-3.5 -mr-3.5', $html);
        $this->assertStringContainsString(
            '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">',
            $html
        );
    }

    // ── Fixtures (mirror SiphpPublicMarketTest / WindyWeatherTest) ───────────

    /** Build a fake BMKG response with the known nested shape. */
    private function fakeBmkg(array $slots): void
    {
        Http::fake([
            'api.bmkg.go.id/*' => Http::response([
                'data' => [
                    ['lokasi' => [
                        'adm1' => '73', 'adm2' => '73.72', 'adm3' => '73.72.02', 'adm4' => '73.72.02.1001',
                        'provinsi' => 'Sulawesi Selatan', 'kotkab' => 'Kota Parepare',
                        'kecamatan' => 'Ujung', 'desa' => 'Labukkang',
                        'lon' => 119.6249880584, 'lat' => -4.017522847, 'timezone' => '+0800', 'type' => 'adm4',
                    ], 'cuaca' => [$slots]],
                ],
            ], 200),
            'maritim.bmkg.go.id/*' => Http::response(['message' => 'not found'], 404),
        ]);
    }

    private function slot(string $at, float $t, int $hu, float $ws, string $wd, string $desc, float $tp = 0.0, int $tcc = 10): array
    {
        return [
            'local_datetime' => $at,
            't' => $t,
            'hu' => $hu,
            'ws' => $ws,
            'wd' => $wd,
            'wd_deg' => 90,
            'wd_to' => 'Barat',
            'tcc' => $tcc,
            'tp' => $tp,
            'weather' => 1,
            'weather_desc' => $desc,
            'vs_text' => null,
            'analysis_date' => '2026-10-12T12:00:00',
        ];
    }


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
}
