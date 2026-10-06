# SUPERBIE — PROMPT 5C.1 IMPLEMENTATION REPORT
## Fix Historical Dinas/Unit Deletion Safety

---

## 1. Status Fix

### ✅ COMPLETE

Root problem ditemukan, diperbaiki di lapisan model (defense-in-depth) + endpoint DELETE, seluruh test historical deletion lulus.

---

## 2. Root Cause

Prompt 5C **tidak memiliki** endpoint/logic DELETE untuk Dinas/Unit (route hanya index/create/store/edit/update). Namun:

1. **Tidak ada proteksi hard-delete di lapisan model.** `DinasUnit` dapat di-`delete()` dari kode mana pun tanpa hambatan.
2. FK `complaints.dinas_unit_id → dinas_units.id` adalah **`ON DELETE SET NULL`**. Jika satu DinasUnit yang sudah dipakai di-hard-delete, **semua complaint terkait otomatis kehilangan destination-nya (`dinas_unit_id = NULL`)** — melanggar historical rule.
3. Prompt 5C bahkan **meng-encode behavior salah** sebagai test: `test_deleting_master_dinas_sets_complaint_null_without_deleting_complaint` meng-assert `dinas_unit_id` menjadi NULL — dan `schema.md` menyatakan nulling sebagai perilaku yang dapat diterima.

Singkatnya: jalur delete destruktif terbuka lebar terhadap data historis.

---

## 3. File yang Diperiksa (audit)

- `app/Http/Controllers/Staff/SuperAdminDinasUnitController.php` — tidak ada `destroy()`
- `app/Models/DinasUnit.php` — tidak ada guard `deleting`
- `app/Models/Complaint.php` — relasi `dinasUnit()`
- `database/migrations/2025_01_01_100007_add_dinas_unit_id_to_complaints_table.php` — FK `nullOnDelete`
- `routes/web.php` — tidak ada route DELETE dinas
- `tests/Feature/DinasUnitRoutingTest.php` — test nulling (salah)

Grep untuk `delete()`, `forceDelete()`, `destroy()`, `detach()`, `nullOnDelete`, `ON DELETE SET NULL`.

---

## 4. File yang Diubah

| File | Perubahan |
|---|---|
| `app/Models/DinasUnit.php` | Tambah `isUsedByComplaints()` + `booted()` `deleting` guard → **block hard-delete bila dipakai complaint** (throw) |
| `app/Http/Controllers/Staff/SuperAdminDinasUnitController.php` | Tambah `destroy()`: tolak (redirect + error) bila dipakai; hapus (detach mapping + audit) bila tidak dipakai |
| `routes/web.php` | Tambah `DELETE super-admin/dinas/{dinas_unit}` → `super-admin.dinas.destroy` |
| `resources/views/super-admin/dinas/edit.blade.php` | Danger zone: tombol hapus (disabled/penjelasan bila dipakai) + flash error; tombol hapus bila belum dipakai |
| `tests/Feature/DinasUnitRoutingTest.php` | Ganti test nulling→block; +7 test (TEST 1–6, unused-delete, auth DELETE) |
| `schema.md` | Koreksi: used Dinas tidak boleh di-hard-delete; FK SET NULL hanya defensive |
| `SETUP_AND_DOCS.md` | §11 diperbarui (delete safety) |
| `README.md` | Catatan delete safety |
| `PROMPT_5C1_DELETION_SAFETY_REPORT.md` | Laporan ini |

**Tidak ada migration baru.** FK tidak diubah.

---

## 5. Behavior DELETE — Sebelum Fix

- Endpoint DELETE: **tidak ada**.
- `DinasUnit::delete()`: **bebas** (tidak diblokir).
- Efek: complaint yang memakai Dinas tersebut → `dinas_unit_id` menjadi **NULL** (FK SET NULL). Historical destination **hilang**. Test bahkan meng-assert nulling sebagai benar.

---

## 6. Behavior DELETE — Sesudah Fix

- Endpoint `DELETE super-admin/dinas/{dinas_unit}` (Super Admin saja):
  - **Dinas sudah dipakai complaint** → **ditolak**: redirect + `withErrors(['dinas_unit' => …])`, pesan menjelaskan harus dinonaktifkan. Dinas & complaint **utuh**, destination **tidak berubah**.
  - **Dinas belum dipakai** → dihapus (detach mapping dari pivot, lalu delete), audit log `dinas_unit.deleted`.
- Lapisan model `DinasUnit::booted()` `deleting`: **selalu** throw `RuntimeException` bila `complaints()->exists()` — melindungi **semua** code path (termasuk masa depan), bukan hanya dashboard.
- FK `ON DELETE SET NULL` **dipertahankan** sebagai defensive DB behavior; namun **tidak pernah tercapai** melalui flow aplikasi normal.
- **Tidak ada** CASCADE ke complaint. **Tidak ada** complaint terhapus. **Tidak ada** nulling akibat DELETE.

