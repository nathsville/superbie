<?php

namespace Database\Seeders;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * GIS Phase 1 — Master wilayah Kota Parepare (kecamatan + kelurahan).
 *
 * CAKUPAN: HANYA Kota Parepare, Sulawesi Selatan (4 kecamatan, 22 kelurahan).
 * Dataset ini TIDAK mencakup wilayah lain di Indonesia.
 *
 * SUMBER DATA (diverifikasi manual — lihat GIS_PHASE1_WILAYAH_PAREPARE.md):
 *   - BPS Kota Parepare — jumlah kecamatan/kelurahan:
 *     https://pareparekota.bps.go.id/ (Kota Parepare Dalam Angka; 4 kecamatan,
 *     22 kelurahan).
 *   - Kode wilayah administrasi (Kemendagri) — 6 digit kecamatan & 10 digit
 *     kelurahan, portal: https://kodewilayah.kemendagri.go.id/
 *     (portal memerlukan login; kode dicocok-silangkan pada beberapa sumber
 *     publik yang mengutip Kemendagri/BPS — lihat dokumentasi).
 *   - Cross-check 22 entri: GAMBAR REFERENSI PENGGUNA (diberikan langsung;
 *     BUKAN verifikasi independen Kemendagri). Hasil: 22/22 cocok, tanpa
 *     perbedaan nilai (lihat GIS_PHASE1_WILAYAH_PAREPARE.md §3.1).
 *   Tanggal verifikasi: 2026-02-11.
 *
 * PENTING:
 *   - Kolom `code` menyimpan KODE KEMENDAGRI (resmi), BUKAN kode BPS. Kode BPS
 *     berbeda skemanya (7 digit: contoh 7372020 / 7372020001) dan TIDAK ditulis
 *     ke DB karena skema hanya menyediakan satu kolom `code`.
 *   - Seeder ini STANDALONE dan TIDAK dipanggil dari DatabaseSeeder, agar
 *     `db:seed` umum tidak menulis master wilayah. Jalankan secara eksplisit:
 *       php artisan db:seed --class=ParepareLocationSeeder
 *   - IDEMPOTENT: menjalankan berulang tidak membuat duplikasi.
 *   - TIDAK menghapus baris apa pun.
 *   - TIDAK mengubah ID master yang sudah direferensikan laporan.
 *   - TIDAK menimpa kode yang bertentangan secara diam-diam: entri bermasalah
 *     dilewati dan dilaporkan.
 */
class ParepareLocationSeeder extends Seeder
{
    /**
     * Dataset terverifikasi Kota Parepare.
     *
     * Struktur: kecamatan (name + Kemendagri code) → daftar kelurahan
     * (name + Kemendagri code). Urutan mengikuti kode wilayah.
     *
     * @var list<array{name: string, code: string, kelurahan: list<array{name: string, code: string}>}>
     */
    private const DATASET = [
        [
            'name' => 'Bacukiki',
            'code' => '73.72.01',
            'kelurahan' => [
                ['name' => 'Lompoe',          'code' => '73.72.01.1004'],
                ['name' => 'Watang Bacukiki', 'code' => '73.72.01.1005'],
                ['name' => 'Lemoe',           'code' => '73.72.01.1009'],
                ['name' => 'Galung Maloang',  'code' => '73.72.01.1010'],
            ],
        ],
        [
            'name' => 'Ujung',
            'code' => '73.72.02',
            'kelurahan' => [
                ['name' => 'Labukkang',     'code' => '73.72.02.1001'],
                ['name' => 'Ujung Sabbang', 'code' => '73.72.02.1002'],
                ['name' => 'Ujung Bulu',    'code' => '73.72.02.1003'],
                ['name' => 'Lapadde',       'code' => '73.72.02.1004'],
                ['name' => 'Mallusetasi',   'code' => '73.72.02.1005'],
            ],
        ],
        [
            'name' => 'Soreang',
            'code' => '73.72.03',
            'kelurahan' => [
                ['name' => 'Lakessi',        'code' => '73.72.03.1001'],
                ['name' => 'Ujung Baru',     'code' => '73.72.03.1002'],
                ['name' => 'Watang Soreang', 'code' => '73.72.03.1003'],
                ['name' => 'Kampung Pisang', 'code' => '73.72.03.1004'],
                ['name' => 'Ujung Lare',     'code' => '73.72.03.1005'],
                ['name' => 'Bukit Indah',    'code' => '73.72.03.1006'],
                ['name' => 'Bukit Harapan',  'code' => '73.72.03.1007'],
            ],
        ],
        [
            'name' => 'Bacukiki Barat',
            'code' => '73.72.04',
            'kelurahan' => [
                ['name' => 'Kampung Baru',      'code' => '73.72.04.1001'],
                ['name' => 'Cappa Galung',      'code' => '73.72.04.1002'],
                ['name' => 'Lumpue',            'code' => '73.72.04.1003'],
                ['name' => 'Tiro Sompe',        'code' => '73.72.04.1004'],
                ['name' => 'Sumpang Minangae',  'code' => '73.72.04.1005'],
                ['name' => 'Bumi Harapan',      'code' => '73.72.04.1006'],
            ],
        ],
    ];

