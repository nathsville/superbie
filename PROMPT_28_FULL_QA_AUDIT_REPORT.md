# PROMPT_28 — Laporan Audit QA Menyeluruh (SuperBie — Lapor Pak Wali)

| | |
|---|---|
| **Tanggal audit** | 2026-10-18 |
| **Auditor** | Sesi QA/auditor profesional (agen AI) — peran: quality assurance + auditor |
| **Basis kode** | commit `720c5cd` (main lokal) + perubahan PHASE J yang belum ter-commit (11 modified, 4 untracked) |
| **Metode** | tinjauan statis menyeluruh (routing, otorisasi, data, frontend/a11y, dokumen) + audit otomasi (test suite, build, dependency audit, cache produksi) + verifikasi silang setiap temuan Tinggi ke atas langsung ke sumber kode |
| **Hasil ringkas** | **1 Kritis · 4 Tinggi · 14 Sedang · ±25 Rendah/Info** · suite **1672/1672 hijau (8787 assertion)** · `npm run build` ✓ · `composer audit` bersih · `config:cache`+`route:cache` ✓ |

> **Catatan integritas audit:** audit ini **tidak mengubah kode aplikasi** apa pun. Satu-satunya file baru dari audit adalah laporan ini. Semua angka, kutipan, dan perilaku diverifikasi pada 2026-10-18. Penomoran `PROMPT_28` mengikuti konvensi repo; bebas di-rename.
>
> **Definisi severity yang dipakai:** **Kritis** = memblokir/merusak produksi atau melanggar aturan bisnis/kepatuhan; **Tinggi** = risiko nyata (keamanan/data/operasional/a11y berat) — perbaiki sebelum produksi; **Sedang** = cacat benar/salah atau risiko berkondisi — perbaiki segera setelahnya; **Rendah/Info** = higiene, konsistensi, penyempurnaan.

---

## 1. Ringkasan Eksekutif

**Putusan:** fondasi rekayasa SuperBie tergolong **kuat dan disiplin** — inti keamanan (otorisasi berlapis, anti-IDOR, anti-XSS, CSRF, privasi lampiran) lulus audit, suite 1672 test hijau, build & cache produksi bersih, audit dependensi PHP bersih. Namun hasil audit menyimpulkan sistem **belum layak produksi tanpa perbaikan**: ada **1 temuan Kritis** (retensi otomatis tidak akan pernah berjalan di production), **4 Tinggi** (seeder kredensial default, kredensial lama masih beredar di repo, runbook deploy buta SIPHP/foto, pelanggaran kontras a11y pada kontrol UI), plus deret Sedang/Rendah.

Kabar baiknya: **seluruh temuan Kritis & Tinggi berbiaya perbaikan kecil** (total estimasi < 1 hari kerja; sebagian besar perubahan 1–5 baris atau revisi dokumen). Tidak ada temuan yang menunjukkan kerusakan struktural, tidak ditemukan celah eksploitatif (SQLi/XSS/IDOR) yang dapat dieksploitasi hari ini, dan tidak ada regresi test.

| Severity | Jumlah | Inti persoalan |
|---|---|---|
| **Kritis** | 1 | Penjadwal retensi `complaints:purge-expired` di production selalu gagal (`--force` tidak diteruskan) |
| **Tinggi** | 4 | `DatabaseSeeder` menyemai 5 akun password publik tanpa guard; kredensial dev DB masih tertulis di file ter-track + riwayat (rotasi belum dijalankan); runbook deploy tidak menyebut `storage:link`/foto SIPHP/backup foto; kontras putih-di-atas-accent-500 = 1.62:1 (melanggar kontrak token repo sendiri) |
| **Sedang** | 14 | termasuk: register/forgot/reset-password tanpa throttle + orakel enumerasi; operasi file di dalam transaksi DB; seksi scheduler runbook basi; `whereDate` mematikan indeks; indeks purge `audit_logs` hilang; 6 isu a11y (skip-link, kontras zebra, overflow landing ×2, `aria-pressed`, tombol tutup toast); 2 critical npm (dev-only); 2 drift dokumen (gerbang WINDY hilang, schema.md WINDY basi) |
| **Rendah/Info** | ±25 | higiene repo (artefak ter-track ±6,5 MB), CSS/a11y minor, nits desain, defense-in-depth keamanan, item keputusan terbuka |

**Status produksi:** perbaiki **P0** (K-01 + T-01 + T-03 + npm fix; ±2–4 jam) lalu **P1** (T-02, T-04, a11y, transaksi file, indeks; ±1–2 hari). Setelah itu risiko produksi tergolong rendah.

**Status pekerjaan yang belum masuk git:** integrasi JDIH (PHASE J) masih **uncommitted** — di luar temuan audit, tetapi dicatat sebagai risiko proses (lihat Lampiran A).

---

## 2. Lingkup, Metode & Batasan

**Cakupan (surface yang diaudit):**
- Halaman publik: landing, `/cuaca` (+detail), `/siphp`, `/siphp/pasar/{slug}`, `/jdih`.
- Alur warga: registrasi, login/reset, pengaduan (buat/edit/lampiran/rating), notifikasi, help desk.
- Panel internal: Operator (dinas-scoped), Admin monitoring, Super Admin (users, dinas, wilayah, kategori, laporan, validasi, review, audit log, konfigurasi, modul SIPHP pasar/barang/harga/toko, sinkronisasi cuaca).
- Non-fungsional: retensi & purge, penjadwal, storage & backup, index DB, cache, konfigurasi produksi, CI, konsistensi dokumen.

**Cara audit:** 4 auditor paralel per domain (keamanan/otorisasi; data & operasional; frontend/a11y; dokumen & higiene) + pemeriksaan mandiri auditor utama (perintah & pembacaan berkas), lalu **verifikasi silang temuan Tinggi+** ke sumber (kecuali 2 temuan visual yang bersifat kandidat — lihat S-08/S-09).

**Perintah kunci yang dijalankan (bukti di Lampiran A):** `php artisan test`, `php artisan route:list`, `php artisan schedule:list`, `php artisan config:cache|route:cache` lalu clear, simulasi `APP_ENV=production php artisan complaints:purge-expired`, `composer audit`, `npm audit`, `npm run build`, `vendor/bin/pint --test`, `git log`/`git status`.

**Batasan yang jujur (tidak diklaim lebih):**
- Ini bukan penetration test dan bukan uji beban; tidak ada eksploitasi nyata yang dicoba.
- Tidak ada browser live/E2E pada audit ini — cek UI manual G5 (SIPHP/JDIH) tetap pending; temuan visual (S-08, S-09) adalah hasil analisis statis yang **wajib dikonfirmasi di browser**.
- Kontras warna dihitung dari token CSS, bukan diukur dengan instrumen/warna layar perangkat.
- Status DB live (isian data nyata, `migrate:status`) tidak ditarik; audit berbasis skema & kode.
- `npm run build` dijalankan ulang saat audit — hanya menulis `public/build` yang git-ignored; tidak mengubah file ter-track.

---

## 3. Temuan Kritis & Tinggi

