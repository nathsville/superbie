<?php

namespace Database\Seeders;

use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketItemCategory;
use App\Models\MarketPrice;
use App\Models\MarketStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PHASE S — DATA DEMO (pengembangan/demo UI ONLY; bukan data resmi).
 *
 * Untuk melihat halaman publik `/siphp` dan panel terisi tanpa menunggu input
 * petugas. Semua angka di sini DUMMY — jangan pernah dianggap harga nyata:
 *   - 12 barang contoh pada 3 kategori master (pokok/subsidi/penting);
 *   - 7 tanggal harian (H-6 … H-0) per pasar × barang → 420 baris, sehingga
 *     tren dan riwayat harga terlihat di UI (D-S6);
 *   - 8 toko contoh (7 terhubung pasar; 1 tanpa pasar). TANPA foto — tidak ada
 *     file gambar palsu yang dibuat; UI menampilkan ikon netral.
 *
 * MODEL HARGA DEMO (deterministik — hasil sama di ulangan mana pun):
 *   harga(pasar, barang, tanggal) =
 *       base(barang, hari ini)
 *     + offset(pasar)                         — selisih antar pasar (barang non-fixed)
 *     + drift(barang) × (tanggal − H-6) / 6   — tren naik perlahan menuju hari ini
 *     + jitter(pasar, barang, tanggal)        — crc32 % rentang, ± wiggle
 *   dibulatkan ke 100 rupiah terdekat. Barang `fixed` (LPG 3 Kg — HET) seragam
 *   di semua pasar tanpa jitter.
 *
 * PENGAMAN:
 *   - MENOLAK berjalan di environment `production` (RuntimeException).
 *   - STANDALONE: tidak dipanggil dari DatabaseSeeder. Jalankan eksplisit:
 *       php artisan db:seed --class=MarketDemoSeeder
 *   - IDEMPOTENT & TIDAK DESTRUKTIF: `firstOrCreate` — baris yang sudah ada
 *     (termasuk hasil koreksi petugas) TIDAK pernah ditimpa. Menjalankan ulang
 *     di hari yang sama = tidak ada perubahan; di hari lain = menggeser jendela
 *     tanggal (baris lama tetap ada sebagai sejarah).
 *
 * MENGHAPUS DATA DEMO (semua baris demo tidak pernah punya `recorded_by`):
 *   php artisan tinker --execute="App\Models\MarketStore::whereIn('name', ['Toko Sembako Barokah','Toko Sinar Jaya','UD Berkah Jaya','Toko Nabila','Kios Amanah','Toko Sembako Mandiri','Toko Bu Rahma','Minimarket Sejahtera'])->delete(); App\Models\MarketPrice::whereNull('recorded_by')->delete(); App\Models\MarketItem::whereIn('name', ['Beras Premium','Gula Pasir','Minyak Goreng Curah','Tepung Terigu','Beras Medium','Minyak Goreng Kemasan','LPG 3 Kg','Telur Ayam Ras','Daging Ayam Ras','Ikan Bandeng','Cabai Rawit Merah','Bawang Merah'])->delete();"
 */
class MarketDemoSeeder extends Seeder
{
    /**
     * Barang demo per slug kategori master.
     * `base` = harga hari ini (rupiah) · `drift` = total kenaikan selama 6 hari
     * (H-6 → H-0) · `wiggle` = amplitudo variasi harian · `fixed` = harga
     * seragam antar pasar (barang bersubsidi/HET).
     *
     * @var array<string, list<array{name: string, unit: string, base: int, drift: int, wiggle: int, fixed?: bool}>>
     */
    private const ITEMS = [
        'barang-pokok' => [
            ['name' => 'Beras Premium',        'unit' => 'kg',     'base' => 14500, 'drift' => 300,  'wiggle' => 100],
            ['name' => 'Gula Pasir',           'unit' => 'kg',     'base' => 17500, 'drift' => 400,  'wiggle' => 100],
            ['name' => 'Minyak Goreng Curah',  'unit' => 'liter',  'base' => 16000, 'drift' => 300,  'wiggle' => 150],
            ['name' => 'Tepung Terigu',        'unit' => 'kg',     'base' => 12000, 'drift' => 200,  'wiggle' => 100],
        ],
        'barang-subsidi' => [
            ['name' => 'Beras Medium',         'unit' => 'kg',     'base' => 13000, 'drift' => 250,  'wiggle' => 100],
            ['name' => 'Minyak Goreng Kemasan', 'unit' => 'liter', 'base' => 19000, 'drift' => 300,  'wiggle' => 150],
            ['name' => 'LPG 3 Kg',             'unit' => 'tabung', 'base' => 21000, 'drift' => 0,    'wiggle' => 0,   'fixed' => true],
        ],
        'barang-penting' => [
            ['name' => 'Telur Ayam Ras',       'unit' => 'kg',     'base' => 31000, 'drift' => 1000, 'wiggle' => 300],
            ['name' => 'Daging Ayam Ras',      'unit' => 'kg',     'base' => 40000, 'drift' => 1500, 'wiggle' => 500],
            ['name' => 'Ikan Bandeng',         'unit' => 'kg',     'base' => 32000, 'drift' => 800,  'wiggle' => 400],
            ['name' => 'Cabai Rawit Merah',    'unit' => 'kg',     'base' => 52000, 'drift' => 6000, 'wiggle' => 1500],
            ['name' => 'Bawang Merah',         'unit' => 'kg',     'base' => 40000, 'drift' => 2500, 'wiggle' => 700],
        ],
    ];

