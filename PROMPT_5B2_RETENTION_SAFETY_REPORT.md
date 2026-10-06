# SUPERBIE — PROMPT 5B.2 RETENTION SAFETY AUDIT & FIX REPORT

- **Tanggal:** Prompt 5B.2
- **Scope:** HANYA dua area — (A) urutan penghapusan physical attachment file vs DB transaction, (B) verifikasi nilai `audit_logs.subject_type`.
- **Metode:** audit kode aktual → perbaikan minimal → test → validasi. Tidak mengubah business rule, tidak menambah fitur, tidak menebak.

---

## 1. Status

### ✅ COMPLETE

Kedua area selesai: satu **bug nyata diperbaiki** (A), satu **diverifikasi sudah benar** (B).

---

## 2. Temuan Audit

### A. Urutan penghapusan attachment — **BUG DITEMUKAN & DIPERBAIKI**

Implementasi Prompt 5B.1 (`ComplaintRetentionService::purge`) melakukan pola **terlarang**:

```
1. delete physical file      ← SEBELUM transaction
2. BEGIN transaction
3. delete DB records
4. COMMIT
```

Jika step 3 gagal → rollback → **DB attachment record masih ada, tetapi physical file sudah hilang**. DB menunjuk ke file yang tidak ada. Ini behavior final yang tidak boleh.

**Bukti (kode lama):**
```php
// 1) Physical attachment files first (we still need their paths).
foreach ($attachments as $attachment) { ... $disk->delete($attachment->path); }   // ← file dulu
DB::transaction(function () { /* delete DB */ });                                  // ← DB setelahnya
```

### B. `audit_logs.subject_type` — **DIVERIFIKASI SUDAH BENAR**

Audit lengkap:

| Sumber | Nilai actual |
|---|---|
| `ComplaintController` (complaint_created) | **`'complaint'`** |
| `OperatorComplaintController` (4×: category_updated, status, assignment, dll.) | **`'complaint'`** |
| `ProfileController` | `'user'` |
| `DatabaseSeeder` (system) | `'System'` |
| `AuditLog` model | **tidak ada** `morphTo` |
| `Relation::morphMap` | **tidak ada** di seluruh project |
| Migration `audit_logs` | `subject_type VARCHAR(100) NULL` (plain string) |

**Kesimpulan:** konvensi project adalah **string literal lowercase** `'complaint'` (bukan FQCN `App\Models\Complaint`, bukan morph alias). Retention menggunakan `->where('subject_type', 'complaint')` → **COCOK dengan penulis audit log**. **Tidak ada perubahan kode** untuk area B (mengubahnya justru akan merusak).

---

## 3. Perbaikan (Area A)

`ComplaintRetentionService::purge()` diubah menjadi tiga fase:

```
PHASE 1 — collect attachment paths (id, disk, path) dari record complaint
        ↓
PHASE 2 — DB::transaction:
            delete complaint_attachments
            delete complaint_notes
            delete complaint_status_histories
            delete audit_logs (subject_type='complaint' AND subject_id)
            delete complaint
          COMMIT
        ↓
PHASE 3 — ONLY setelah commit: delete physical files dari paths yang dikumpulkan
```

Return signature diperluas: `['deleted', 'missing_files', 'failed_files']`.

**Jaminan:**
- DB rollback → file **belum** dihapus.
- DB commit → file kemudian dibersihkan.
- File cleanup gagal → **tidak** ada fake rollback DB.

---

## 4. Failure Behavior (diverifikasi)

| Case | Behavior | Bukti |
|---|---|---|
| **A — DB transaction gagal** | Complaint + semua child + audit log + file **tidak** terhapus; error ter-log; batch lanjut | Test B |
| **B — DB commit berhasil** | DB committed; file dibersihkan setelah commit | Test A |
| **C — physical file tidak ada** | Tidak crash; dihitung `missing_files`; DB tetap dihapus; tidak ada file baru | Test C |
| **D — file deletion gagal** | DB tetap committed; dihitung `failed_files`; `Log::error`; command melaporkan warning; **tidak** rollback/insert ulang | Test D |

---

## 5. Path Safety

- Cleanup **hanya** menggunakan `complaint_attachments.path` milik complaint yang diproses.
- Tidak menghapus path arbitrary, file user lain, system, category, dinas, atau folder global recursive.
- Menggunakan mekanisme `Storage::disk(...)` existing (disk `private`), tanpa mengubah storage architecture.
- Test `test_path_safety_unrelated_file_is_not_touched` membuktikan file privat lain tetap ada.

---

## 6. Idempotency

