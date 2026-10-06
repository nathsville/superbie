# SUPERBIE — PROMPT 5B IMPLEMENTATION REPORT
## Final Business Rule Implementation — Daily Limit, Identity, Attachment, Retention, Category → Dinas/Unit, Privacy

- **Tanggal:** Prompt 5B
- **Pendekatan:** AUDIT → implementasi hanya business rule final → STOP pada area yang belum diputuskan (tanpa menebak).
- **Aturan inti dipatuhi:** tidak menebak business rule; tidak membuat daftar Dinas resmi; tidak membuat fake NIK; NIK immutable; tidak mengubah historical Petugas; tidak membuat public tracking/notification/malware scanning/citizen reply; tidak mengubah status lifecycle; tidak menghapus data existing sembarangan; tidak membuat retention destructive tanpa timestamp yang jelas.

---

## 1. Status

### ⚠️ PARTIALLY COMPLETE

- **Daily limit, Identity, Attachment, Category↔Dinas/Unit (struktur), Privacy, Public tracking, Notification** → **selesai & terverifikasi**.
- **Retention** → **BLOCKED** (business rule menetapkan 5 tahun → permanent deletion, tetapi **timestamp referensi tidak ditentukan**). Tidak ada scheduler destructive dibuat.

---

## 2. Business Rules Implemented

| Rule | Status | Ringkasan |
|---|---|---|
| **Daily limit** | ✅ COMPLETE | 5 laporan / hari kalender (timezone aplikasi), server-side, default 5 bila `app_settings` kosong |
| **Identity** | ✅ COMPLETE | Nama, NIK (16 digit, unique, **immutable**), Nomor HP (unique, lokal+internasional, normalisasi), Alamat (free text). Wajib di registrasi & profil |
| **Attachment** | ✅ COMPLETE | Opsional; maks 10 file; maks 20 MB/file; JPG/JPEG/PNG/PDF/MP4; **tanpa** malware scanning |
| **Retention** | ⛔ BLOCKED | 5 tahun → permanent deletion, **timestamp referensi belum ditentukan** → tidak diimplementasikan |
| **Category → Dinas/Unit** | ✅ STRUCTURE (CRUD DEFERRED) | Many-to-many (`dinas_units` + pivot). Struktur siap; data resmi **tidak** di-seed; Super Admin CRUD = NEXT SCOPE |
| **Public tracking** | ✅ COMPLETE | Tidak diperlukan; tidak ada route/endpoint; guest tidak dapat mengakses data laporan |
| **Notification** | ✅ COMPLETE | Tidak diperlukan MVP; tidak ada implementasi |
| **Privacy** | ✅ COMPLETE | Owner-only; guest dilarang; internal note/audit tersembunyi; IDOR tertutup |

---

## 3. Files Changed

### Baru
| File | Alasan |
|---|---|
| `config/business_rules.php` | Single source of truth nilai final (limit=5, attachment 10/20MB/mimes, NIK 16, retention 5 tahun + reference_column=null) |
| `database/migrations/2025_01_01_100005_add_identity_fields_to_users_table.php` | Tambah `users.nik`, `users.phone_number`, `users.address` (nullable + unique, non-destruktif) |
| `database/migrations/2025_01_01_100006_create_dinas_units_and_pivot_table.php` | Tabel `dinas_units` + pivot `category_dinas_unit` (many-to-many) |
| `app/Models/DinasUnit.php` | Model Dinas/Unit + relasi `categories()` (many-to-many) |
| `tests/Feature/BusinessRuleImplementationTest.php` | 29 test untuk semua rule final (daily limit, NIK, phone, address, attachment, M2M, privacy/IDOR) |
| `PROMPT_5B_IMPLEMENTATION_REPORT.md` | Laporan ini |

