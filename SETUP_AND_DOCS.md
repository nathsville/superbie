# SuperBie — Command Center "Lapor Pak Wali"

Aplikasi web pengaduan masyarakat Pemerintah Kota Parepare berbasis Laravel monolith dengan arsitektur peran bertingkat: **Masyarakat**, **Operator**, **Admin**, dan **Super Admin**.

---

## 1. Persyaratan Sistem

Implementasi ini dijalankan langsung di lingkungan **Windows + Laragon** (bukan Docker):

- PHP **8.4.x** (diuji pada 8.4.26)
- Composer
- Node.js + npm (untuk build aset Vite)
- MySQL (dijalankan oleh Laragon; port dev proyek ini `3307`)
- Port `3307` (MySQL) dan `5173` (Vite dev server) tersedia di host

> **Catatan (Prompt 8):** Dokumentasi sebelumnya mendokumentasikan alur Docker (`Dockerfile`, `docker-compose.yml`, `docker compose up`) yang **tidak ada** di repositori ini. Docker bukan jalur menjalankan aplikasi versi ini; dokumentasi telah diselaraskan dengan runtime aktual (Laragon/MySQL).

---

## 2. Cara Menjalankan Aplikasi (Laragon/MySQL)

### Langkah 1: Clone & Siapkan Environment

```bash
# Salin konfigurasi environment
cp .env.example .env

# Generate application key
php artisan key:generate
```

Pastikan variabel database di `.env` sesuai MySQL lokal Anda, misalnya:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=superbie
DB_USERNAME=root
DB_PASSWORD=
```

### Langkah 2: Install Dependensi & Build Aset

```bash
composer install
npm install
npm run build      # atau: npm run dev (Vite dev server di port 5173)
```

### Langkah 3: Migrasi & Seed Database

```bash
php artisan migrate --seed
```

> Seeder mengisi **akun development** dan **data contoh (sample/development)**, bukan daftar resmi Kategori/Dinas. Daftar resmi dikelola Super Admin melalui dashboard.

### Langkah 4: Jalankan Aplikasi

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000` (atau vhost Laragon) di browser.

---

## 3. Akun Development untuk Pengujian

Semua akun menggunakan password: `password123`

| Peran | Email | Akses & Kemampuan |
|---|---|---|
| **Masyarakat** | `warga1@superbie.local` | Dashboard sendiri, riwayat sendiri, buat laporan |
| **Masyarakat** | `warga2@superbie.local` | Dashboard sendiri (terisolasi dari warga 1) |
| **Operator** | `operator@superbie.local` | Penanganan operasional laporan: review, kategori, penugasan, status, catatan, respons |
| **Admin** | `admin@superbie.local` | **Monitoring saja** (tanpa aksi pengelolaan) |
| **Super Admin** | `superadmin@superbie.local` | Akses penuh seluruh modul dan sistem |

---

## 4. Cara Menjalankan Automated Tests

```bash
# Jalankan seluruh test suite (test DB: superbie_testing, MySQL)
php artisan test

# Atau jalankan test spesifik otorisasi dashboard
php artisan test --filter=DashboardAuthorizationTest
```

> **Penting:** test menggunakan database MySQL terpisah `superbie_testing` (lihat `phpunit.xml`, port `3307`), **bukan** SQLite. Pastikan MySQL berjalan sebelum menjalankan test.

---

## 5. Struktur Project