- Eligibility rule **tidak diubah**: `submitted_at IS NOT NULL` AND `submitted_at <= cutoff`.
- Run kedua: complaint sudah tidak ada → tidak ada yang terhapus, tidak fatal.
- File yang sudah hilang → hanya `missing_files` (tidak fatal).
- File cleanup gagal pasca-commit → rerun **tidak** menghapus data complaint lain.
- Test `test_purge_is_idempotent` (existing) tetap hijau.

---

## 7. Audit Log

- **Dihapus:** `audit_logs` dengan `subject_type = 'complaint'` AND `subject_id` = complaint (nilai **actual** project).
- **Dipertahankan:** `subject_type` lain (`user`, `System`, `ComplaintCategory`) dan complaint lain yang belum expired.
- Tidak menghapus seluruh tabel. Test V1–V4 tetap hijau; ditambah lock-in test yang menulis audit log via flow aplikasi nyata dan memverifikasi nilai `'complaint'`.

---

## 8. Tests

```
Tests: 252 passed
Assertions: 778
Failed: 0
```

- Sebelum 5B.2: 246 tests / 757 assertions.
- `ComplaintRetentionTest`: 19 → **25 test** (+6 untuk 5B.2):

| Test | Membuktikan |
|---|---|
| `test_a_successful_retention_deletes_record_and_physical_file` | DB record + file terhapus |
| `test_b_database_failure_rolls_back_and_keeps_physical_file` | DB rollback → file **tetap ada** (memaksa failure nyata di dalam transaction via model event throw) |
| `test_c_missing_physical_file_does_not_fail_purge` | File hilang → purge tetap sukses |
| `test_d_file_cleanup_failure_is_reported_without_db_rollback` | File gagal dihapus → DB tetap committed, `failed_files=1`, tanpa fake rollback |
| `test_path_safety_unrelated_file_is_not_touched` | File lain tidak tersentuh |
| `test_audit_log_written_by_app_uses_complaint_subject_type` | `subject_type` actual = `'complaint'` cocok dengan retention |

> Catatan Test D: simulasi kegagalan native filesystem tidak reliable dengan local driver, jadi diverifikasi pada level kontrak service menggunakan fake disk yang `exists()=true`, `delete()=false` — bukan mock palsu; ini benar-benar mengeksekusi jalur kode `failed_files`.

> Catatan Test B: kegagalan transaction dibuat **nyata** (event `Complaint::deleting` melempar exception), sehingga `DB::transaction` melakukan rollback sungguhan — bukan mock.

---

## 9. Files Changed

| File | Perubahan |
|---|---|
| `app/Services/ComplaintRetentionService.php` | **FIX**: urutan jadi collect → transaction → commit → file cleanup; `failed_files`; log `error` untuk kegagalan file; komentar FK/subject_type diperjelas |
| `app/Console/Commands/PurgeExpiredComplaints.php` | Melaporkan `failed_files` + warning jika ada physical cleanup yang gagal |
| `tests/Feature/ComplaintRetentionTest.php` | +6 test (A–D, path safety, lock-in subject_type) |
| `PROMPT_5B2_RETENTION_SAFETY_REPORT.md` | Laporan ini |

**Tidak ada** perubahan pada: `config/business_rules.php` (rule tetap), migration, model, route, enum, atau business rule lain.

---

## 10. Validation

```
php artisan test                     → 252 passed / 778 assertions / 0 failed
php artisan complaints:purge-expired --dry-run → OK (0 eligible, tidak menghapus)
php artisan migrate:status           → 10 migrations, semua Ran, 0 Pending
php artisan route:list               → 32 routes (tidak berubah)
php artisan view:cache               → OK
npm run build                        → OK
```

---

## 11. Regression

| Area | Status |
|---|---|
| 4 roles | ✅ OK |
| Petugas historical data | ✅ OK |
| Status lifecycle (7/11) | ✅ OK |
| Citizen privacy | ✅ OK |
| Daily limit | ✅ OK |
| Identity | ✅ OK |
| Attachment rules | ✅ OK |
| No migration | ✅ Tidak ada migration baru |

---

## 12. Remaining TODO

Tidak ada di dalam scope 5B.2. (Di luar scope: daftar resmi Dinas/Unit + CRUD mapping Super Admin — NEXT SCOPE.)

---

## 13. Final Verdict

### ✅ COMPLETE

- **Area A** (attachment safety): **BUG diperbaiki** — physical file tidak lagi dihapus sebelum DB transaction commit; sekarang collect → transaction → commit → file cleanup.
- **Area B** (`audit_logs.subject_type`): **DIVERIFIKASI BENAR** — nilai actual project `'complaint'`; tidak ada perubahan kode diperlukan.

Business rule retention tidak berubah. Tidak ada fitur baru. Tidak ada destructive deletion pada data nyata.

**STOP.** Menunggu instruksi berikutnya.