### Diubah
| File | Perubahan | Alasan |
|---|---|---|
| `app/Http/Requests/Citizen/StoreComplaintRequest.php` | Attachment: `max:10`, `max:20480`, mimes `jpg,jpeg,png,pdf,mp4` (dari config); daily limit pakai config default 5 bila AppSetting kosong | Rule final attachment + daily limit |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Validasi NIK (`digits:16`, unique), phone (regex lokal/internasional, unique), address (required); normalisasi phone; simpan field baru | Identity required + phone normalization |
| `app/Http/Controllers/Citizen/ProfileController.php` | Phone & address editable (required), phone unique+normalized, NIK **tidak** di-update; import `Rule` | Identity di profil |
| `app/Models/User.php` | `fillable` + `booted()` guard: NIK tidak dapat diubah setelah terisi | NIK immutable (server-side, tahan HTTP tampering) |
| `app/Models/ComplaintCategory.php` | Relasi `dinasUnits()` (belongsToMany) | Category ↔ Dinas/Unit M2M |
| `database/factories/UserFactory.php` | Generate `nik` (16 digit), `phone_number`, `address` unik | Test/environment konsisten |
| `database/seeders/DatabaseSeeder.php` | Identity **development fixture** untuk 2 akun masyarakat (ditandai jelas) | Akun contoh dapat login; tanpa klaim data resmi |
| `resources/views/auth/register.blade.php` | Field NIK, Nomor HP, Alamat | Identity required di UI |
| `resources/views/citizen/profile/edit.blade.php` | NIK read-only (tampil penuh), phone & address editable | NIK immutable + phone/address editable di UI |
| `resources/views/citizen/complaint/create.blade.php` | `accept` + teks bantuan → JPG/JPEG/PNG/PDF/MP4, 20 MB, 10 file | Rule final attachment |
| `tests/Feature/CitizenComplaintFlowTest.php` | Profile update test menyertakan phone & address required | Test lama selaras rule final (bukan "diubah agar hijau") |
| `README.md`, `SETUP_AND_DOCS.md`, `schema.md` | Sinkronisasi keputusan final + TODO tersisa | §N dokumentasi |

---

## 4. Database Changes

**Migration 1 — `2025_01_01_100005_add_identity_fields_to_users_table`**
- `users.nik` : `CHAR(16) NULL` + **UNIQUE**
- `users.phone_number` : `VARCHAR(20) NULL` + **UNIQUE**
- `users.address` : `TEXT NULL`

> Nullable dipilih **sengaja** (non-destruktif): menambah kolom NOT NULL UNIQUE pada tabel berisi data lama tidak mungkin tanpa NIK palsu (dilarang). "Required" ditegakkan di **layer registrasi** (server-side); baris lama tetap NULL tanpa fabrikasi (MySQL mengizinkan banyak NULL pada UNIQUE index).

**Migration 2 — `2025_01_01_100006_create_dinas_units_and_pivot_table`**
- `dinas_units` : `id, name(150), code(50) NULL UNIQUE, description(500) NULL, is_active, sort_order, timestamps`
- `category_dinas_unit` : `id, category_id FK→complaint_categories (CASCADE), dinas_unit_id FK→dinas_units (CASCADE), timestamps`, **UNIQUE(category_id, dinas_unit_id)**

Tidak ada kolom/tabel lama dihapus. `complaint_categories.dinas_name` dipertahankan (kompatibilitas). Tidak ada remap assignment historian.

---

## 5. Validation (server-side)

| Layer | Perubahan |
|---|---|
| **Form Request** (`StoreComplaintRequest`) | Attachment final; daily limit default 5 via config; kategori aktif |
| **Controller** (`RegisteredUserController`) | NIK/phone/address required; NIK 16 digit unique; phone regex lokal+internasional + unique; email unique; phone dinormalisasi |
| **Controller** (`ProfileController`) | name/phone/address required; phone unique (ignore self) + normalized; NIK tidak disentuh |
| **Service** | Tidak ada service baru; nilai final di `config/business_rules.php` |
| **Policy** | Tidak ada policy baru; otorisasi existing (reporter ownership) dipertahankan |
| **Middleware** | `role:` + `auth` existing dipertahankan |
| **Model** (`User::booted`) | Guard NIK immutable (defense-in-depth) |

Semua enforcement **server-side**; frontend hanya UX.

---

