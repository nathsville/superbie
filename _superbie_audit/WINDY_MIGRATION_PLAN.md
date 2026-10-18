# WINDY MIGRATION PLAN
## Rencana Migrasi WINDY (Google Apps Script) → Fitur Laravel SuperBie

- **Proyek target:** SuperBie — Lapor Pak Wali — `D:\Kuliah\Semester 7\Magang\superbie` (Laravel `^13.17`, PHP `^8.3`, MySQL/InnoDB)
- **Sumber lama:** `D:\Kuliah\Semester 7\Magang\superbie\WINDY` (Google Apps Script — "Parepare Weather Intelligence Dashboard"/PWID)
- **Dokumen sumber utama:** `D:\Kuliah\Semester 7\Magang\_superbie_audit\WINDY_MIGRATION_AUDIT.md` (selanjutnya disebut **Audit**)
- **Sifat tahap:** **PERENCANAAN & PENYELARASAN TATA KELOLA — TIDAK ADA IMPLEMENTASI**
- **Status dokumen:** `DRAFT REVISI 3 (PHASE 4E) — KEPUTUSAN PEMILIK D-11…D-17 DICATAT` · **IMPLEMENTASI SLICE 1 + GELOMBANG + MULTI-LOKASI + PRAKIRAAN GELOMBANG PER HARI + TATA LETAK EXECUTIVE COMMAND CENTER SUDAH DILAKUKAN** (lihat `WINDY_IMPLEMENTATION_REPORT.md`; 2026-10-15; O-14b kini terpenuhi dengan data gelombang nyata; dukungan multi-lokasi siap, hanya Parepare yang dikonfigurasi).
- **Tanggal revisi:** **2026-10-12** (revisi Phase 1) · **2026-10-13** (penyelarasan Phase 3) · **2026-10-14** (pembaruan dokumentasi Phase 4E)
- **Catatan revisi:** Dokumen ini adalah **hasil revisi** atas draft sebelumnya. Perubahan utama (Phase 1): pemisahan tegas antara keputusan final / usulan teknis / pertanyaan terbuka / gerbang persetujuan; penambahan checklist penyelesaian desain. **Phase 3:** dokumen tata kelola (`rules.md`, `prd.md`, `design.md`, `architecture.md`, `schema.md`, `README.md`, `PROGRESS.md`) **telah diperbarui** agar lingkup WINDY yang disetujui konsisten (O-1); ditambahkan **status keputusan O-1…O-16** dan penegasan eksplisit atas pola implementasi yang **belum terverifikasi**. **Phase 4E:** **keputusan pemilik D-11…D-17** dicatat (horizon 3 hari & BMKG gratis; zona waktu WITA; ambang lama sebagai nilai awal; sumber gratis + penelitian data gelombang BMKG; tanpa impor historis; sinkronisasi terjadwal+manual; batasan stack). Lihat §1.2.1 dan §13.

> **Pernyataan integritas.** Dokumen ini adalah **rencana**, bukan laporan verifikasi runtime. Semua fakta tentang WINDY dirujuk ke Audit (yang berbasis pembacaan source code). Semua usulan nama kelas/tabel/route/kolom diberi label **[USULAN]**. Hal yang belum diperiksa ditandai **BELUM DIVERIFIKASI**. **Tidak ada nilai rahasia** (API key/token/password) yang dicantumkan di dokumen ini.

---

## 0. Legenda Status Keputusan (dipakai konsisten di semua dokumen)

| Label | Arti |
|---|---|
| **FINAL** | Keputusan pengguna yang mengikat (**D-1…D-10** di §1.2 dan **D-11…D-17** di §1.2.1). Tidak boleh diubah untuk menyelaraskan dokumen. |
| **[USULAN]** | Usulan teknis dari tim desain; **belum disetujui**; dapat berubah. |
| **BELUM DIVERIFIKASI** | Belum diperiksa terhadap repository/API/Google Sheets; tidak dianggap fakta. |
| **TERBUKA (O-n)** | Pertanyaan yang menunggu keputusan pemilik; memblokir implementasi terkait. |
| **GERBANG** | Titik persetujuan eksplisit sebelum pekerjaan berikutnya boleh dimulai. |

---

## 1. Tujuan dan Ruang Lingkup

### 1.1 Tujuan

Merencanakan migrasi fungsi WINDY dari Google Apps Script ke modul Laravel di SuperBie sebagai **fitur informasi cuaca publik pada landing page**, dengan penyimpanan utama **MySQL**. **Phase 1–2** bersifat perencanaan & audit baca-saja; **Phase 3** menyelaraskan dokumen tata kelola dengan lingkup yang disetujui (O-1). **Tidak ada** kode/database yang diubah pada tahap perencanaan ini.

### 1.2 Keputusan pengguna yang menjadi dasar (FINAL, mengikat)

| # | Keputusan pengguna (FINAL) |
|---|---|
| **D-1** | WINDY adalah **fitur informasi cuaca publik pada landing page SuperBie**, bersama menu: **Beranda**, **Lapor Pak Wali**, **WINDY**, **SIPHP**, **JDIH**. |
| **D-2** | Pengunjung dapat membuka **WINDY tanpa login** dan **hanya membaca** data cuaca yang sudah dipublikasikan. |
| **D-3** | **Hanya Super Admin terautentikasi** yang dapat mengelola konfigurasi WINDY dan menjalankan **sinkronisasi manual melalui endpoint internal yang dilindungi**. |
| **D-4** | Sistem menggunakan **MySQL** sebagai penyimpanan utama. |
| **D-5** | Gunakan **data cuaca aktual dari sumber API yang sudah divalidasi**. Angka sintetis, hardcoded, atau nilai default **bukan** pengukuran aktual. |
| **D-6** | **Embed peta cuaca Windy.com dipertahankan.** |
| **D-7** | **Chart.js diperbolehkan** untuk visualisasi. |
| **D-8** | **Impor historis Google Sheets hanya boleh dilaksanakan setelah validasi, dry-run, laporan hasil, dan persetujuan eksplisit.** ⛔ **DIGANTIKAN oleh D-15 (Phase 4E): tidak ada impor historis.** |
| **D-9** | Seluruh fungsi WINDY yang relevan **dipertahankan atau dirancang ulang**, **kecuali peta batas wilayah dan seluruh implementasi GIS**. |
| **D-10** | Role SuperBie tetap tepat **empat**: Masyarakat, Operator, Admin, Super Admin. **Jangan menambahkan role baru.** |