```
superbie/
├── app/
│   ├── Enums/
│   │   ├── ComplaintStatus.php       # Status laporan & transisi (frozen)
│   │   ├── NoteVisibility.php        # internal vs public_response
│   │   └── UserRole.php              # Definisi 4 peran sistem
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                 # Login, Register, Forgot/Reset Password
│   │   │   ├── Citizen/              # Dashboard & riwayat masyarakat
│   │   │   ├── Staff/                # Dashboard Operator, Admin, Super Admin
│   │   │   ├── SuperAdmin/           # Master data Kategori & Dinas/Unit + mapping
│   │   │   └── DashboardRedirectController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureUserHasRole.php # Otorisasi peran sisi server
│   │   │   └── EnsureUserIsActive.php# Blokir akun nonaktif
│   │   └── Requests/                 # Form Requests (validasi + authorize)
│   ├── Models/                       # User, Complaint, ComplaintCategory, DinasUnit, dll.
│   ├── Observers/                    # Audit observer
│   └── Services/                     # Business logic (DashboardCacheService, retention, dll.)
├── config/
│   └── business_rules.php            # Single source of truth aturan bisnis
├── database/
│   ├── migrations/                   # Skema database MySQL sesuai schema.md
│   └── seeders/DatabaseSeeder.php    # Akun dev & data contoh (sample, bukan resmi)
├── resources/
│   ├── css/app.css                   # Design tokens, animasi, reduced-motion
│   └── views/
│       ├── components/layouts/       # Layout app & dashboard (Blade components)
│       ├── auth/                     # Login, register, forgot-password, reset-password
│       ├── public/landing.blade.php  # Landing page
│       ├── citizen/                  # Dashboard, riwayat, form laporan, profil
│       ├── operator/                 # Dashboard & penanganan laporan
│       ├── admin/                    # Dashboard, monitoring laporan, audit log (read-only)
│       └── super-admin/              # Dashboard, kategori, dinas, mapping
├── routes/
│   ├── web.php                       # Route web (tanpa role petugas)
│   └── console.php                   # Scheduler (purge retention harian)
└── tests/Feature/                    # Test fitur (DB: superbie_testing)
```

---

## 6. Daftar TODO — Requirement yang Belum Ditentukan

Sesuai aturan **"DOCUMENTATION FIRST / DO NOT INVENT"**, berikut hal-hal yang belum ditentukan secara resmi dalam spesifikasi dan ditandai sebagai `TODO`:

1. **DONE (Prompt 5B)** — Daily report limit final = **5 laporan / hari kalender** (timezone aplikasi), diatur di `config/business_rules.php` dan dapat dioverride via `app_settings.daily_report_limit`.
2. `TODO: Define official category → dinas/unit mapping` — Daftar kategori resmi Pemerintah Kota Parepare dan **daftar resmi Dinas/Unit** belum ditetapkan. Struktur many-to-many (`dinas_units` + pivot `category_dinas_unit`) dan **CRUD Super Admin (Dinas/Unit: Prompt 5C; Kategori: Prompt 6)** sudah tersedia. Complaint menyimpan **tujuan Dinas/Unit aktual** (`complaints.dinas_unit_id`). Seed berisi *sample/development*, bukan daftar resmi.
3. `TODO: Define official statuses, SLA, and workflow transitions` — **Status resmi dan transition matrix: DONE (finalized per Prompt 3B).** SLA dan escalation masih pending. **Prompt 8:** karena SLA belum ditetapkan sebagai business rule, tidak ada nilai SLA (mis. 3/5/7 hari atau 24 jam) yang boleh dihitung, ditampilkan, atau diklaim di dashboard/view mana pun. Nilai SLA yang sebelumnya muncul di Admin Dashboard/`Monitoring Laporan` telah **dihapus**; sebagai gantinya hanya ditampilkan **umur laporan** (selisih faktual dari `submitted_at`) yang bukan klaim SLA.
4. **DONE (Prompt 5B)** — Attachment rules final = **opsional, maks 10 file, maks 20 MB/file, format JPG/JPEG/PNG/PDF/MP4, tanpa malware scanning.** Diatur di `config/business_rules.php`.
5. **DONE (Prompt 5B)** — Public tracking **tidak diperlukan** untuk MVP; guest tidak boleh melihat data laporan. Tidak ada endpoint/route public tracking.
6. **DONE (Prompt 5B)** — Notification **tidak diperlukan** untuk MVP.
7. `TODO: Define retention & privacy policy` — **Privacy: DONE (Prompt 5B)** — masyarakat hanya melihat laporan miliknya; guest tidak dapat mengakses data laporan. **Retention: DONE (Prompt 5B.1)** — 5 tahun sejak `complaints.submitted_at` → **permanent deletion** (data anak complaint, berkas lampiran fisik, audit log terkait complaint). Command `complaints:purge-expired` + scheduler harian (02:00). Akun user & master data tidak terhapus.
8. **DONE — BUSINESS DECISION FINAL (Prompt 4B)** — Mekanisme tanggapan/balasan Masyarakat saat status `waiting_for_information`. **Keputusan resmi (H1–H12 = Tidak):** Masyarakat **tidak** dapat membalas atau menambahkan informasi/bukti setelah laporan dikirim. Tidak ada citizen response, citizen reply, form tambahan, attachment tambahan, endpoint/route respons, maupun transisi status yang dipicu Masyarakat. Status `waiting_for_information` hanya **ditampilkan** sebagai "Menunggu Informasi"; perpindahan status tetap dilakukan **Operator/Super Admin** sesuai transition matrix yang sudah frozen (`waiting_for_information → in_progress` atau `→ resolved`). Tidak ada entity/kolom/migration tambahan yang diperlukan.
9. ~~`TODO: Define retention reference timestamp`~~ **DONE (Prompt 5B.1)** — Reference retention FINAL = `complaints.submitted_at` ("sejak laporan masuk"). Implementasi: `app/Services/ComplaintRetentionService.php` + command `complaints:purge-expired` (`--dry-run`, `--force` untuk production). **Permanent deletion**, tanpa archive/soft-delete/grace period. `submitted_at IS NULL` tidak pernah dianggap expired; tidak ada fallback ke `created_at`.
10. ~~`TODO: Define official Dinas/Unit list`~~ **PARTIAL (Prompt 5C, 6)** — Struktur relasional + CRUD Super Admin (Dinas/Unit & Kategori) + mapping + routing Operator selesai. **Daftar resmi Dinas/Unit masih belum tersedia**; yang ada adalah sample/development (ditandai jelas, bukan daftar resmi). Super Admin dapat menambah/mengubah/menonaktifkan via dashboard.

