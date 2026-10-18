# Rencana Integrasi SIPHP di SuperBie — Modul Pasar (Native)

**Status:** DISETUJUI — G1 ✅ (keputusan owner 2026-10-17) · **Implementasi S0–S4 ✅ (2026-10-17; ter-commit `720c5cd`, belum di-push)** · **Tanggal:** 2026-10-17
**Pengambil keputusan:** pemilik proyek (owner)

Dokumen terkait:

- `ERD_SIPHP_PAREPARE.md` — hasil observasi pasif situs lama `https://www.siphp-parepare.com/` (2026-10-15).
- `prd.md`, `rules.md`, `schema.md`, `design.md`, `architecture.md` — sumber kebenaran yang **wajib diperbarui lebih dulu** (gerbang G2) sebelum kode ditulis.
- `PROGRESS.md` — catatan progres.

---

## 1. Latar belakang dan keputusan owner

Portal SuperBie direncanakan menghubungkan empat layanan: SIPHP, WINDY, JDIH, dan Command Center — Lapor Pak Wali (`prd.md` §1). Baru Lapor Pak Wali (+ WINDY) yang dibangun; SIPHP masih berjalan sebagai situs terpisah.

**Keputusan owner (2026-10-17) yang sudah tercatat:**

| ID | Keputusan |
|---|---|
| **D-S1** | **Bentuk integrasi:** membangun ulang modul SIPHP secara **native di SuperBie** — bukan tautan keluar, bukan embed/iframe, bukan sinkronisasi data dari sistem lama. |
| **D-S2** | **Sumber data:** **input manual di SuperBie**. Modul mandiri; tidak membaca/menulis ke `siphp-parepare.com` sama sekali. |
| **D-S3** | **Tampilan:** **halaman publik `/siphp`** (baca-saja, tanpa login) + pintu masuk di landing page SuperBie. |
| **D-S4a** | **Direktori retail** dibangun **sekaligus** bersama modul harga (bukan fase terpisah). |
| **D-S4c** | **Pengaduan pasar tidak dibangun ulang.** Keluhan seputar pasar tetap lewat Lapor Pak Wali (§6.1). |
| **D-S5** | **Pengelola data = Super Admin saja.** Admin tetap monitoring read-only; publik baca-saja; tidak ada role baru. |
| **D-S6** | **Harga disimpan sebagai riwayat** per (pasar × barang × tanggal) dengan unique key; halaman publik menampilkan harga terbaru + tanggalnya. |
| **D-S7** | **Foto retail disimpan di public disk** (foto toko memang untuk dipajang publik), nama file generated + validasi MIME/ukuran. |

Dokumen ini adalah **gerbang perencanaan** sebelum menyentuh kode/skema, mengikuti pola yang dipakai saat WINDY disetujui: rencana → gerbang rilis → `schema.md` diperbarui → migrasi → implementasi, **tanpa teknologi baru dan tetap empat role**.

Catatan penting: keputusan ini **mencabut status lama** SIPHP sebagai "Won't Have / not built (navigation labels only)" (`prd.md` F-015, `rules.md` §1.2, `design.md`, `README.md`). Pencabutan itu diformalkan lewat gerbang **G2** di §8.

---

## 2. Ruang lingkup

### 2.1 Usulan termasuk

| # | Fitur | Catatan |
|---|---|---|
| 1 | **Info harga barang per pasar** — 5 pasar, 3 kategori (barang pokok / subsidi / penting) | Inti modul; menyamai fungsi SIPHP lama |
| 2 | **Master data** — pasar, kategori barang, barang | Dikelola staf internal dari panel Super Admin |
| 3 | **Input harga manual** — per (barang × pasar × tanggal) | Mengikuti D-S2 |
| 4 | **Direktori retail/toko** — nama, jenis, alamat, telepon, jam buka, foto | Menyamai SIPHP lama; **D-S4a: dibangun sekaligus** (bukan fase terpisah) |
| 5 | **Halaman publik `/siphp`** + pintu di landing (nav + footer) | D-S3 |

Fakta terverifikasi dari ERD yang dipakai sebagai data awal (seeder master):

- **5 pasar:** Lakessi, Senggol, Labukkang, Sumpang Minangae, Wekke'e.
- **3 kategori:** barang pokok, barang subsidi, barang penting.
- Daftar barang **tidak** di-seed (tidak terverifikasi) — diisi petugas lewat panel.

