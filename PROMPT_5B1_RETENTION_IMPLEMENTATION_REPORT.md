# SUPERBIE — PROMPT 5B.1 RETENTION IMPLEMENTATION REPORT
## Retention 5 Tahun → Permanent Deletion

- **Tanggal:** Prompt 5B.1
- **Pendekatan:** AUDIT → implementasi (command + service + scheduler) → test → validasi → dokumentasi.
- **Prinsip dipatuhi:** retention destructive HANYA diimplementasikan karena timestamp referensi (`submitted_at`) kini FINAL; tidak menghapus user/master data; tidak ada archive/soft-delete/grace period; tidak ada migration/schema baru; tidak menjalankan purge destructive terhadap data development/production.

---

## 1. Status

### ✅ COMPLETE

Seluruh scope retention Prompt 5B.1 selesai & teruji (lihat §16 & §AF checklist).

---

## 2. Final Business Rule

```
Retention   = 5 years
Reference   = complaints.submitted_at
Action      = permanent deletion
Complaint-related audit logs = deleted
```

- Timezone: mengikuti `config('app.timezone')` = `UTC` (timezone aplikasi existing; tidak ada timezone baru).
- Formula: `retention_expired_at = submitted_at + 5 years`; memenuhi syarat hapus jika `submitted_at + 5 years <= now`.
- Tidak ada fallback ke `created_at`/`updated_at`/`resolved_at`/`closed_at`/status history timestamp.
- `submitted_at IS NULL` **tidak pernah** dianggap expired.

---

## 3. Retention Logic

Query (di `App\Services\ComplaintRetentionService::expiredQuery()`):

```php
$years  = (int) config('business_rules.retention.years', 5); // = 5
$cutoff = now()->subYears($years);

Complaint::query()
    ->whereNotNull('submitted_at')        // NULL handling eksplisit
    ->where('submitted_at', '<=', $cutoff);
```

SQL yang dihasilkan (diverifikasi test):
```sql
select * from `complaints` where `submitted_at` is not null and `submitted_at` <= ?
```

Tidak menyentuh `created_at` (diverifikasi test T5). Pemrosesan via `orderBy('id')->chunkById(100)` (batch).

---

## 4. Data Deletion Matrix

| Data | Delete? | Evidence/Reason |
|------|---------|-----------------|
| `complaints` | **YES** | Target retention (5 tahun sejak `submitted_at`) |
| `complaint_status_histories` | **YES** | Data anak complaint (FK RESTRICT) |
| `complaint_notes` | **YES** | Data anak complaint — termasuk public response & internal note (FK RESTRICT) |
| `complaint_attachments` | **YES** | Data anak complaint (FK RESTRICT) |
| physical attachment files | **YES** | Berkas privat di disk `private` ikut dihapus |
| complaint-related `audit_logs` | **YES** | Business rule FINAL: `subject_type='complaint'` AND `subject_id` = complaint |
| `users` | **NO** | Tidak ada user retention; identitas (NIK/HP/Alamat) tetap |
| `complaint_categories` | **NO** | Master data |
| `dinas_units` | **NO** | Master data |
| `category_dinas_unit` | **NO** | Master mapping |
| unrelated `audit_logs` | **NO** | User/category/system/complaint lain yang belum expired |

Tidak ditemukan tabel lain dengan FK langsung ke `complaints.id` selain ketiga tabel anak di atas.

---

## 5. Files Changed

### Baru
| File | Alasan |
|---|---|
| `app/Services/ComplaintRetentionService.php` | Domain logic retention (query expired + purge per complaint, transaction, file cleanup) |
| `app/Console/Commands/PurgeExpiredComplaints.php` | Artisan command `complaints:purge-expired` (`--dry-run`, `--force`) |
| `tests/Feature/ComplaintRetentionTest.php` | 19 test retention (T1–T5, U, V, W, X, Y, idempotency, dry-run, safety) |
| `PROMPT_5B1_RETENTION_IMPLEMENTATION_REPORT.md` | Laporan ini |