---

## 7. Catatan Implementasi Prompt 4 — Citizen Complaint Experience

- **Status timeline (sisi Masyarakat):** hanya menampilkan status yang benar-benar tercatat pada `complaint_status_histories` (bukan seluruh daftar enum). `note` pada riwayat status bersifat operasional internal dan **tidak** ditampilkan ke Masyarakat. Alasan penolakan resmi disampaikan melalui **public response** (`complaint_notes.visibility = public_response`), yang diwajibkan secara server-side oleh `ComplaintStatus::requiresPublicResponse()`.
- **Penyimpanan lampiran privat:** disk `local` (root `storage/app/private`, sama dengan disk `private`) dikonfigurasi dengan `'serve' => false` agar route `/storage/{path}` tidak pernah terdaftar untuk file privat. Lampiran pengaduan hanya dilayani melalui controller terotorisasi (`citizen.complaint.attachment`, `operator.complaint.attachment`).
- **Allowlist field pembuatan laporan:** request Masyarakat hanya menerima `category_id`, `title`, `description`, `location_text`, dan `attachments[]`. `status`, `assigned_to`, `reporter_id`, dan field internal lain diabaikan (bukan mass-assignment).

---

## 8. Catatan Implementasi Prompt 4B — Finalisasi Citizen Experience

Business decision `waiting_for_information` telah **FINAL** (Prompt 4B) dengan hasil H1–H12 = **Tidak**. Konsekuensi yang divalidasi pada implementasi:

- **Tidak ada citizen response mechanism.** Tidak ada route, controller action, form, endpoint, atau tabel khusus respons Masyarakat. Ini diverifikasi secara otomatis oleh `tests/Feature/CitizenExperienceFinalTest.php` (§tidak ada route respons).
- **`waiting_for_information` hanya ditampilkan.** Halaman detail Masyarakat menampilkan status "Menunggu Informasi" tanpa tombol apa pun untuk membalas/menambah bukti.
- **Status tetap dikendalikan staf.** Masyarakat tidak memiliki kemampuan mengubah status (authority Masyarakat = NO, frozen). Hanya Operator/Super Admin yang dapat menjalankan `waiting_for_information → in_progress` / `→ resolved`.
- **Tidak ada perubahan schema.** `complaint_notes` tetap `internal | public_response` (staff-only). Tidak ada migration/kolom/entity baru. Historical data aman.
- **Komunikasi publik tetap satu arah** dari staf ke Masyarakat melalui **public response** (`complaint_notes.visibility = public_response`), yang diwajibkan server-side untuk status `rejected` dan `resolved` via `ComplaintStatus::requiresPublicResponse()`.
- **Isolasi data & lampiran** (Prompt 4) tetap berlaku: dashboard/history/detail di-scope ke `reporter_id` authenticated user pada level database; lampiran disajikan lewat controller terotorisasi dengan cek kepemilikan ganda (`complaint_id` + `reporter_id`), tanpa direct public serving (`local` disk `'serve' => false`).