**Konsekuensi keputusan yang sudah FINAL (tidak lagi menjadi pertanyaan terbuka):**
- MySQL = FINAL (D-4) → skema final tinggal dirancang, bukan dipertanyakan.
- Pengecualian peta batas wilayah/GIS = FINAL (D-9) → tidak dipertanyakan lagi.
- Chart.js = FINAL diperbolehkan (D-7) → tidak dipertanyakan lagi.
- Dry-run sebelum impor = **TIDAK BERLAKU** — impor historis ditiadakan (**D-8 digantikan oleh D-15**); bukan pilihan maupun persyaratan.
- Admin monitoring-only & hanya Super Admin yang mengubah konfigurasi = FINAL (D-3, D-10).

### 1.2.1 Keputusan pemilik proyek — Phase 4E (FINAL, mengikat dokumentasi)

> Ditambahkan pada **Phase 4E (pembaruan dokumentasi)**. Keputusan ini **melengkapi** D-1…D-10 dan **mengikat**. Jangan mengarang keputusan tambahan di luar ini; hal yang belum diputuskan tetap menjadi **pertanyaan terbuka** (§6.1).

| # | Keputusan pemilik (FINAL — Phase 4E) |
|---|---|
| **D-11** | **Horizon prakiraan = 3 hari.** Prioritaskan **API BMKG yang gratis** untuk prakiraan cuaca daratan. **Jangan** memilih OpenWeatherMap berbayar atau layanan berbayar lain. **Jangan** mengasumsikan API cuaca umum BMKG otomatis menyediakan data gelombang laut. |
| **D-12** | **Zona waktu operasional & tampilan = `Asia/Makassar` (WITA).** Waktu **pengamatan** (`observed_at`), **penerbitan prakiraan** (`issued_at`), dan **sinkronisasi** (`synced_at`) **dibedakan**. Konversi waktu sumber **secara konsisten ke WITA** untuk tampilan. Konvensi **penyimpanan** timestamp **belum ditentukan memadai** oleh keputusan pemilik → tetap **keputusan teknis yang perlu diklarifikasi (O-15)**; jangan mengarang. |
| **D-13** | **Ambang WINDY lama = nilai awal spesifikasi, bukan ambang yang sudah divalidasi secara operasional.** Hujan 20/50; kecepatan angin 25/40 km/jam; tinggi gelombang laut 1,5/2,0 m. Prioritas status **SIAGA > WASPADA > NORMAL**. Untuk **hujan**, **satuan & periode pengukuran wajib diperjelas** sebelum implementasi (**jangan** membandingkan mm/3 jam dengan mm/hari secara langsung). **Status gelombang hanya boleh dihitung bila tersedia data gelombang numerik yang terverifikasi & kompatibel.** **Dilarang** menghasilkan nilai sintetis, mengarang tinggi gelombang, atau menampilkan nilai default seolah pengamatan nyata; bila data tidak tersedia → **"Data tidak tersedia"**/perilaku aman setara. |
| **D-14** | **Prioritaskan sumber data gratis & resmi.** **Teliti** API prakiraan cuaca **perairan BMKG** sebagai kandidat sumber gelombang — **tanpa** mengklaim endpoint/skema/parameter/lisensi/ketersediaan sudah terverifikasi. **Pertahankan embed Windy.com** sebagai **visualisasi peta interaktif yang terpisah** dari sumber data backend; `embed2.html` **bukan** otomatis API data terstruktur untuk Laravel. **Jangan** memakai Windy Point Forecast API berbayar. **Patuhi kewajiban atribusi & lisensi** penyedia (hasil Phase 4A); **jangan** menghapus kewajiban atribusi BMKG/OpenWeatherMap yang sudah tercatat. |
| **D-15** | **Tidak ada impor historis Google Sheets.** **Jangan** membaca/menyalin/mengimpor/mengubah/menghapus data Google Sheets. Pengumpulan data baru **baru dimulai setelah implementasi & persetujuan release gate**. |
| **D-16** | **Sinkronisasi otomatis terjadwal & sinkronisasi manual sama-sama direncanakan.** Hanya **Super Admin** memicu sinkronisasi manual & mengelola konfigurasi WINDY; **Admin hanya memantau**; **publik hanya melihat data yang tersedia**. Sinkronisasi harus menangani **kegagalan API**, mencatat hasil **secara aman**, dan **mencegah eksekusi bersamaan**. **Scheduler hosting belum terverifikasi** → **jangan** menyatakan sinkronisasi otomatis siap produksi sampai lingkungan hosting & pemicu scheduler dikonfirmasi. **Jangan** menetapkan interval sinkronisasi tanpa dasar kebijakan penyedia & keputusan terdokumentasi. |
| **D-17** | **Pertahankan stack & pola arsitektur SuperBie** (Routes → Form Request → Controller → Service → Model → MySQL; Blade/Alpine/Vite; mekanisme audit/cache/config existing). **Jangan** menambah framework/package/library/service/queue worker/job infrastructure/database baru/teknologi lain. **Jangan** Docker. **Jangan** menghidupkan kembali GIS/peta batas wilayah/GeoJSON/`boundary.html`. Sistem internal tetap terlindungi autentikasi + otorisasi. **Jangan** menyalin `XFrameOptionsMode.ALLOWALL`. **Empat role tetap.** |

**Konsekuensi keputusan Phase 4E:**
- **O-4 (sumber prakiraan 7 hari) → ditutup:** horizon = **3 hari** dari **BMKG gratis** (D-11). Prakiraan 7 hari **tidak** dalam lingkup.
- **O-3 (OWM) → diarahkan:** prioritaskan **BMKG gratis**; **OWM berbayar dilarang** (D-11). Bila OWM gratis tetap dipakai, **kewajiban atribusi OWM tetap berlaku** (D-14).
- **O-6 (impor historis) → ditutup:** **tidak ada impor historis Google Sheets** (D-15).
- **O-5 (akses Sheets) → tidak lagi diperlukan** karena impor historis ditiadakan (D-15).
- **O-14 (ambang) → sebagian:** nilai lama dicatat sebagai **nilai awal spesifikasi**; **satuan/periode hujan** dan **verifikasi data gelombang** tetap terbuka (D-13).
- **O-15 (zona waktu) → sebagian:** operasional & tampilan = **WITA** (D-12); **konvensi penyimpanan** timestamp tetap **perlu klarifikasi teknis**.
- **O-13 (strategi sinkronisasi) → diarahkan:** terjadwal + manual (D-16); eksekusi terjadwal bergantung **O-7**.

### 1.3 Catatan atas menu landing page (D-1)

