# GIS Phase 1 — Fondasi Data Wilayah Kota Parepare

Status: **selesai & diterapkan ke DB pengembangan (uncommitted)** · Tanggal verifikasi data: **11 Februari 2026** · Tanggal penerapan DB: **12 Oktober 2026**

Dokumen ini mencatat sumber, hasil verifikasi, cara menjalankan, dan keterbatasan
fondasi data wilayah untuk pengembangan GIS SuperBie. Tahap ini **hanya** menyiapkan
master wilayah — **bukan** poligon/analitik spasial (lihat §7).

> **Catatan penerapan (12 Okt 2026):** database pengembangan `superbie` sebelumnya
> menyimpan **kode pos** (mis. `91121`) pada kolom `code`. Nilai tersebut **ditimpa**
> dengan kode wilayah **Kemendagri** (mis. `73.72.01`) setelah backup, dengan
> persetujuan pengguna. Detail di §9.

---

## 1. Cakupan

- **Hanya Kota Parepare, Sulawesi Selatan.** Dataset ini sengaja **tidak** mencakup
  kabupaten/kota lain. Jangan memperluas ke seluruh Indonesia.
- Dua level wilayah yang sudah ada di skema: `kecamatan` dan `kelurahan`
  (tidak ada tabel provinsi/kabupaten — sesuai keputusan proyek).
- Total terverifikasi: **4 kecamatan** dan **22 kelurahan**.

---

## 2. Sumber resmi & tanggal verifikasi

| Aspek | Sumber | Tautan | Tanggal verifikasi |
|---|---|---|---|
| Jumlah & nama kecamatan/kelurahan | BPS Kota Parepare (Kota Parepare Dalam Angka) | https://pareparekota.bps.go.id/ | 2026-02-11 |
| Kode wilayah administrasi (Kemendagri) | Portal Kode Wilayah Kemendagri | https://kodewilayah.kemendagri.go.id/ | 2026-02-11 |
| Konfirmasi jumlah (4 kecamatan / 22 kelurahan) | Peraturan Walikota / dokumen resmi (UU Pembentukan Parepare) | https://jdih.jabarprov.go.id/ (salinan UU) | 2026-02-11 |
| Cross-check daftar 22 kelurahan + kode pos | **Gambar referensi pengguna** (diberikan langsung; **bukan** verifikasi independen Kemendagri) | — (tidak ada tautan) | 2026-02-11 |

### Catatan metodologi kode wilayah

- Portal `kodewilayah.kemendagri.go.id` **memerlukan login** dan tidak dapat dibaca
  otomatis pada audit ini. Kode **kecamatan** (6 digit) dipastikan dari kutipan resmi
  Kemendagri (contoh format `73.72.01`).
- Kode **kelurahan** (10 digit) tidak dapat diambil langsung dari portal pada audit
  ini, sehingga **dicocok-silangkan pada beberapa sumber publik independen** yang
  mengutip Kemendagri/BPS, dengan **struktur kode konsisten** (Pasal 8 Permendagri
  137/2017: `73.72.KK.1NNN`, digit pertama kelurahan = `1`):
  - https://pos.hanifmu.com/kabupaten-kota/73.72
  - https://kodepos.co.id/kodepos/sulawesi-selatan/kota-pare-pare
  - https://kodepos.nomor.net/ (Kota Pare Pare)
  - https://www.detik.com/sulsel/berita/d-6919676/
  - https://klikresi.com/kodepos/sulawesi-selatan/kota-pare-pare
  - Entri kelurahan Wikipedia (mis. Labukkang → `73.72.02.1001`).

**Keterbatasan yang jujur:** kode kelurahan **belum diverifikasi langsung dari portal
Kemendagri ber-login**. Kode yang dimasukkan konsisten lintas sumber publik, tetapi
**belum boleh disebut "resmi 100%"** sampai diverifikasi ulang pada portal Kemendagri
atau salinan Kepmendagri terbaru. Jika ditemukan perbedaan, perbaiki melalui
Super Admin (dashboard) atau perbarui dataset seeder — **jangan menebak**.

### Kode BPS vs Kemendagri

