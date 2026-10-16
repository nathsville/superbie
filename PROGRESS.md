# PROGRESS - SuperBie "Lapor Pak Wali"

Ringkasan progres pembuatan aplikasi pengaduan masyarakat (fitur pengaduan saja).
Terakhir diperbarui: 8 Oktober 2026.

---

## 1. Ringkasan Singkat

| Item | Nilai |
|---|---|
| Nama | SuperBie - Lapor Pak Wali |
| Jenis | Aplikasi pengaduan masyarakat (MVP) |
| Stack | Laravel (monolith) + Blade + Alpine.js + Tailwind CSS + MySQL |
| Peta | Leaflet + OpenStreetMap (read-only) |
| Migrasi DB | 25 (semua `Ran`) |
| Route | 101 |
| Test | 1462 passed / 7082 assertions / 0 gagal |
| Build aset | `npm run build` OK |

Prinsip: per irisan vertikal kecil, Laravel sebagai source of truth, tanpa Livewire/React/Redis/CDN.

---

## 2. Status Per Tahap

| Tahap | Isi | Status |
|---|---|---|
| PHASE 1 | Fondasi: auth masyarakat/internal, peran (masyarakat, Operator, Admin, Super Admin), profil pelapor, master data (kategori, dinas/unit, kecamatan, kelurahan) | Selesai |
| PHASE 2 | Form pengaduan, lampiran, panel Operator/Admin, batas 5 laporan/hari | Selesai |
| PHASE 2R | Imutabilitas metadata routing oleh Operator | Selesai |
| PHASE 3 - Bagian 1A/1B-1/1B-2/1C | Validasi Super Admin: antrean validasi awal, kirim penyelesaian oleh Operator, validasi penyelesaian (approve/return) | Selesai & dibekukan |
| PHASE 3 - Bagian 2 | Rating kepuasan pelapor (1-5 bintang) untuk laporan `resolved` | Selesai & dibekukan |
| PHASE 3 - Bagian 2 (review) | Ulasan tertulis opsional pada rating (`complaint_ratings.review_text`) — immutable, terlihat pelapor + Operator/Super Admin (read-only); moderasi BELUM ditetapkan | Selesai |
| PHASE 3 - Bagian 3 | Penutupan laporan (`resolved → closed`) manual oleh Operator/Super Admin (D-3.1..D-3.5) — **DIHENTIKAN pada LC-2** (`closed` kini legacy) | Selesai (historis) |
| PHASE 4 - Bagian 2 | Rating analytics (Admin/Super Admin global, Operator Dinas sendiri) via query param pada dashboard yang ada | Selesai & dibekukan |
| Geographic Analytics | Peta wilayah + analitik administratif di Dashboard Admin (Top Kecamatan/Kelurahan, backlog) | Selesai |
| Dashboard Redesign | Perombakan dashboard + navigasi progressive enhancement (Prompt 25-27) | Selesai |
| PHASE N4 | Notifikasi in-app (bell + halaman `/notifikasi`): milestone matrix M1-M12 (M9/M11 **dormant** pada lifecycle aktif LC-2), `notifications` table + `NotificationService`, owner-scoped read/mark/delete | Selesai |
| PHASE N6A / F-1 | Guard integritas workflow: Operator tidak dapat "Perbarui Status" selama `review_state = pending_completion_validation` (server-side, sebelum mutasi); opsi kembali setelah `completion_returned_to_operator`; Super Admin tidak terpengaruh (G7) | Selesai & dibekukan |
| PHASE N6C / N6B-1 | Completion Validation Queue hanya menampilkan `status = in_progress` DAN `review_state = pending_completion_validation` (query rule, tanpa mutasi `review_state`); interaksi G7 disengaja: direct resolve tidak mengubah `review_state` sehingga baris keluar dari antrian | Selesai & dibekukan |
| PHASE N6E / N6D-1 | Approve **dan** Return hanya actionable dari `status = in_progress` DAN `review_state = pending_completion_validation` (server-side, sebelum mutasi, re-check baris terkunci); baris stale (mis. hasil G7, `rejected`/`closed` + pending) ditolak tanpa mutasi/audit/M6; halaman detail validasi penyelesaian read-only untuk baris stale (deep-link M5 tetap aman) | Selesai |
| Operasional Produksi | Retention 5 tahun, rate limiting, hardening keamanan, runbook produksi | Selesai |
| LC-2A/2B/2C | Normalisasi lifecycle: matriks 3 transisi (`submitted → in_progress → {resolved \| rejected}`), presentasi status, dan KPI/dashboard/cache selaras lifecycle aktif (legacy = histori) | Selesai |
| PROMPT TAHAP 2 | Help Desk / Pusat Bantuan: tiket bantuan terpisah dari laporan + percakapan tertulis, ditangani Super Admin | Selesai |
| STAGE 3 | Balasan resmi Super Admin terhadap ulasan masyarakat (`complaint_ratings.admin_response`) — create-once, immutable | Selesai |

