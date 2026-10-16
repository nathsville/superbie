<?php

namespace Tests\Feature;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Database\Seeders\ParepareLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * GIS Phase 1 — Master wilayah Kota Parepare (ParepareLocationSeeder).
 *
 * Memvalidasi seeder fondasi data wilayah:
 *   1. jumlah kecamatan/kelurahan sesuai dataset terverifikasi (4 / 22);
 *   2. setiap kelurahan terhubung ke kecamatan yang benar;
 *   3. idempotent (dua kali jalan tidak duplikat);
 *   4. kode wilayah disimpan sebagai string dan nol di depan tidak hilang;
 *   5. konflik kode / nama ambigu TIDAK menyebabkan perubahan diam-diam;
 *   6. relasi laporan yang sudah ada tidak berubah setelah seeding;
 *   7. foreign key kelurahan tetap valid;
 *   8. data wilayah lama yang tidak dapat dipetakan tidak dihapus otomatis.
 *
 * Semua fixture bersifat test-only dan berjalan di database testing
 * (`superbie_testing`, lihat phpunit.xml) — tidak menyentuh database operasional.
 *
 * Dataset harapan di bawah ditulis ULANG secara independen dari seeder, agar
 * tes benar-benar memverifikasi keluaran seeder (bukan sekadar mencerminkan
 * implementasinya). Nilai harapan = gambar referensi pengguna (22 entri) yang
 * telah dicocokkan 22/22 dengan dataset; kode pos dari gambar tidak disimpan
 * (skema sengaja tanpa kolom postal_code).
 * Sumber: BPS Kota Parepare + kode wilayah Kemendagri
 * (lihat GIS_PHASE1_WILAYAH_PAREPARE.md).
 */
class ParepareLocationSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dataset terverifikasi Kota Parepare (independen dari seeder).
     *
     * @var array<string, array<string, string>>  kecamatan code => [kelurahan name => kelurahan code]
     */
    private const EXPECTED = [
        '73.72.01' => [ // Bacukiki
            'Lompoe'          => '73.72.01.1004',
            'Watang Bacukiki' => '73.72.01.1005',
            'Lemoe'           => '73.72.01.1009',
            'Galung Maloang'  => '73.72.01.1010',
        ],
        '73.72.02' => [ // Ujung
            'Labukkang'     => '73.72.02.1001',
            'Ujung Sabbang' => '73.72.02.1002',
            'Ujung Bulu'    => '73.72.02.1003',
            'Lapadde'       => '73.72.02.1004',
            'Mallusetasi'   => '73.72.02.1005',
        ],
        '73.72.03' => [ // Soreang
            'Lakessi'        => '73.72.03.1001',
            'Ujung Baru'     => '73.72.03.1002',
            'Watang Soreang' => '73.72.03.1003',
            'Kampung Pisang' => '73.72.03.1004',
            'Ujung Lare'     => '73.72.03.1005',
            'Bukit Indah'    => '73.72.03.1006',
            'Bukit Harapan'  => '73.72.03.1007',
        ],
        '73.72.04' => [ // Bacukiki Barat
            'Kampung Baru'     => '73.72.04.1001',
            'Cappa Galung'     => '73.72.04.1002',
            'Lumpue'           => '73.72.04.1003',
            'Tiro Sompe'       => '73.72.04.1004',
            'Sumpang Minangae' => '73.72.04.1005',
            'Bumi Harapan'     => '73.72.04.1006',
        ],
    ];

    private const EXPECTED_KECAMATAN_NAMES = [
        '73.72.01' => 'Bacukiki',
        '73.72.02' => 'Ujung',
        '73.72.03' => 'Soreang',
        '73.72.04' => 'Bacukiki Barat',
    ];

    private function runSeeder(): void
    {
        $this->seed(ParepareLocationSeeder::class);
    }

    // =========================================================================
    // 1. Jumlah sesuai dataset terverifikasi
    // =========================================================================

    public function test_seeder_creates_exactly_four_kecamatan_and_twenty_two_kelurahan(): void
    {
        $this->runSeeder();

        $this->assertSame(4, Kecamatan::count());
        $this->assertSame(22, Kelurahan::count());

        // Setiap kecamatan terverifikasi ada dengan kode resminya.
        foreach (self::EXPECTED_KECAMATAN_NAMES as $code => $name) {
            $this->assertDatabaseHas('kecamatan', ['name' => $name, 'code' => $code]);
        }
    }

    public function test_seeder_matches_the_verified_dataset_exactly(): void
    {
        $this->runSeeder();

        foreach (self::EXPECTED as $kecamatanCode => $kelurahanMap) {
            $kecamatan = Kecamatan::where('code', $kecamatanCode)->firstOrFail();

            $actual = Kelurahan::where('kecamatan_id', $kecamatan->id)
                ->pluck('code', 'name')
                ->all();

            ksort($actual);
            $expected = $kelurahanMap;
            ksort($expected);

            $this->assertSame(
                $expected,
                $actual,
                "Kelurahan untuk kecamatan {$kecamatanCode} tidak sesuai dataset terverifikasi."
            );
        }
    }

    // =========================================================================
    // 2. Relasi kelurahan → kecamatan benar
    // =========================================================================
    public function test_each_kelurahan_is_linked_to_the_correct_kecamatan(): void
    {
        $this->runSeeder();

        // Kelurahan berkode '73.72.0X.YYYY' HARUS milik kecamatan '73.72.0X'.
        foreach (Kelurahan::with('kecamatan')->get() as $kelurahan) {
            $this->assertNotNull($kelurahan->kecamatan, "Kelurahan {$kelurahan->name} tanpa kecamatan.");
            $this->assertSame(
                substr($kelurahan->code, 0, 8),
                $kelurahan->kecamatan->code,
                "Kelurahan {$kelurahan->name} ({$kelurahan->code}) terhubung ke kecamatan yang salah."
            );
        }
    }

    /**
     * Kunci pengelompokan sesuai gambar referensi pengguna: setiap kelurahan
     * HARUS berada tepat di bawah kecamatan yang ditunjuk gambar.
     */
    public function test_grouping_matches_the_user_reference_list(): void
    {
        $this->runSeeder();

        // nama kelurahan => nama kecamatan (dari gambar referensi pengguna)
        $expectedGrouping = [
            'Labukkang'        => 'Ujung',
            'Lapadde'          => 'Ujung',
            'Mallusetasi'      => 'Ujung',
            'Ujung Bulu'       => 'Ujung',
            'Ujung Sabbang'    => 'Ujung',
            'Bukit Harapan'    => 'Soreang',
            'Bukit Indah'      => 'Soreang',
            'Kampung Pisang'   => 'Soreang',
            'Lakessi'          => 'Soreang',
            'Ujung Baru'       => 'Soreang',
            'Ujung Lare'       => 'Soreang',
            'Watang Soreang'   => 'Soreang',
            'Bumi Harapan'     => 'Bacukiki Barat',
            'Cappa Galung'     => 'Bacukiki Barat',
            'Kampung Baru'     => 'Bacukiki Barat',
            'Lumpue'           => 'Bacukiki Barat',
            'Sumpang Minangae' => 'Bacukiki Barat',
            'Tiro Sompe'       => 'Bacukiki Barat',
            'Galung Maloang'   => 'Bacukiki',
            'Lemoe'            => 'Bacukiki',
            'Lompoe'           => 'Bacukiki',
            'Watang Bacukiki'  => 'Bacukiki',
        ];

        $this->assertCount(22, $expectedGrouping);

        foreach ($expectedGrouping as $kelurahanName => $kecamatanName) {
            $this->assertSame(
                $kecamatanName,
                Kelurahan::where('name', $kelurahanName)->firstOrFail()->kecamatan->name,
                "Kelurahan {$kelurahanName} tidak berada di kecamatan {$kecamatanName}."
            );
        }
    }

    // =========================================================================
    // 3. Idempotent — dua kali jalan tidak duplikat
    // =========================================================================

    public function test_running_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->runSeeder();
        $firstKecamatan = Kecamatan::pluck('id', 'code')->all();
        $firstKelurahan = Kelurahan::pluck('id', 'code')->all();

        $this->runSeeder();

        $this->assertSame(4, Kecamatan::count());
        $this->assertSame(22, Kelurahan::count());

        // ID tidak berubah pada jalan kedua (baris yang sama dipakai ulang).
        $this->assertSame($firstKecamatan, Kecamatan::pluck('id', 'code')->all());
        $this->assertSame($firstKelurahan, Kelurahan::pluck('id', 'code')->all());

        // Tidak ada kode ganda.
        $this->assertSame(
            Kecamatan::count(),
            Kecamatan::distinct()->count('code'),
            'Ada kode kecamatan duplikat.'
        );
        $this->assertSame(
            Kelurahan::count(),
            Kelurahan::distinct()->count('code'),
            'Ada kode kelurahan duplikat.'
        );
    }

    // =========================================================================
    // 4. Kode disimpan sebagai string, nol di depan terjaga
    // =========================================================================

    public function test_codes_are_stored_as_strings_and_keep_leading_zeros(): void
    {
        $this->runSeeder();

        // Kolom `code` bertipe string (varchar), bukan numerik.
        foreach (['kecamatan', 'kelurahan'] as $table) {
            $columns = collect(DB::select("SHOW COLUMNS FROM {$table}"))->keyBy('Field');
            $this->assertStringStartsWith(
                'varchar',
                strtolower($columns['code']->Type),
                "Kolom {$table}.code harus bertipe string."
            );
        }

        // Nilai yang tersimpan identik sebagai string (termasuk segmen ber-nol).
        foreach (self::EXPECTED as $kecamatanCode => $kelurahanMap) {
            $this->assertSame(
                $kecamatanCode,
                Kecamatan::where('code', $kecamatanCode)->value('code'),
                "Kode kecamatan {$kecamatanCode} berubah saat disimpan."
            );

            foreach ($kelurahanMap as $kelurahanCode) {
                $this->assertSame(
                    $kelurahanCode,
                    Kelurahan::where('code', $kelurahanCode)->value('code'),
                    "Kode kelurahan {$kelurahanCode} berubah saat disimpan."
                );
            }
        }
    }

    public function test_leading_zero_code_survives_a_round_trip(): void
    {
        // Bukti bahwa lapisan penyimpanan tidak membuang nol di depan.
        $kecamatan = Kecamatan::create(['name' => 'Kecamatan Nol', 'code' => '0073']);
        $kelurahan = Kelurahan::create([
            'kecamatan_id' => $kecamatan->id,
            'name'         => 'Kelurahan Nol',
            'code'         => '0073.0001',
        ]);

        $this->assertSame('0073', $kecamatan->fresh()->code);
        $this->assertSame('0073.0001', $kelurahan->fresh()->code);
        $this->assertIsString($kecamatan->fresh()->code);
    }

    // =========================================================================
    // 5. Konflik kode / nama ambigu tidak mengubah data diam-diam
    // =========================================================================

    public function test_code_conflict_does_not_silently_overwrite(): void
    {
        // Sebuah baris asing sudah memakai kode resmi Bacukiki (73.72.01).
        $impostor = Kecamatan::create(['name' => 'Kecamatan Palsu', 'code' => '73.72.01']);

        $this->runSeeder();

        // Baris asing TIDAK diubah, dan Bacukiki TIDAK dibuat diam-diam.
        $this->assertSame('Kecamatan Palsu', $impostor->fresh()->name);
        $this->assertSame('73.72.01', $impostor->fresh()->code);
        $this->assertSame(0, Kecamatan::where('name', 'Bacukiki')->count());

        // Tiga kecamatan lain tetap dibuat (hanya entri bermasalah yang dilewati).
        $this->assertSame(3, Kecamatan::whereIn('code', ['73.72.02', '73.72.03', '73.72.04'])->count());
    }

    public function test_name_match_with_different_code_is_not_silently_rewritten(): void
    {
        // Nama 'Ujung' cocok, tetapi sudah berkode lain → code TIDAK boleh diubah.
        $existing = Kecamatan::create(['name' => 'Ujung', 'code' => '99.99.99']);

        $this->runSeeder();

        $this->assertSame('99.99.99', $existing->fresh()->code, 'Kode yang bertentangan diubah diam-diam.');
        $this->assertSame(1, Kecamatan::where('name', 'Ujung')->count());
        $this->assertSame(0, Kecamatan::where('code', '73.72.02')->count());
    }

    public function test_name_match_with_null_code_is_adopted_without_changing_id(): void
    {
        // Baris lama tanpa kode, namanya cocok → kode diisi, ID tidak berubah.
        $existing = Kecamatan::create(['name' => 'Soreang', 'code' => null]);
        $originalId = $existing->id;

        $this->runSeeder();

        $this->assertSame($originalId, Kecamatan::where('name', 'Soreang')->value('id'));
        $this->assertSame('73.72.03', $existing->fresh()->code);

        // Kelurahan Soreang dibuat di bawah kecamatan lama (ID dipertahankan).
        $this->assertSame(
            7,
            Kelurahan::where('kecamatan_id', $originalId)->count()
        );
    }

    // =========================================================================
    // 6. Relasi laporan tidak berubah setelah seeding
    // =========================================================================

    public function test_existing_complaint_relations_survive_seeding(): void
    {
        // Fixture mirip data lama: kecamatan/kelurahan tanpa kode + sebuah laporan.
        $kecamatan = Kecamatan::create(['name' => 'Ujung', 'code' => null]);
        $kelurahan = Kelurahan::create([
            'kecamatan_id' => $kecamatan->id,
            'name'         => 'Labukkang',
            'code'         => null,
        ]);

        $complaint = Complaint::create([
            'reference_code' => 'LPW-GIS1-' . strtoupper(uniqid()),
            'reporter_id'    => User::factory()->create(['role' => 'masyarakat', 'is_active' => true])->id,
            'category_id'    => ComplaintCategory::factory()->create()->id,
            'title'          => 'Laporan uji wilayah',
            'description'    => 'Deskripsi laporan pengujian fondasi wilayah.',
            'status'         => ComplaintStatus::Submitted,
            'submitted_at'   => now(),
            'kecamatan_id'   => $kecamatan->id,
            'kelurahan_id'   => $kelurahan->id,
        ]);

        $this->runSeeder();

        $fresh = $complaint->fresh();
        $this->assertSame($kecamatan->id, $fresh->kecamatan_id);
        $this->assertSame($kelurahan->id, $fresh->kelurahan_id);

        // Baris master yang direferensikan tetap baris yang SAMA (ID tidak berubah).
        $this->assertSame('Ujung', Kecamatan::find($kecamatan->id)->name);
        $this->assertSame('Labukkang', Kelurahan::find($kelurahan->id)->name);
        $this->assertSame($complaint->id, Complaint::find($complaint->id)->id);
    }

    // =========================================================================
    // 7. Foreign key kelurahan tetap valid
    // =========================================================================

    public function test_every_kelurahan_has_a_valid_kecamatan_foreign_key(): void
    {
        $this->runSeeder();

        // Tidak ada kelurahan yatim.
        $orphans = Kelurahan::query()
            ->leftJoin('kecamatan', 'kecamatan.id', '=', 'kelurahan.kecamatan_id')
            ->whereNull('kecamatan.id')
            ->count();

        $this->assertSame(0, $orphans, 'Ada kelurahan dengan kecamatan_id yang tidak valid.');

        $this->assertSame(22, Kelurahan::whereNotNull('kecamatan_id')->count());
    }

    public function test_kecamatan_cannot_be_deleted_while_it_owns_kelurahan(): void
    {
        $this->runSeeder();

        $kecamatan = Kecamatan::where('code', '73.72.01')->firstOrFail();

        // Guard historis: kecamatan yang masih memiliki kelurahan tidak boleh dihapus.
        $this->assertTrue($kecamatan->isInUse());
        $this->expectException(\RuntimeException::class);
        $kecamatan->delete();
    }

    // =========================================================================
    // 8. Data wilayah lama yang tidak dapat dipetakan tidak dihapus otomatis
    // =========================================================================

    public function test_unmappable_legacy_wilayah_is_not_deleted(): void
    {
        $legacyKecamatan = Kecamatan::create(['name' => 'Kecamatan Lama', 'code' => null]);
        $legacyKelurahan = Kelurahan::create([
            'kecamatan_id' => $legacyKecamatan->id,
            'name'         => 'Kelurahan Lama',
            'code'         => null,
        ]);

        $this->runSeeder();

        // Baris lama tetap ada (seeder tidak menghapus apa pun).
        $this->assertDatabaseHas('kecamatan', ['id' => $legacyKecamatan->id, 'name' => 'Kecamatan Lama']);
        $this->assertDatabaseHas('kelurahan', ['id' => $legacyKelurahan->id, 'name' => 'Kelurahan Lama']);

        // Kode baris lama TIDAK diisi dengan nilai tebakan.
        $this->assertNull($legacyKecamatan->fresh()->code);
        $this->assertNull($legacyKelurahan->fresh()->code);
    }

    public function test_seeder_never_deletes_existing_rows(): void
    {
        $before = Kecamatan::create(['name' => 'Kecamatan Sentuh', 'code' => null]);

        $this->runSeeder();

        $this->assertDatabaseHas('kecamatan', ['id' => $before->id]);
    }

    // =========================================================================
    // 9. Seeder standalone (tidak dijalankan oleh db:seed default)
    // =========================================================================

    public function test_wilayah_seeder_is_standalone_and_not_wired_into_database_seeder(): void
    {
        // Seeder wilayah TIDAK dipanggil dari DatabaseSeeder (agar db:seed umum
        // tidak menulis master wilayah — konsisten dengan aturan proyek).
        $databaseSeeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString('ParepareLocationSeeder', $databaseSeeder);
    }

    // =========================================================================
    // 10. Guard: tabel wilayah tidak menyimpan field GIS/aktif (FINAL rule)
    // =========================================================================

    public function test_wilayah_tables_have_no_gis_or_lifecycle_columns(): void
    {
        foreach (['kecamatan', 'kelurahan'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'is_active'));
            foreach (['latitude', 'longitude', 'polygon', 'geometry', 'postal_code'] as $gis) {
                $this->assertFalse(Schema::hasColumn($table, $gis), "{$table} must not have {$gis}");
            }
        }
    }
}