- Kolom `code` pada DB menyimpan **kode Kemendagri** (mis. `73.72.01`, `73.72.02.1001`).
- **Kode BPS berbeda skema** (7 digit: kecamatan `7372020`, kelurahan `7372020001`)
  dan **tidak** disimpan (skema hanya menyediakan satu kolom `code`).
  Contoh pemetaan BPS: Labukkang → Kemendagri `73.72.02.1001` / BPS `7372020001`.
- Jangan menganggap keduanya identik.

---

## 3. Daftar data yang diverifikasi

### Kecamatan (kode Kemendagri)

| Kode | Kecamatan | Jumlah kelurahan |
|---|---|---|
| 73.72.01 | Bacukiki | 4 |
| 73.72.02 | Ujung | 5 |
| 73.72.03 | Soreang | 7 |
| 73.72.04 | Bacukiki Barat | 6 |
| | **TOTAL** | **22** |

### Kelurahan (kode Kemendagri)

| Kecamatan | Kode | Kelurahan |
|---|---|---|
| Bacukiki | 73.72.01.1004 | Lompoe |
| Bacukiki | 73.72.01.1005 | Watang Bacukiki |
| Bacukiki | 73.72.01.1009 | Lemoe |
| Bacukiki | 73.72.01.1010 | Galung Maloang |
| Ujung | 73.72.02.1001 | Labukkang |
| Ujung | 73.72.02.1002 | Ujung Sabbang |
| Ujung | 73.72.02.1003 | Ujung Bulu |
| Ujung | 73.72.02.1004 | Lapadde |
| Ujung | 73.72.02.1005 | Mallusetasi |
| Soreang | 73.72.03.1001 | Lakessi |
| Soreang | 73.72.03.1002 | Ujung Baru |
| Soreang | 73.72.03.1003 | Watang Soreang |
| Soreang | 73.72.03.1004 | Kampung Pisang |
| Soreang | 73.72.03.1005 | Ujung Lare |
| Soreang | 73.72.03.1006 | Bukit Indah |
| Soreang | 73.72.03.1007 | Bukit Harapan |
| Bacukiki Barat | 73.72.04.1001 | Kampung Baru |
| Bacukiki Barat | 73.72.04.1002 | Cappa Galung |
| Bacukiki Barat | 73.72.04.1003 | Lumpue |
| Bacukiki Barat | 73.72.04.1004 | Tiro Sompe |
| Bacukiki Barat | 73.72.04.1005 | Sumpang Minangae |
| Bacukiki Barat | 73.72.04.1006 | Bumi Harapan |

> Catatan ejaan: sumber menulis `Watang Bacukiki` (kadang `Wt. Bacukiki`), `Lapadde`
> (kadang `Lappade`), `Watang Soreang` (kadang `Wattang Soreang`). Dipakai ejaan
> resmi BPS: **Watang Bacukiki**, **Lapadde**, **Watang Soreang**.

### 3.1 Cross-check dengan gambar referensi pengguna (22 entri)

Seluruh **22 entri** pada gambar referensi pengguna telah dicocokkan dengan dataset
seeder. Hasil: **22/22 cocok** (kecamatan, nama, dan kode identik). **Tidak ada
perbedaan nilai** yang memerlukan perubahan data.