- Menu **Beranda** dan **Lapor Pak Wali** sudah ada di SuperBie saat ini (`route('home')` → `resources/views/public/landing.blade.php`).
- Menu **WINDY** adalah objek migrasi dokumen ini.
- Menu **SIPHP** dan **JDIH** disebut pemilik sebagai bagian navigasi landing page, tetapi **di luar ruang lingkup migrasi WINDY ini** dan **tidak dianalisis** di sini. **Jangan mengklaim integrasi atau status implementasinya tanpa bukti** → **BELUM DIVERIFIKASI**; statusnya menjadi **TERBUKA (O-11)**. **Phase 2 (read-only) memverifikasi:** **tidak ada** route/view/modul SIPHP maupun JDIH di repository saat ini → keduanya **hanya label navigasi**, bukan fitur berfungsi. Keputusan navigasi final menunggu **O-11 (NEEDS AUTHORITY/POLICY INPUT)**.

### 1.4 Ruang lingkup

**Termasuk:**
- Halaman publik WINDY tanpa login, baca-saja (D-2).
- Integrasi API cuaca nyata **BMKG gratis** (D-5, D-11).
- Status risiko NORMAL/WASPADA/SIAGA + rekomendasi publik.
- **Prakiraan 3 hari** dari **API BMKG gratis** (D-5, D-11; horizon 3 hari).
- Riwayat cuaca tersimpan tervalidasi **yang dikumpulkan setelah implementasi** (D-4, D-15) — **bukan** impor historis.
- Chart.js (D-7) dan embed Windy.com (D-6, D-14).
- Konfigurasi Super Admin + sinkronisasi manual internal + sinkronisasi otomatis terjadwal (D-3, D-16).

**Dikecualikan (FINAL):**
- ❌ **Peta batas wilayah** (`boundary.html`, GeoJSON ADM2, proyeksi Web Mercator, SVG polygon) dan **seluruh implementasi GIS** (D-9, D-17).
- ❌ Penambahan role baru (D-10, D-17).
- ❌ Menyalin akses anonim penuh & `XFrameOptions ALLOWALL` dari WINDY lama (temuan keamanan S-1, S-2 pada Audit; D-17).
- ❌ Menyalin rumus prakiraan sintetis (`Math.sin/cos`) sebagai data aktual (D-5, D-13).
- ❌ Menu SIPHP/JDIH (di luar lingkup, §1.3).
- ❌ **Impor historis Google Sheets** (D-15) — tidak ada pembacaan/penyalinan/impor/perubahan/penghapusan data Sheets.
- ❌ **Prakiraan 7 hari** (D-11: horizon = 3 hari).
- ❌ **OpenWeatherMap berbayar** / layanan berbayar lain (D-11); **Windy Point Forecast API berbayar** (D-14).

### 1.5 Yang tidak boleh disalahtafsirkan

> **Persetujuan atas dokumen perencanaan ini BUKAN izin untuk mengubah kode.** Implementasi hanya boleh dimulai setelah gerbang persetujuan §6 terpenuhi, termasuk pembaruan dokumen tata kelola (§6.2).

---

## 2. Prinsip Migrasi

1. **Acuan perilaku, bukan cetak biru teknis.** WINDY lama dipakai sebagai referensi kebutuhan bisnis; dibangun ulang mengikuti pola Laravel SuperBie (Service, Form Request, middleware `role:*`, enum, cache service, audit log).
2. **Tidak mereplikasi bug.** Beberapa perilaku lama adalah cacat nyata (Audit §3.11) dan **tidak boleh** disalin.
3. **Integritas data diutamakan.** Tidak ada angka sintetis/hardcode/nilai default yang ditampilkan sebagai data aktual (D-5; Audit temuan K-7).
4. **Keamanan dibangun dari nol.** WINDY lama tanpa autentikasi; model akses dirancang ulang memakai 4 role tetap (D-10).
5. **Perubahan aditif.** Rencana implementasi menyentuh file existing seminimal mungkin; perubahan disengaja dan terdaftar.
6. **Belum terverifikasi ditandai jujur.** Item yang belum diperiksa ditandai `BELUM DIVERIFIKASI` dan tidak dianggap fakta.
7. **Keputusan tidak dikarang.** Ketidakpastian diselesaikan sebagai usulan atau pertanyaan terbuka, bukan dengan asumsi yang dipresentasikan sebagai fakta.

---

## 3. Klasifikasi Keputusan

### 3.1 Keputusan FINAL (dari pemilik)

Lihat §1.2 (D-1…D-10) dan §1.2.1 (D-11…D-17). Keputusan ini **tidak boleh diubah** demi menyelaraskan dokumen.

### 3.2 Usulan teknis [USULAN] (belum disetujui)

Ringkas; rincian di `WINDY_TARGET_ARCHITECTURE.md` dan `WINDY_DATA_MAPPING.md`:

| # | Usulan teknis | Status |
|---|---|---|
| U-1 | Nama route/kelas/controller/service/adapter | [USULAN] — menunggu F3 |
| U-2 | Skema tabel (`weather_observations`, dll.) & kolom | [USULAN] — menunggu F3 |
| U-3 | Strategi sinkronisasi (terjadwal + manual internal) | [USULAN] — **arah FINAL (D-16)**; detail & eksekusi terjadwal menunggu O-7 |
| U-4 | Kebijakan waktu: tampilan WITA; konvensi **penyimpanan** timestamp | **Tampilan = FINAL (D-12, WITA)**; penyimpanan [USULAN] — TERBUKA (O-15) |
| U-5 | Model ambang risiko per parameter | [USULAN] — **nilai awal = legacy (D-13)**; satuan/periode hujan & gelombang TERBUKA (O-14) |
| U-6 | Penyimpanan konfigurasi (`app_settings` vs tabel baru) | [USULAN] — TERBUKA (O-16) |
| U-7 | Kunci cache & strategi invalidasi | [USULAN] |
| U-8 | Metadata batch impor & strategi transaksi | **TIDAK BERLAKU** — impor historis ditiadakan (D-15) |

### 3.3 Pertanyaan terbuka (TERBUKA) — lihat §6.1

### 3.4 Gerbang persetujuan (GERBANG) — lihat §6.2

---

## 4. Tahapan Migrasi

Rincian arsitektur → `WINDY_TARGET_ARCHITECTURE.md`; data → `WINDY_DATA_MAPPING.md`; akses → `WINDY_ACCESS_MATRIX.md`; kriteria uji → `WINDY_ACCEPTANCE_CRITERIA.md`.