    /** Selisih harga antar pasar (rupiah) untuk barang non-`fixed`. */
    private const MARKET_OFFSETS = [
        'lakessi'          => 0,
        'senggol'          => 250,
        'labukkang'        => -150,
        'sumpang-minangae' => 400,
        'wekkee'           => -250,
    ];

    /** @var list<array{name: string, market: ?string, type: string, address: string, phone: string, opening_hours: string}> */
    private const STORES = [
        ['name' => 'Toko Sembako Barokah',  'market' => 'senggol',          'type' => 'Sembako',             'address' => 'Jl. Pasar Senggol No. 3',            'phone' => '0812-0000-0001', 'opening_hours' => '06.30 – 20.00 WITA'],
        ['name' => 'Toko Sinar Jaya',       'market' => 'lakessi',          'type' => 'Sembako & Kelontong', 'address' => 'Jl. Pasar Lakessi No. 5',             'phone' => '0812-0000-0002', 'opening_hours' => '07.00 – 21.00 WITA'],
        ['name' => 'UD Berkah Jaya',        'market' => 'labukkang',        'type' => 'Sembako',             'address' => 'Jl. Pasar Labukkang No. 2',           'phone' => '0812-0000-0003', 'opening_hours' => '07.00 – 20.00 WITA'],
        ['name' => 'Toko Nabila',           'market' => 'sumpang-minangae', 'type' => 'Kelontong',           'address' => 'Jl. Pasar Sumpang Minangae No. 8',    'phone' => '0812-0000-0004', 'opening_hours' => '06.00 – 19.00 WITA'],
        ['name' => 'Kios Amanah',           'market' => 'wekkee',           'type' => 'Sembako',             'address' => "Jl. Pasar Wekke'e No. 7",             'phone' => '0812-0000-0005', 'opening_hours' => '06.30 – 19.30 WITA'],
        ['name' => 'Toko Sembako Mandiri',  'market' => null,               'type' => 'Sembako',             'address' => 'Jl. Poros Parepare Km 2',             'phone' => '0812-0000-0006', 'opening_hours' => '07.00 – 21.00 WITA'],
        ['name' => 'Toko Bu Rahma',         'market' => 'senggol',          'type' => 'Sembako & Sayur',     'address' => 'Jl. Pasar Senggol No. 11',            'phone' => '0812-0000-0007', 'opening_hours' => '06.00 – 18.00 WITA'],
        ['name' => 'Minimarket Sejahtera',  'market' => null,               'type' => 'Minimarket',          'address' => 'Jl. Poros Parepare Km 3',             'phone' => '0812-0000-0008', 'opening_hours' => '08.00 – 22.00 WITA'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'MarketDemoSeeder adalah alat DEMO pengembangan — menolak berjalan di production.'
            );
        }

        $markets = Market::query()
            ->whereIn('slug', array_keys(self::MARKET_OFFSETS))
            ->get()
            ->keyBy('slug');

        if ($markets->count() !== count(self::MARKET_OFFSETS)) {
            throw new RuntimeException(
                'Master pasar belum lengkap. Jalankan dulu: php artisan db:seed --class=MarketMasterSeeder'
            );
        }

