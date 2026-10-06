# SUPERBIE — PROMPT 6 (Bagian 2/3) IMPLEMENTATION REPORT
## Integrasi Category ↔ Dinas/Unit, UI Super Admin, dan Operator Routing

---

## A. AUDIT (Phase 3)

### A.1 Category (`ComplaintCategory`)
- `complaints()` HasMany, `dinasUnits()` BelongsToMany (pivot `category_dinas_unit`), cast `is_active`/`sort_order`, `scopeActive()`, `HasFactory`, `isUsedByComplaints()` + `booted()` `deleting` guard (Bagian 1).
- **Tidak ada relasi duplikat.**

### A.2 Dinas/Unit (`DinasUnit`)
- `categories()` BelongsToMany (kebalikan pivot), `complaints()` HasMany, `scopeActive()`, `isUsedByComplaints()` + `deleting` guard (5C.1).
- Konvensi nama relasi existing = `categories()` (bukan `complaintCategories()`). **Dipertahankan** (§6: jangan buat relasi kedua dengan makna sama).

### A.3 Pivot (`category_dinas_unit`)
- `id`, `category_id` FK → `complaint_categories` (CASCADE), `dinas_unit_id` FK → `dinas_units` (CASCADE), timestamps, **UNIQUE(category_id, dinas_unit_id)**.
- Constraint duplicate **sudah ada** → tidak dibuat ulang, tidak ada migration baru.

### A.4 Complaint
- `category()` BelongsTo; `dinasUnit()` BelongsTo ke `complaints.dinas_unit_id` (tujuan aktual, tersimpan). Konsisten.

### A.5 `dinas_name`
- Dipakai **hanya sebagai label tampilan** (citizen create/show, operator show, admin). **Tidak ada** logika routing/validasi/assignment yang memakai `dinas_name`. Aman, namun tampilan citizen masih bergantung padanya → diperbaiki agar mapping-first (Bagian 2).

### A.6 Operator routing
- `show()` menampilkan tujuan = mapping kategori **∩ aktif** (+ tujuan tersimpan tetap disertakan). `updateDestination()` memvalidasi: **mapped** → **exists** → **aktif** (kecuali tujuan tersimpan). Sudah benar.

### A.7 Citizen form
- Hanya memilih `category_id`; tidak ada input Dinas/Unit. Validasi kategori aktif server-side (`StoreComplaintRequest`). Sudah benar; `dinas_name` di dropdown dihapus.

### A.8 Gap yang ditutup pada Bagian 2
1. Mapping controller belum memakai Form Request (validasi/authorization inline) → **dibuat `SyncCategoryMappingRequest`**.
2. Audit mapping hanya satu entri → **ditambah entri granular** (added/removed).
3. View mapping berada di folder `dinas/` (tidak konsisten dgn alur Category) → **dipindah ke `categories/mapping.blade.php`** + badge Mapped/Aktif/Nonaktif.
4. Citizen form masih menampilkan `dinas_name` sebagai instansi → **diubah mapping-first** (fallback legacy).

---

## B. IMPLEMENTASI

### Created
| File | Alasan |
|---|---|
| `app/Http/Requests/SuperAdmin/SyncCategoryMappingRequest.php` | Validasi + authorization mapping server-side; de-dup id; IDOR-safe. |
| `resources/views/super-admin/categories/mapping.blade.php` | UI mapping Category-centric (badge Mapped/Aktif/Nonaktif). |
| `tests/Feature/CategoryMappingAndRoutingTest.php` | 25 test Bagian 2 (mapping, IDOR, inactive, histori, operator, citizen, regression). |
| `PROMPT_6_PART2_IMPLEMENTATION_REPORT.md` | Laporan ini. |

### Modified
| File | Alasan |
|---|---|
| `app/Http/Controllers/Staff/SuperAdminCategoryMappingController.php` | Pakai Form Request; audit granular added/removed; urutkan Dinas aktif dulu. |
| `app/Http/Controllers/Citizen/ComplaintController.php` | `create()` memuat mapping + payload `mapped_dinas` (mapping-first). |
| `resources/views/citizen/complaint/create.blade.php` | Dropdown kategori tanpa `dinas_name`; kartu instansi dari mapping (fallback legacy). |
| `resources/views/citizen/complaint/show.blade.php` | Tampilkan `dinas_name` hanya bila ada (hindari `()`). |
| `schema.md` | Pivot = mapping saat ini (bukan histori); `dinas_unit_id` bukan turunan pivot. |
| `SETUP_AND_DOCS.md` | §13 baru (Bagian 2). |
| `README.md` | Mapping ≠ historical destination; `dinas_name` bukan routing. |