| Fase | Nama | Hasil (deliverable) | Dependensi | Gerbang |
|---|---|---|---|---|
| **F0** | Audit & inventarisasi | `WINDY_MIGRATION_AUDIT.md` | — | ✅ Selesai |
| **F1** | **Perencanaan & keputusan pemilik** (dokumen ini) | 5 dokumen perencanaan + jawaban pemilik atas pertanyaan terbuka (§6.1) | Audit | 🔴 **Gerbang utama** |
| **F2** | **Pembaruan tata kelola** | Perubahan `rules.md`, `prd.md`, `design.md`, `architecture.md`, `schema.md` (+ `README.md`, `PROGRESS.md`) | Persetujuan F1 | ✅ **Selesai (Phase 3)** — implementasi tetap tergerbang |
| **F3** | Desain teknis rinci & kontrak API | Skema tabel final, kontrak payload, validasi bentuk respons API nyata | F2 | 🟠 Gerbang skema |
| **F4** | Implementasi backend Laravel | Service, adapter API, controller, Form Request, enum, migration/model, config, route (aditif) | F3 | 🟠 Gerbang sebelum menyentuh file existing |
| **F5** | Implementasi frontend Blade/JS | View, komponen, Chart.js, embed Windy.com | F4 | 🟠 Gerbang tampilan & dependency |
| **F6** | Pengujian fitur/keamanan/regresi | Suite test lulus | F4–F5 | — |
| **F7** | ~~Dry-run & impor data historis~~ **DITIADAKAN (D-15)** | **Tidak ada impor historis Google Sheets.** Pengumpulan data baru dimulai setelah implementasi & persetujuan release gate. | — | — |
| **F8** | Validasi terhadap perilaku lama | Perbedaan perilaku terdokumentasi & disetujui | F6; akses web app lama | 🟠 Gerbang penerimaan perbedaan |
| **F9** | Peluncuran & rollback | Rencana deploy + rollback disetujui | F8 | 🔴 **Gerbang peluncuran** |

> **Urutan fase dan gerbang dipertahankan.** Implementasi (F4–F5) **tidak boleh** dimulai sebelum F1 (persetujuan lingkup) dan F2 (revisi tata kelola yang diperlukan) selesai.

---

## 5. Detail Tahapan

### F0 — Audit & Inventarisasi ✅ SELESAI

- **Hasil:** Audit lengkap 15 file WINDY; arsitektur lama direkonstruksi; 16 fitur dipetakan; 30+ komponen dipetakan ke kandidat Laravel.
- **Dampak file:** Tidak ada (laporan di luar repository).

### F1 — Perencanaan & Keputusan Pemilik ⛔ **GERBANG UTAMA**

- **Prasyarat:** Audit ditinjau pemilik.
- **Aktivitas:** Menyusun/merevisi 5 dokumen perencanaan; mengajukan pertanyaan terbuka (§6.1) untuk dijawab tertulis.
- **Hasil:** 5 dokumen perencanaan; jawaban tertulis pemilik.
- **Dampak file:** Tidak ada (dokumen di folder audit eksternal).
- **Risiko:** Melanjutkan tanpa keputusan = melanggar aturan proyek sendiri (Audit K-1).
- **Kriteria penerimaan:** Seluruh pertanyaan §6.1 terjawab tertulis; pemilik menyetujui arah perencanaan.
- **Gerbang persetujuan:** 🔴 **WAJIB.**

### F2 — Pembaruan Tata Kelola ✅ **SELESAI (Phase 3)**

- **Prasyarat:** F1 disetujui.
- **Aktivitas:** Memperbarui dokumen tata kelola agar modul cuaca tidak lagi berada di luar ruang lingkup. **Dilaksanakan Phase 3:** `rules.md §1`, `prd.md §1/§3/F-015/F-019`, `design.md §1`, `architecture.md` (Scope, §11, §7, §15), `schema.md` (scope note + TODO), serta `README.md` & `PROGRESS.md`.
- **Hasil:** Dokumen tata kelola yang konsisten dengan D-1…D-17 dan status O-1…O-16.
- **Dampak file:** **Dokumen tata kelola** (`.md`) saja — **tidak menyentuh kode/database**.
- **Catatan:** Implementasi **tetap tergerbang** oleh pertanyaan terbuka (§6.1) dan gerbang F3–F9.
- **Gerbang persetujuan:** ✅ Diperbarui (O-1).

### F3 — Desain Teknis Rinci & Kontrak API

- **Prasyarat:** F2 selesai.
- **Aktivitas:** Menetapkan skema tabel final; kontrak payload internal; **verifikasi bentuk respons nyata** API BMKG (**3 hari**); menetapkan strategi sinkronisasi (**D-16: terjadwal + manual**), kebijakan waktu (**tampilan WITA — D-12**; konvensi penyimpanan — O-15), dan nilai ambang risiko (**nilai awal — D-13**; final — O-14).
- **Catatan verifikasi:** Bentuk respons BMKG **BELUM DIVERIFIKASI** (Audit U-3); keberadaan/nilai API key **BELUM DIVERIFIKASI** (Audit U-2). Verifikasi membutuhkan panggilan jaringan nyata dan **API key dari pemilik** → tidak dilakukan pada tahap ini.
- **Dampak file:** Tidak ada (dokumen desain).
- **Gerbang persetujuan:** 🟠 Ya — skema & kontrak.

### F4 — Implementasi Backend Laravel

- **Prasyarat:** F3 selesai.
- **Hasil (semua [USULAN]):** lihat `WINDY_TARGET_ARCHITECTURE.md` §3.
- **Dampak file:** File **baru** + perubahan **aditif** pada file existing (`routes/web.php`, `routes/console.php` bila job disetujui, partial nav, `config/services.php` bila perlu).
- **Risiko:** `routes/web.php` **sudah modified** di working tree → **jangan menimpa perubahan yang ada** (Audit §5.9).
- **Gerbang persetujuan:** 🟠 Ya — sebelum mengubah file existing.

### F5 — Implementasi Frontend Blade/JS

- **Prasyarat:** F4 selesai.
- **Dampak file:** View/komponen baru + modifikasi partial nav; `resources/js/app.js` (import) aditif.
- **Risiko:** Konflik design system (WINDY lama gelap/Bootstrap vs SuperBie terang/Tailwind — Audit K-4); test shell dashboard yang mengunci bentuk (`GeographicDashboardTest`, Progressive Navigation).
- **Gerbang persetujuan:** 🟠 Ya — tampilan & dependency (Chart.js).

### F6 — Pengujian

- **Prasyarat:** F4–F5 selesai.
- **Kriteria lengkap:** `WINDY_ACCEPTANCE_CRITERIA.md`.
- **Dampak file:** Test baru di `tests/Feature/*`.
- **Gerbang persetujuan:** Tidak (kecuali menyentuh test existing).

### F7 — Dry-Run & Impor Data Historis ⛔ **DITIADAKAN (D-15)**

