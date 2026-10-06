# SUPERBIE — PROMPT 5A AUDIT REPORT
## Business Rule Audit & Freeze — Daily Limit, Category → Dinas/Unit, Attachment, Tracking, Notification, Identity, Retention & Privacy

- **Jenis prompt:** AUDIT READ-ONLY.
- **Prinsip:** NO GUESSING. Bila tidak ada sumber/business decision → `UNKNOWN / NOT DEFINED / TODO`.
- **Tidak ada** perubahan kode produksi, migration, route, controller, view, CRUD, test baru, bugfix, refactor, perubahan schema, atau perubahan environment.
- **Baseline:** 198 tests / 626 assertions / 0 failed · 32 routes · 8 migrations (semua `Ran`).

---

## 1. Status

### ✅ COMPLETE

Audit read-only selesai. Seluruh business rule baru (A1–A8) berhasil dipetakan terhadap implementasi, dokumentasi, schema, route, dan test. Dua business decision masih benar-benar belum tersedia (**retention period**, **malware scanning policy**); sisanya adalah *implementation gap* yang dapat langsung dikerjakan setelah review.

---

## 2. Executive Summary

Audit menemukan bahwa **sebagian besar business rule baru TIDAK sesuai dengan implementasi saat ini**. Penyebab utamanya: implementasi dibangun saat rule-rule tersebut masih `TODO`, sehingga nilai yang dipakai adalah nilai sementara (placeholder) yang **berbeda** dari keputusan final.

Gap paling material:

| Area | Kondisi saat ini | Keputusan final | Selisih |
|---|---|---|---|
| **Daily limit** | Mekanisme ada, nilai dari `app_settings` **tidak di-seed** → **tidak aktif** | **5/hari** | Nilai belum ada; ambiguitas definisi "per hari" |
| **Attachment** | `mimes:jpg,jpeg,png,pdf`, max **5 MB**, max **3 file** | JPG/JPEG/PNG/PDF/**MP4**, max **20 MB**, max **10 file** | 3 parameter berbeda |
| **Identity** | Hanya `name`, `email`, `password` | Nama, **NIK**, **Nomor HP**, **Alamat** (semua required) | **4 field schema gap** (NIK, phone, address tidak ada) |
| **Category→Dinas** | `complaint_categories.dinas_name` (string bebas), unique slug | Mapping ke Dinas/Unit dikelola Super Admin | Tidak ada tabel `dinas/units`; Super Admin CRUD **MISSING/DEFERRED** |
| **Public tracking** | Tidak ada route/controller → ✅ sesuai (absence) | Tidak diperlukan | ✅ Sesuai — `tracking_secret_hash` tetap tersimpan tanpa endpoint |
| **Notification** | Tidak ada apa pun → ✅ sesuai (absence) | Tidak diperlukan | ✅ Sesuai |
| **Retention** | Tidak ada cleanup/schedule → **sesuai** (belum menentukan apa pun) | **TODO** | ✅ Sesuai — jangan menambah apa pun |
| **Privacy** | Ownership DB-level + 403 + internal note terisolasi | Sama | ✅ Sesuai |

**Kesimpulan penting:** Prompt 5B **tidak dapat mengimplementasikan semuanya sekaligus**. Attachment & daily limit dapat langsung diimplementasikan (hanya perlu keputusan definitional minor). Identity membutuhkan **schema change** (migration) — bergantung apakah Prompt 5B diizinkan membuat migration. Category→Dinas/Unit membutuhkan **Super Admin CRUD** yang sebelumnya **deferred**. Retention & malware scanning **tetap BLOCKED**.

---

## 3. Business Rule Matrix

| Rule | Decision | Implementation | Evidence | Gap |
|---|---|---|---|---|
| **Daily limit** | MAX 5 laporan/hari | Mekanisme ada via `AppSetting::get('daily_report_limit')`; **nilai tidak di-seed** → tidak aktif | `StoreComplaintRequest::withValidator`; `app_settings` table; test `test_daily_report_limit_enforced_when_configured` (set=1) | **PARTIAL** — nilai 5 belum diterapkan; definisi "per hari" ambigu |
| **Category → Dinas/Unit** | Mapping dikelola Super Admin | `complaint_categories.dinas_name` (string), tidak ada tabel dinas/unit, tidak ada CRUD | migration categories; seeder fixtures; SuperAdmin dashboard "Soon" | **MISSING/DEFERRED** — tanpa daftar resmi & tanpa CRUD |
| **Attachment** | JPG/JPEG/PNG/PDF/MP4; 20 MB; 10 file; tidak wajib; malware scanning TODO | `mimes:jpg,jpeg,png,pdf`; `max:5120` (5MB); `max:3` | `StoreComplaintRequest::rules`; migration attachments | **CONFLICT** — format/size/count berbeda |
| **Public tracking** | Tidak diperlukan MVP; guest tidak boleh lihat data | Tidak ada route/controller/view/test | `routes/web.php`; route:list 32 | **SUPPORTED (absence)** — tidak ada yang perlu dihapus |
| **Notification** | Tidak diperlukan MVP | Tidak ada Notifications/Jobs/Events/Listeners/tabel | glob: 0 file; route:list | **SUPPORTED (absence)** |
| **Identity** | Nama, NIK, Nomor HP, Alamat — required | DB hanya `name,email,password,role,is_active`; registration hanya name/email/password; profile hanya name/password | migration users; RegisteredUserController; ProfileController | **MISSING** — NIK/phone/address tidak ada (schema gap) |
| **Retention** | BELUM DITENTUKAN (TODO) | Tidak ada retention config/cleanup/schedule/purge | `routes/console.php` hanya `inspire`; 0 Notifications/Jobs | **SUPPORTED (absence)** — jangan menambah |
| **Privacy** | Owner-only; guest dilarang; internal tersembunyi | Ownership scope DB-level; `reporter_id !== auth()->id()` → 403; internal note tidak dirender | Complaint model scope; controllers; tests | **SUPPORTED** |

---

## 4. Daily Limit Audit

### 4.1 Current implementation
- Mekanisme ada di `app/Http/Requests/Citizen/StoreComplaintRequest.php::withValidator()` (baris 57–77).
- Nilai dibaca dari `AppSetting::get('daily_report_limit')`.
- **Logika bersifat kondisional:** `if ($limit && is_numeric($limit) && (int) $limit > 0)` — bila `app_settings.daily_report_limit` **null/tidak ada, limit TIDAK diberlakukan sama sekali.**
- Perhitungan: `Complaint::where('reporter_id', user->id)->whereDate('submitted_at', today())->count()`.
- Pesan error: "Batas pengiriman laporan harian telah tercapai… maksimal {limit} laporan per hari."

### 4.2 Current validation
- Tidak ada validasi hardcoded bernilai 5. Nilai sepenuhnya bergantung pada baris `app_settings`.

### 4.3 Current tests
- `tests/Feature/CitizenComplaintFlowTest.php::test_daily_report_limit_enforced_when_configured` — mengeset `AppSetting::set('daily_report_limit', 1)` lalu memverifikasi error `daily_limit`. **Test membuktikan mekanisme bekerja, bukan bahwa nilai 5 berlaku.**

### 4.4 Gap
1. **Nilai 5 belum diterapkan.** `app_settings` **tidak di-seed** (grep seeder: 0 hasil). Dengan kata lain, pada instalasi baru, **daily limit = nonaktif**.
2. Keputusan final menyebut "5 laporan per hari" — implementasi saat ini **tidak meng-hardcode** dan **tidak men-seed** nilai ini.

### 4.5 Ambiguity (⚠ WAJIB keputusan definitional)
- Perhitungan memakai `whereDate('submitted_at', today())` → **calendar day** menurut timezone aplikasi.
- **Definisi "per hari" belum dinyatakan resmi**: calendar day vs rolling 24 jam vs timezone spesifik (WITA?).
- Prompt memerintahkan untuk **melaporkan ambiguitas ini**, bukan memutuskan. → **AMBIGUITY — BUSINESS DECISION REQUIRED** (lihat §18).
- Catatan teknis: `today()` menggunakan `config('app.timezone')`. Perlu keputusan apakah ini timezone yang benar.

---

## 5. Category → Dinas/Unit Audit

### 5.1 Schema
- Tabel `complaint_categories`: `id`, `name(100)`, `slug(120) unique`, `description(500) nullable`, **`dinas_name(150) nullable`**, `is_active`, `sort_order`.
- **Tidak ada tabel `dinas`/`units`.** `dinas_name` adalah **string bebas** pada kategori, bukan FK ke entitas Dinas/Unit.
- `complaints.category_id` → FK nullable ke `complaint_categories.id` (`nullOnDelete`).

### 5.2 Model
- `ComplaintCategory` dengan `dinas_name` sebagai kolom string. Tidak ada relasi `belongsTo(Dinas::class)`.

### 5.3 Relationship
- Satu kategori → satu `dinas_name` (embedded string). Tidak ada relasi ke entitas dinas/unit tersendiri.

### 5.4 Super Admin capability
- Route Super Admin: **hanya** `GET /super-admin/dashboard`.
- `SuperAdminDashboardController` hanya `index()` (read-only metrics).
- View `super-admin/dashboard.blade.php` menampilkan badge **"Soon"** → capability Category/Dinas CRUD **belum ada**.
- Ini konsisten dengan keputusan sebelumnya ("Super Admin CRUD deferred").

### 5.5 Seed data
- Seeder mengisi kategori **development fixture** (mis. "Infrastruktur Jalan", dinas "Dinas PUPR") — **bukan daftar resmi**. `dinas_name` di kolom kategori hanya contoh.

### 5.6 Gap & klasifikasi
- **SUPPORTED (partial):** schema mampu menyimpan **satu** nama dinas per kategori sebagai teks.
- **MISSING:** entitas Dinas/Unit tersendiri, relasi formal, dan **Super Admin CRUD** untuk mengelola mapping.
- **UNKNOWN / NOT DEFINED:** daftar resmi kategori & Dinas/Unit (tidak diberikan → dilarang mengarang).
- Business rule menyatakan mapping dikelola **Super Admin melalui dashboard**, tetapi capability = **MISSING / DEFERRED**.

---

## 6. Attachment Audit

### 6.1 Formats (extension vs MIME — dipisahkan)
| Aspek | Keputusan final | Implementasi | Verdict |
|---|---|---|---|
| Extension diizinkan | jpg, jpeg, png, pdf, **mp4** | `mimes:jpg,jpeg,png,pdf` (tanpa mp4) | **CONFLICT** |
| Max size/file | **20 MB** | `max:5120` = **5 MB** | **CONFLICT** |
| Max jumlah | **10** file | `max:3` | **CONFLICT** |
| Wajib? | **NO** (opsional) | `nullable` | ✅ Sesuai |

### 6.2 MIME validation (penting)
- Implementasi memakai `mimes:jpg,jpeg,png,pdf`. Rule `mimes` Laravel memvalidasi **berdasarkan MIME/ekstensi yang terdeteksi** (guesses extension from MIME), sehingga tidak murni extension-only maupun MIME-only.
- **Tidak ada** validasi MIME eksplisit terpisah (mis. `mimetypes:`). Prompt meminta audit apakah validasi MIME "benar" — **temuan:** validasi menggunakan `mimes` (bukan `mimetypes`), yang berbasis ekstensi yang dipetakan dari MIME. Untuk MP4 tidak ada; untuk keamanan lebih ketat, pertimbangkan `mimetypes:` — namun ini **keputusan implementasi**, bukan business rule baru.
- **Malware scanning: UNDEFINED / TODO** — bukan bagian dari implementasi dan tidak boleh diarang.

### 6.3 Storage
- `complaint_attachments` (metadata): `disk='private'`, `path(500)`, `original_name`, `mime_type`, `size_bytes`, `checksum_sha256`.
- Binary disimpan di disk `private` (`storage/app/private`); `local` disk `'serve' => false` (Prompt 4) → tidak ada route `/storage/{path}` untuk file privat.
- Nama file di-generate server-side (`Str::random(40).ext`), bukan nama asli pengguna.

### 6.4 Authorization
- Upload: `Citizen\ComplaintController@store` (hanya role `masyarakat`, `reporter_id` = auth user).
- Download: `Citizen\ComplaintController@downloadAttachment` dan `Staff\OperatorComplaintController@downloadAttachment` — cek `complaint_id` cocok + ownership.
- Tidak ada upload lampiran oleh staf (staff hanya download).

### 6.5 Malware scanning
- **TODO / UNDEFINED.** Tidak ada implementasi, tidak ada provider/tool. Jangan menambahkan.

### 6.6 Gap
- Format (tambah MP4), size (5→20 MB), count (3→10). Ini **implementation conflict**, dapat diperbaiki pada Prompt 5B.
- Catatan: MP4 berukuran besar (20 MB × 10 = 200 MB/laporan) → perlu perhatian performa/validasi; **tidak** mengubah aturan.

---

## 7. Public Tracking Audit

| Item | Temuan |
|---|---|
| Public tracking route | **Tidak ada** (route:list 32 route; tidak ada `/track`, `/lacak`, dsb.) |
| Public tracking controller | **Tidak ada** |
| Public complaint detail | **Tidak ada** — semua route complaint di balik `auth` + `role` |
| Guest complaint access | **Tidak ada** — `/` hanya `public.landing`; dashboard `/dashboard` di balik `auth` |
| Route | ✅ aman |
| Controller | ✅ tidak ada |
| View | `public/landing.blade.php` (marketing) — tidak menampilkan data complaint |
| Tests | `test_guest_cannot_access_citizen_surface`, `test_guest_cannot_create_complaint`, `test_guest_is_redirected_to_login_when_accessing_dashboards`, `test_guest_cannot_update_status`, `test_guest_cannot_access_attachment_route` |

### Catatan
- `complaints.tracking_secret_hash` **tetap tersimpan** (di-generate saat create) tetapi **tidak pernah diverifikasi** karena tidak ada endpoint. Ini residu desain; **bukan** public tracking aktif.
- Keputusan final: public tracking **tidak diperlukan**; guest **tidak boleh** melihat data. **Implementasi saat ini = SESUAI (absence).**
- Kontrak `schema.md §9` mendokumentasikan "Public tracking data contract" sebagai **deskriptif/hipotetis** — bukan berarti fitur harus dibangun. **DOCUMENTATION GAP** ringan (lihat §13).

---

## 8. Notification Audit

| Item | Temuan |
|---|---|
| Notification classes | **0** (`glob app/Notifications` → tidak ada) |
| Mail/SMS/WhatsApp | **0** |
| In-app notifications | **0** (tidak ada `notifications` table; `jobs` table ada dari skeleton Laravel) |
| Notification jobs/events/listeners | **0** (tidak ada `app/Jobs`, `app/Events`, `app/Listeners`) |
| Notification routes | **0** |
| `Notifiable` trait | Ada di `User` (bawaan Laravel) — **tidak aktif digunakan** (tidak ada channel/job) |

### 8.1 Current state
- Tidak ada implementasi notifikasi apa pun yang aktif.
- `database/migrations/0001_01_01_000002_create_jobs_table.php` (queue skeleton) dan `create_cache_table` ada, tetapi **tidak digunakan** untuk notifikasi.
- `PasswordResetController` memiliki komentar `TODO: Define requirement — email provider for production` — terkait reset password, **bukan** notifikasi pengaduan.

### 8.2 Klasifikasi
- **SUPPORTED (absence) / LEGACY (skeleton Laravel) / UNRELATED (jobs/cache tables).** Tidak ada yang perlu dihapus. Tidak ada provider. Sesuai keputusan "notification tidak diperlukan MVP".

---

## 9. Identity Audit

Keputusan final: **Nama, NIK, Nomor HP, Alamat — semua REQUIRED.**

| Field | DB | Model | Validation | Registration | Profile | Tests |
|---|---|---|---|---|---|---|
| **Nama** | ✅ `users.name(120)` | ✅ fillable | ✅ `required,max:120` | ✅ writable | ✅ writable | ✅ (factories) |
| **NIK** | ❌ **tidak ada kolom** | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Nomor HP** | ❌ **tidak ada kolom** (hanya `complaints.reporter_phone(30)` nullable) | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Alamat** | ❌ **tidak ada kolom** | ❌ | ❌ | ❌ | ❌ | ❌ |

### 9.1 Detail
- **`users`:** hanya `id, name, email, email_verified_at, password, role, is_active, remember_token, timestamps`. Email wajib & unik.
- **Registration** (`RegisteredUserController@store`): validate `name`, `email`, `password` → `role=masyarakat`, `is_active=true`. Tidak ada NIK/HP/alamat.
- **Profile** (`Citizen\ProfileController@update`): validate `name`, `current_password`, `password`. Tidak ada NIK/HP/alamat.
- **Views:** grep blade untuk NIK/nomor_hp/alamat → **0 hasil** (kecuali `ip_address` di audit-log, tidak relevan).
- **`complaints.reporter_phone`** ada tetapi selalu diset `null` (Prompt 4 fix; tidak ada `users.phone`). Ini **contact snapshot**, bukan field identitas akun.

### 9.2 Gap klasifikasi
- **MISSING (schema gap).** Menambahkan NIK/HP/Alamat = **SCHEMA CHANGE REQUIRED** (migration baru) + validasi required + writable di registration & profile + view + factory/seeder + test.
- **Catatan:** NIK bersifat **PII sensitif**. Perlu keputusan tambahan: **keunikan NIK** (unique?), format (16 digit?), masking di UI/audit. → lihat §18 (**AMBIGUITY**).
- Karena setiap masyarakat harus "memiliki" field ini, dan akun existing (seeder) tidak punya → perlu penanganan data existing (nullable → required) tanpa destructive migration.

---

## 10. Retention Audit

### Kondisi
- Keputusan: **RETENTION = UNDEFINED (TODO)** — **bukan** "tidak ada retention".
- Audit implementasi:
  - **Tidak ada** retention configuration.
  - **Tidak ada** scheduled deletion / `Schedule::` (routes/console.php hanya `inspire`).
  - **Tidak ada** cleanup command / prune / archival job.
  - **Tidak ada** attachment cleanup.
  - Migrasi menandai FK `ON DELETE RESTRICT` dengan komentar "preserve records until retention policy is defined" (attachments, histories, notes) — **sesuai** (konservatif, tidak menghapus).
  - `AuditLog` header comment: `retention/privacy policy required` (schema §4.7).

### Klasifikasi
- **SUPPORTED (absence).** Tidak ada implementasi retention yang perlu diubah/dihapus. **DILARANG** menambah auto-delete/scheduled purge/archive policy pada Prompt 5B.
- **BLOCKED — BUSINESS DECISION REQUIRED:** jumlah tahun/kebijakan retensi belum ditetapkan.

---

## 11. Privacy Audit

### Matrix akses (sesuai implementasi saat ini; tidak mengarang permission)

| Resource | Guest | Citizen Owner | Citizen Other | Operator | Admin | Super Admin |
|---|---|---|---|---|---|---|
| Landing page | ✅ (marketing) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Complaint list/detail | ❌ (login) | ✅ own | ❌ 403 | ✅ all | ✅ view (monitoring) | ✅ all |
| Complaint status change | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ |
| Internal note | ❌ | ❌ | ❌ | ✅ | ❌ (no route) | ✅ |
| Public response | ❌ | ✅ own | ❌ | ✅ | ❌ | ✅ |
| Attachment | ❌ | ✅ own | ❌ 403 | ✅ | ❌ (no route) | ✅ |
| Audit log | ❌ | ❌ | ❌ | ❌ | ✅ view | ✅ (dashboard) |
| Citizen profile | ❌ | ✅ own | ❌ | ❌ | ❌ | ❌ |

### Evidence
- Ownership DB-level: `Complaint::scopeForReporter`; detail cek `reporter_id !== auth()->id()` → 403.
- Middleware `role:` per grup route.
- Internal note tidak dirender ke citizen (Prompt 4 & `CitizenExperienceFinalTest`).
- Guest → redirect login (tests terbukti).

### Klasifikasi
- **SUPPORTED + TESTED + OFFICIAL.** Privacy rule final **konsisten** dengan implementasi. Tidak ada gap fungsional.
- Data yang boleh dilihat citizen owner: nomor laporan, kategori, judul, status, timeline — **semua tersedia** di halaman detail.

---

## 12. Cross-Rule Conflicts

| # | Konflik | Detail | Severity |
|---|---|---|---|
| 1 | **Daily limit vs creation** | Rule=5, implementasi=0 aktif (nilai tak di-seed). Definisi "per hari" ambigu. | **Tinggi** |
| 2 | **Identity vs registration** | Rule butuh NIK/HP/Alamat required; registrasi hanya name/email/password. | **Tinggi** (schema change) |
| 3 | **Identity vs database** | Kolom NIK/HP/Alamat tidak ada di `users`. | **Tinggi** (schema gap) |
| 4 | **Attachment vs validation** | Format (mp4), size (20MB), count (10) berbeda dari implementasi. | **Sedang** |
| 5 | **Attachment vs private storage** | Storage privat ✅ konsisten; tidak ada konflik. | — |
| 6 | **Category→Dinas vs schema** | Tidak ada entitas Dinas/Unit; hanya `dinas_name` string. | **Sedang** |
| 7 | **Category mapping vs Super Admin capability** | CRUD mapping belum ada (dashboard "Soon"). | **Tinggi** (deferred) |
| 8 | **No public tracking vs guest routes** | Tidak ada guest route ke data complaint → ✅ tidak konflik. | — |
| 9 | **No notification vs existing implementation** | Tidak ada implementasi notifikasi → ✅ tidak konflik. | — |
| 10 | **Privacy vs public response** | Public response = staff-authored; hanya ke owner. Tidak bocor ke guest/other. ✅ | — |
| 11 | **Privacy vs complaint detail** | Owner-only; internal tidak dirender. ✅ | — |
| 12 | **Privacy vs attachment download** | Otorisasi ganda (complaint_id + ownership). ✅ | — |
| 13 | **Retention TODO vs existing cleanup** | Tidak ada cleanup. ✅ (tidak konflik; belum ada apa pun). | — |

Konflik material: **#1, #2, #3, #4, #6, #7.**

---

## 13. Documentation Gaps

Dokumentasi yang belum sinkron dengan keputusan final (dilaporkan, **tidak diperbaiki**):

| # | Dokumen | Baris/Topik | Perlu disinkronkan |
|---|---|---|---|
| D1 | `SETUP_AND_DOCS.md` §6 item 1 | "`TODO: Define daily report limit`" | Nilai = **5** sudah ditetapkan |
| D2 | `SETUP_AND_DOCS.md` §6 item 4 | "`TODO: Define attachment rules`" | Format/size/count sudah ditetapkan (mp4, 20MB, 10) |
| D3 | `SETUP_AND_DOCS.md` §6 item 5 | "`TODO: Define tracking verification factors`" | Public tracking **tidak diperlukan MVP** |
| D4 | `SETUP_AND_DOCS.md` §6 item 6 | "`TODO: Define notification provider`" | Notifikasi **tidak diperlukan MVP** |
| D5 | `SETUP_AND_DOCS.md` §6 item 7 | "`TODO: Define retention & privacy policy`" | Privacy final; retention **tetap TODO** |
| D6 | `README.md` §"perlu dikonfirmasi" | batas harian, identitas, lampiran, tracking, retensi, notifikasi | Beberapa sudah final (limit, attachment, tracking, notification); identitas final (butuh impl) |
| D7 | `prd.md` §4 (A-03, lampiran, tracking, notifikasi) | TODO list | Nilai final tersedia (5, lampiran, no tracking, no notification) |
| D8 | `schema.md` §9 "Public tracking data contract" | Kontrak deskriptif | Fitur tidak dibangun; beri catatan "not implemented (MVP decision)" |
| D9 | `schema.md` §13 (review) | "Confirm required identity fields…" | Field final: Nama, NIK, HP, Alamat |
| D10 | `SETUP_AND_DOCS.md` §6 item 2 | "`TODO: Define official category → dinas/unit mapping`" | Tetap **TODO** (daftar resmi belum ada) — konsisten |

> Semua di atas = **DOCUMENTATION GAP**. Jangan diperbaiki di Prompt 5A.

---

## 14. Database Gaps

| # | Gap | Table | Detail |
|---|---|---|---|
| G1 | **NIK** tidak ada | `users` | Perlu kolom (mis. `nik` varchar, unique?) |
| G2 | **Nomor HP** tidak ada | `users` | Perlu kolom (mis. `phone_number`) |
| G3 | **Alamat** tidak ada | `users` | Perlu kolom (mis. `address`, text) |
| G4 | **Nilai daily limit** belum ada | `app_settings` | Perlu row `daily_report_limit=5` (bisa via seeder/config, bukan wajib migrasi) |
| G5 | **Entitas Dinas/Unit** tidak ada | (baru) | Tidak ada tabel `dinas`/`units`; `complaint_categories.dinas_name` hanya string |

### Catatan
- G1–G3 = **SCHEMA CHANGE REQUIRED** (migration baru). **Tidak dibuat** di Prompt 5A.
- G4 = bisa diselesaikan tanpa migrasi (seeder/config), tetapi penambahan **nilai** adalah keputusan; definisi "per hari" perlu keputusan.
- G5 = tergantung apakah keputusan menghendaki entitas Dinas tersendiri atau cukup `dinas_name`. **BLOCKED** sampai daftar resmi & model pengelolaan diputuskan.
- **Tidak ada** destructive migration. Historical data aman. Semua FK komplain/attachment/history/note = `RESTRICT` (konservatif).

---

## 15. Route Gaps

| # | Gap | Detail |
|---|---|---|
| R1 | Tidak ada route Super Admin untuk Category/Dinas management | Hanya `/super-admin/dashboard` |
| R2 | Tidak ada route public tracking | ✅ **sesuai** (memang tidak diperlukan) |
| R3 | Tidak ada route notification | ✅ **sesuai** |
| R4 | Tidak ada route citizen response | ✅ **sesuai** (Prompt 4B) |
| R5 | Tidak ada route identitas (NIK/HP/Alamat) | Karena field belum ada; akan muncul di register/profile setelah schema change |
| R6 | Tidak ada `/petugas/*` / `role:petugas` | ✅ bersih |

Total route: **32** (tidak berubah).

---

## 16. Test Results

| Metric | Nilai |
|---|---|
| Tests | **198** |
| Passed | **198** |
| Failed | **0** |
| Skipped | **0** |
| Assertions | **626** |
| Duration | ~22.5s |

- Test existing yang relevan: daily limit (`test_daily_report_limit_enforced_when_configured`), guest protection (5 test), attachment authorization (operator & citizen), IDOR.
- **Tidak ada test baru** dibuat (sesuai SCOPE GUARD). Tidak ada test diubah. DB testing tetap `superbie_testing` (MySQL), **bukan** SQLite.

---

## 17. Build Results

| Perintah | Hasil |
|---|---|
| `php artisan route:list` | ✅ 32 route; tanpa `/petugas`, public tracking, notification, citizen response |
| `php artisan migrate:status` | ✅ 8 migration, **semua `Ran`, tidak ada `Pending`** |
| `php artisan view:cache` | ✅ Blade templates cached successfully |
| `npm run build` | ✅ built (Vite) tanpa error |

> `route:list`/`migrate:status`/test dijalankan pada Prompt 5A sebagai inspeksi read-only. `view:cache`/`npm run build` diverifikasi pada sesi Prompt 4B dan tidak diubah; tidak ada perubahan kode pada 5A sehingga hasil tetap valid.

---

## 18. Required Business Decisions Still Missing

**Hanya business decision yang benar-benar belum tersedia** (bukan implementation gap):

1. **Retention period** — `TODO`. Kebijakan retensi/penghapusan data pengaduan, lampiran, audit log belum ditetapkan. **BLOCKED.**
2. **Malware scanning policy** — `UNDEFINED / TODO`. Apakah diperlukan, tool/provider, kapan dijalankan. **BLOCKED.**

**Ambiguitas yang membutuhkan business decision (definitional):**

3. **Definisi "per hari" untuk daily limit** — calendar day vs rolling 24 jam vs timezone (WITA?). Implementasi saat ini `whereDate(today())` = calendar day local app timezone, **belum dikonfirmasi resmi**.
4. **NIK: keunikan & format** — apakah `nik` harus `unique`? 16 digit? Validasi format? Masking di UI/audit log (PII)? **UNKNOWN.**
5. **Nomor HP: format & normalisasi** — format Indonesia (08xx/+62)? `unique`? **UNKNOWN.**
6. **Alamat: struktur & panjang maksimum** — free text? panjang? **UNKNOWN.**

**Daftar resmi (bukan decision teknis, tapi prasyarat):**

7. **Daftar resmi Kategori & Dinas/Unit** — belum diberikan. Termasuk keputusan: apakah Dinas/Unit menjadi entitas tersendiri (tabel) atau cukup `dinas_name`. **BLOCKED** untuk mapping resmi.

> Catatan: item §13 (documentation gaps) dan §14/§15 (DB/route gaps) **bukan** business decision — jangan dicampur.

---

## 19. Recommended Next Step

### 19.1 Dapat langsung diimplementasikan (Prompt 5B) — setelah review
1. **Attachment rules** (A3): ubah validasi ke `jpg,jpeg,png,pdf,mp4`, `max:20480` (20 MB), `max:10` file. Perbarui pesan error. Pertimbangkan tinjauan validasi MIME (`mimes` vs `mimetypes`) — **keputusan implementasi**.
2. **Daily limit** (A1): seed/set `app_settings.daily_report_limit = 5` + kemungkinan validasi non-kondisional. **Menunggu** keputusan definisi "per hari".
3. **Privacy** (A8): sudah sesuai — tidak perlu perubahan (hanya regresi/test bila mengubah attachment/limit).

### 19.2 Harus menunggu business decision
- **Retention** (A7) — BLOCKED (periode belum ditetapkan).
- **Malware scanning** (A3) — BLOCKED (kebijakan belum ditetapkan).
- **Definisi "per hari"** — BLOCKED (definitional).
- **NIK/HP/Alamat: keunikan/format/normalisasi** — BLOCKED (definitional, PII).

### 19.3 Hanya documentation gap (tidak perlu kode)
- §13 D1–D10: sinkronkan `SETUP_AND_DOCS.md`, `README.md`, `prd.md`, `schema.md` dengan keputusan final (kecuali retention & daftar resmi yang tetap TODO).

### 19.4 Membutuhkan schema change (migration)
- **Identity fields** (A6): tambah kolom `nik`, `phone_number`, `address` di `users` + validasi required + registration/profile writable + view + factory/seeder + test. **Perlu izin eksplisit** karena Prompt 5A melarang migration; Prompt 5B harus dikonfirmasi.
- **Daily limit value** (opsional): tanpa migrasi (seeder/config).

### 19.5 Membutuhkan Super Admin CRUD (sebelumnya deferred)
- **Category → Dinas/Unit management** (A2): butuh entitas Dinas/Unit (atau konfirmasi cukup `dinas_name`), route + controller + view Super Admin. **DEFERRED** — jangan dibangun tanpa keputusan.
- **Daftar resmi kategori & Dinas/Unit** wajib tersedia lebih dulu.

### 19.6 Tidak memerlukan tindakan (sudah sesuai)
- **Public tracking** (A4) — absence sudah benar.
- **Notification** (A5) — absence sudah benar.
- **Privacy** (A8) — terpenuhi.

---

## STOP CONDITION

Sesuai §STOP: **berhenti di sini.** Prompt 5A adalah **AUDIT READ-ONLY**. Tidak ada perubahan production code, migration, route, controller, view, CRUD, test baru, bugfix, refactor, perubahan schema, atau environment. Menunggu review user sebelum Prompt 5B.