### K-01 — [KRITIS] Retensi otomatis tidak akan pernah berjalan di production (`--force` tidak diteruskan penjadwal)

- **Area:** Operasional / kepatuhan retensi (aturan bisnis FINAL, Prompt 5B.1)
- **Bukti:**
  - `routes/console.php:25` — `Schedule::command('complaints:purge-expired')->dailyAt('02:00');` — **tanpa** `--force`.
  - `app/Console/Commands/PurgeExpiredComplaints.php:32–35` — `if ($isProduction && ! $this->option('force') && ! $dryRun) { $this->error(...); return self::FAILURE; }`.
  - Bandingkan status benar: `routes/console.php:41–43` (`weather:sync --force` → penjadwal meneruskan flag).
  - **Simulasi produksi dijalankan:** `APP_ENV=production php artisan complaints:purge-expired` → `Menjalankan purge di production memerlukan flag --force.` + `EXIT_CODE=1` — persis yang akan terjadi pada tiap run penjadwal di production.
  - `php artisan schedule:list` → `0 2 * * * php artisan complaints:purge-expired` (tanpa `--force`).
- **Dampak:** mulai hari pertama deployment, tugas retensi 5 tahun **gagal setiap malam secara silent** (hanya jejak error di log), PII laporan tersimpan melewati kebijakan retensi, dan tabel `complaints`/`audit_logs`/lampiran tumbuh tanpa pembersihan. Ini pelanggaran aturan bisnis yang sudah difinalkan, bukan sekadar bug kosmetik.
- **Verifikasi ulang:** `APP_ENV=production php artisan complaints:purge-expired` (harus exit 0 **setelah** perbaikan).
- **Rekomendasi:** ubah baris 25 menjadi `Schedule::command('complaints:purge-expired --force')->dailyAt('02:00')` + test kontrak yang mengunci flag (mis. assert string perintah terjadwal). Catatan kecil: komentar `PurgeExpiredComplaints.php:17` terbalik ("required to run **outside** non-production") — perbaiki menjadi "wajib di production".

### T-01 — [TINGGI] `DatabaseSeeder` menyemai akun dengan password publik (`password123`) tanpa penjagaan produksi

- **Area:** Keamanan / operasional seed
- **Bukti:** `database/seeders/DatabaseSeeder.php:25–82` — 5 akun `firstOrCreate` (superadmin/admin/operator/2 warga) dengan `Hash::make('password123')`; docblock `:17–19` menyatakan "for local development… NEVER use in production", tetapi **tidak ada cek environment di `run()`** (`:21` dan seterusnya). Bandingkan pola benar di `MarketDemoSeeder.php:100–104` (melempar exception bila production).
- **Dampak:** satu `php artisan db:seed` (atau migrasi yang memanggil seed) di/terhadap production membuat **Super Admin aktif dengan password yang tertulis di repositori** — risiko take-over penuh.
- **Verifikasi ulang:** jalankan `db:seed` di staging → 5 akun masuk; bandingkan `MarketDemoSeeder` yang menolak.
- **Rekomendasi:** guard eksplisit di awal `run()` (`if (app()->environment('production')) { $this->command->error(...); return; }` atau throw) + test yang mengunci perilaku itu; jangan mengandalkan disiplin operator.

### T-02 — [TINGGI] Kredensial DB pengembangan masih tertulis plaintext di file ter-track dan commit lama (rotasi belum dijalankan)

- **Area:** Keamanan / higiene repo
- **Bukti:** `PROMPT_18_SECURITY_ARCHITECTURE_AUDIT.md` memuat pasangan kredensial DB dev (baris 23 dst. — diverifikasi ada; juga disebut di baris lain); jejak yang sama ada di riwayat commit lama; `CONTRIBUTING.md:164–169` **sudah mengakui** hal ini: "Rotasi kredensial DB pengembangan yang bocor… rotasi tetap langkah yang benar; … Pastikan repo tetap private selama riwayat git masih memuat kredensial lama."
- **Dampak:** selama belum dirotasi & repo belum pernah publik, risikonya kecil; tetapi kebocoran accidental (arsip, clone, screenshot) memohon kredensial yang masih valid. Sudah tercatat sebagai utang lama (PROMPT_23/24) → yang menjadi temuan audit adalah **statusnya masih terbuka**.
- **Rekomendasi (owner/ops):** rotasi password DB dev **dan** production-adjacent segera; redaksi berkas laporan lama (ganti literal dengan placeholder); putuskan apakah rewrite riwayat git diperlukan (untuk repo privat: tidak wajib, tapi harus disadari); tetap private.

### T-03 — [TINGGI] Runbook deployment/operasi buta terhadap SIPHP, foto publik, dan `storage:link`

- **Area:** Operasional / deploy & backup
- **Bukti:** `PRODUCTION_OPERATIONS.md:102` — "**IMPLEMENTED.** One scheduled task is defined" (faktanya dua: purge + `weather:sync` 3-jaman); grep seluruh berkas: **0 kemunculan** `storage:link`, `SIPHP`, `WINDY`, `weather`, `cuaca`, `foto/photo`. Padahal modul SIPHP menyimpan foto toko di **public disk** (`config/siphp.php:35–43`; `config/filesystems.php:51–58,86–88`) dan tidak ada route `/storage` di aplikasi — foto **hanya** tersaji lewat symlink `public/storage` dari `php artisan storage:link`.
- **Dampak:** (a) deploy mengikuti runbook apa adanya → semua foto di `/siphp` 404 (fitur publik baru tampil rusak); (b) kebijakan backup hanya membahas DB → foto bukti/hasil kerja staf **tidak masuk cakupan backup** dan hilang bila server rebuild; (c) sinkronisasi cuaca terjadwal tidak muncul di daftar pemantauan.
- **Verifikasi ulang:** di server staging tanpa `storage:link`: buka `/siphp` → gambar toko gagal; tambah langkah ke runbook → gambar tampil.
- **Rekomendasi:** tambahkan ke `PRODUCTION_OPERATIONS.md`: langkah `php artisan storage:link` pada checklist deploy; baris tugas terjadwal kedua (`weather:sync --force`); cakupan backup direktori `storage/app/public` (atau arahkan foto SIPHP ke disk backup-aware); item verifikasi gambar publik pasca-deploy.

### T-04 — [TINGGI] Kontras putih-di-atas-`accent-500` = 1.62:1 — melanggar kontrak token desain repo sendiri