### Diubah
| File | Perubahan |
|---|---|
| `config/business_rules.php` | `retention.reference_column`: `null` → `'submitted_at'` (frozen final) |
| `routes/console.php` | Registrasi `Schedule::command('complaints:purge-expired')->dailyAt('02:00')` |
| `README.md` | Status retention → FINAL/DONE |
| `SETUP_AND_DOCS.md` | TODO §6 item 7 & 9 → DONE; §9 retention bullet; tambah §10 |
| `schema.md` | §7 Relationships and deletion rules → aturan retention final |

---

## 6. Command

```
php artisan complaints:purge-expired [--dry-run] [--force]
```

- `--dry-run` : melaporkan jumlah yang akan diproses, **tidak** menghapus.
- `--force` : wajib untuk eksekusi di environment `production`.
- Command auto-discovered (Laravel 13, `app/Console/Commands`).

---

## 7. Dry Run

```
$ php artisan complaints:purge-expired --dry-run
DRY-RUN: 0 laporan memenuhi syarat permanent deletion (tidak ada yang dihapus).
```

Output hanya jumlah (tanpa data sensitif). Diverifikasi test `test_dry_run_reports_without_deleting` bahwa tidak ada record/file yang terhapus.

---

## 8. Scheduler

- **Lokasi:** `routes/console.php`
- **Definisi:** `Schedule::command('complaints:purge-expired')->dailyAt('02:00')`
- **Verifikasi:** `php artisan schedule:list` → `0 2 * * * php artisan complaints:purge-expired`
- **Frekuensi:** harian 02:00 (technical decision, bukan business rule).
- **Production setup requirement:** server harus menjalankan cron `* * * * * php artisan schedule:run`. **Mendefinisikan schedule TIDAK membuatnya berjalan otomatis** — perlu cron. Scheduler hanya memicu command; tidak mengubah aturan retention.

---

## 9. Safety

| Aspek | Implementasi |
|---|---|
| **Environment guard** | Purge di `production` menolak jalan tanpa `--force` |
| **Dry-run** | `--dry-run` tanpa penghapusan DB/file |
| **Force mechanism** | `--force` untuk production (sesuai konvensi Artisan) |
| **Transaction** | `DB::transaction(...)` membungkus seluruh deletion DB per complaint (children → complaint) |
| **Batch processing** | `chunkById(100)` — tidak memuat seluruh set ke memory |
| **Error handling** | Gagal 1 complaint → dicatat (`warn` + `report($e)`), batch lanjut; DB rollback untuk complaint tersebut (via transaction); file orphan tidak menggagalkan batch (`Log::warning`) |
| **Idempotency** | Run 2 tidak error (complaint sudah tidak ada) — diverifikasi test |
| **File safety** | File dihapus lebih dulu (butuh path), record dihapus dalam transaction; file tidak-bisa-dibuktikan-terkait TIDAK dihapus |
| **No production damage** | Tidak pernah menjalankan purge destructive pada data nyata; test pakai `superbie_testing` + `Storage::fake` |

Catatan atomicity: filesystem bukan transactional. Urutan yang dipakai (hapus file → transaction hapus record) tidak menghasilkan partial DB record; jika rollback terjadi, file untuk complaint yang gagal mungkin sudah hilang — dapat ditoleransi karena target adalah penghapusan permanen complaint tersebut. Tidak ada orphan DB record (record anak & complaint dihapus atomik di DB).

---

## 10. Attachment Cleanup

- **Database record:** `complaint_attachments` dihapus (`where complaint_id = ...`) di dalam transaction.
- **Physical file:** `Storage::disk($attachment->disk)->delete($attachment->path)` sebelum record dihapus. Disk `private` (`storage/app/private`), file **tidak** pernah public-served.
- **Orphan handling:** Jika file fisik sudah tidak ada → dihitung (`missing_files`) + `Log::warning`, **tidak** membuat file baru, **tidak** menggagalkan batch.
- Diverifikasi test `test_expired_attachment_file_and_record_are_deleted`, `test_unexpired_attachment_file_and_record_are_preserved`, `test_missing_physical_file_does_not_fail_purge`.

---

## 11. Audit Log Cleanup