---

## 9. Catatan Implementasi Prompt 5B — Final Business Rules

Business rules final diimplementasikan; nilai utama terpusat di `config/business_rules.php` (single source of truth).

- **Daily limit:** maks **5 laporan / hari kalender** (timezone aplikasi). Mekanisme existing (`DailyReportLimit`) dipertahankan; nilai default 5 dipakai bila `app_settings.daily_report_limit` belum di-set. Divalidasi server-side; laporan ke-6 ditolak tanpa membuat record/attachment/history.
- **Identity (NIK/HP/Alamat):** kolom `users.nik` (char 16, unique, immutable), `users.phone_number` (unique, local+international, dinormalisasi ke bentuk lokal `08…`), `users.address` (text, free text tanpa batas bisnis). Wajib di **registrasi & profil** (server-side). NIK **tidak dapat diubah** (ditolak di model layer & tidak ada input editable di profil). Kolom ditambahkan **nullable** untuk non-destruksi (tanpa NIK palsu untuk user lama).
- **Attachment:** opsional, maks **10 file**, maks **20 MB/file**, format **JPG/JPEG/PNG/PDF/MP4**. Tanpa malware scanning. Lampiran tetap privat & terotorisasi.
- **Category ↔ Dinas/Unit:** relasi **many-to-many** (`dinas_units` + pivot `category_dinas_unit`). `complaint_categories.dinas_name` (legacy) dipertahankan sebagai kompatibilitas, **bukan sumber resmi routing**. **Data Dinas/Unit resmi tidak di-seed**; **CRUD Dinas/Unit + Category + mapping oleh Super Admin sudah tersedia** (Prompt 5C & 6) — daftar resmi tetap menunggu input Super Admin.
- **Public tracking:** tidak diperlukan MVP — tidak ada endpoint/route; guest tidak dapat mengakses data laporan.
- **Notification:** tidak diperlukan MVP — tidak ada implementasi.
- **Privacy:** masyarakat hanya melihat laporan miliknya; guest dilarang; internal note/audit tersembunyi (diverifikasi test).
- **Retention:** **DONE (Prompt 5B.1)** — 5 tahun sejak `complaints.submitted_at` → **permanent deletion**. Lihat §10.

---

## 10. Catatan Implementasi Prompt 5B.1 — Retention & Permanent Deletion

Business rule retention **FINAL**:

```
Retention   = 5 tahun dari complaints.submitted_at (timezone aplikasi)
Action      = permanent deletion
Audit log   = terkait complaint → ikut dihapus; tidak terkait → dipertahankan
```

**Implementasi:**
- Service: `app/Services/ComplaintRetentionService.php` (query expired, purge per-complaint).
- Command: `php artisan complaints:purge-expired` (`--dry-run`, `--force` untuk production).
- Scheduler: `routes/console.php` → `Schedule::command('complaints:purge-expired')->dailyAt('02:00')`.
  Memerlukan cron `* * * * * php artisan schedule:run` pada server (tidak otomatis).
  > **Prompt 23:** mekanisme produksi yang menjalankan `schedule:run` (cron/systemd/platform scheduler) **belum ditentukan** → `DECISION REQUIRED`. Lihat `PRODUCTION_OPERATIONS.md` §3.

**Data yang DIHAPUS:** `complaints`, `complaint_status_histories`, `complaint_notes`, `complaint_attachments` (+ berkas fisik di disk privat), `audit_logs` dengan `subject_type='complaint'` dan `subject_id` complaint tersebut.

**Data yang TIDAK dihapus:** `users` (+ NIK/HP/Alamat), `complaint_categories`, `dinas_units`, `category_dinas_unit`, audit log tak terkait (user/kategori/system/complaint lain).

**Safety:** batch `chunkById` (100), **urutan aman: collect attachment paths → DB transaction (children → audit → complaint) → COMMIT → baru hapus physical file** (Prompt 5B.2). DB rollback → file tidak terhapus; kegagalan file cleanup → DB tidak di-rollback palsu (dilaporkan sebagai `failed_files`). Error per-complaint tidak menghentikan batch, idempotent, `--dry-run` tanpa penghapusan, `--force` wajib di production. `submitted_at IS NULL` tidak pernah dianggap expired. Tidak ada migration/schema baru.

---