### Deleted
| File | Alasan |
|---|---|
| `resources/views/super-admin/dinas/mapping.blade.php` | Digantikan view Category-centric; tidak ada referensi lain (hanya di laporan historis). |

**Tanpa migration baru. Tanpa perubahan schema.**

---

## C. MAPPING (Category ↔ Dinas/Unit)

- Relasi **many-to-many** via `category_dinas_unit` (UNIQUE pair). Satu kategori ↔ banyak Dinas; satu Dinas ↔ banyak kategori.
- **Super Admin** mengelola via `super-admin/kategori/{category}/mapping` (edit + update/sync), dari halaman Kategori (`Kelola Mapping`) atau dari edit Dinas.
- `sync()` hanya menyentuh pivot kategori yang di-bind; complaint **tidak pernah** diubah.

## D. OPERATOR (validasi tujuan aktual)

Server-side pada `updateDestination()`:
1. `exists` — Dinas/Unit harus ada (jika tidak → error).
2. `mapped` — harus terdaftar pada mapping kategori complaint.
3. `is_active` — untuk tujuan **baru**; tujuan yang **sudah tersimpan** (historis, mungkin nonaktif) tetap diterima.
- Daftar pilihan di UI = mapping kategori **∩ aktif** (+ tujuan tersimpan). **Bukan** semua Dinas, **bukan** `dinas_name`.

## E. HISTORICAL SAFETY

- Perubahan mapping → `complaints.dinas_unit_id` **tidak berubah** (test: mapping A,D1,D2 → D2,D3; complaint tetap D1).
- Deaktivasi Dinas/Unit → complaint historis **tetap** menunjuk Dinas tersebut, tetap dapat dibuka/diproses.
- Tidak ada bulk reassignment; pivot **bukan** sumber histori tujuan.
- **Konfirmasi:** `mapping change → historical complaint unchanged` ✅

## F. SECURITY (authorization)

| Role | Category mgmt | Mapping mgmt | Dinas mgmt |
|---|---|---|---|
| Masyarakat | 403 | 403 | 403 |
| Operator | 403 | 403 | 403 |
| Admin | 403 | 403 | 403 |
| Super Admin | allowed | allowed | allowed |
| Guest | redirect login | redirect login | redirect login |

Server-side: middleware `role:super_admin` **+** `authorize()` di Form Request. IDOR: update mapping hanya memengaruhi kategori yang di-bind.

## G. TEST

- **Tests: 328** (Bagian 1: 303 → +25)
- **Assertions: 1056** (Bagian 1: 985)
- **Failed: 0**

## H. BUILD

- `npm run build` → **OK** (`✓ built in 699ms`)
- `php artisan view:cache` → **OK**

## I. MIGRATION

- **New migrations: 0**
- **Migration status: 11 Ran / 0 Pending**
- Route count: **48** (0 route Petugas).

---

## Validasi akhir

```
php artisan test            → 328 passed / 1056 assertions / 0 failed
php artisan migrate:status  → 11 migrations, 0 Pending
php artisan route:list      → 48 routes (0 route Petugas)
php artisan view:cache      → OK
npm run build               → OK
complaints:purge-expired --dry-run → OK
```

## Business rules yang dipastikan tidak berubah
Retention · lifecycle status (7/11) · citizen workflow/privacy · attachment rules · daily limit · identity · public tracking/notification (nonaktif) · 4 role aktif (Petugas historis) · mapping many-to-many · delete-protection Dinas (5C.1) & Category.

## Known limitations
- Daftar resmi Kategori/Dinas/Unit **belum tersedia** (seed = *sample/development*).
- `dinas_name` legacy dipertahankan (tidak dihapus) — kini **hanya** fallback tampilan.
- Hard-delete Dinas/Unit yang **belum pernah dipakai** complaint tetap diizinkan.

---

## STATUS: **COMPLETE**

**HARD STOP.** Tidak mengerjakan final cleanup / final regression suite / final documentation pass / final acceptance report (scope Bagian 3/3). Menunggu instruksi **"Lanjut Prompt 6 Bagian 3"**.