| Kecamatan | Kelurahan (seeder) | Kode (seeder) | Kode (gambar) | Kode pos (gambar) | Cocok |
|---|---|---|---|---|---|
| Ujung | Labukkang | 73.72.02.1001 | 73.72.02.1001 | 91111 | ✅ |
| Ujung | Lapadde | 73.72.02.1004 | 73.72.02.1004 | 91112 | ✅ |
| Ujung | Mallusetasi | 73.72.02.1005 | 73.72.02.1005 | 91111 | ✅ |
| Ujung | Ujung Bulu | 73.72.02.1003 | 73.72.02.1003 | 91113 | ✅ |
| Ujung | Ujung Sabbang | 73.72.02.1002 | 73.72.02.1002 | 91114 | ✅ |
| Soreang | Bukit Harapan | 73.72.03.1007 | 73.72.03.1007 | 91131 | ✅ |
| Soreang | Bukit Indah | 73.72.03.1006 | 73.72.03.1006 | 91131 | ✅ |
| Soreang | Kampung Pisang | 73.72.03.1004 | 73.72.03.1004 | 91131 | ✅ |
| Soreang | Lakessi | 73.72.03.1001 | 73.72.03.1001 | 91133 | ✅ |
| Soreang | Ujung Baru | 73.72.03.1002 | 73.72.03.1002 | 91131 | ✅ |
| Soreang | Ujung Lare | 73.72.03.1005 | 73.72.03.1005 | 91131 | ✅ |
| Soreang | Watang Soreang | 73.72.03.1003 | 73.72.03.1003 | 91132 | ✅ |
| Bacukiki Barat | Bumi Harapan | 73.72.04.1006 | 73.72.04.1006 | 91121 | ✅ |
| Bacukiki Barat | Cappa Galung | 73.72.04.1002 | 73.72.04.1002 | 91122 | ✅ |
| Bacukiki Barat | Kampung Baru | 73.72.04.1001 | 73.72.04.1001 | 91121 | ✅ |
| Bacukiki Barat | Lumpue | 73.72.04.1003 | 73.72.04.1003 | 91123 | ✅ |
| Bacukiki Barat | Sumpang Minangae | 73.72.04.1005 | 73.72.04.1005 | 91121 | ✅ |
| Bacukiki Barat | Tiro Sompe | 73.72.04.1004 | 73.72.04.1004 | 91125 | ✅ |
| Bacukiki | Galung Maloang | 73.72.01.1010 | 73.72.01.1010 | 91121 | ✅ |
| Bacukiki | Lemoe | 73.72.01.1009 | 73.72.01.1009 | 91121 | ✅ |
| Bacukiki | Lompoe | 73.72.01.1004 | 73.72.01.1004 | 91125 | ✅ |
| Bacukiki | Watang Bacukiki | 73.72.01.1005 | 73.72.01.1005 | 91121 | ✅ |

**Kode pos** hanya dicatat sebagai **data referensi** dari gambar pengguna. Kode pos
**tidak** disimpan ke database (tahap ini tidak meminta perubahan skema; tabel
`kecamatan`/`kelurahan` sengaja tanpa kolom `postal_code`).

### 3.2 Variasi ejaan (dicatat, TIDAK dinormalkan)

Nama pada gambar referensi memuat variasi/abreviasi. Nilai yang dipakai seeder adalah
ejaan BPS; variasi **dicatat di sini** dan **tidak** disimpan sebagai alias:

| Kecamatan | Nama seeder (dipakai) | Variasi pada gambar/sumber lain | Catatan |
|---|---|---|---|
| Bacukiki | Watang Bacukiki | `WT. Bacukiki` | Abreviasi "WT." = "Watang" |
| Bacukiki Barat | Cappa Galung | `Cappagalung` | Ditulis menyatu (tanpa spasi) pada gambar |
| Soreang | Watang Soreang | `Watang Soreang` (gambar: "Watang Soreang") | Gambar menegaskan "Watang Soreang" |
| Ujung | Lapadde | `Lappade` | Beberapa sumber menulis `Lappade` |

Tidak ada normalisasi otomatis. Jika perlu alias, harus ditangani sebagai keputusan
terpisah (mis. kolom/ tabel alias) — **bukan** dengan mengubah nama yang tersimpan.

### 3.3 Format kode

- Format kode **Kemendagri bertitik**, konsisten di seluruh dataset:
  - Kecamatan: `73.72.KK` (contoh `73.72.01`).
  - Kelurahan: `73.72.KK.1NNN` (contoh `73.72.02.1001`).
- Kode disimpan sebagai **string** (kolom `VARCHAR(50)`), sehingga nol/segmen bertitik
  tidak hilang. Seeder **mempertahankan** format bertitik ini (tidak ada konversi ke
  format tanpa titik).
- Kode BPS (mis. `7372020` / `7372020001`) **berbeda skema** dan **tidak** dipakai.

---

## 4. Cara menjalankan seeder dengan aman

Seeder wilayah bersifat **standalone** dan **tidak** dipanggil oleh `DatabaseSeeder`
(agar `db:seed` umum tidak menulis master wilayah). Jalankan secara eksplisit:

```bash
# Tulis master wilayah Kota Parepare (idempotent, aman diulang)
php artisan db:seed --class=ParepareLocationSeeder
```

Menjalankan berulang **tidak** membuat duplikasi (dicocokkan berdasarkan kode resmi,
lalu nama yang ketat), **tidak** menghapus baris apa pun, dan **tidak** mengubah ID
master yang sudah direferensikan laporan.

**JANGAN** menjalankan `migrate:fresh`, `db:wipe`, `migrate:rollback`, atau seeder
terhadap database operasional tanpa persetujuan eksplisit.

### Verifikasi jumlah

```bash
php artisan tinker
>>> App\Models\Kecamatan::count();   // 4
>>> App\Models\Kelurahan::count();   // 22
```

Atau via SQL baca-saja:

```sql
SELECT k.code, k.name, COUNT(l.id) AS jumlah_kelurahan
FROM kecamatan k LEFT JOIN kelurahan l ON l.kecamatan_id = k.id
GROUP BY k.id, k.code, k.name ORDER BY k.code;
```

---

## 5. Perilaku seeder & penanganan konflik

`ParepareLocationSeeder` mencocokkan tiap entri dengan urutan aman berikut:

1. **Kode Kemendagri** (prioritas utama) — baris yang sudah memakai kode itu dipakai ulang.
2. **Nama** (ketat) — hanya untuk **mengisi `code` yang masih NULL** pada baris yang
   sama; **ID tidak berubah**.
3. **Buat baru** jika tidak ada yang cocok.

Konflik berikut **dilewati** (tidak ada koreksi spekulatif) dan dilaporkan di output:

- Kode sudah dipakai oleh **nama berbeda** → entri dilewati, baris asing tidak diubah.
- **Nama ganda** (ambigu, >1 baris) → entri dilewati.
- Nama cocok tetapi **berkode berbeda** → `code` **tidak** diubah.
- Kelurahan berkode sama tetapi berada di **kecamatan lain** → **tidak dipindahkan**
  (memindahkan dapat merusak relasi laporan).

Output seeder merangkum: `dibuat`, `kode diisi pada baris ada`, `sudah sesuai`,
`konflik dilewati`, beserta daftar konflik.

---

## 6. Data yang belum dapat diverifikasi / keterbatasan skema

- **Kode kelurahan belum diverifikasi langsung dari portal Kemendagri ber-login**
  (lihat §2). Sudah dicocok-silangkan lintas sumber publik **dan** dengan gambar
  referensi pengguna; tetap perlu verifikasi final.
- **Gambar referensi pengguna BUKAN verifikasi independen.** Gambar tersebut dipakai
  untuk cross-check konsistensi (kecamatan, nama, kode, kode pos) — **bukan** bukti
  resmi Kemendagri. Kode tetap berstatus "belum terverifikasi final dari sumber resmi".
- **Skema tidak menyimpan sumber/versi dataset wilayah** (tidak ada kolom
  `source`/`version`/`verified_at`). Karena itu, sumber & tanggal dicatat **di dokumen
  ini**, bukan di DB. Menambah migration hanya untuk metadata ini **belum** dilakukan
  (tidak ada alasan teknis mendesak pada tahap ini).
- **Tidak ada kolom kode pos** (`postal_code`) dan **tidak ada kolom kode BPS terpisah**
  — hanya satu kolom `code` (Kemendagri). Kode pos dari gambar hanya dicatat di dokumen.
- **Master wilayah tidak "resmi penuh"** sampai kode kelurahan diverifikasi ulang.

---

## 7. Batasan tahap ini (di luar scope)

Tahap ini **tidak** mencakup, dan **tidak** ada implementasi tersembunyi untuk:

- GeoJSON / TopoJSON / poligon batas administrasi.
- Choropleth, spatial join, atau analisis spasial berbasis koordinat↔polygon.
- Geocoding / reverse geocoding.
- Perubahan desain peta Leaflet.
- Tabel provinsi/kabupaten baru.

Fitur-fitur tersebut akan dikerjakan pada tahap terpisah **setelah** fondasi data ini
divalidasi.