## 11. Catatan Implementasi Prompt 5C — Dinas/Unit Master Data & Category Routing

**Master data Dinas/Unit (Super Admin saja):**
- Field: `name`, `code` (opsional, unik), `description` (opsional, sudah ada sejak 5B), `is_active`, `sort_order`. **Tidak ada field baru ditambahkan.**
- CRUD: `super-admin/dinas` (`SuperAdminDinasUnitController`) — index, create, store, edit, update, **destroy (DELETE)**. Route middleware `role:super_admin` + `authorize()` di Form Request.
- Authorization **server-side**; Admin/Operator/Masyarakat → **403**.
- **Delete safety (Prompt 5C.1):** Dinas/Unit yang **sudah pernah digunakan** sebagai tujuan aktual complaint **TIDAK DAPAT di-hard-delete** — ditolak di model layer + endpoint `DELETE` (redirect + error). Super Admin harus **menonaktifkannya**. Hanya Dinas/Unit yang **belum pernah digunakan** yang boleh dihapus. FK `ON DELETE SET NULL` tetap ada hanya sebagai defensive DB behavior dan **tidak pernah tercapai** melalui flow aplikasi.

**Status active/inactive:**
- Dinas/Unit nonaktif **tidak dapat dipilih** sebagai tujuan penugasan baru.
- Complaint yang sudah menunjuk Dinas/Unit nonaktif **tetap** menunjuk, dapat ditampilkan & diproses; **tidak** di-unassign/pindah/hapus.

**Mapping Category ↔ Dinas/Unit (many-to-many, Super Admin):**
- `super-admin/kategori/{category}/mapping` (`SuperAdminCategoryMappingController`) — edit + update (`sync`).
- Menghapus/mengubah mapping **tidak** mengubah complaint lama (tujuan aktual tersimpan pada complaint).

**Routing Operator (actual destination):**
- `complaints.dinas_unit_id` (nullable, FK → `dinas_units.id`, **ON DELETE SET NULL**) menyimpan **tujuan aktual** (Prompt 5C). Migration non-destruktif.
- Operator menetapkan tujuan dari Dinas/Unit yang **terhubung dengan kategori** complaint dan **aktif** (untuk penugasan baru). Route: `PATCH operator/laporan/{complaint}/tujuan`.
- Validasi server-side: tujuan harus ter-mapping ke kategori; nonaktif ditolak kecuali merupakan tujuan yang sudah tersimpan (histori). `null`/kosong = hapus tujuan.
- Audit log memakai konvensi existing (`subject_type` string: `dinas_unit`, `complaint_category`, `complaint`).

**Data sample:** seed mengisi **4 Dinas/Unit sample** + 5 mapping (ditandai jelas sebagai *development sample*, bukan daftar resmi). Super Admin mengelola data resmi via dashboard.

---

## 12. Catatan Implementasi Prompt 6 (Bagian 1) — Category & Master Data Management

**Master data Kategori (Super Admin saja):**
- Field (schema existing, **tanpa field baru**): `name` (wajib), `description` (opsional), `dinas_name` (label legacy, opsional), `is_active`, `sort_order`. `slug` **diturunkan server-side** dari `name` (unik, tidak diterima dari request).
- CRUD: `super-admin/kategori` (`SuperAdminCategoryController`) — index, create, store, edit, update, **toggle status (activate/deactivate)**, destroy. Route middleware `role:super_admin` + `authorize()` di Form Request.
- Authorization **server-side**; Operator/Admin/Masyarakat → **403**; guest → redirect login.
- Audit log memakai konvensi existing: `subject_type = 'complaint_category'`, aksi `complaint_category.created|updated|activated|deactivated|deleted`.

**Category active/inactive (server-side):**
- Kategori **aktif** → tersedia untuk laporan baru (Masyarakat memilih kategori).
- Kategori **nonaktif** → **ditolak server-side** saat pembuatan laporan (bukan hanya disembunyikan di UI). Validasi di `StoreComplaintRequest` (`exists` + cek `is_active`).
- Kategori **tidak ditemukan** → ditolak server-side.
- Masyarakat **tidak** memilih Dinas/Unit pada form laporan; `dinas_unit_id` pada request diabaikan (tujuan ditentukan Operator lewat workflow internal).