## 6. Tests

```
Tests: 227 passed
Assertions: 691
Failed: 0
```

- Sebelum 5B: 198 tests / 626 assertions.
- Ditambah: `BusinessRuleImplementationTest` (29 test) → **227 total**.
- 1 test existing (`CitizenComplaintFlowTest::test_citizen_can_view_and_update_profile`) diperbarui karena profil kini mewajibkan phone & address (perubahan rule final, bukan penghijauan test).
- Tidak ada test yang dihapus/dilonggarkan. DB testing tetap **`superbie_testing`** (MySQL), bukan SQLite.

Cakupan baru: daily limit (0/4/5/6, per-user, reset hari) · NIK (required, 16 digit, alfabet, <16, >16, duplikat, tampil penuh, immutable) · phone (lokal, internasional→normalisasi, duplikat, update) · address (free text >255) · attachment (opsional, 5 format, format salah, 20MB ok, >20MB tolak, 10 file ok, 11 tolak, IDOR) · M2M (category→banyak dinas, dinas→banyak category, duplikat pivot) · privacy (guest, cross-user, cross-attachment, internal hidden).

---

## 7. Security / IDOR

| Vektor | Hasil |
|---|---|
| Guest → dashboard/detail complaint | **Redirect login** ✅ |
| Citizen A → complaint Citizen B | **403** ✅ |
| Citizen A → attachment Citizen B | **403** ✅ |
| NIK tampering via HTTP (profil) | **Ditolak** (model guard + tidak ada input) ✅ |
| Duplicate NIK/phone | **Ditolak server-side** (unique) ✅ |
| Status/reporter/assigned tampering | **Ditolak** (di luar allowlist, existing) ✅ |
| Internal note leak | **Tidak tampil** ✅ |
| Lampiran langsung via URL publik | **Tidak ada** (`local` disk `serve=false`) ✅ |

Authorisasi tetap server-side; tidak bergantung pada kontrol UI tersembunyi.

---

## 8. Retention

### ⛔ BLOCKED — NOT IMPLEMENTED

- **Klaim eksplisit:** retention **TIDAK** diimplementasikan. Yang ada hanya **dokumentasi + config placeholder**.
- **Alasan BLOCKED:** business rule menetapkan "5 tahun → permanent deletion" tetapi **tidak menentukan timestamp referensi**. Tersedia `created_at`, `submitted_at`, `resolved_at` (+ histori status) — memilih salah satu adalah business decision. Menjalankan scheduler destructive atas asumsi melanggar §E, §R.2, §U.12.
- **Bukti:** `config/business_rules.php` → `retention.years = 5`, `retention.reference_column = null`. Tidak ada `Schedule::`, tidak ada command cleanup/prune/purge, tidak ada `app/Console`, tidak ada job destructive (`routes/console.php` hanya `inspire`).
- **Keputusan yang dibutuhkan:** (a) timestamp referensi retention; (b) timezone; (c) kebijakan batch/grace period; (d) perlakukan `audit_logs` (yang menyimpan `subject_id` complaint) terhadap deletion.
- **Data terdampak yang sudah diaudit (untuk kelak):** `complaints` (HARUS), `complaint_status_histories` (HARUS, FK RESTRICT), `complaint_notes` (HARUS, FK RESTRICT), `complaint_attachments` (HARUS + hapus file privat), `audit_logs` (BELUM JELAS → BLOCKED), `complaint_categories`/`dinas_units` (TIDAK DIHAPUS — master data).
- **Jaminan keamanan data:** tidak ada permanent deletion dijalankan terhadap data production/development mana pun.

---

## 9. Category → Dinas/Unit