Artefak pendukung (sudah ada di repo): `prd.md`, `architecture.md`, `schema.md`, `design.md`, `rules.md`, `PRODUCTION_OPERATIONS.md`, `SETUP_AND_DOCS.md`, dan laporan `PROMPT_*.md`.

---

## 3. Fitur Utama yang Sudah Jadi

- Registrasi/login masyarakat; login internal (Operator/Admin/Super Admin).
- Kirim pengaduan + lampiran (disimpan via Laravel Storage, bukan path publik).
- Lokasi: **Kecamatan + Kelurahan wajib** (master wilayah dikelola Super Admin; dataset BPS resmi belum tersedia). **Koordinat opsional** (pasangan latitude/longitude) dengan peta Leaflet (tanpa API key).
- Riwayat & detail status laporan oleh pelapor (login; guest tidak dapat mengakses data laporan — tanpa pelacakan publik).
- Panel Operator: tangani laporan sesuai Dinas/Unit (review, status, catatan internal, respons publik, lampiran, lokasi). Operator **tidak** mengubah kategori/tujuan Dinas/Unit/penugasan (diputuskan Super Admin pada validasi awal).
- Panel Admin: monitoring + dashboard analitik (termasuk peta wilayah).
- Panel Super Admin: manajemen pengguna, validasi awal laporan, validasi penyelesaian, kategori, Dinas/Unit, mapping, wilayah, konfigurasi, audit.
- Siklus status laporan (LC-2): 7 nilai enum dipertahankan; **lifecycle aktif = 4 status** (`submitted`, `in_progress` aktif; `resolved`, `rejected` hasil akhir bisnis) dengan **3 transisi** (`submitted → in_progress`; `in_progress → resolved`; `in_progress → rejected`). `under_review`, `waiting_for_information`, `closed` = **legacy** (histori); tanpa reopen. KPI dashboard menghitung aktif = `submitted` + `in_progress`, selesai = `resolved`, ditolak = `rejected`; legacy dikecualikan dari aktif dan dilabeli "historis".
- Validasi review terpisah: 5 nilai `review_state` (+ NULL legacy).
- Rating kepuasan pelapor (Bagian 2): 1 laporan maksimal 1 rating, hanya saat `resolved`, tidak bisa diubah.
- Ulasan tertulis pelapor (Bagian 2, review): opsional (nullable) pada baris rating yang sama (`complaint_ratings.review_text`), immutable, maks 5000 karakter, selalu di-escape; terlihat oleh pelapor (detail sendiri) + Operator/Super Admin (read-only); tidak masuk analitik rating dan tidak disalin ke metadata audit (hanya `has_review` boolean). **Moderasi belum ditetapkan** (`prd.md` §4 `TODO`).
- Penutupan laporan `resolved → closed` (Bagian 3): **DIHENTIKAN pada LC-2.** `resolved` dan `rejected` kini hasil akhir bisnis; `closed` menjadi status legacy (histori). Data `closed` lama tetap ada dan dapat dibaca, tanpa konversi.
- Rating analytics (PHASE 4 Bagian 2): section pada dashboard Admin/Super Admin (global) dan Operator (Dinas sendiri); Masyarakat 403; via query param tanpa route baru.
- Retention otomatis: laporan dihapus 5 tahun setelah `submitted_at` (perintah `complaints:purge-expired` + scheduler).
- Navigasi progressive enhancement (bukan SPA): tukar region shell via `fetch()`, tetap jalan tanpa JS.
- **Notifikasi in-app (PHASE N4):** bell aktif dengan badge unread (sembunyi di 0, `9+` bila banyak) + dropdown 10 terbaru + halaman `/notifikasi` (paginasi 20/halaman, terbaru dulu). Notifikasi dibuat **di dalam transaksi** aksi bisnis yang sama lewat `NotificationService` eksplisit (bukan observer/event), idempoten via UNIQUE `(recipient_user_id, complaint_id, type, source_event_id)`. Penerima: **aktif saja**, **bukan aktor**, **bukan Admin/Petugas**; Operator dari `dinas_unit_id → DinasUnit::operator()` (bukan `assigned_to`); Super Admin aktif (single query). Read/unread + hapus **owner-scoped** (IDOR tertutup). Hanya in-app (tanpa email/queue/broadcast). Retensi: notifikasi dihapus sebelum complaint di-purge.
- **Help Desk / Pusat Bantuan (PROMPT TAHAP 2):** sistem tiket bantuan **terpisah** dari laporan. Masyarakat membuat tiket (kategori allowlist, judul, deskripsi, referensi laporan opsional miliknya sendiri) dan berkomunikasi lewat percakapan tertulis; **Super Admin** menangani seluruh tiket (balasan resmi + perubahan status). **Operator tidak menangani** Help Desk; **Admin tidak punya akses** (server-side `role:super_admin`). Tiket **tidak pernah** mengubah status/kategori/routing/catatan laporan. Status tiket: `open`, `in_progress`, `waiting_for_user`, `resolved`, `closed` dengan matriks transisi eksplisit; balasan biasa hanya pada `open`/`in_progress`/`waiting_for_user`, `resolved` hanya dapat **dibuka kembali** (pesan wajib), `closed` terminal. Pengirim **selalu** dari sesi (tidak dapat diforged); pesan di-escape (XSS); audit metadata tanpa isi pesan; endpoint tulis dibatasi `help-desk-write` (20/10 menit). Notifikasi tiket, lampiran, moderasi, SLA, dan retensi tiket **belum dibuat** (pekerjaan lanjutan).
- **Balasan resmi Super Admin terhadap ulasan (STAGE 3):** Super Admin dapat memberikan **satu balasan resmi** atas ulasan tertulis masyarakat. Disimpan pada kolom baru `complaint_ratings.admin_response` (**TEXT nullable**, migration additive + reversible) — **tanpa tabel/model baru**. **Create-once & immutable** (tanpa endpoint ubah/hapus; request kedua tidak pernah menimpa balasan pertama, dijaga `UPDATE ... WHERE admin_response IS NULL` di dalam transaksi). Balasan **tidak** mengubah `rating`, `review_text`, status laporan, KPI, maupun Rating Analytics. Hanya dapat dibuat jika rating ada, ulasan tertulis non-kosong, dan belum dibalas. **Hanya Super Admin** (server-side `role:super_admin`); Admin/Operator/Masyarakat ditolak, termasuk request HTTP langsung; identitas pembalas & relasi objek berasal dari sesi/route binding (anti-forgery/IDOR). Teks di-trim, maks 5000 karakter, selalu di-escape Blade. Pelapor melihat balasan **read-only** pada laporan miliknya; Operator hanya melihat rating/ulasan read-only (tanpa kontrol balasan). Audit `complaint.review_responded` hanya mencatat id + `has_response` (tanpa isi teks). Route `POST /super-admin/balasan-ulasan/{complaint}` sengaja **di luar** prefix read-only `/super-admin/laporan`. Notifikasi/percakapan lanjutan **belum dibuat**.