---

## 7. Behavior INACTIVE

- Active → tersedia untuk assignment baru (jika ter-mapping ke kategori).
- Inactive → **tidak** tersedia untuk assignment baru.
- Inactive yang sudah menjadi destination complaint → **tetap tersimpan, tetap terlihat, tetap processable**.
- Tidak ada automatic reassignment.
- Reaktifkan → mapping tetap tersedia (mapping tidak pernah dihapus karena inactive).

---

## 8. Historical Destination Behavior

- Ubah/hapus mapping → complaint lama **tidak berubah**.
- Dinas menjadi inactive → complaint lama **tetap** menunjuk Dinas tersebut.
- Percobaan DELETE Dinas terpakai → **ditolak**, `dinas_unit_id` **tetap** (bukan NULL).
- Complaint tetap dapat ditampilkan & diproses.

---

## 9. Tests Baru/Diubah

| Test | Isi |
|---|---|
| ~~`test_deleting_master_dinas_sets_complaint_null_…`~~ → **`test_model_blocks_hard_delete_of_used_dinas_unit`** | Model menolak delete (throw) |
| **TEST 1** `test_1_used_dinas_cannot_be_deleted` | DELETE ditolak; dinas & complaint utuh; destination sama |
| **TEST 2** `test_2_used_dinas_can_be_deactivated` | Deactivate berhasil; complaint tak berubah |
| **TEST 3** `test_3_inactive_destination_remains_historical` | Inactive → destination tetap |
| **TEST 4** `test_4_inactive_not_available_for_new_assignment` | Inactive tidak muncul & ditolak server-side |
| **TEST 5** `test_5_existing_inactive_destination_still_valid_for_existing_complaint` | Complaint + destination inactive tetap valid |
| **TEST 6** `test_6_delete_attempt_must_not_null_destination` | Delete gagal → destination **bukan NULL** |
| `test_unused_dinas_can_be_deleted` | Kemampuan delete tetap ada untuk unit tak terpakai |
| `test_only_super_admin_can_delete_dinas` | Operator/Admin/Citizen → 403 |

---

## 10. Total Tests

**279** (sebelum 5C.1: 271)

## 11. Assertions

**883** (sebelum: 849)

## 12. Failed Tests

**0**

## 13. Migration Status

11 migrations, semua **Ran**, **0 Pending**. Tidak ada migration baru (FK tidak diubah).

## 14. Route Count

**41** (sebelum: 40; +1 `DELETE super-admin/dinas/{dinas_unit}`)

## 15. Build Status

- `php artisan view:cache` → **OK**
- `npm run build` → **OK** (built in 967ms)

## 16. Retention Regression

- `php artisan complaints:purge-expired --dry-run` → **OK** (`DRY-RUN: 0 laporan…`)
- Retention service **tidak diubah**.

## 17. Business Rules yang Dipastikan Tidak Berubah

- Retention (5 tahun, `submitted_at`, permanent deletion, audit log) ✅
- Status lifecycle (7 status / 11 transisi) ✅
- Citizen workflow (no reply, privacy owner-only) ✅
- Attachment rules (10 file, 20 MB, JPG/JPEG/PNG/PDF/MP4, no malware scan) ✅
- Daily report limit (5/hari kalender) ✅
- Identity (NIK 16 unique immutable, phone unique/editable, address free text) ✅
- Privacy / public tracking (disabled) / notification (disabled) ✅
- Mapping Category ↔ Dinas/Unit tetap **many-to-many** ✅
- 4 roles + Petugas historical ✅

## 18. Known Limitations

- Hard-delete **unused** Dinas/Unit tetap diizinkan (sesuai prompt: "boleh mempertahankan behavior delete hanya jika aman"). Unit yang belum pernah dipakai aman untuk dihapus.
- FK `ON DELETE SET NULL` tetap ada sebagai defensive DB behavior; tidak diubah menjadi CASCADE/RESTRICT (sesuai instruksi). Proteksi utama ada di lapisan aplikasi/model.
- Daftar resmi Dinas/Unit tetap belum tersedia (di luar scope 5C.1).

---

## Verdict

### ✅ COMPLETE

Seluruh test historical deletion **lulus**; tidak ada yang gagal. Business rule lain tidak berubah.

**Evidence:**
- `php artisan test` → **279 passed / 883 assertions / 0 failed**
- `php artisan migrate:status` → 11 migrations, semua Ran, 0 Pending
- `php artisan route:list` → **41 routes**
- `php artisan view:cache` → OK
- `npm run build` → OK
- `php artisan complaints:purge-expired --dry-run` → OK

**STOP.** Tidak melanjutkan ke Prompt 6 atau scope lain.
