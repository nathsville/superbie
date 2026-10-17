<?php

namespace Database\Seeders;

use App\Models\Market;
use App\Models\MarketItemCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PHASE S — master data SIPHP (pasar + kategori barang).
 *
 * SUMBER (fakta teramati, bukan asumsi): `ERD_SIPHP_PAREPARE.md` —
 *   - 5 pasar dari dropdown form pengaduan situs lama:
 *     Lakessi, Senggol, Labukkang, Sumpang Minangae, Wekke'e.
 *   - 3 kategori dari route publik /info-harga/{kategori}/... :
 *     barang-pokok, barang-subsidi, barang-penting.
 *
 * PENTING:
 *   - TIDAK ada barang dan TIDAK ada harga yang di-seed — keduanya diinput
 *     petugas melalui panel (D-S2). Jangan menambah data yang tidak teramati.
 *   - STANDALONE: tidak dipanggil dari DatabaseSeeder. Jalankan eksplisit:
 *       php artisan db:seed --class=MarketMasterSeeder
 *   - IDEMPOTENT: `firstOrCreate` berdasarkan slug; aman dijalankan berulang.
 *   - TIDAK menghapus dan TIDAK mengubah baris yang sudah ada.
 */
class MarketMasterSeeder extends Seeder
{
    /** @var list<array{name: string}> */
    private const MARKETS = [
        ['name' => 'Lakessi'],
        ['name' => 'Senggol'],
        ['name' => 'Labukkang'],
        ['name' => 'Sumpang Minangae'],
        ['name' => "Wekke'e"],
    ];

    /** @var list<array{name: string, slug: string, sort_order: int}> */
    private const CATEGORIES = [
        ['name' => 'Barang Pokok',   'slug' => 'barang-pokok',   'sort_order' => 1],
        ['name' => 'Barang Subsidi', 'slug' => 'barang-subsidi', 'sort_order' => 2],
        ['name' => 'Barang Penting', 'slug' => 'barang-penting', 'sort_order' => 3],
    ];

    public function run(): void
    {
        $createdMarkets = 0;
        $existingMarkets = 0;
        $createdCategories = 0;
        $existingCategories = 0;

        DB::transaction(function () use (
            &$createdMarkets,
            &$existingMarkets,
            &$createdCategories,
            &$existingCategories
        ) {
            foreach (self::MARKETS as $market) {
                $model = Market::firstOrCreate(
                    ['slug' => Str::slug($market['name'])],
                    ['name' => $market['name'], 'is_active' => true],
                );

                $model->wasRecentlyCreated ? $createdMarkets++ : $existingMarkets++;
            }

            foreach (self::CATEGORIES as $category) {
                $model = MarketItemCategory::firstOrCreate(
                    ['slug' => $category['slug']],
                    ['name' => $category['name'], 'sort_order' => $category['sort_order']],
                );

                $model->wasRecentlyCreated ? $createdCategories++ : $existingCategories++;
            }
        });

        if ($this->command !== null) {
            $this->command->info(sprintf(
                '[MarketMasterSeeder] pasar dibuat: %d, sudah ada: %d; kategori dibuat: %d, sudah ada: %d.',
                $createdMarkets,
                $existingMarkets,
                $createdCategories,
                $existingCategories,
            ));
        }
    }
}