---

## 4. Yang Belum / Catatan

- **Dataset wilayah BPS** belum diberikan (blokir data master wilayah resmi; tidak memengaruhi fitur lain).
- **Keputusan bisnis yang masih terbuka** (tidak diselesaikan di sini): staff password reset; konfigurasi operasional yang lebih luas (saat ini hanya `daily_report_limit`); rate limit registrasi; SLA/eskalasi.
- **PROMPT TAHAP 2 — Help Desk open items:** kebijakan retensi/penghapusan tiket (saat ini **tidak ada** — tiket tidak ikut purge laporan); moderasi konten tiket/pesan (**tidak ada**); notifikasi tiket (**tidak ada** — tabel `notifications` berjangkar `complaint_id` NOT NULL); lampiran tiket (**tidak ada**); SLA/eskalasi tiket (**tidak ada**).
- **STAGE 3 — open items:** notifikasi balasan ulasan (**tidak ada**); percakapan balasan berantai (**tidak ada** — satu balasan create-once); moderasi ulasan/balasan (**belum ditetapkan**, `prd.md` §4 `TODO`); kebijakan retensi khusus balasan (**tidak ada** — balasan ikut terhapus bersama baris rating oleh purge laporan 5 tahun).
- **STAGE 3B — stabilization & verifikasi migration (selesai, uncommitted):** (1) Flaky test paging `SuperAdminComplaintManagementTest::test_pagination_is_server_side` diperbaiki pada akar masalahnya: `ComplaintCategoryFactory` menurunkan `slug` (UNIQUE) dari frasa 2 kata acak; kini memakai `fake()->unique()->words(2, true)` (konvensi yang sama dengan `UserFactory`/`DinasUnitFactory`) sehingga slug unik secara konstruksi. **Hanya fixture test yang berubah — tidak ada perilaku runtime produksi.** Terverifikasi 25/25 run lulus + probe 2000 nama tanpa duplikat. (2) Seluruh 25 migration diuji **nyata** dari database kosong pada database audit terisolasi `superbie_stage3b_audit` (MySQL 8.0.30, `127.0.0.1:3307`); skema penting (rating/ulasan/balasan, Help Desk, sistem inti) terverifikasi. (3) Reversibilitas Stage 3 `2025_01_11_100021_add_admin_response_to_complaint_ratings_table` diuji nyata: rollback terarah (`--path`, hanya migration ini) → `admin_response` hilang, `review_text`/kolom lain/tabel tetap ada, data dummy baris rating tetap utuh; reapply → kolom kembali `text NULL`. Database `superbie` dan `superbie_testing` **tidak** mengalami operasi destruktif.
- **PHASE 6C — legacy semantics remediation (selesai, uncommitted):** 9 keputusan PHASE 6B diimplementasikan. Baris historis peran `petugas` **dipertahankan** (historical; tanpa remap/hapus). Kolom legacy `assigned_to`, `tracking_secret_hash`, `reporter_phone`, `public_updated_at` **DEPRECATED** (tidak lagi ditulis; dipertahankan historical; tanpa DROP kolom). `reporter_email` dan `complaint_categories.dinas_name` **DIPERTAHANKAN** (snapshot/label historis). KPI "belum ditugaskan" (`assigned_to IS NULL`) **DIDEFINISIKAN ULANG** menjadi "Belum Ditentukan Dinas/Unit" (`dinas_unit_id IS NULL`, scope aktif). Filter penugasan Operator (mine/unassigned) **DIHAPUS**.
- Semua pekerjaan masih **uncommitted** di `main` lokal. Jangan push (origin/upstream tidak relevan).
- Tidak dikerjakan (di luar scope): notifikasi **eksternal** (email/SMS/WhatsApp), pelacakan publik, reopening/SLA, modul SIPHP/JDIH. **Catatan (Phase 3, O-1):** modul **Weather (WINDY)** kini **termasuk lingkup yang disetujui** sebagai dashboard cuaca publik baca-saja — tetapi belum diimplementasikan dan tunduk pada gerbang rilis `_superbie_audit/WINDY_MIGRATION_PLAN.md`. **Catatan (Phase 4E, D-11…D-17):** keputusan pemilik WINDY dicatat — horizon prakiraan **3 hari** dari **BMKG gratis** (tanpa penyedia berbayar); tampilan **WITA** (konvensi penyimpanan waktu masih terbuka); ambang lama hanya **nilai awal**; **tanpa impor historis Google Sheets**; sinkronisasi **terjadwal + manual** (manual oleh Super Admin; terjadwal bergantung host scheduler yang belum terverifikasi; Admin monitoring-only); patuhi **atribusi** BMKG/OWM; **tanpa** teknologi/Docker baru. **Belum ada kode/database WINDY yang dibuat.**