- **Deleted:** `audit_logs` dengan `subject_type = 'complaint'` **dan** `subject_id` = id complaint yang dihapus.
- **Preserved:** audit log dengan `subject_type` lain (`user`, `System`, `ComplaintCategory`, dst) dan audit log complaint lain yang belum expired.
- Tidak menghapus seluruh tabel `audit_logs`.
- Diverifikasi test V1–V4.

---

## 12. Tests

```
Tests: 246 passed
Assertions: 757
Failed: 0
```

- Sebelum 5B.1: 227 tests / 691 assertions.
- Ditambah `ComplaintRetentionTest`: **19 test** (T1–T5, dependency cascade, audit V1–V4, attachment W, user X, multi Y, idempotency, dry-run, safety).
- DB testing: `superbie_testing` (MySQL) via `RefreshDatabase`. Storage: `Storage::fake('private')`. Tidak ada data nyata terhapus.

> Catatan T5: kolom `complaints.submitted_at` adalah `NOT NULL DEFAULT CURRENT_TIMESTAMP`, sehingga baris NULL tidak mungkin ada. T5 memverifikasi **query** (eksplisit `is not null`, tanpa `created_at`) + membuktikan `created_at` tua tidak membuat complaint segar eligible.

---

## 13. Regression

| Area | Status |
|---|---|
| 4 roles (`masyarakat/operator/admin/super_admin`) | ✅ OK |
| Petugas historical data | ✅ OK (tidak ada role/route aktif; baris historis utuh) |
| Status lifecycle (7 status / 11 transisi) | ✅ OK (tidak diubah) |
| Citizen privacy (owner-only, guest denied, internal hidden) | ✅ OK |
| Daily limit (5/hari kalender) | ✅ OK |
| Identity (NIK 16 unique immutable, phone unique/editable lokal+intl, address free text) | ✅ OK |
| Attachment rules (opsional, 10 file, 20 MB, JPG/JPEG/PNG/PDF/MP4, no malware scan) | ✅ OK |
| Routes | ✅ 32 (tidak berubah) |
| Migrations | ✅ 10, semua Ran, 0 Pending |

---

## 14. Migration

**No migration required.**

Retention tidak membutuhkan perubahan schema. FK ke `complaints.id` tetap `RESTRICT` — deletion order (anak dulu, lalu complaint) menangani dependency tanpa mengubah FK.

---

## 15. Remaining TODO

- **Daftar resmi Dinas/Unit & kategori** — belum tersedia (di luar scope retention; NEXT SCOPE).
- **Category ↔ Dinas/Unit CRUD (Super Admin)** — belum dibuat (di luar scope retention).
- **Deployment scheduler** — butuh cron `schedule:run` di server production (operational setup, bukan kode).

Tidak ada TODO di dalam scope retention 5B.1.

---

## 16. Final Verdict

### ✅ COMPLETE

Retention business rule final diimplementasikan penuh dan teruji (checklist §AF):

1. ✅ Expired detection pakai `submitted_at`
2. ✅ Perhitungan 5 tahun benar (boundary tepat 5 tahun = expired)
3. ✅ Complaint dihapus permanen
4. ✅ Data dependen complaint dihapus (status histories, notes, attachments)
5. ✅ Berkas lampiran fisik dihapus
6. ✅ Audit log terkait complaint dihapus
7. ✅ User tidak terhapus
8. ✅ Master data tidak terhapus
9. ✅ Audit log tak terkait tidak terhapus
10. ✅ Command idempotent
11. ✅ Dry-run tersedia & teruji
12. ✅ Automated tests pass (246/757)
13. ✅ Regression pass
14. ✅ Scheduler terintegrasi (`dailyAt('02:00')`)
15. ✅ Tidak ada destructive deletion terhadap production

**Evidence:**
- `php artisan test` → **246 passed / 757 assertions / 0 failed**
- `php artisan migrate:status` → 10 migrations, semua **Ran**, 0 Pending
- `php artisan route:list` → **32 routes** (tidak berubah)
- `php artisan view:cache` → OK
- `npm run build` → OK (built in 980ms)
- `php artisan complaints:purge-expired --dry-run` → OK

**STOP.** Tidak melanjutkan ke Prompt 6. Menunggu review & instruksi berikutnya.