### 2.2 Tidak termasuk (non-goals)

- **Tidak** ada scraping, iframe, atau sinkronisasi ke `siphp-parepare.com` (D-S1/D-S2).
- **Tidak** menyentuh/mengubah sistem SIPHP lama — di luar kendali repo ini.
- **Tidak** ada role baru: tetap `masyarakat`, `operator`, `admin`, `super_admin`.
- **Tidak** ada teknologi baru: tanpa Docker/Redis/queue baru/SPA/Livewire/API seperangkat microservice (`rules.md` §2).
- **Tidak** ada endpoint publik/ekspor data terbuka.
- **D-S4c (rekomendasi):** **form pengaduan pasar** milik SIPHP lama **tidak dibangun ulang** — lihat §6.1.

---

## 3. Status keputusan

Seluruh keputusan owner untuk fase ini **sudah diambil** (D-S1…D-S7 pada §1) pada 2026-10-17. Yang tersisa berikut ini **tidak memblokir** S1 dan diputuskan pada fase yang disebut:

| Item | Status / rencana |
|---|---|
| Retensi data harga/retail | `TODO: Define requirement` — belum ditetapkan; **jangan mengarang angka** |
| Label pintu masuk di landing: "SIPHP" atau "Harga pasar"? | **Diputuskan (S3):** publik memakai **"Harga pasar"** (bahasa warga, konsisten dengan tab "Cuaca"). Tab nav landing hanya memuat 3 tautan (satu baris pada `lg`), sehingga "Harga pasar" mengambil kursi tengah dan tautan anchor "Tanya jawab" keluar dari tab — FAQ tetap terjangkau dari section-nya sendiri + pintu footer. Pintu lain: pintu footer "Harga pasar", daftar Layanan "Harga pasar kota", daftar Sumber "Harga pasar: input manual petugas". Brand "SIPHP" tetap disebut pada catatan sumber halaman `/siphp`. |
| Teks keterangan sumber data di halaman publik | **Diputuskan (S3):** memakai `config('siphp.source_note')` + kalimat "Modul SIPHP SuperBie — dibangun ulang secara native di portal ini; halaman ini tidak mengambil data dari situs lain." |

---

## 4. Data model usulan [USULAN — final menunggu pemkes + `schema.md`]

Konvensi mengikuti `schema.md` §1 (InnoDB, `utf8mb4`, plural snake_case, FK + transaksi). Nama tabel versi `ERD_SIPHP_PAREPARE.md` (Indonesia: `pasar`, `kategori_barang`, `barang`, `harga`, `retail`) dicatat sebagai alternatif penamaan; rekomendasi memakai nama English agar tidak rancu dengan istilah "kategori laporan"/"barang" di UI.

```text
markets                     -- pasar
  id, name, slug (unique), address (nullable), is_active, timestamps

market_item_categories      -- kategori barang (pokok | subsidi | penting)
  id, name (unique), slug (unique: barang-pokok | barang-subsidi | barang-penting),
  position (urutan tampil), timestamps

market_items                -- barang
  id, category_id FK -> market_item_categories,
  name, unit (satuan, nullable), is_active, timestamps
  index: (category_id), (name)

market_prices               -- harga
  id, market_id FK -> markets, item_id FK -> market_items,
  price DECIMAL(14,2), effective_date DATE, recorded_by FK -> users (nullable),
  timestamps
  unique: (market_id, item_id, effective_date)   -- 1 harga per barang per pasar per tanggal

market_stores               -- direktori retail/toko (D-S4a: sekaligus)
  id, market_id FK nullable, name, type (jenis), address, phone,
  opening_hours, photo_path (nullable), is_active, timestamps
```

Catatan:

- `recorded_by` menyimpan staf yang menginput (jejak, bukan otorisasi).
- Foto retail **bukan** lampiran pengaduan — alur private-storage untuk lampiran (`rules.md` §5) tidak berubah.
- Semua tabel hanya ditulis lewat panel internal; tidak ada jalur tulis publik.

---

## 5. Arsitektur dan pola implementasi (mengikuti repo)