---

## 5. Cara Menjalankan

```bash
# instalasi
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build

# jalankan lokal
php artisan serve

# test
composer test
```

Akses LAN (untuk tim): gunakan `serve-lan.ps1` (WiFi kantor biasa) atau `hotspot-lan.ps1`
(PC jadi hotspot, dipakai bila WiFi kantor mengaktifkan client isolation).

---

## 6. Milestone Angka (progres test — historis per tahap)

> Catatan: tabel di bawah mencatat jumlah test **pada saat tahap itu selesai** (konteks historis). Angka **terkini** yang terverifikasi: **1462 test / 7082 assertions**, **101 route**, **25 migrasi**.

| Tahap | Test | Assertions |
|---|---|---|
| Baseline | 971 | 4629 |
| 1B-1 | 994 | 4688 |
| 1B-2 | 1028 | 4785 |
| 1C | 1086 | 4995 |
| Bagian 2 (Rating) | 1120 | 5177 |
| Bagian 3 + PHASE 4 Bagian 2 | 1193 | 5445 |
| PHASE N4 (Notifikasi in-app) | 1269 | 6048 |
| PHASE N6A / F-1 (Guard completion gate) | 1287 | 6108 |
| PHASE N6C / N6B-1 (Queue eligibility + G7 interaction) | 1295 | 6158 |
| LC-2A/2B/2C (Lifecycle + presentasi + KPI/dashboard/cache) | 1346 | 6514 |
| Bagian 2 (Ulasan tertulis) | 1375 | 6645 |
| PROMPT TAHAP 2 (Help Desk / Pusat Bantuan) | 1427 | 6961 |
| STAGE 3 (Balasan resmi Super Admin atas ulasan) | 1462 | 7082 |

Migrasi: 22 -> 23 -> 24 -> 25. Route: 90 -> 100 -> 101.