- **Keputusan pemilik (FINAL, D-15):** **tidak ada impor historis Google Sheets.** Tidak ada pembacaan/penyalinan/impor/perubahan/penghapusan data Sheets.
- **Konsekuensi:** akses Sheets (O-5) dan pertanyaan impor (O-6) **ditutup**; metadata batch impor (U-8) **tidak berlaku**.
- **Pengumpulan data baru** (observasi/prakiraan) **baru dimulai setelah implementasi & persetujuan release gate** — bukan impor historis.
- **Dampak file:** tidak ada artefak impor.
- **Gerbang persetujuan:** — (tidak berlaku).

### F8 — Validasi terhadap Perilaku Lama

- **Prasyarat:** F6 selesai; web app lama masih dapat diakses.
- **Risiko:** Deployment URL & status web app lama **BELUM DIVERIFIKASI** (Audit U-4) → validasi mungkin **tidak dapat** dilakukan; nyatakan eksplisit bila demikian.
- **Gerbang persetujuan:** 🟠 Ya — menerima perbedaan perilaku yang disengaja (mis. prakiraan nyata menggantikan sintetis).

### F9 — Peluncuran & Rollback 🔴 **GERBANG**

- **Prasyarat:** F8 selesai.
- **Catatan:** Target deployment **BELUM DIVERIFIKASI/DITENTUKAN** (Audit §9 Fase 8). CI existing (`rules.md §9A`) **tidak boleh** menambah langkah deploy otomatis.
- **Gerbang persetujuan:** 🔴 **WAJIB** — target deployment & jadwal scheduler (bila **sinkronisasi terjadwal** diadopsi — **D-16**; host/pemicu belum terverifikasi — **O-7**).

---

## 6. Gerbang Persetujuan Tata Kelola

### 6.1 Pertanyaan terbuka (TERBUKA) — status Phase 3/4E

> Daftar ini **sudah dibersihkan**: pertanyaan yang jawabannya sudah FINAL (MySQL, pengecualian GIS/peta batas, izin Chart.js, kewajiban dry-run) **dihapus** dari daftar terbuka. **Phase 3 (penyelarasan tata kelola)** menambahkan **status bukti** untuk tiap pertanyaan memakai kosakata Phase 2: `CONFIRMED BY LOCAL SOURCE`, `NEEDS EXTERNAL VERIFICATION`, `NEEDS AUTHORITY/POLICY INPUT`, `NEEDS HOSTING/ENVIRONMENT VERIFICATION`, `NEEDS DATA OWNER ACCESS`, `READY FOR SPECIFICATION`, `BLOCKED`. **Phase 4E** menambahkan status `CLOSED — diputuskan (D-n)` / `CLOSED — tidak berlaku (D-n)` untuk item yang ditutup oleh keputusan pemilik D-11…D-17. **"Disetujui arahnya" ≠ "terverifikasi sebagai fakta."**

| # | Pertanyaan terbuka | Status (Phase 3/4E) | Catatan penyelarasan (Phase 3/4E) | Blokir? |
|---|---|---|---|---|
| **O-1** | Persetujuan formal perubahan lingkup & dokumen tata kelola mana yang diperbarui (Audit Q-1) | **READY FOR SPECIFICATION — tata kelola diperbarui** | `rules.md`, `prd.md`, `design.md`, `architecture.md`, `schema.md`, `README.md`, `PROGRESS.md` **telah diperbarui** pada Phase 3 agar WINDY (dashboard cuaca publik) masuk lingkup. Implementasi **tetap tergerbang** oleh O-n lain. | 🟠 (turunan) |
| **O-2** | Batas rinci akses publik: seluruh bagian WINDY anonim, atau ada yang dibatasi? | **NEEDS AUTHORITY/POLICY INPUT** | Arah D-2 (baca-saja, tanpa login) disetujui; rincian bagian yang dibatasi belum ditetapkan. | 🔴 YA |
| **O-3** | Apakah OpenWeatherMap tetap dipakai? Siapa menyediakan API key & menyetujui kuota/biaya? | **NEEDS AUTHORITY/POLICY INPUT** (dipersempit oleh D-11) | **D-11:** prioritaskan **BMKG gratis**; **OWM berbayar dilarang**. Bila OWM **gratis** tetap dipakai, kuota/biaya & **atribusi OWM (D-14)** tetap harus dipenuhi. | 🟠 YA (bila OWM gratis dipakai) |
| **O-4** | Sumber prakiraan 7 hari (BMKG/OWM) & apakah menyediakan seluruh rentang? | **CLOSED — diputuskan (D-11)** | **Horizon = 3 hari** dari **API BMKG gratis**. Prakiraan 7 hari **di luar lingkup**. | — |
| **O-5** | Akses Google Sheets aktual + ekspor untuk impor? Siapa memvalidasi? | **CLOSED — tidak berlaku (D-15)** | **Tidak ada impor historis Google Sheets**; akses Sheets tidak lagi diperlukan. | — |
| **O-6** | Apakah data historis perlu diimpor sama sekali? | **CLOSED — diputuskan (D-15)** | **Tidak ada impor historis.** Pengumpulan data baru dimulai setelah implementasi & persetujuan release gate. | — |
| **O-7** | Apakah job/sinkronisasi terjadwal diperlukan & siapa menjalankan scheduler di host? | **NEEDS HOSTING/ENVIRONMENT VERIFICATION** | `rules.md §9A`: scheduler = tanggung jawab host; keberadaan cron host **belum diverifikasi**. | 🟠 YA (bila job) |
| **O-8** | Kebijakan CSP/privasi untuk embed Windy.com | **NEEDS AUTHORITY/POLICY INPUT** (teknis dapat dispesifikasikan) | **Kebijakan allowlist/privasi final BELUM ditetapkan** — status ini **tidak** berarti kebijakan CSP/privasi embed telah selesai. Teknis dapat dispesifikasikan, tetapi repo **belum** memiliki CSP/`frame-ancestors`/`X-Frame-Options`. | 🟠 YA |
| **O-9** | Apakah teks rekomendasi perangkat daerah (instansi spesifik) sudah final & disetujui? | **NEEDS AUTHORITY/POLICY INPUT** | Teks lama hardcode nama instansi; **bukan** teks kebijakan final. | 🟠 YA |
| **O-10** | Apakah logo (`logo.html`) adalah logo resmi yang boleh dipakai? | **NEEDS AUTHORITY/POLICY INPUT** | Logo lama = data-URI base64 tanpa bukti asal-usul/lisensi. | 🟠 YA |
| **O-11** | Status menu SIPHP & JDIH (dikelola internal, tautan eksternal, atau label?) | **NEEDS AUTHORITY/POLICY INPUT** | Phase 2: **tidak ada** route/view SIPHP/JDIH di repo → hanya label navigasi. | 🟠 YA |
| **O-12** | Apakah Admin memperoleh halaman monitoring internal WINDY? | **NEEDS AUTHORITY/POLICY INPUT** | Keputusan, bukan otomatis wajib; Admin tetap monitoring-only. | 🟠 YA |
| **O-13** | Strategi sinkronisasi: terjadwal, manual internal, atau kombinasi? | **CLOSED — arah diputuskan (D-16)** | **Terjadwal + manual** direncanakan; detail interval & eksekusi terjadwal bergantung **O-7** (host) & kebijakan penyedia. Jangan tetapkan interval tanpa dasar. | 🔴 YA (eksekusi terjadwal) |
| **O-14** | Nilai ambang risiko final per parameter & penanganan parameter tanpa sumber nyata | **SEBAGIAN CLOSED** (dipersempit oleh D-13) | **D-13:** nilai lama = **nilai awal spesifikasi** (bukan tervalidasi operasional). **Hujan:** `tp` = **mm/3 jam** terverifikasi (O-14a). **Gelombang:** **TERSEDIA & terverifikasi** via API maritim BMKG (O-14b; wilayah `P.AE.01`). Ambang **final** masih butuh validasi operasional. | 🟠 YA (validasi ambang) |
| **O-15** | Kebijakan zona waktu: **tampilan** WITA (FINAL) & **konvensi penyimpanan** timestamp | **NEEDS AUTHORITY/POLICY INPUT** — **tampilan = FINAL (D-12)**; **penyimpanan = keputusan teknis yang perlu diklarifikasi** | **D-12:** operasional & tampilan = **`Asia/Makassar` (WITA)**; waktu pengamatan/penerbitan/sinkronisasi dibedakan; konversi sumber konsisten ke WITA. **Konvensi penyimpanan belum ditentukan memadai** → tetap terbuka; jangan mengarang. | 🟠 YA (untuk penyimpanan) |
| **O-16** | Penyimpanan konfigurasi WINDY: `app_settings` (existing) atau tabel khusus? | **READY FOR SPECIFICATION** | Pola `AppSettingService`/`app_settings` tersedia & terverifikasi di repo. | 🟠 YA |