---

## 8. Berkas terkait

| Berkas | Perubahan |
|---|---|
| `database/seeders/ParepareLocationSeeder.php` | **Baru** — seeder wilayah Kota Parepare (standalone, idempotent, aman konflik) |
| `tests/Feature/ParepareLocationSeederTest.php` | **Baru** — 17 tes (151 assertions) untuk dataset, relasi, idempotensi, konflik, perlindungan laporan |
| `GIS_PHASE1_WILAYAH_PAREPARE.md` | **Baru** — dokumen ini |

Tidak ada migration baru, tidak ada route baru, tidak ada perubahan pada
`DatabaseSeeder` atau kode runtime produksi.

---

## 9. Catatan penerapan ke database pengembangan `superbie` (12 Okt 2026)

### 9.1 Temuan sebelum penerapan

Database pengembangan `superbie` **sudah** berisi 4 kecamatan + 22 kelurahan dengan
**nama dan relasi yang benar**, tetapi kolom `code` berisi **kode pos**, bukan kode
Kemendagri:

- `kecamatan.code`: `91111` (Ujung), `91121` (Bacukiki), `91131` (Soreang), `NULL` (Bacukiki Barat).
- `kelurahan.code`: 10 baris berisi kode pos (`91111`…`91132`), 12 baris `NULL`.

Karena seeder menolak menimpa kode yang berbeda secara diam-diam, eksekusi langsung
akan menghasilkan 5 konflik + 16 entri ter-skip. Seeding **dihentikan** dan dilaporkan.

### 9.2 Tindakan (disetujui pengguna)

1. **Backup** `superbie` dibuat lebih dulu (di luar repo & di luar `public/`):
   `D:\Kuliah\Semester 7\Magang\_superbie_db_backups\superbie_20261012_081708.sql` (1.22 MB).
2. **Audit** seluruh 26 baris: semua cocok dengan dataset berdasarkan **nama** (tanpa
   ambiguitas), sehingga setiap baris punya kode target Kemendagri yang pasti.
3. **Penimpaan** `code` dilakukan dalam **satu transaksi** — **26 baris** berubah
   (`code` lama = kode pos/NULL → kode Kemendagri). **Hanya kolom `code`** yang diubah;
   ID, nama, `kecamatan_id`, dan relasi laporan **tidak disentuh**.
4. **Verifikasi**: 4 kecamatan / 22 kelurahan; seluruh kode = dataset; tidak ada kode
   duplikat; relasi FK valid; jumlah & relasi `complaints` identik sebelum/sesudah
   (24 laporan, 11 ber-`kecamatan_id`, 11 ber-`kelurahan_id`, 0 yatim).
5. **Seeder dijalankan** (`php artisan db:seed --class=ParepareLocationSeeder`) **dua
   kali**: keduanya melaporkan `dibuat: 0, kode diisi: 0, sudah sesuai: 26, konflik: 0`
   → idempotent & tidak ada konflik.

### 9.3 Hasil akhir `code`

| Kecamatan | Kode | | Kelurahan | Kode |
|---|---|---|---|---|
| Bacukiki | 73.72.01 | | (4 kelurahan) | 73.72.01.1004/1005/1009/1010 |
| Ujung | 73.72.02 | | (5 kelurahan) | 73.72.02.1001–1005 |
| Soreang | 73.72.03 | | (7 kelurahan) | 73.72.03.1001–1007 |
| Bacukiki Barat | 73.72.04 | | (6 kelurahan) | 73.72.04.1001–1006 |

Semua nilai bertipe **string** dengan format **bertitik** (Kemendagri).

### 9.4 Catatan penting

- **Kode pos TIDAK hilang dari sistem**, hanya tidak lagi menempati kolom `code`.
  Jika kode pos masih diperlukan, ia harus disimpan di kolom/tabel terpisah
  (belum dilakukan — di luar scope tahap ini).
- Kode Kemendagri ini **belum diverifikasi independen** dari portal resmi; dasar
  persetujuan adalah gambar referensi pengguna (lihat §2 & §6).
- File backup **tidak** dimasukkan ke repo/Git (disimpan di luar proyek).