**Historical safety (Category):**
- Menonaktifkan kategori **tidak mengubah** complaint lama: `category_id`, status, timeline, audit, dan tujuan Dinas/Unit aktual tetap.
- Mengubah/menghapus **mapping** tidak mengubah complaint lama (tujuan aktual tersimpan pada `complaints.dinas_unit_id`).
- **Delete safety:** kategori yang **sudah pernah digunakan** complaint **tidak dapat di-hard-delete** — ditolak di model layer (`deleting` guard) + endpoint `DELETE` (redirect + error). Super Admin harus **menonaktifkannya**. Hanya kategori **belum pernah digunakan** yang boleh dihapus. FK `complaints.category_id` `ON DELETE SET NULL` tetap hanya sebagai defensive DB behavior dan **tidak pernah tercapai** lewat flow aplikasi (nulling akan melanggar aturan histori).

**Category ↔ Dinas/Unit:**
- Tetap **many-to-many** (tidak diubah menjadi one-to-many). Mapping dikelola Super Admin; Dinas/Unit nonaktif tetap boleh ter-mapping (tidak auto-dihapus) namun tidak tersedia untuk penugasan baru; reaktivasi mengembalikan ketersediaannya.
- `dinas_name` legacy **bukan** sumber kebenaran routing; routing memakai `category_dinas_unit` dan tujuan aktual pada complaint.

**Tidak berubah:** lifecycle status, retention, citizen workflow/privacy, attachment rules, daily limit, identity, public tracking/notification, peran (4 role aktif; Petugas historis). **Tidak ada migration baru.**

---

## 13. Catatan Implementasi Prompt 6 (Bagian 2) — Integrasi Category ↔ Dinas/Unit, UI Super Admin & Operator Routing

**Mapping management (Super Admin saja):**
- `super-admin/kategori/{category}/mapping` — `SuperAdminCategoryMappingController` (edit + update/sync). View: `super-admin/categories/mapping.blade.php`.
- Validasi server-side via `SyncCategoryMappingRequest` (`authorize()` super_admin; `dinas_unit_ids.*` → `integer` + `exists:dinas_units,id`; id duplikat di-collapse). Hanya baris pivot kategori yang di-bind yang berubah (**IDOR-safe**); complaint **tidak pernah** disentuh.
- UI menampilkan kategori + daftar Dinas/Unit dengan badge **Mapped / Aktif / Nonaktif** (memakai pola badge existing). Dinas/Unit nonaktif **tetap terlihat** (mapping dipertahankan).
- Audit: `category_mapping.updated` (+ granular `category_mapping.dinas_added` / `category_mapping.dinas_removed`), `subject_type='complaint_category'` (konvensi existing).

**Category ↔ Dinas/Unit:**
```
Category  ↕ (many-to-many, tabel category_dinas_unit) ↕  Dinas/Unit
```
- Satu kategori ↔ banyak Dinas/Unit; satu Dinas/Unit ↔ banyak kategori. `UNIQUE(category_id, dinas_unit_id)`.

**Tujuan aktual complaint (BUKAN pivot):**
- `complaints.dinas_unit_id` menyimpan **tujuan aktual** yang dipilih Operator — **bukan** `category_dinas_unit`. Pivot = mapping saat ini; `complaints.dinas_unit_id` = histori. **mapping ≠ historical destination.**
- Operator memilih tujuan dari: kategori complaint → mapping → **filter `is_active = true`** → pilih. Validasi server-side: `exists` + `is_active` + **ter-mapping ke kategori complaint** (bukan sekadar `find`).
- Complaint dengan tujuan Dinas/Unit nonaktif **tetap** dapat dibuka/diproses; tujuan historis tidak diubah.

**Legacy `dinas_name`:**
- Hanya **label tampilan** (fallback). **Bukan** sumber routing/validasi/assignment. Form citizen kini menampilkan instansi penanggung jawab dari **mapping** (`category_dinas_unit`, aktif) dengan fallback `dinas_name`; dropdown kategori tidak lagi menempelkan `dinas_name`. Field DB legacy **tidak dihapus**.

**Citizen form:** hanya memilih **Kategori**; tidak ada input Dinas/Unit; `dinas_unit_id` pada request diabaikan.

**Regression 5C.1:** Dinas/Unit terpakai complaint tetap tidak dapat di-hard-delete; Dinas/Unit tak terpakai tetap dapat dihapus (mapping tidak menghalangi delete unit tak terpakai).