### 6.2 Pernyataan gerbang

- **F2 (pembaruan tata kelola) telah dilaksanakan pada Phase 3** untuk lingkup WINDY (O-1): dokumen tata kelola diperbarui agar modul cuaca publik tidak lagi berada di luar lingkup. Perubahan **hanya** pada dokumen (`.md`); **tidak ada** kode/database yang disentuh.
- **Phase 4E mencatat keputusan pemilik D-11…D-17** (§1.2.1) ke dokumentasi. Ini **keputusan pemilik**, bukan usulan tim desain.
- **Persetujuan arah ≠ verifikasi fakta.** **O-3** (OWM gratis bila dipakai), **O-7** (host scheduler) **belum** terverifikasi; **API perairan BMKG belum diteliti** (D-14) → **tidak** boleh diklaim terverifikasi.
- **Pola implementasi yang BELUM terverifikasi di repo (Phase 2/4C):** `Cache::lock`, integrasi HTTP cuaca (`Http::`), background job/queue worker yang benar-benar dipakai, serta CSP/security headers. Rencana yang bergantung padanya wajib menandai **NEEDS HOSTING/ENVIRONMENT VERIFICATION**.
- **Spesifikasi BELUM siap implementasi** selama gerbang rilis wajib (**`WINDY_ACCEPTANCE_CRITERIA.md §7` — REL-9**) belum terpenuhi — termasuk verifikasi API, **satuan/periode ambang hujan**, **data gelombang**, **atribusi/lisensi**, kebijakan CSP/privasi, dan **host scheduler**.
- **Persetujuan atas dokumen perencanaan ini bukan izin untuk mengubah kode.**

---

## 7. Checklist Penyelesaian Desain (sebelum implementasi)

> Checklist ini merujuk masalah **data, API, keamanan, cache, dan impor**. Seluruh item harus tercentang (atau ditandai sengaja sebagai "tidak berlaku" dengan alasan) sebelum F4 dimulai.

### 7.1 Data
- [ ] Skema tabel final disetujui; pemisahan observasi / agregat historis / prakiraan / alert / metadata sumber (lihat `WINDY_DATA_MAPPING.md`).
- [ ] Semantik `Wind_Max` (agregat) tidak disamakan dengan observasi sesaat.
- [ ] Nilai default lama (mis. hujan `5.0`) ditandai **bukan pengukuran aktual** sampai sumber & semantiknya dibuktikan.
- [ ] Strategi identitas sumber (foreign key) konsisten.
- [ ] Unique constraint & strategi idempotensi per entitas diusulkan & divalidasi.