| Lapisan | Rencana | Pola acuan di repo |
|---|---|---|
| Rute publik | `GET /siphp` (+ halaman per pasar) — tanpa login, `abort_unless(config('siphp.enabled'), 404)` | `PublicWeatherController` (`/cuaca`) |
| Rute panel | `super-admin/siphp/...` (middleware `role:super_admin`) | `SuperAdminWeatherController`, `SuperAdminCategoryController` |
| Controller | Tipis: `App\Http\Controllers\Market\PublicMarketController` + controller panel terpisah per domain | `app/Http/Controllers/**` |
| Service | `App\Services\Market\...` untuk query tampilan & penulisan multi-step | `app/Services/Weather/**` |
| Form Request | Validasi semua input (harga numerik ≥ 0, tanggal, FK exists, slug unik) | `app/Http/Requests/**` |
| Audit | Semua aksi tulis panel dicatat via `AuditLogService` | pola yang berlaku |
| Config | `config/siphp.php` + `SIPHP_ENABLED` (default `true`, mengikuti gaya WINDY) | `config/weather.php` |
| Rate limit | Limiter bernama untuk endpoint tulis panel (anti-abuse); nilai ditaruh di `config/business_rules.php` **setelah** ada dasar | `complaint-submission`, `help-desk-write` |
| View | Blade + Tailwind + Alpine; bahasa Indonesia; empty/loading/error state; `prefers-reduced-motion`; aksesibel | `resources/views/public/weather.blade.php` |
| Cache | Tidak wajib di awal; jika perlu, ikuti mekanisme cache yang sudah ada (tanpa teknologi baru) | `DashboardCacheService` |

**Aturan tampilan data (konsisten dengan aturan WINDY D-13):** jangan pernah tampilkan angka default/sintetis sebagai data nyata. Jika belum ada harga → tampilkan state **"Data harga belum tersedia"** beserta penjelasan, bukan angka 0 palsu.

---

## 6. Hal yang harus diputuskan khusus

### 6.1 Pengaduan pasar (D-S4c)

SIPHP lama memiliki form pengaduan warga (nama, pasar, nomor telepon, kategori, pesan). SuperBie sudah punya sistem pengaduan lengkap (Lapor Pak Wali) dengan validasi, routing dinas, dan audit.

**Keputusan owner (D-S4c, 2026-10-17): tidak dibangun ulang.** Keluhan seputar pasar masuk lewat Lapor Pak Wali; jika perlu, Super Admin menambahkan kategori baru (perubahan **data**, bukan kode) lewat menu kategori yang sudah ada. Tidak ada kanal pengaduan kedua di `/siphp`.

### 6.2 Jejak data & tanggung jawab isi

Karena data diinput manual (D-S2):

- Halaman publik menampilkan **tanggal pembaruan terakhir** per harga — tidak boleh menyiratkan "harga hari ini" jika memang bukan.
- Audit log mencatat siapa menginput/mengubah harga dan kapan.
- Tidak ada atribusi pihak ketiga (berbeda dari WINDY/BMKG).

---

## 7. Rencana fase (irisan vertikal kecil)

| Fase | Isi | Prasyarat |
|---|---|---|
| **S0** | Dokumen ini + keputusan §3 | — |
| **S1** | Update dokumen scope (G2) → `schema.md` §4 ditambah spesifikasi tabel pasar → migrasi + model + seeder master (5 pasar, 3 kategori) + `config/siphp.php` | G1, G2, G3 |
| **S2** | Panel Super Admin: master pasar/kategori/barang + input harga + CRUD retail + foto (D-S4a sekaligus) | S1 |
| **S3** | Halaman publik `/siphp` + pintu di landing (nav + footer) | S2 |
| **S4** | Feature test (publik, panel, otorisasi negatif, validasi), audit UI/aksesibilitas, update `PROGRESS.md` | S3 |

**Status implementasi (2026-10-17): S0–S4 selesai — ter-commit `720c5cd` (belum di-push).** Penyimpangan kecil dari §5, dilaporkan apa adanya:

- Controller publik bernama `App\Http\Controllers\PublicMarketController` (bukan `Market\PublicMarketController`) — mengikuti preseden `LandingController`; tidak ada `App\Services\Market` karena query tampilan cukup sebagai satu query "harga terbaru per barang" di controller tipis.
- Audit memakai pola repo `AuditLog::create` langsung (tidak ada service audit baru); otorisasi lewat middleware `role:super_admin` + `authorize()` di 5 Form Request.
- Rate limit tulis panel **belum** ditambahkan — §5 mensyaratkannya "setelah ada dasar"; tidak ada nilai dasar sehingga tidak ada angka yang dikarang.
- JS progressive navigation mengecualikan `/siphp` persis seperti `/cuaca` (swap `#main-content` akan merusak chrome + Alpine halaman full-page ini), dikunci oleh test.
- Data demo pengembangan (bukan data resmi): `MarketDemoSeeder` — dev-only (menolak `production`), idempoten `firstOrCreate` (tidak menimpa baris yang ada), disediakan agar UI publik dan panel dapat dilihat terisi tanpa menunggu input petugas.
- Paket publik lanjutan (permintaan owner): indikator naik/turun harga vs pencatatan sebelumnya (tanpa pembanding → "—") + grafik tren per barang di halaman pasar (`x-siphp.trend-chart`, SVG native, jendela 30 pencatatan terakhir, hanya baris tercatat; pemilih `?barang={id}` — tanpa route baru).
- Inventaris route terkunci di `ProgressiveNavigationTest` diperbarui **105 → 136** (+31 route PHASE S, tercatat eksplisit di test).
- Bukti: 19 feature test SIPHP baru (149 assertion) hijau; suite penuh hijau. Cek UI manual di browser **belum** dijalankan (lihat laporan akhir).

---

## 8. Gerbang rilis (gates)

- **G1 — Persetujuan rencana:** §2–§7 disetujui owner; keputusan §3 terjawab (atau sengaja ditunda dan ditandai).
- **G2 — Dokumen scope diperbarui:** `prd.md` (F-015 diaktifkan kembali sebagai fase berikutnya + F-ID baru bila perlu), `rules.md` §1.2, `design.md`, `architecture.md`, `schema.md`, `README.md`, `PROGRESS.md` — mencatat keputusan D-S1..S3 dan status baru SIPHP.
- **G3 — `schema.md` lebih dulu:** spesifikasi tabel pasar ditulis di `schema.md` **sebelum** migrasi dibuat (aturan `README.md`).
- **G4 — Implementasi S1–S3** mengikuti pola repo; tanpa teknologi/role baru. ✅ (2026-10-17)
- **G5 — Verifikasi:** Feature tests hijau + hasil dilaporkan apa adanya; UI dicek responsive + reduced-motion; tidak ada data sintetis di UI. ✅ test hijau (19 test SIPHP + suite penuh); ✅ tidak ada data sintetis (empty state jujur, dikunci test); markup responsif + `data-reveal` mengikuti gerbang `.js`/`prefers-reduced-motion` global — **cek UI manual di browser belum dijalankan** (rekomendasi sebelum rilis).

---

## 9. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Data harga basi (input manual) | Tampilkan tanggal pembaruan; state "belum tersedia" yang jujur; audit input |
| Rancu istilah "kategori"/"barang" di UI internal | Label jelas: "Kategori Barang Pasar", "Kategori Laporan"; pemisahan modul di nav Super Admin |
| Ekspektasi "integrasi" = data otomatis dari situs lama | D-S2 sudah menetapkan input manual; dokumen ini menegaskan non-goal |
| Scope creep (pengaduan pasar, GIS, dsb.) | §2.2 non-goals + §6.1 rekomendasi |
| Duplikasi logika pengaduan | Tidak membangun kanal pengaduan kedua (§6.1) |

---

## 10. Definisi selesai (DoD) modul

1. Master data + input harga berjalan dari panel Super Admin dengan validasi & audit.
2. Halaman publik `/siphp` menampilkan harga per pasar/kategori + tanggal pembaruan, dengan state kosong yang jujur; tanpa login.
3. Pintu masuk tersedia di landing (nav + footer).
4. Otorisasi server-side: publik hanya baca; tulis hanya role internal yang disetujui (D-S5).
5. Feature tests + laporan hasil; `schema.md`, `prd.md`, `rules.md`, `PROGRESS.md` sinkron.
6. Tanpa teknologi baru, tanpa role baru, tanpa menyentuh sistem SIPHP lama.

---

*Dokumen ini draft kerja. Setelah G1 disetujui, dokumen ini menjadi lampiran rencana; keputusan final tetap dicatat di `prd.md`/`rules.md`/`schema.md`.*