    /** @var list<string> */
    private array $created = [];

    /** @var list<string> */
    private array $adopted = [];

    /** @var list<string> */
    private array $unchanged = [];

    /** @var list<string> */
    private array $conflicts = [];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::DATASET as $kecamatanData) {
                $kecamatan = $this->resolveKecamatan(
                    $kecamatanData['name'],
                    $kecamatanData['code'],
                );

                // Entri kecamatan bermasalah (konflik/ambigu) dilewati bersama
                // seluruh kelurahannya — tidak ada koreksi spekulatif.
                if ($kecamatan === null) {
                    continue;
                }

                foreach ($kecamatanData['kelurahan'] as $kelurahanData) {
                    $this->resolveKelurahan(
                        $kecamatan,
                        $kelurahanData['name'],
                        $kelurahanData['code'],
                    );
                }
            }
        });

        $this->reportSummary();
    }

    /**
     * Resolve a Kecamatan row for the verified dataset entry.
     *
     * Urutan pencocokan (aman):
     *   1. Cocokkan KODE Kemendagri (prioritas utama).
     *   2. Jika tidak ada, cocokkan NAMA secara ketat — hanya untuk MENGISI kode
     *      yang masih NULL pada baris yang sama (ID tidak berubah).
     *   3. Jika tidak ada, buat baris baru.
     *
     * Konflik (kode dipakai nama lain, nama ganda, nama cocok tapi berkode
     * berbeda) → entri DILEWATI dan dilaporkan; kode TIDAK diubah.
     */
    private function resolveKecamatan(string $name, string $code): ?Kecamatan
    {
        // 1. Prioritas: kode resmi.
        $byCode = Kecamatan::query()->where('code', $code)->first();
        if ($byCode !== null) {
            if ($byCode->name !== $name) {
                $this->conflicts[] = "Kecamatan kode {$code} sudah dipakai oleh \"{$byCode->name}\" "
                    . "(dataset: \"{$name}\") — entri dilewati, tidak ada perubahan.";

                return null;
            }

            $this->unchanged[] = "Kecamatan {$name} ({$code})";

            return $byCode;
        }

        // 2. Pencocokan nama ketat (hanya untuk adopsi kode yang kosong).
        $byName = Kecamatan::query()->where('name', $name)->get();
        if ($byName->count() > 1) {
            $this->conflicts[] = "Kecamatan \"{$name}\" ambigu: {$byName->count()} baris bernama sama "
                . '— entri dilewati, tidak ada perubahan.';

            return null;
        }

        if ($byName->count() === 1) {
            $match = $byName->first();

            if ($match->code !== null && $match->code !== $code) {
                $this->conflicts[] = "Kecamatan \"{$name}\" sudah memiliki code \"{$match->code}\" "
                    . "(dataset: \"{$code}\") — entri dilewati, code TIDAK diubah.";

                return null;
            }

            if ($match->code === null) {
                $match->update(['code' => $code]);
                $this->adopted[] = "Kecamatan {$name} ({$code}) — code diisi pada baris cocok-nama.";

                return $match;
            }

            $this->unchanged[] = "Kecamatan {$name} ({$code})";

            return $match;
        }

        // 3. Buat baru.
        $created = Kecamatan::create(['name' => $name, 'code' => $code]);
        $this->created[] = "Kecamatan {$name} ({$code})";

        return $created;
    }

    /**
     * Resolve a Kelurahan row for the verified dataset entry, scoped to its
     * parent Kecamatan. See {@see resolveKecamatan()} for the matching rules.
     *
     * Kelurahan dengan kode yang sama tetapi berada di Kecamatan lain TIDAK
     * dipindahkan (memindahkan dapat merusak relasi laporan) — entri dilewati
     * dan dilaporkan.
     */
    private function resolveKelurahan(Kecamatan $kecamatan, string $name, string $code): ?Kelurahan
    {
        // 1. Prioritas: kode resmi.
        $byCode = Kelurahan::query()->where('code', $code)->first();
        if ($byCode !== null) {
            if ($byCode->name !== $name) {
                $this->conflicts[] = "Kelurahan kode {$code} sudah dipakai oleh \"{$byCode->name}\" "
                    . "(dataset: \"{$name}\") — entri dilewati, tidak ada perubahan.";

                return null;
            }

            if ((int) $byCode->kecamatan_id !== (int) $kecamatan->id) {
                $this->conflicts[] = "Kelurahan \"{$name}\" ({$code}) berada di kecamatan lain "
                    . "(id {$byCode->kecamatan_id}, dataset kecamatan id {$kecamatan->id}) "
                    . '— entri dilewati, TIDAK dipindahkan.';

                return null;
            }

            $this->unchanged[] = "Kelurahan {$name} ({$code})";

            return $byCode;
        }

        // 2. Pencocokan nama ketat DI DALAM kecamatan induk.
        $byName = Kelurahan::query()
            ->where('kecamatan_id', $kecamatan->id)
            ->where('name', $name)
            ->get();

        if ($byName->count() > 1) {
            $this->conflicts[] = "Kelurahan \"{$name}\" di kecamatan id {$kecamatan->id} ambigu: "
                . "{$byName->count()} baris bernama sama — entri dilewati, tidak ada perubahan.";

            return null;
        }

        if ($byName->count() === 1) {
            $match = $byName->first();

            if ($match->code !== null && $match->code !== $code) {
                $this->conflicts[] = "Kelurahan \"{$name}\" (kecamatan id {$kecamatan->id}) sudah memiliki "
                    . "code \"{$match->code}\" (dataset: \"{$code}\") — entri dilewati, code TIDAK diubah.";

                return null;
            }

            if ($match->code === null) {
                $match->update(['code' => $code]);
                $this->adopted[] = "Kelurahan {$name} ({$code}) — code diisi pada baris cocok-nama.";

                return $match;
            }

            $this->unchanged[] = "Kelurahan {$name} ({$code})";

            return $match;
        }

        // 3. Buat baru.
        $created = Kelurahan::create([
            'kecamatan_id' => $kecamatan->id,
            'name'         => $name,
            'code'         => $code,
        ]);
        $this->created[] = "Kelurahan {$name} ({$code})";

        return $created;
    }

    private function reportSummary(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->info(sprintf(
            '[ParepareLocationSeeder] dibuat: %d, kode diisi pada baris ada: %d, sudah sesuai: %d, konflik dilewati: %d.',
            count($this->created),
            count($this->adopted),
            count($this->unchanged),
            count($this->conflicts),
        ));

        foreach ($this->adopted as $line) {
            $this->command->line('  ~ ' . $line);
        }

        foreach ($this->conflicts as $line) {
            $this->command->warn('  ! ' . $line);
        }
    }

    /**
     * Ringkasan konflik terakhir (untuk pengujian / pemanggilan programatik).
     *
     * @return list<string>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