### 7.2 API
- [ ] Bentuk respons BMKG terverifikasi dengan panggilan nyata (**horizon 3 hari — D-11**).
- [ ] Keputusan penggunaan OWM **gratis** (bila dipakai) ditetapkan; **OWM berbayar dilarang (D-11)**.
- [ ] Validasi payload (lengkap/tidak lengkap/tidak valid) dirancang.
- [ ] Penanganan sumber yang tidak menyediakan seluruh rentang prakiraan dirancang.
- [ ] Penanganan parameter tidak tersedia saat menghitung status risiko dirancang (bukan dianggap 0/aman).
- [ ] **Satuan & periode pengukuran hujan diperjelas (D-13)** — jangan bandingkan mm/3 jam dengan mm/hari.
- [ ] **Kandidat API prakiraan cuaca perairan BMKG diteliti untuk data gelombang (D-14)** — ✅ **SELESAI (2026-10-15):** API maritim BMKG `maritim.bmkg.go.id/marine2026-data/perairan/{KODE}.json` **terverifikasi** (field `wave_height` meter + `wave_cat`); wilayah Parepare = `P.AE.01` (point-in-polygon). Lihat `WINDY_IMPLEMENTATION_REPORT.md §1.1`.
- [ ] **Struktur API maritim diuji lintas wilayah** — ✅ **SELESAI (2026-10-15):** **seluruh 375 wilayah** (38 provinsi) diuji → 375/375 HTTP 200; struktur konsisten; **2 variasi nyata** ditangani parser (1 wilayah tanpa blok stasiun, 12 wilayah `wind_gust` NULL); parser diperkeras (grup `forecast_*` dinamis, `wind_gust` NULL tetap NULL, waktu ISO/UTC, angka string/koma). Lihat `WINDY_IMPLEMENTATION_REPORT.md §1.1.1`.
- [ ] **Dukungan lebih dari satu wilayah pantau** — ✅ **SELESAI (2026-10-15):** daftar lokasi berurutan di `config/weather.php` (`locations`), tiap lokasi membawa `adm4` **dan** `marine_area_code` sendiri; sinkronisasi, gelombang, cache, dan rute bersifat **per lokasi** (`/cuaca/{key}`). **DIPERBARUI (2026-10-15):** atas permintaan pemilik terbaru ("publik bisa memilih wilayah pantau dari 4 kecamatan dan juga bisa memilih parepare"), kini **5 wilayah** dikonfigurasi — Kota Parepare (default) + 4 kecamatan (Ujung, Soreang, Bacukiki Barat, Bacukiki); semua `adm4` & `marine_area_code` **terverifikasi live**. Kecamatan pedalaman (Bacukiki, Bacukiki Barat) → `marine_area_code = null` ("Data tidak tersedia"). Lihat `WINDY_IMPLEMENTATION_REPORT.md §1.2 & §1.6`.
- [ ] **Rincian prakiraan gelombang per hari di halaman publik** — ✅ **SELESAI (2026-10-15):** bagian "Prakiraan Gelombang Laut" pada `/cuaca`; slot dikelompokkan per **hari kalender WITA**, dipotong `forecast_days`, `wave_cat` = kategori slot tertinggi (label BMKG, tanpa peringkat buatan); hanya dari baris `wave_forecasts` tersimpan. Lihat `WINDY_IMPLEMENTATION_REPORT.md §1.3`.
- [ ] **Tata letak halaman cuaca + peta berlapis + grafik 3 hari** — ✅ **SELESAI (2026-10-15):** `/cuaca` disusun ulang mengikuti tampilan WINDY lama (3 tab: Ringkasan Eksekutif / Peta Cuaca / Prakiraan & Analitik); peta Windy berlapis (angin/hujan/awan/suhu/gelombang); grafik 3 hari (SVG inline, tanpa pustaka baru). **Tema terang** mengikuti halaman situs lain — pemilik memutuskan **tidak** memakai nuansa gelap "command center". **Tidak** menampilkan tekanan udara/peluang hujan % (tidak ada di BMKG), horizon 7 hari (D-11=3), atau teks rekomendasi instansi (O-9). Lihat `WINDY_IMPLEMENTATION_REPORT.md §1.4`.
- [ ] **Perbaikan "Alpine Expression Error: tab is not defined"** — ✅ **SELESAI (2026-10-15):** scope Alpine `tab` dipindah **ke dalam `#main-content`** (agar ikut tersalin oleh lapisan navigasi progresif Prompt 26 yang hanya menukar `#main-content`), jam header dibuat *self-contained*, dan **`/cuaca` dikecualikan** dari lapisan navigasi (`resources/js/navigation.js`) sehingga selalu navigasi penuh. Uji regresi ditambahkan. Lihat `WINDY_IMPLEMENTATION_REPORT.md §1.5`.

### 7.3 Keamanan
- [ ] Model akses publik baca-saja & endpoint internal terproteksi disetujui (`WINDY_ACCESS_MATRIX.md`).
- [ ] Rate limit, CSRF, otorisasi, audit log, escaping output ditetapkan.
- [ ] Penyimpanan secret (`.env`/`config`) ditetapkan; **tidak ada** rahasia ke browser.
- [ ] Kebijakan embed Windy.com & keamanan browser (CSP) ditetapkan (O-8).
- [ ] **Kewajiban atribusi & lisensi BMKG/OpenWeatherMap dipenuhi (D-14)** dan tercatat di kriteria penerimaan.

### 7.4 Cache
- [ ] Kunci cache & TTL ditetapkan; invalidasi setelah perubahan konfigurasi dirancang.
- [ ] Penanganan cache kedaluwarsa saat API gagal dirancang.
- [ ] Penanganan kondisi cache belum pernah terisi dirancang.
- [ ] Strategi sinkronisasi (terjadwal + manual — D-16) & perlindungan terhadap dua sinkronisasi bersamaan dirancang.

### 7.5 Impor
- [x] **DITIADAKAN (D-15):** tidak ada impor historis Google Sheets. Item impor tidak berlaku.

---

## 8. Hasil Setiap Tahap (Definition of Done)

| Fase | Definition of Done |
|---|---|
| F1 | 5 dokumen perencanaan tersedia; O-1…O-16 terjawab tertulis (atau ditandai sengaja ditunda dengan alasan) |
| F2 | Dokumen tata kelola diperbarui & disetujui; konsisten dengan D-1…D-17 |
| F3 | Skema tabel + kontrak payload disetujui; bentuk respons API terverifikasi; strategi sinkronisasi & **kebijakan waktu tampilan** ditetapkan; **satuan/periode hujan & kandidat data gelombang ditinjau** |
| F4 | Endpoint berjalan; otorisasi server-side; tanpa secret di repo; test backend lulus |
| F5 | UI sesuai `design.md`; tanpa fake metric; navigasi per-role benar |
| F6 | Seluruh suite lulus; tanpa penurunan cakupan |
| F7 | ~~Laporan dry-run disetujui; impor tervalidasi; rekonsiliasi selesai~~ **DITIADAKAN (D-15)** |
| F8 | Perbedaan perilaku terdokumentasi & disetujui |
| F9 | Rencana rollback disetujui; tidak ada deploy otomatis tanpa keputusan |

---

## 9. Larangan Implementasi Sebelum Persetujuan

> **Sampai gerbang F3–F9 terpenuhi, DILARANG:**
> - Menulis/mengubah kode, konfigurasi, migration, route, controller, model, view, JS, CSS, aset, atau file sumber WINDY.
> - Mengubah database atau menjalankan migration/seeder/impor/pemanggilan API eksternal.
> - **Membaca/menyalin/mengimpor/mengubah/menghapus data Google Sheets (D-15).**
> - Menyatakan integrasi/API/data historis/trigger terverifikasi tanpa bukti (O-3, O-7).
> - Menghapus/memindahkan/mengganti nama/menimpa file yang ada.
> - Operasi Git destruktif (`add`, `commit`, `push`, `reset`, `clean`, `checkout` yang membuang perubahan).
> - Memasukkan folder `WINDY/` ke staging atau mengubah status tracking-nya.
> - Mengaktifkan kembali implementasi GIS/peta batas wilayah.
> - Menyimpan API key/token/kredensial dalam dokumen.
> - Menyajikan nilai sintetis, tinggi gelombang yang dikarang, atau nilai default sebagai pengamatan nyata (D-13).

---

## 10. Ringkasan