        $categories = MarketItemCategory::query()
            ->whereIn('slug', array_keys(self::ITEMS))
            ->get()
            ->keyBy('slug');

        if ($categories->count() !== count(self::ITEMS)) {
            throw new RuntimeException(
                'Master kategori belum lengkap. Jalankan dulu: php artisan db:seed --class=MarketMasterSeeder'
            );
        }

        // 7 tanggal harian: H-6 … H-0 (indeks 0..6).
        $dates = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $dates[] = now()->subDays($daysAgo)->toDateString();
        }

        $createdItems = 0;
        $createdPrices = 0;
        $existingPrices = 0;
        $createdStores = 0;

        DB::transaction(function () use ($markets, $categories, $dates, &$createdItems, &$createdPrices, &$existingPrices, &$createdStores) {
            foreach (self::ITEMS as $categorySlug => $items) {
                foreach ($items as $entry) {
                    $item = MarketItem::firstOrCreate(
                        [
                            'category_id' => $categories[$categorySlug]->id,
                            'name'        => $entry['name'],
                        ],
                        [
                            'unit'      => $entry['unit'],
                            'is_active' => true,
                        ],
                    );

                    if ($item->wasRecentlyCreated) {
                        $createdItems++;
                    }

                    foreach (self::MARKET_OFFSETS as $marketSlug => $marketOffset) {
                        foreach ($dates as $index => $date) {
                            $price = MarketPrice::firstOrCreate(
                                [
                                    'market_id'      => $markets[$marketSlug]->id,
                                    'item_id'        => $item->id,
                                    'effective_date' => $date,
                                ],
                                [
                                    'price'       => self::demoPrice($entry, $marketSlug, $marketOffset, $index, 6, $date),
                                    'recorded_by' => null,
                                ],
                            );

                            $price->wasRecentlyCreated ? $createdPrices++ : $existingPrices++;
                        }
                    }
                }
            }

            foreach (self::STORES as $entry) {
                $store = MarketStore::firstOrCreate(
                    ['name' => $entry['name']],
                    [
                        'market_id'     => $entry['market'] !== null ? $markets[$entry['market']]->id : null,
                        'type'          => $entry['type'],
                        'address'       => $entry['address'],
                        'phone'         => $entry['phone'],
                        'opening_hours' => $entry['opening_hours'],
                        'photo_path'    => null,
                        'is_active'     => true,
                    ],
                );

                if ($store->wasRecentlyCreated) {
                    $createdStores++;
                }
            }
        });

        if ($this->command !== null) {
            $this->command->info(sprintf(
                '[MarketDemoSeeder] barang baru: %d; harga baru: %d (sudah ada: %d); toko baru: %d.',
                $createdItems,
                $createdPrices,
                $existingPrices,
                $createdStores,
            ));
            $this->command->warn(
                '[MarketDemoSeeder] DATA DEMO — bukan harga resmi. Hapus kapan saja (lihat docblock kelas ini).'
            );
        }
    }

    /**
     * Harga demo deterministik:
     *   base + offset pasar + drift × (indeks/last) + jitter, dibulatkan ke 100
     *   rupiah terdekat. Jitter di-key oleh TANGGAL (bukan indeks) supaya dua
     *   tanggal berbeda tidak pernah berbagi nilai yang sama — gerak harian
     *   terlihat organik — sementara nilai sebuah tanggal tetap sama di
     *   ulangan mana pun. Barang `fixed` mengabaikan offset & jitter.
     *
     * @param array{name: string, unit: string, base: int, drift: int, wiggle: int, fixed?: bool} $entry
     */
    private static function demoPrice(array $entry, string $marketSlug, int $marketOffset, int $index, int $lastIndex, string $date): int
    {
        if ($entry['fixed'] ?? false) {
            return $entry['base'];
        }

        $drift = (int) round($entry['drift'] * $index / $lastIndex);
        $jitter = 0;

        if ($entry['wiggle'] > 0) {
            $span = 2 * $entry['wiggle'] + 1;
            $jitter = (crc32($marketSlug . '|' . $entry['name'] . '|' . $date) % $span) - $entry['wiggle'];
        }

        return max(500, (int) (round(($entry['base'] - $entry['drift'] + $drift + $marketOffset + $jitter) / 100) * 100));
    }
}