- **Schema:** `dinas_units` (identity, `is_active`, `sort_order`) + pivot `category_dinas_unit` (UNIQUE pair, FK CASCADE).
- **Relasi:** `ComplaintCategory::dinasUnits()` ↔ `DinasUnit::categories()` — **many-to-many** (satu kategori → banyak dinas; satu dinas → banyak kategori). Terverifikasi test.
- **Mapping:** tersedia di level model/pivot. **Belum ada** UI/route Super Admin untuk mengelola mapping.
- **Super Admin authorization:** belum ada route mapping; Super Admin tetap role full-access tetapi CRUD mapping **belum** dibuat. Ini **NEXT SCOPE / BLOCKED** (butuh daftar resmi & keputusan UI).
- **Data Dinas/Unit resmi:** **BELUM tersedia** → **tidak di-seed** (tidak menebak nama Dinas). `complaint_categories.dinas_name` legacy dipertahankan sebagai kompatibilitas, bukan sumber resmi.

---

## 10. Documentation Updated

- `README.md` — status final daily limit, identity, attachment, tracking, notification, retention.
- `SETUP_AND_DOCS.md` — TODO §6 disinkronkan; ditambah §9 "Catatan Implementasi Prompt 5B".
- `schema.md` — kolom `users.nik/phone_number/address`; `dinas_units`; pivot `category_dinas_unit`; catatan `dinas_name` legacy.
- `PROMPT_5B_IMPLEMENTATION_REPORT.md` — laporan ini.

---

## 11. Remaining TODO / BLOCKED

**BLOCKED (business decision):**
1. **Retention reference timestamp** — 5 tahun → permanent deletion, timestamp awal belum ditentukan (dari `created_at`/`submitted_at`/`closed_at`?), plus timezone, batch/grace period, dan perlakukan `audit_logs`.
2. **Daftar resmi Dinas/Unit** — belum diberikan; CRUD mapping Super Admin menunggu ini (NEXT SCOPE).
3. **Daftar resmi kategori** — masih dev fixture.
4. **Malware scanning** — dinyatakan **tidak diperlukan** (FINAL), bukan TODO.

**Belum diputuskan (definitional, dicatat, tidak menghalangi implementasi saat ini):**
5. **NIK format/unique** — sudah final 16 digit + unique (terimplementasi). Tidak ada leftover.
6. **Phone canonical format** — dipilih normalisasi ke bentuk lokal `08…` (dokumentasi di controller + config). Jika project menghendaki format kanonik lain (E.164), itu keputusan tambahan — saat ini konsisten.
7. **Existing user tanpa NIK/phone/address** — kolom nullable; user lama melengkapi via profil. Tidak ada backfill paksa (tidak ada NIK palsu).

---

## 12. Regression Check

| Area | Status |
|---|---|
| **4 roles** (`masyarakat/operator/admin/super_admin`) | ✅ tidak berubah |
| **Petugas historical data** | ✅ tidak diubah; tidak ada role/route `petugas` aktif |
| **Complaint workflow** | ✅ tidak berubah (create/show/attachment/status) |
| **Status lifecycle** (7 status, 11 transisi, closed terminal) | ✅ tidak berubah |
| **Citizen privacy** (owner-only, guest denied, internal hidden) | ✅ terverifikasi |
| **Private attachments** | ✅ tetap private + terotorisasi |
| **Routes** | ✅ 32 (tidak bertambah) |
| **Migrations** | ✅ 10, semua `Ran`, 0 Pending; non-destruktif |
| **Build** | ✅ Vite built sukses |

---

## 13. Final Verdict

### PARTIALLY COMPLETE

Seluruh business rule final yang **dapat ditentukan** telah diimplementasikan & diuji (daily limit, identity, attachment, category↔dinas struktur, privacy, public tracking, notification). **Retention dinyatakan BLOCKED** karena business rule tidak menentukan timestamp referensi — tidak ada destructive scheduler dibuat (sesuai §E, §R, §U). Category↔Dinas/Unit CRUD + data resmi = NEXT SCOPE.

**Evidence:**
- `php artisan test` → **227 passed / 691 assertions / 0 failed**
- `php artisan migrate:status` → 10 migrations, semua **Ran**, **0 Pending**
- `php artisan route:list` → **32 routes** (tanpa petugas/tracking/notification)
- `php artisan view:cache` → OK
- `npm run build` → OK

**STOP.** Tidak melanjutkan ke Prompt 6. Menunggu review & instruksi berikutnya.