- Migrasi WINDY direncanakan sebagai **fitur cuaca publik tanpa login, baca-saja** (D-2) + **konfigurasi & sinkronisasi manual internal Super Admin** (D-3), dengan **MySQL** (D-4), **API BMKG gratis** (D-5, D-11), **embed Windy.com** (D-6, D-14), **Chart.js** (D-7).
- **Horizon prakiraan = 3 hari** dari BMKG gratis (D-11); **OWM berbayar dilarang**; **prakiraan 7 hari di luar lingkup**.
- **Zona waktu operasional & tampilan = WITA** (D-12); **konvensi penyimpanan timestamp masih perlu klarifikasi teknis (O-15)**.
- **Ambang lama = nilai awal spesifikasi** (D-13); **satuan/periode hujan** & **verifikasi data gelombang** masih terbuka.
- **Tidak ada impor historis Google Sheets** (D-15); pengumpulan data baru dimulai setelah implementasi & release gate.
- **Peta batas wilayah/GIS dikecualikan** (D-9, D-17). **Empat role tetap** (D-10, D-17).
- **Dokumen tata kelola telah diselaraskan (Phase 3, O-1).** **Phase 4E mencatat keputusan D-11…D-17.** Implementasi **tetap tergerbang** oleh pertanyaan terbuka O-2/O-3/O-7…O-16 (§6.1) dan gerbang F3–F9.
- Dokumen pendamping: `WINDY_TARGET_ARCHITECTURE.md`, `WINDY_DATA_MAPPING.md`, `WINDY_ACCESS_MATRIX.md`, `WINDY_ACCEPTANCE_CRITERIA.md`.

---

## 11. Ringkasan Revisi (2026-10-12)

| # | Perubahan |
|---|---|
| 1 | Menambahkan §0 Legenda Status Keputusan dan §3 Klasifikasi Keputusan (final / usulan / terbuka / gerbang). |
| 2 | Menghapus pertanyaan terbuka yang sudah FINAL: MySQL, pengecualian GIS, izin Chart.js, kewajiban dry-run. |
| 3 | Menyusun ulang daftar pertanyaan terbuka menjadi O-1…O-16 (termasuk OWM, sumber prakiraan 7 hari, akses Sheets aktual, persetujuan dokumen tata kelola, strategi sinkronisasi, zona waktu, ambang risiko, penyimpanan konfigurasi). |
| 4 | Menambahkan §7 Checklist Penyelesaian Desain (data, API, keamanan, cache, impor). |
| 5 | Memperbarui tanggal revisi ke **2026-10-12** (tanggal pengerjaan sebenarnya). |
| 6 | Menegaskan Admin monitoring-only & sinkronisasi manual hanya via endpoint internal Super Admin. |

## 12. Ringkasan Revisi Phase 3 (2026-10-13) — Penyelarasan Tata Kelola

| # | Perubahan |
|---|---|
| 1 | Menandai F2 **selesai**: dokumen tata kelola diperbarui agar WINDY (dashboard cuaca publik) masuk lingkup (O-1). |
| 2 | Menambahkan **status keputusan Phase 3** untuk O-1…O-16 (kosakata Phase 2) + catatan penyelarasan tiap item. |
| 3 | Menegaskan **"persetujuan arah ≠ verifikasi fakta"** untuk O-3/O-4 (provider & prakiraan 7 hari), O-5 (Sheets), O-7 (host). |
| 4 | Menegaskan pola yang **BELUM terverifikasi** di repo: `Cache::lock`, HTTP client cuaca, job/queue worker, CSP/security headers. |
| 5 | Menegaskan **divergensi zona waktu (O-15)** tidak diselesaikan diam-diam: spesifikasi mengusulkan UTC, pola proyek menyimpan lokal (`Asia/Makassar`). |
| 6 | Menegaskan SIPHP/JDIH **hanya label navigasi** (tidak ada route/view di repo) dan **tidak ada** GIS/peta batas wilayah. |

## 13. Ringkasan Revisi Phase 4E (2026-10-14) — Keputusan Pemilik D-11…D-17

| # | Perubahan |
|---|---|
| 1 | Menambahkan **§1.2.1 keputusan pemilik D-11…D-17**: horizon **3 hari** + BMKG gratis (D-11); zona waktu **WITA** (D-12); ambang lama sebagai **nilai awal** (D-13); sumber **gratis & resmi** + penelitian data gelombang BMKG + atribusi (D-14); **tanpa impor historis** (D-15); sinkronisasi **terjadwal + manual** (D-16); batasan **stack** (D-17). |
| 2 | Menutup **O-4** (prakiraan 7 hari → horizon 3 hari), **O-5/O-6** (impor historis ditiadakan), mengarahkan **O-3** (OWM gratis, berbayar dilarang), **O-13/O-14/O-15** dipersempit sesuai D-11…D-16. |
| 3 | Memperbarui §1.4 ruang lingkup, §3.2 usulan, §5 F7 (ditiadakan), §6.1 matriks O-n, §6.2 pernyataan gerbang, §7 checklist, §8 DoD, §9 larangan, §10 ringkasan. |
| 4 | Menegaskan **tampilan WITA = FINAL (D-12)** tetapi **konvensi penyimpanan timestamp tetap perlu klarifikasi teknis (O-15)** — tidak dinyatakan siap. |
| 5 | Menegaskan **larangan** nilai sintetis/tinggi gelombang karangan/default sebagai pengamatan nyata (D-13) dan **kewajiban atribusi** BMKG/OWM (D-14). |

## 14. Ringkasan Revisi Phase 4G (2026-10-15) — Koreksi Minor (F4F-01…F4F-12)

| # | Temuan | Perubahan |
|---|---|---|
| 1 | **F4F-01** | Legenda §0 dan rujukan label keputusan **FINAL** kini mencakup **D-1…D-17** (bukan hanya D-1…D-10): §0 legenda, §3.1, §5 F2, §6.1. |
| 2 | **F4F-02** | §1.2: persyaratan **dry-run impor ditandai TIDAK BERLAKU** (D-8 digantikan D-15). |
| 3 | **F4F-03** | §7.1: frasa **"metadata batch impor"** dihapus dari checklist skema. |
| 4 | **F4F-04** | §5 F9: asumsi **"job per jam"** diganti dengan **sinkronisasi terjadwal** sesuai D-16 & status O-7. |
| 5 | **F4F-05** | §6.2: rujukan gerbang rilis diperbaiki menjadi **`WINDY_ACCEPTANCE_CRITERIA.md §7` (REL-9)**. |
| 6 | **F4F-09** | §6.1 O-8: status utama dijelaskan sebagai **NEEDS AUTHORITY/POLICY INPUT** (teknis dapat dispesifikasikan) — **tidak** berarti kebijakan CSP/privasi selesai; status substantif & blokir tidak berubah. |

**Akhir `WINDY_MIGRATION_PLAN.md`. Tidak ada implementasi. Tata kelola diselaraskan (Phase 3); keputusan pemilik D-11…D-17 dicatat (Phase 4E); koreksi minor F4F-01…F4F-12 diterapkan (Phase 4G); implementasi tetap tergerbang.**
