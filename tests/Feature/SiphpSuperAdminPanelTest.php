<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketItemCategory;
use App\Models\MarketStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PHASE S — Super Admin market panel (D-S2/D-S5/D-S7; prd F-020).
 *
 * Acceptance criteria under test:
 *   · ONLY Super Admin can write (server-side middleware + request authorize);
 *     Admin/Operator/Masyarakat get 403 and guests are redirected to login;
 *   · master data + price rows + stores are entered manually here;
 *   · prices are unique per (market × item × date): re-entry UPDATES the row;
 *   · historical integrity: used markets/items/categories cannot be deleted;
 *   · store photos go to the public disk with a generated filename; replacing
 *     or deleting a store removes the previous file;
 *   · every write is audited.
 */
class SiphpSuperAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $operator;
    private User $citizen;
    private MarketItemCategory $category;
    private MarketItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->operator   = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $this->citizen    = User::factory()->create(['role' => 'masyarakat', 'is_active' => true]);

        $this->category = MarketItemCategory::create([
            'name'       => 'Barang Pokok',
            'slug'       => 'barang-pokok',
            'sort_order' => 1,
        ]);

        $this->item = MarketItem::create([
            'category_id' => $this->category->id,
            'name'        => 'Beras',
            'unit'        => 'kg',
            'is_active'   => true,
        ]);
    }

    private function makeMarket(array $overrides = []): Market
    {
        static $seq = 0;
        $n = ++$seq;

        return Market::create(array_merge([
            'name'      => 'Pasar Uji ' . $n,
            'slug'      => 'pasar-uji-' . $n,
            'is_active' => true,
        ], $overrides));
    }

    private function pricePayload(MarketItem $item, int|string $price, string $date = '2026-10-15'): array
    {
        return [
            'effective_date' => $date,
            'rows'           => [
                ['item_id' => $item->id, 'price' => $price],
            ],
        ];
    }

    // =========================================================================
    // AUTHORIZATION — SUPER ADMIN ONLY (server-side)
    // =========================================================================

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('super-admin.markets.index'))->assertRedirect(route('login'));
        $this->get(route('super-admin.market-prices.index'))->assertRedirect(route('login'));
    }

    public function test_only_super_admin_can_access_the_panel(): void
    {
        $market = $this->makeMarket();

        $this->actingAs($this->superAdmin)->get(route('super-admin.markets.index'))->assertOk();

        foreach ([$this->admin, $this->operator, $this->citizen] as $user) {
            $this->actingAs($user)->get(route('super-admin.markets.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.market-items.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.market-prices.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.market-stores.index'))->assertForbidden();
            $this->actingAs($user)->post(route('super-admin.markets.store'), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->post(route('super-admin.market-prices.store', $market), [])->assertForbidden();
            $this->actingAs($user)->post(route('super-admin.market-stores.store'), ['name' => 'X'])->assertForbidden();
        }
    }

    public function test_panel_pages_render_for_super_admin(): void
    {
        $market = $this->makeMarket(['name' => 'Lakessi']);

        $this->actingAs($this->superAdmin)->get(route('super-admin.markets.index'))->assertOk()->assertSee('Lakessi');
        $this->actingAs($this->superAdmin)->get(route('super-admin.markets.create'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.markets.edit', $market))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.market-categories.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.market-items.index'))->assertOk()->assertSee('Beras');
        $this->actingAs($this->superAdmin)->get(route('super-admin.market-items.create'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.market-prices.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.market-prices.catat', $market))->assertOk()->assertSee('Beras');
        $this->actingAs($this->superAdmin)->get(route('super-admin.market-stores.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('super-admin.market-stores.create'))->assertOk();
    }

    // =========================================================================
    // MASTER DATA — MARKET
    // =========================================================================

    public function test_market_crud_generates_slug_and_is_audited(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.markets.store'), [
                'name'      => 'Pasar Lakessi',
                'address'   => 'Jl. Lakessi',
                'is_active' => '1',
            ])
            ->assertRedirect(route('super-admin.markets.index'));

        $market = Market::where('name', 'Pasar Lakessi')->firstOrFail();
        $this->assertSame('pasar-lakessi', $market->slug);
        $this->assertTrue($market->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id'     => $this->superAdmin->id,
            'action'       => 'market.created',
            'subject_type' => 'market',
            'subject_id'   => $market->id,
        ]);

        // A second market with the same name gets a suffixed, unique slug.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.markets.store'), ['name' => 'Pasar Lakessi'])
            ->assertRedirect(route('super-admin.markets.index'));

        $this->assertDatabaseHas('markets', ['slug' => 'pasar-lakessi-2']);

        // Update: the name changes, the public slug stays stable.
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.markets.update', $market), [
                'name'      => 'Pasar Lakessi Baru',
                'address'   => 'Jl. Baru',
                'is_active' => '1',
            ])
            ->assertRedirect(route('super-admin.markets.index'));

        $market->refresh();
        $this->assertSame('Pasar Lakessi Baru', $market->name);
        $this->assertSame('pasar-lakessi', $market->slug);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'market.updated',
            'subject_type' => 'market',
            'subject_id'   => $market->id,
        ]);
    }

    public function test_market_activation_toggle_is_audited(): void
    {
        $market = $this->makeMarket();

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.markets.toggle', $market))
            ->assertRedirect(route('super-admin.markets.index'));

        $this->assertFalse($market->refresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'market.deactivated',
            'subject_type' => 'market',
            'subject_id'   => $market->id,
        ]);

        $this->actingAs($this->superAdmin)->patch(route('super-admin.markets.toggle', $market));

        $this->assertTrue($market->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'market.activated', 'subject_id' => $market->id]);
    }

    public function test_market_with_price_history_cannot_be_deleted(): void
    {
        $used = $this->makeMarket();
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $used), $this->pricePayload($this->item, 13500));

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.markets.destroy', $used))
            ->assertRedirect(route('super-admin.markets.index'))
            ->assertSessionHasErrors('market');

        $this->assertDatabaseHas('markets', ['id' => $used->id]);

        // An unused market may be deleted (and the deletion is audited).
        $unused = $this->makeMarket();

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.markets.destroy', $unused))
            ->assertRedirect(route('super-admin.markets.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('markets', ['id' => $unused->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'market.deleted', 'subject_id' => $unused->id]);
    }

    // =========================================================================
    // MANUAL PRICE ENTRY — ONE ROW PER MARKET × ITEM × DATE
    // =========================================================================

    public function test_price_entry_upserts_one_row_per_slot_and_skips_blank_or_inactive_rows(): void
    {
        $market = $this->makeMarket();
        $inactiveItem = MarketItem::create([
            'category_id' => $this->category->id,
            'name'        => 'Barang Nonaktif',
            'is_active'   => false,
        ]);

        $payload = [
            'effective_date' => '2026-10-15',
            'rows' => [
                ['item_id' => $this->item->id, 'price' => 13500],
                ['item_id' => $inactiveItem->id, 'price' => 5000],
                ['item_id' => $this->item->id, 'price' => ''],
            ],
        ];

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), $payload)
            ->assertRedirect(route('super-admin.market-prices.catat', ['market' => $market->id, 'date' => '2026-10-15']));

        $this->assertDatabaseHas('market_prices', [
            'market_id'      => $market->id,
            'item_id'        => $this->item->id,
            'effective_date' => '2026-10-15',
            'price'          => 13500,
            'recorded_by'    => $this->superAdmin->id,
        ]);

        // Inactive items are never priced; blank inputs are never stored as 0.
        $this->assertDatabaseMissing('market_prices', ['item_id' => $inactiveItem->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'market_prices.recorded',
            'subject_type' => 'market_price',
        ]);

        // Re-submitting the same date UPDATES the row; the slot count stays 1.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), $this->pricePayload($this->item, 14000))
            ->assertRedirect();

        $this->assertDatabaseCount('market_prices', 1);
        $this->assertDatabaseHas('market_prices', ['price' => 14000]);

        // A blank-only submission on a NEW date writes nothing at all.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), $this->pricePayload($this->item, '', '2026-10-16'))
            ->assertRedirect();

        $this->assertDatabaseMissing('market_prices', ['effective_date' => '2026-10-16']);
    }

    public function test_price_entry_validates_amount_and_date(): void
    {
        $market = $this->makeMarket();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), $this->pricePayload($this->item, -5))
            ->assertSessionHasErrors('rows.0.price');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), [
                'rows' => [['item_id' => $this->item->id, 'price' => 1000]],
            ])
            ->assertSessionHasErrors('effective_date');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), $this->pricePayload($this->item, 'seribu'))
            ->assertSessionHasErrors('rows.0.price');
    }

    public function test_price_row_deletion_is_audited(): void
    {
        $market = $this->makeMarket();
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), $this->pricePayload($this->item, 13500));

        $price = \App\Models\MarketPrice::firstOrFail();

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.market-prices.destroy', $price))
            ->assertRedirect(route('super-admin.market-prices.catat', ['market' => $market->id, 'date' => '2026-10-15']));

        $this->assertDatabaseMissing('market_prices', ['id' => $price->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'market_prices.deleted', 'subject_type' => 'market_price']);
    }

    // =========================================================================
    // RETAIL STORES + PUBLIC PHOTO (D-S7)
    // =========================================================================

    public function test_store_photo_lifecycle_on_the_public_disk(): void
    {
        Storage::fake('public');

        $market = $this->makeMarket();

        // Create with photo.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-stores.store'), [
                'market_id' => $market->id,
                'name'      => 'Toko Sinar Jaya',
                'type'      => 'Sembako',
                'photo'     => UploadedFile::fake()->image('toko.jpg', 800, 600),
                'is_active' => '1',
            ])
            ->assertRedirect(route('super-admin.market-stores.index'));

        $store = MarketStore::where('name', 'Toko Sinar Jaya')->firstOrFail();
        $this->assertStringStartsWith('siphp/stores/', $store->photo_path);
        Storage::disk('public')->assertExists($store->photo_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'market_store.created', 'subject_type' => 'market_store']);

        // Replace the photo: the old file is removed, the new one exists.
        $oldPath = $store->photo_path;

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.market-stores.update', $store), [
                'name'  => 'Toko Sinar Jaya',
                'photo' => UploadedFile::fake()->image('baru.png', 640, 480),
            ])
            ->assertRedirect(route('super-admin.market-stores.index'));

        $store->refresh();
        $this->assertNotSame($oldPath, $store->photo_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($store->photo_path);

        // Deleting the store removes its file too.
        $currentPath = $store->photo_path;

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.market-stores.destroy', $store))
            ->assertRedirect(route('super-admin.market-stores.index'));

        $this->assertDatabaseMissing('market_stores', ['id' => $store->id]);
        Storage::disk('public')->assertMissing($currentPath);
    }

    public function test_store_photo_rejects_non_image_and_oversized_files(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-stores.store'), [
                'name'  => 'Toko Dokumen',
                'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('market_stores', ['name' => 'Toko Dokumen']);

        $maxKb = (int) config('siphp.store_photo.max_kilobytes');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-stores.store'), [
                'name'  => 'Toko Foto Besar',
                'photo' => UploadedFile::fake()->image('besar.jpg')->size($maxKb + 1),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('market_stores', ['name' => 'Toko Foto Besar']);
    }

    // =========================================================================
    // HISTORICAL-INTEGRITY GUARDS — ITEMS & CATEGORIES
    // =========================================================================

    public function test_item_with_price_history_cannot_be_deleted(): void
    {
        $market = $this->makeMarket();
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.market-prices.store', $market), $this->pricePayload($this->item, 13500));

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.market-items.destroy', $this->item))
            ->assertSessionHasErrors('item');

        $this->assertDatabaseHas('market_items', ['id' => $this->item->id]);

        $unused = MarketItem::create([
            'category_id' => $this->category->id,
            'name'        => 'Barang Baru',
            'is_active'   => true,
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.market-items.destroy', $unused))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('market_items', ['id' => $unused->id]);
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.market-categories.destroy', $this->category))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('market_item_categories', ['id' => $this->category->id]);

        $empty = MarketItemCategory::create([
            'name'       => 'Kategori Kosong',
            'slug'       => 'kategori-kosong',
            'sort_order' => 9,
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.market-categories.destroy', $empty))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('market_item_categories', ['id' => $empty->id]);
    }
}