- **Area:** Aksesibilitas (WCAG 2.1 AA) / kepatuhan kontrak desain
- **Bukti:** `resources/css/app.css:49–57` menyatakan kontrak eksplisit: "Contrast was measured, not assumed… **accent-500 and lighter are FILL colours only — #FFC14D on white is 1.6:1, so it must never carry text.** Use accent-600/700 for that." Pelanggaran:
  - `resources/views/components/profile/edit-button.blade.php:13` — `text-sm font-semibold text-white bg-accent-500` (label tombol Edit/Ubah).
  - `resources/views/components/profile/avatar.blade.php:48` — badge kamera `bg-accent-500 … text-white`.
  - Terukur: putih (#FFF) pada #FFC14D = **1.62:1**; ambang AA untuk teks = 4.5:1.
- **Dampak:** label/ikon praktis tak terbaca bagi pengguna low-vision & semua konteks cahaya terang; melanggar aturan yang repo sendiri tetapkan sebagai hasil pengukuran.
- **Rekomendasi:** ganti fill menjadi `bg-accent-600` (putih pada #996300 ≈ 5.1:1) **atau** pertahankan fill `accent-500` dengan teks gelap (`text-ink` ≈ 8–9:1). Audit singkat kelas `accent-500` lain di komponen profil saat memperbaiki.

---

## 4. Temuan Sedang (14)

### Keamanan

**S-01 — Register / forgot-password / reset-password tanpa throttle + orakel enumerasi (Sedang)**
`routes/web.php:55–66` — grup hanya `Route::middleware('guest')`; `POST /register`, `/forgot-password`, `/reset-password` tidak ber-`throttle:` (bandingkan `/login` yang dibatasi 5/60 dtk via `LoginRequest`). `RegisteredUserController.php:30/36` memvalidasi `phone_number`/`nik` unik dengan pesan spesifik ("NIK sudah terdaftar.") → orakel enumerasi NIK/telepon/email terdaftar; akun baru langsung aktif + auto-login (`:50–56`). **Catatan:** keputusan rate-limit register/reset sudah tercatat sebagai **DECISION REQUIRED** di `prd.md:196–197,235` — temuan ini menunggu keputusan owner; rekomendasi teknis: `throttle:5,1` + pesan generik.

### Data & Operasional

**S-02 — Operasi file di dalam transaksi DB → berkas hilang/orphan saat rollback (Sedang)**
`SuperAdminMarketStoreController.php` — `storePhoto()` dipanggil **di dalam** `DB::transaction` (`:81`; update `:113,:119,:122`; destroy `:153` menghapus foto sebelum/bersama commit). `Citizen\ComplaintController.php` — unggah lampiran di dalam transaksi create (`:126` lalu `:169–189`) dan hapus+unggah lampiran di dalam transaksi update (`:345`, `:377–380`, `:384–404`). Bila transaksi gagal setelah file dihapus, baris DB tetap menunjuk file yang sudah hilang (foto/lampiran **tidak bisa dipulihkan**); bila gagal setelah file dibuat, tersisa orphan di disk. Peluang pemicu rendah (akses staf, rollback jarang) — karena itu dinilai Sedang, bukan Tinggi. Pola benign sudah ada di retensi (`ComplaintRetentionService.php:113–142` — hapus file SETELAH commit). **Rekomendasi:** pindahkan store/delete file ke setelah commit (pola retensi) atau kompensasi eksplisit saat rollback.

**S-03 — Seksi scheduler runbook basi: "One scheduled task is defined" (Sedang)**
`PRODUCTION_OPERATIONS.md:102` — padahal `routes/console.php:25,41–43` mendaftarkan **dua** tugas (retensi + `weather:sync --force` 3-jaman). Sinkronisasi cuaca tidak muncul di pemantauan mana pun di runbook. **Rekomendasi:** perbarui daftar + cara memverifikasi kedua tugas (setelah K-01 diperbaiki, keduanya harus sukses).

**S-04 — `whereDate()` mematikan indeks di layar catat harga (Sedang)**
`SuperAdminMarketPriceController.php:67` — `->whereDate('effective_date', $date)`, padahal indeks yang tersedia `['market_id','effective_date']` (`database/migrations/2026_10_17_100001_create_market_tables.php:76`). Predikat `DATE(kolom)=…` tidak memakai indeks → memindai seluruh riwayat harga pasar tersebut, tumbuh tiap hari. Jalur tulis sudah benar (`:101` menulis nilai mentah). **Rekomendasi:** `->where('effective_date', $date->toDateString())`.

**S-05 — Indeks pendukung purge `audit_logs` tidak ada (Sedang)**
`database/migrations/2024_01_01_100003_create_audit_logs_table.php:19,26,28` — indeks hanya pada `action`, `created_at`, `(actor_id, created_at)`; sedangkan purge retensi menghapus berdasarkan `subject_type + subject_id` (`ComplaintRetentionService.php:105–108`) → full scan per laporan yang dipurge pada tabel append-only yang terus tumbuh. Catatan drift: `schema.md:455` menyebut indeks komposit yang tidak terimplementasi. **Rekomendasi:** migrasi kecil penambah `index(['subject_type','subject_id'])` + selaraskan schema.md.

### Frontend & Aksesibilitas

**S-06 — Skip-link hanya ada di landing (Sedang)**
`resources/views/public/landing.blade.php:352` ("Lewati ke konten utama") — satu-satunya di repo (grep): `/cuaca`, `/siphp`, `/siphp/pasar/*`, `/jdih`, dan shell dashboard hanya punya target `#main-content` tanpa tautan lompat. Pengguna keyboard harus menelusuri header/sidebar di setiap halaman. **Rekomendasi:** angkat komponen skip-link (landing sudah jadi contoh) dan pakai di 5 shell lainnya.

**S-07 — Kontras label kecil di baris zebra tabel SIPHP: 4.35:1 (Sedang)**
`text-ink-faint` (#64748B) di atas `bg-surface-sunken` (#F1F5F9) = **4.35:1** — di bawah ambang AA 4.5:1 untuk teks 13px; dipakai di baris genap tabel: `public/siphp.blade.php:124,128,138`; `public/siphp-market.blade.php:122,126,136`. **Rekomendasi:** naikkan ke `ink-soft` pada baris suken, atau matikan zebra untuk label kecil.

**S-08 — Nama footer landing `nowrap` berisiko overflow di layar ≤360px (Sedang — kandidat, konfirmasi browser)**
`public/landing.blade.php:339–345` (`.lp-name`: `font-size: clamp(52px, 13vw, 208px); white-space: nowrap`) dipakai untuk "Lapor Pak Wali" (`:649`). Pada viewport 320px, ukuran minimum 52px menghasilkan perkiraan lebar > 320px tanpa penjagaan overflow. **Rekomendasi:** turunkan batas minimum clamp (mis. 40px) atau izinkan pemotongan terkendali; verifikasi di 320/360px (bagian dari cek G5).

**S-09 — Tab header `lg` 3 tautan berisiko melebihi track 3 kolom pada 1024–1280px (Sedang — kandidat, konfirmasi browser)**
`public/landing.blade.php:389–401` — tab `lg:col-span-3` (±240px pada 1024px setelah padding) memuat 3 tautan `whitespace-nowrap text-[17px]` + `gap-6` (kasus terburuk login: "Cuaca"+"Harga pasar"+"Akun saya" ≈ 268px). **Rekomendasi:** kecilkan `lg:gap` / padding, atau perluas track, atau biarkan judul pendek; verifikasi di 1024/1152/1280px (bagian dari cek G5). Ini konsekuensi desain tab 3 tautan (D-J4) yang perlu dipastikan tidak pecah di batas breakpoint.

**S-10 — Tombol layer cuaca: status hanya warna, tanpa `aria-pressed` (Sedang)**
`public/weather.blade.php:401` — `:class="overlay === '…' ? 'bg-ink text-white' : '…'"`; grep tidak menemukan `aria-pressed` di berkas. Pengguna screen reader tidak bisa mengetahui layer aktif. **Rekomendasi:** tambah `aria-pressed="true|false"` pada tombol toggle.

**S-11 — Tombol tutup toast 16px & `aria-hidden` tak konsisten (Sedang)**
`components/layouts/app.blade.php:58–62,83–87` — target sentuh `w-4 h-4` (~16px, jauh di bawah 44px) dan svg tanpa `aria-hidden` (padahal di elemen lain `:54/:79` sudah diberi). **Rekomendasi:** perbesar area tap (min 32–44px), tambah `aria-hidden="true"` + `sr-only` "Tutup".

### Dependensi & CI

**S-12 — `npm audit`: 2 critical (shell-quote via concurrently) & CI mengabaikan hasil audit (Sedang — dev-only)**
`npm audit` → `shell-quote 1.8.4–1.10.0` (GHSA-pqg4-j6r4-53mv, command injection) tertarik oleh `concurrently@10.0.x` — **devDependency** (tidak ikut ke produksi; dependensi produksi hanya Alpine/Leaflet). `.github/workflows/ci.yml:104–109` menjalankan `composer audit || true` dan `npm audit || true` (informatif) → critical ini lolos CI tanpa noise. **Rekomendasi:** `npm audit fix` / naikkan `concurrently`; putuskan kebijakan ambang CI (mis. eventual `npm audit --omit=dev` sebagai blocker).

---

## 5. Temuan Rendah & Info (±25)

**Keamanan (defense-in-depth / minor):**
- Atribut istimewa mass-assignable di `User::$fillable` (`role`, `is_active`, `dinas_unit_id`) — `app/Models/User.php:23–34`. Tidak ada jalur eksploitatif saat ini (semua jalur tulis tervalidasi), tetapi satu `update($request->…)` yang lolos review bisa eskalasi. Info.
- Kebijakan password masih default framework (min 8 tanpa kompleksitas/breach-check) — `RegisteredUserController.php:32`; berlaku sampai Super Admin.
- Cek unik `phone_number` sebelum normalisasi → duplikat `+62…` bisa gagal di DB dengan 500 (uniformity) — `RegisteredUserController.php:30` vs `:47`; `ProfileController.php:40` vs `:79`.
- Lampiran diunduh `inline`, bukan `attachment` (`Citizen\ComplaintController.php:465`; `OperatorComplaintController.php:828`) — PDF dari origin aplikasi; allowlist + `nosniff` membatasi, tetapi prefer `Content-Disposition: attachment`.
- `.env.example` membawa default dev: `SESSION_ENCRYPT=false`, `SESSION_SECURE_COOKIE=false`, `SESSION_SAME_SITE` tak diset (`.env.example:37,42`) — salin mentah ke produksi = cookie tidak Secure/encrypted (pastikan checklist produksi menimpanya).
- CSP tanpa `default-src`/`script-src` (`AddSecurityHeaders.php:38–41`, diakui di docblock `:25–28`) — tanpa mitigasi-in-depth XSS masa depan; keputusan CSP/Windy (O-8) masih terbuka.

**Data & ops:**
- `/siphp` menampilkan toko dari pasar yang **nonaktif** — `PublicMarketController.php:60–64` (tanpa filter pasar aktif) vs klaim `:28–29` dan konsistensi halaman detail `:85–89`.
- Edit Kecamatan/Kelurahan tidak meng-invalidasi cache dashboard/rating (tidak ada observer; `AppServiceProvider.php:25–29`) — nama wilayah basi hingga TTL 60 dtk.
- `.env.example` tidak memuat `WINDY_HTTP_TIMEOUT`/`WINDY_MARINE_HTTP_TIMEOUT` (`config/weather.php:166,249`) — kunci tuning tak ditemukan.
- Retensi `market_prices`/`market_stores` dan audit-log non-pengaduan masih `TODO: Define requirement` (`schema.md:420,718`) — keputusan owner; terkait K-01 untuk bayangannya.
- `/siphp` menghitung agregat tiap request tanpa cache (`PublicMarketController.php:105–120`).

**Frontend/a11y minor:**
- `ink-ghost` (#94A3B8) di atas putih = 2.56:1 — teks 11px tips/timestamp (`components/notification-item.blade.php:90,99`; `components/profile/field.blade.php:77`; `components/siphp/price-delta.blade.php:15`).
- Target ikon < 44px: `app.css:521–523` (32px), `:630–635` (36px); `notification-item.blade.php:113`; `layouts/dashboard.blade.php:339` (40px).
- Label sumbu chart `font-size="17"` (tangga tipe repo: 13/17/22) — `components/siphp/trend-chart.blade.php:106,131`; `components/weather/trend-chart.blade.php:124,156`.
- Select `siphp-market.blade.php:165` memakai hairline (`border border-line`) — menyimpang dari semangat §9 design ("no hairlines").
- CTA header landing 16px vs subpages 17px (`landing.blade.php:375,380` vs `jdih.blade.php:140–145`) — nits.
- `navigation.js:430–443` — tautan hash beda-path belum dikecualikan (teoretis; belum ada kasus nyata).
- Duplikat basi `resources/js/js/**` (4 berkas: `navigation.js`, `app.js`, `leaflet-global.js`, `leaflet-plugins.js`) — **tidak diimpor** Vite, tetapi salinannya sudah ketinggalan guard SIPHP/JDIH → jebakan drift.
- `resources/views/layouts/app.blade.php`, `layouts/dashboard.blade.php` — layout lama tak terpakai (live pair ada di `components/layouts/`).

**Higiene repo (Rendah):**
- Artefak ter-track ±6,5 MB tanpa rujukan docs fungsional: `design.html` (113 KB), `screenshot-reference.png` (791 KB), `tampilanwindy1–4.png` (±4,7 MB), `Banking Dashboard UI – … Design.jpg`, `_lc2c_baseline_test.txt` (log PHPUnit lama + **memuat path absolut lokal**), `_lc2c_final_test.txt` (kosong), `_lc2c_final_test2.txt`, `hotspot-lan.ps1`/`serve-lan.ps1`.
- `hotspot-lan.ps1:25,45` memuat passphrase default hardcode (helper LAN) — rendahkan/parameterkan.
- `docker/nginx/default.conf` ter-track padahal dokumen menyatakan "no Docker" (`SETUP_AND_DOCS.md:9,17`) — leftover dari commit lama; beri label "referensi" atau hapus.
- `WINDY/boundary.html` ter-track padahal `rules.md:15` melarang pemakaian boundary.html untuk WINDY — pastikan hanya arsip legacy & tidak dipakai; folder `WINDY/**` tak dirujuk docs mana pun.
- Pint: `vendor/bin/pint --test` **gagal** pada ±150 berkas dan tidak ada `pint.json`; CI tidak menjalankan pint → tidak memblokir apa pun, tapi berarti "standar pint" tidak benar-benar dipakai. Putuskan: adopsi baseline (`pint.json`) atau jalankan sekali.
- `ci.yml:57–58` memakai `root/root` untuk service MySQL ephemeral CI (standar & aman untuk CI throwaway — Info saja).

---

## 6. Drift Dokumentasi (2 Sedang + 1 Rendah)

**D-01 — [Sedang] Gerbang rilis WINDY yang dirujuk 9 tempat tidak ada di repo (dan tidak pernah ada di git)**
`_superbie_audit/WINDY_MIGRATION_PLAN.md` dirujuk di `architecture.md:8`, `PROGRESS.md:90`, `prd.md:18,76`, `rules.md:7`, `README.md:3`; `_superbie_audit/WINDY_DATA_MAPPING.md` di `architecture.md:216`, `schema.md:6,715` (+ komentar kode `database/migrations/2026_10_15_100001_create_weather_tables.php:12`). Verifikasi: `git log --all -- '*WINDY_MIGRATION*' '*WINDY_DATA_MAPPING*' '_superbie_audit'` → **nol riwayat** — dokumen gerbang F3 yang menjadi syarat proses implementasi WINDY tidak dapat diaudit. **Rekomendasi:** putuskan — (a) salin dokumen gerbang ke repo, atau (b) perbaiki rujukan agar menunjuk sumber yang benar (mis. keputusan D-11…D-17 di prd/architecture) dan tandai eksplisit "arsip di luar repo".

**D-02 — [Sedang] `schema.md` masih menyatakan tabel WINDY "belum ada" padahal sudah dimigrasikan (tanpa seksi weather di schema)**
`schema.md:6` ("not defined here yet and is not part of the current migrations") dan `:715` ("NOT yet in schema.md") bertentangan dengan kenyataan: `2026_10_15_100001_create_weather_tables`, `100002_create_wave_forecasts`, `100003_add_multi_location_support` sudah berjalan; grep `weather|WINDY|cuaca` di schema.md hanya menemukan kedua baris itu. Karena aturan repo mewajibkan schema.md diperbarui **sebelum** migrasi, ada pelanggaran proses yang belum dibereskan dokumentasinya. **Rekomendasi:** tulis seksi tabel weather (+ keputusan penyimpanan waktu O-15) ke schema.md, atau tandai jelas statusnya.

**D-03 — [Rendah] Angka & status basi di dokumen**
- `PROGRESS.md:17` ("| Route | 101 |") dan `:119` ("terkini… 1462 test/7082 assertions, 101 route, 25 migrasi") vs fakta audit: **137 route, 29 migrasi, 1672 test/8787 assertions**.
- `PROGRESS.md:90` — "**Belum ada kode/database WINDY yang dibuat**" — bertentangan dengan implementasi (route `/cuaca`, migrasi weather, `weather:sync`).
- `SIPHP_INTEGRATION_PLAN.md:3,161` — status "uncommitted" vs `PROGRESS.md` yang menyatakan ter-commit `720c5cd`.
- `prd.md:18` masih memuat klausa lama "SIPHP dan JDIH tetap tidak dibangun" yang sudah disuperseded di baris yang sama (gaya log revisi — beri penanda "→ diperbarui" agar tidak menyesatkan).
- Catalog `PROMPT_*` memiliki celah (22 hilang; 1–3 tidak pernah ada; `PROMPT 3B` dirujuk `prd.md:95` tanpa berkas) — Info.

---

## 7. Yang Diverifikasi Aman (kekuatan yang ditemukan)

- **Cakupan otorisasi route:** seluruh 137 route diperiksa — semua route panel ber-`auth`+`role:*`; `EnsureUserIsActive` global di grup web (`bootstrap/app.php:20–25`); parameter dinamis numerik di-`whereNumber`; tidak ada salah urutan wildcard.
- **Anti-IDOR:** pengaduan warga (kepemilikan dicek di show/edit/update/unduh/rating), help desk & notifikasi (`forUser`/`appNotifications()->findOrFail`), operator (`visibleToOperator` + mirror guard) — semua spot-check auditor lolos.
- **XSS/SQLi:** nol `{!! !!}` di Blade; konten user dirender lewat `{{ }}`/`@json`; fragmen raw SQL konstan atau ber-binding; sort di-allowlist.
- **CSRF:** semua aksi berubah-status memakai POST/PATCH/PUT/DELETE; 73 form blade ber-`@csrf`.
- **Auth hardening:** login generik + limiter 5/60 dtk; session regenerate; ganti password meng-invalidasi sesi lain (`authenticateSessions()`); NIK tidak bisa diubah setelah diset (`User.php:63–69`).
- **Unggahan & storage:** lampiran pengaduan → disk privat + nama acak + mimes/max; foto toko → public disk + nama acak; penghapusan file lama ikut saat penggantian (jalur normal).
- **Retensi (logika inti):** urutan hapus anak→induk, file dihapus setelah commit, kegagalan file dilaporkan (masalah K-01 murni di sisi penjadwal, bukan logika service).
- **Timezone:** `Asia/Makassar` konsisten (config/app, .env.example, perilaku perintah).
- **Cache & produksi:** `config:cache` + `route:cache` sukses; nol `env()` di luar `config/`; observer cache terpasang untuk entitas utama.
- **Seeders demo:** `MarketDemoSeeder` menolak production (berlawanan dengan T-01 — jadi polanya sudah ada, tinggal diterapkan).
- **Dependensi PHP:** `composer audit` → "No security vulnerability advisories found" (exit 0).
- **Kualitas & regresi:** 1672/1672 test hijau (8787 assertion, ±240 dtk); `npm run build` ✓ 2.76 dtk; CI GitHub Actions menjalankan build+test.
- **Migrasi market:** unique `(market_id,item_id,effective_date)` + indeks `(market_id,effective_date)` + FK restrict/nullOnDelete sesuai maksud dokumen.

---

## 8. Keputusan & Pekerjaan Terbuka (inventaris dari dokumen)

| Topik | Sumber |
|---|---|
| Rate-limit register/forgot-password (DECISION REQUIRED) | `prd.md:196–197,235` (terkait S-01) |
| Rate limit panel repo-wide, retensi `market_prices`/`market_stores` (TODO: Define requirement), cache halaman publik | `PROGRESS.md:82,85`; `SIPHP_INTEGRATION_PLAN.md:165,170`; `schema.md:420,718` |
| SLA/eskalasi, moderasi/duplikat/spam, retensi & notifikasi Help Desk, notifikasi balasan ulasan (STAGE 3) | `prd.md:85–103`; `PROGRESS.md:83–84` |
| Target deployment & metode, host scheduler, monitoring/alerting, log retention, kebijakan backup + RPO/RTO, prosedur restore | `PRODUCTION_OPERATIONS.md:29,35,40,206,251–265,312,323–335,363` (+ P23-D1…D10 `:379–388`) |
| WINDY: O-2/O-3/O-7/O-8/O-9/O-10/O-12/O-14/O-15/O-16 + gerbang F3 (lihat D-01/D-02) | `prd.md:128`; `architecture.md:216`; `schema.md:715` |
| Dataset BPS wilayah; reset password staf; metrik target numerik | `PROGRESS.md:81–82`; `prd.md:439` |
| G5 cek UI manual (SIPHP/JDIH) & cek browser audit ini (S-08/S-09) | `SIPHP_INTEGRATION_PLAN.md`; `JDIH_INTEGRATION_PLAN.md`; laporan ini |

---

## 9. Lampiran A — Bukti Perintah Audit (2026-10-18)

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` | **1672/1672 passed · 8787 assertions · 240 dtk** |
| `npm run build` | ✓ built in 2.76 dtk (app js 261 KB / 78,6 KB gzip; css 102,9 KB) |
| `php artisan config:cache` → `route:cache` → clear | Semua sukses; tidak ada sisa `bootstrap/cache/{config.php,routes-v7.php}` |
| `composer audit` | "No security vulnerability advisories found" (exit 0) |
| `npm audit` | **2 critical** — `shell-quote` via `concurrently` (dev-only; `npm audit fix`) |
| `vendor/bin/pint --test` | FAIL pada ±150 berkas; tidak ada `pint.json`; CI tidak menjalankan pint |
| `php artisan route:list` | 137 baris method-route (terkunci test; inventaris test = 137) |
| Migrasi | 29 berkas (`database/migrations`) |
| `php artisan schedule:list` | `0 2 * * * complaints:purge-expired` (tanpa `--force`); `0 */3 * * * weather:sync --force` |
| Simulasi produksi | `APP_ENV=production php artisan complaints:purge-expired` → error flag `--force` + **EXIT_CODE=1** (bukti K-01) |
| `git log` gerbang WINDY | Nol riwayat untuk `_superbie_audit/…`, `WINDY_MIGRATION_PLAN`, `WINDY_DATA_MAPPING` (bukti D-01) |
| `git status --short` | **11 modified** (PROGRESS, README, architecture, design, prd, navigation.js, landing.blade, routes/web, rules, schema, ProgressiveNavigationTest) + **4 untracked** (JDIH plan, controller, blade, test) — PHASE J belum di-commit |

---

## 10. Lampiran B — Rekomendasi Prioritas (urutan kerja)

**P0 — sebelum deploy (blocker & cepat, estimasi 2–4 jam total):**
1. **K-01**: `--force` pada `Schedule::command('complaints:purge-expired …')` + test kontrak + rapikan komentar `:17`.
2. **T-01**: guard produksi di `DatabaseSeeder::run()` + test.
3. **T-03**: tambahkan ke `PRODUCTION_OPERATIONS.md`: langkah `storage:link`, baris tugas cuaca terjadwal, cakupan backup foto `storage/app/public`.
4. **S-12**: `npm audit fix` (naikkan `concurrently`).
5. Jalankan ulang simulasi produksi purge (harus exit 0) + suite penuh.

**P1 — sebelum/selama hardening produksi (estimasi 1–2 hari):**
6. **T-02** (owner/ops): rotasi kredensial DB dev; redaksi `PROMPT_18`; repo tetap privat.
7. **T-04 + S-06…S-11**: paket aksesibilitas (2 kontras token, skip-link 5 shell, `aria-pressed`, tombol toast, zebra suken, overflow landing ×2 setelah konfirmasi browser).
8. **S-02**: pindahkan operasi file ke luar transaksi (pola retensi) di 2 controller.
9. **S-04/S-05**: `whereDate` → `where`; migrasi indeks `audit_logs(subject_type, subject_id)`.
10. **S-03**: perbarui daftar tugas terjadwal di runbook (setelah K-01).

**P2 — setelahnya:**
11. **S-01**: keputusan owner (throttle register/reset + pesan generik + panel rate-limit repo-wide).
12. **D-01/D-02/D-03**: sinkronisasi dokumen (gerbang WINDY, seksi schema weather, angka PROGRESS/SIPHP plan).
13. Higiene: hapus/pindahkan `resources/js/js/**`, artifak ter-track, `pint.json`/keputusan pint, kebijakan audit CI, parameter `hotspot-lan.ps1`, bersihkan log `_lc2c` (memuat path lokal).
14. Re-audit singkat pasca-perbaikan (fokus regresi K-01…T-04).

---

## 11. Lampiran C — Status Perbaikan Pasca-Audit (2026-10-18)

Bagian ini ditambahkan **setelah** laporan final; temuan §3–§6 tetap sebagaimana temuan saat audit.

### C.1 — Paket P0

Perbaikan paket P0 (Lampiran B) dikerjakan pada basis kode yang sama dan diverifikasi ulang:

| Temuan | Status | Perubahan & bukti |
|---|---|---|
| K-01 | ✅ Diperbaiki | `routes/console.php` menjadwalkan `complaints:purge-expired --force` (pola sama dengan `weather:sync`); komentar penjadwal + docblock flag command dirapikan. Bukti: `schedule:list` menampilkan `--force`; simulasi produksi: tanpa flag → exit 1, dengan `--force --dry-run` → exit 0. |
| T-01 | ✅ Diperbaiki | `DatabaseSeeder::run()` melempar exception di `production` (meniru guard `MarketDemoSeeder`); test baru `tests/Feature/DatabaseSeederGuardTest.php` (menolak di produksi; fixture pengembangan tetap tersemai). |
| T-03 | ✅ Diperbaiki (dokumen) | `PRODUCTION_OPERATIONS.md`: inventaris 2 tugas terjadwal + penjelasan `--force`, langkah `php artisan storage:link` di checklist deploy, baris backup foto `storage/app/public`, baris insiden foto `/siphp` 404. |
| S-12 | ✅ Diperbaiki | `npm audit fix`: `concurrently` 10.0.5→10.0.6 & `shell-quote` 1.9.0→1.12.0 (keduanya dev-only); `npm audit` → **0 vulnerabilities**. |
| Kontrak regresi | ✅ | `ProductionOperationsTest` mengunci: purge `0 2 * * *` + `--force`, weather `0 */3 * * *` + `--force`, serta kontrak produksi command (tolak tanpa flag / lanjut dengan flag, `--dry-run`). |

**Verifikasi ulang pasca-perbaikan P0:** suite penuh **1675/1675 hijau (8800 assertion)**; `npm run build` sukses.

### C.2 — Paket aksesibilitas (T-04 + S-06…S-11)

| Temuan | Status | Perubahan & bukti |
|---|---|---|
| T-04 | ✅ Diperbaiki | `profile/edit-button.blade.php` + `profile/avatar.blade.php`: isian `accent-500` → `accent-600 hover:accent-700` (putih di atas #996300 ≈ 5.1:1; `accent-500` tetap token isian-saja). Docblock tombol mencatat alasan kontras. |
| S-06 | ✅ Diperbaiki | Komponen baru `components/skip-link.blade.php`; terpasang di cuaca, siphp, halaman pasar, dan shell dashboard (landing & JDIH menyusul di paket JDIH). Test kontrak: teks skip-link + `href="#main-content"` pada 5 jalur + `id="main-content"` di dashboard. |
| S-07 | ✅ Diperbaiki | Label unit + baris tanggal pada baris zebra SIPHP (indeks & detail pasar): `text-ink-faint` → `text-ink-soft` (AA di atas `surface-sunken`). Header tren ber-latar terang sengaja tidak diubah — di luar cakupan temuan. |
| S-08 | ✅ Diperbaiki + bukti browser | Footer landing `.lp-name`: `clamp(52px, 13vw, 208px)` → `clamp(40px, …)`. Chrome headless, kolom 320px: sebelum — glyph menembus tepi (terukur berhenti di x=319, terpotong); sesudah — glyph berhenti di x=302, padding kanan utuh. Ukur kanvas Poppins (letter-spacing −0.055em) mendukung: 353px @52px vs ±283px @41,6px. Catatan metode: Chrome Windows memaksa viewport ≥512px, jadi kasus 320px direproduksi via `html{width:320px}` + nilai clamp yang memang dihitung browser pada vw=320. |
| S-09 | ✅ Diperbaiki + bukti browser | Track 3 kolom mustahil memuat tab 3 tautan pada `lg`: terukur 291px (tamu) / 325px (login) vs band 240px di 1024px — label “Masuk” keluar dari band (terlihat pada tangkapan live 1024). Perbaikan di lima lembar: `lg:col-span-4 lg:gap-4 xl:gap-6 2xl:col-span-3` (band kembali berakhir di sumbu 42% pada 2xl). Bukti: live 1024 (`/`, `/siphp`, `/jdih`) + static login 1024/1152/1280 — label ketiga duduk di dalam band. |
| S-10 | ✅ Diperbaiki | Tombol lapisan peta cuaca: `:aria-pressed="overlay === …"` (5 tombol). Test kontrak mengunci 5 kemunculan; halaman live `/cuaca` juga 5. |
| S-11 | ✅ Diperbaiki | Tombol tutup toast (sukses & galat): target 44px (`p-3.5` + `-my-3.5 -mr-3.5`) dan ikon `aria-hidden="true"`; `aria-label` tetap sumber nama. Test kontrak. |
| Kontrak regresi | ✅ Baru | `tests/Feature/AccessibilityContractTest.php` — 7 test / 30 assertion mengunci T-04, S-06, S-07, S-10, S-11. (Perilaku viewport S-08/S-09 diverifikasi manual via Chrome headless — batas repo tanpa runner JS.) |

**Verifikasi ulang pasca-perbaikan a11y:** suite penuh **1682/1682 hijau (8835 assertion)**; `npm run build` sukses; bukti tangkapan layar di `%TEMP%\opencode\shots` (`lpsim-320-before/after`, `tab-1024-after`, `authed-1024/1152/1280-after`, `jdih-1024`, `siphp-1024`).

**Paket JDIH (PHASE J) — selesai & ter-commit** (`13b462f`): halaman pemberitahuan `/jdih` + pintu landing, sekaligus melunasi S-08/S-09 di landing & JDIH (swap skip-link + lebar tab `lg`). Gerbang G5 browser lulus: `/jdih` live 1024 & 512 (notice, alamat tujuan, tombol lanjut/kembali, tanpa redirect otomatis) + pintu footer "Dokumen hukum" dan daftar Layanan "Dokumen hukum (JDIH)". Test: `JdihNoticePageTest` (5 test / 19 assertion).

### C.3 — Paket P1 (S-02, S-04, S-05)

| Temuan | Status | Perubahan & bukti |
|---|---|---|
| S-02 | ✅ Diperbaiki | Lima jalur tulis/hapus file dipisahkan dari transaksi DB. **Foto toko** (`SuperAdminMarketStoreController`): create — file ditulis sebelum transaksi, dihapus balik bila rollback; update — unggahan baru ditulis lebih dulu (kompensasi saat rollback), file lama/yang dilepas dihapus hanya setelah commit; destroy — file dihapus setelah commit. **Lampiran laporan** (`Citizen\ComplaintController`): create & resubmission — file baru dikompensasi saat rollback, file yang dilepas dihapus hanya setelah commit. Bukti: `tests/Feature/TransactionalFileOpsTest.php` (5 test injeksi kegagalan via model event / emit notifikasi) menegakkan dua invarian: rollback tidak meninggalkan file yatim, dan tidak pernah ada baris yang menunjuk file hilang. |
| S-04 | ✅ Diperbaiki | `SuperAdminMarketPriceController::catat()`: `whereDate('effective_date', $date)` → `where('effective_date', $date)` — `DATE(kolom)` mematikan indeks `(market_id, effective_date)` dan memindai seluruh riwayat harga pasar. Bukti kontrak SQL: query log layar entri tidak boleh mengandung `DATE(\`effective_date\`)`. |
| S-05 | ✅ Diperbaiki (kode + dokumen) | Migrasi `2026_10_18_100001_add_subject_index_to_audit_logs_table.php` menambah indeks `(subject_type, subject_id)` untuk purge retensi — **dijalankan juga di DB dev**. Drift dokumen diselaraskan: `schema.md` kini mencatat indeks tunggal `(action)`/`(created_at)` yang benar-benar dimigrasikan + baris indeks baru. Bukti kontrak: `Schema::hasIndex` (nama + kolom). |

**Verifikasi ulang pasca-perbaikan P1:** suite penuh **1689/1689 hijau (8858 assertion)**; regresi fokus 76 test (panel SIPHP + alur validasi awal + pengalaman pelapor) hijau; migrasi dev DB sukses.

### C.4 — Paket P2 dokumen (D-02, D-03, T-02 parsial)

| Temuan | Status | Perubahan & bukti |
|---|---|---|
| D-02 | ✅ Diperbaiki (dokumen) | `schema.md`: scope note diluruskan (model WINDY kini ada + sudah dimigrasikan); seksi baru **§4.17–§4.20** mendokumentasikan `weather_sources`, `weather_locations`, `weather_forecasts`, `wave_forecasts` **as built** (mengikuti migrasi `2026_10_15_100001`–`100003`): tanpa tabel observasi ("current" = slot prakiraan terdekat), hujan mm/3 jam (O-14a; NULL ≠ 0), laut dalam knot, UNIQUE idempotensi; catatan kebijakan waktu: tersimpan **WITA** sebagaimana implementasi — konvensi **O-15 tetap tercatat terbuka** di `architecture.md:216` (tidak diputus ulang di sini); §5 bertambah 5 baris indeks weather; TODO WINDY di §13 ditutup. |
| D-03 | ✅ Diperbaiki (dokumen) | `PROGRESS.md`: angka terkini **30 migrasi / 137 route / 1689 test / 8858 assertion** (dua tempat) + catatan "Belum ada kode/database WINDY" diganti fakta implementasi. `SIPHP_INTEGRATION_PLAN.md`: status "uncommitted" → ter-commit `720c5cd` (2 tempat). `prd.md`: klausa lama "SIPHP dan JDIH tetap tidak dibangun" diberi penanda **→ DIPERBARUI** ke catatan PHASE S/J. Celah katalog `PROMPT_*` (Info) tidak diubah. |
| T-02 | ⚠️ Parsial — redaksi ✅, rotasi pending (owner) | Literal kredensial dev/test DB di laporan lama diganti placeholder: `PROMPT_18_SECURITY_ARCHITECTURE_AUDIT.md` (3 tempat) + `PROMPT_19_HARDENING_REPORT.md`; verifikasi grep: **nol kemunculan** di seluruh file ter-track. `phpunit.xml` **sudah bersih** (kredensialnya telah dipindah keluar pada prompt lanjutan — diverifikasi ulang). **Sisa aksi owner:** rotasi kredensial DB + keputusan rewrite riwayat git; repo tetap private. |

### C.5 — Paket P3 higiene minor (non-destruktif)

| Temuan | Status | Perubahan & bukti |
|---|---|---|
| `/siphp` menampilkan toko dari pasar nonaktif | ✅ Diperbaiki | `PublicMarketController::index()` — direktori toko kini hanya memuat toko aktif **dan** (tanpa pasar, D-S4a **atau** pasarnya aktif), konsisten dengan daftar pasar aktif + halaman detail pasar. Bukti: `SiphpPublicMarketTest::test_active_store_of_a_deactivated_market_is_hidden` (toko pasar nonaktif tersembunyi; toko pasar aktif & toko tanpa pasar tetap tampil). |
| `.env.example` kehilangan kunci tuning timeout WINDY | ✅ Diperbaiki | Ditambahkan `WINDY_HTTP_TIMEOUT=10` (land) dan `WINDY_MARINE_HTTP_TIMEOUT=10` (marine) — nilai default sesuai `config/weather.php:166,249`; sebelumnya kunci tak ditemukan di mana pun. |
| Kontras `ink-ghost` pada teks 11px (2.56:1 — gagal AA) | ✅ Diperbaiki | Empat pemakaian **teks**: `notification-item.blade.php:90,99`, `profile/field.blade.php:77`, `siphp/price-delta.blade.php:15` → `ink-soft` (#475569 ≈ 7.5:1 di atas putih). `ink-ghost` tetap untuk elemen non-teks (ikon/aksen). |

**Verifikasi ulang pasca-perbaikan P3:** suite penuh **1690/1690 hijau (8863 assertion)**; test terarah SIPHP publik + kontrak aksesibilitas 19/19; `npm run build` sukses (perubahan blade).

### C.6 — Paket P4 minor lanjutan (a11y target, nit tipe/kontrol, invalidasi wilayah, cache `/siphp`)

| Temuan | Status | Perubahan & bukti |
|---|---|---|
| Target ikon <44px | ✅ Diperbaiki | `app.css`: `.sb-rail-expand` 2rem→2.75rem, `.sb-rail-logout` (collapsed) 2.25rem→2.75rem. `notification-item.blade.php`: tombol hapus → area sentuh 44px dengan pola repo (padding + margin negatif; footprint visual tetap) — pola sama dengan perbaikan toast S-11. `layouts/dashboard.blade.php`: lonceng notifikasi `w-10 h-10` → `w-11 h-11`. |
| Label sumbu chart `font-size="17"` (tidak pada tangga tipe) | ✅ Diperbaiki | `design.md:296` menetapkan chart axis text = **13px**; 4 titik (`components/siphp/trend-chart.blade.php` dan `components/weather/trend-chart.blade.php`, ×2 masing-masing) → `font-size="13"`. |
| Select hairline di public sheet | ✅ Diperbaiki | `public/siphp-market.blade.php` (select tren): `border border-line bg-surface` → `bg-surface-sunken` tanpa border — sesuai `design.md:285` ("public sheets … no hairlines") dan pola kontrol sheet publik lain (pemilih lokasi cuaca). |
| CTA header landing 16px vs subpages 17px | ✅ Diperbaiki | `public/landing.blade.php`: dua CTA header `text-base` → `text-[17px]` (menyamai CTA subpage; 17px = reading text per `design.md`). |
| Tautan hash lintas-halaman tidak dikecualikan (teoretis) | ✅ Diperbaiki | `navigation.js`: semua tautan ber-fragment kini diserahkan ke browser (navigasi penuh; scroll-to-fragment native selalu benar) — swap SPA hanya mengganti `#main-content`, sehingga target fragmen bisa berada di luar wilayah swap. Tautan tanpa hash tidak berubah; guard halaman cuaca/siphp/jdih tetap. |
| Edit Kecamatan/Kelurahan tidak meng-invalidasi cache wilayah (nama basi ≤60 dtk) | ✅ Diperbaiki | Observer baru `KecamatanObserver` + `KelurahanObserver` (pola ComplaintCategory/DinasUnit → `DashboardCacheService::clearGlobalDashboardCaches()`), terdaftar di `AppServiceProvider`. Bukti: `DashboardCachingTest::test_admin_dashboard_cache_is_cleared_when_kecamatan_or_kelurahan_changes` (cache admin hilang pada tulis kecamatan **dan** kelurahan). |
| `/siphp` menghitung agregat tiap request tanpa cache | ✅ Diperbaiki | `PublicMarketCacheService` (TTL 60 dtk — batas staleness yang sama dengan dashboard staf; pola version-key tanpa tag) + observer `MarketDataObserver` untuk 5 model (`Market`, `MarketItem`, `MarketItemCategory`, `MarketPrice`, `MarketStore`): setiap tulisan Eloquent membump versi = invalidasi semua entri dalam satu tulis. Controller meng-cache agregat per halaman; pemilihan tren (param `barang`) tetap dihitung live. Bukti: 2 test kontrak — version bump satu-tulis + hasil index ter-cache dan tetap segar setelah pencatatan baru. |
| (Konsistensi a11y) label popup peta | ✅ Diperbaiki | `.sb-popup-label` `ink-ghost` → `ink-soft` (kontras AA; kelanjutan perbaikan kontras teks kecil di P3). |

**Verifikasi ulang pasca-perbaikan P4:** suite penuh **1693/1693 hijau (8897 assertion)**; test terarah (caching dashboard + SIPHP publik + kontrak aksesibilitas) 28/28; `npm run build` sukses.

**Sisa temuan:** T-02 rotasi kredensial (aksi owner), S-01 (keputusan owner), D-01 (keputusan owner), CSP/O-8 & retensi `market_prices`/`market_stores` + audit-log non-pengaduan (keputusan owner), higiene repo sisanya: artefak ter-track/layout lama/duplikat `resources/js/js` (keputusan owner), pint/CI (keputusan owner) — sesuai urutan prioritas Lampiran B.

---

*Laporan ini disusun dari audit berbasis bukti: setiap klaim memuat rujukan berkas:baris yang dapat diverifikasi ulang pada basis kode yang diaudit. Temuan §3–§6 adalah snapshot saat audit (auditor tidak mengubah kode aplikasi); perbaikan di Lampiran C (C.1 paket P0, C.2 paket a11y, C.3 paket P1, C.4 paket dokumen P2, C.5 higiene P3, C.6 minor P4) dikerjakan pasca-audit dan diverifikasi ulang.*
