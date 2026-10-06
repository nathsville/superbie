# SUPERBIE — PROMPT 4 REPORT
## Citizen / Masyarakat Complaint Experience

- **Tanggal eksekusi:** (Prompt 4)
- **Scope:** Audit + penyempurnaan sisi Masyarakat/Pelapor pada Complaint Lifecycle yang **sudah frozen** (Prompt 3B).
- **Environment:** Laravel 13.34.0, PHP 8.4.26 (native Laragon), MySQL 127.0.0.1:3307, DB `superbie` / testing `superbie_testing`. Tanpa Docker, tanpa Livewire, tanpa perubahan arsitektur.

---

## 1. Executive Summary

Prompt 4 berfokus pada penguatan dan verifikasi pengalaman Masyarakat pada alur pengaduan. Audit menemukan bahwa **mayoritas fitur citizen-facing sudah benar** (isolasi data, pembuatan laporan dengan allowlist, detail authorization, attachment authorization, public response vs internal note). Tiga perbaikan nyata dilakukan:

1. **P0 (Security/Privacy):** Riwayat status (timeline) sisi Masyarakat sebelumnya menampilkan `complaint_status_histories.note` — yang merupakan catatan operasional internal. Field tersebut kini **tidak** lagi dirender untuk Masyarakat. Alasan penolakan resmi tetap terlihat melalui *public response* (diwajibkan server-side).
2. **P0 (Security hardening):** Disk `local` yang ber-root pada `storage/app/private` (sama dengan disk `private`) diubah menjadi `'serve' => false` sehingga route `/storage/{path}` tidak pernah terdaftar untuk file privat. Lampiran hanya dilayani lewat controller terotorisasi.
3. **C (Correctness):** Memperbaiki referensi field yang tidak ada (`$user->phone`) pada pembuatan laporan yang selalu menghasilkan null.

Ditambah **28 test baru** (`CitizenComplaintExperienceTest`) yang lulus, dan **15 test lama** tetap hijau. Total suite: **187 tests, 475 assertions, 0 failure**.

**Status keseluruhan: PARTIALLY COMPLETE — BLOCKED.** Satu REQUIREMENT belum memiliki keputusan resmi: mekanisme balasan/tanggapan Masyarakat saat status `waiting_for_information` (lihat §13 & §19). Semua P0/P1 (security, creation, history, detail, timeline, public response, rejection visibility, attachment, IDOR, tampering, guest protection) telah terpenuhi.

---

## 2. Baseline Sebelum Perubahan

Dijalankan `php artisan test` sebelum perubahan:

| Metrik | Nilai |
|---|---|
| Tests | 159 |
| Passed | 159 |
| Assertions | 409 |
| Failures | 0 |

Test suite awal: `CitizenComplaintFlowTest`, `ComplaintStatusLifecycleTest`, `DashboardAuthorizationTest`, `DashboardCachingTest`, `OperatorComplaintWorkflowTest`, `StaffDashboardRenderTest`, `ExampleTest` (Feature+Unit).

---

## 3. Audit Findings (Langkah 1)

Klasifikasi: A = Already correct, B = Incomplete, C = Incorrect, D = Missing, E = Business decision required.

| # | Area | Kelas | Keterangan |
|---|---|---|---|
| 1 | Dashboard Masyarakat (data sendiri) | A | `DashboardController` + `DashboardCacheService::getCitizenData()` memfilter `reporter_id` di query DB, bukan di Blade. |
| 2 | Pembuatan laporan (allowlist, server-side) | A | `StoreComplaintRequest` authorize `role === masyarakat`; hanya memvalidasi field yang diizinkan; status di-set `Submitted` di controller. |
| 3 | Status awal `submitted` | A | Eksplisit + tertest. |
| 4 | Kategori + Dinas/Unit | E | Data *development fixture* (ditandai). Daftar resmi & mapping = TODO. Menampilkan `dinas_name` yang ada = benar. |
| 5 | Riwayat (pagination + isolasi) | A | `forReporter(...)->paginate(15)`. |
| 6 | Detail authorization (IDOR) | A | `show()` abort 403 jika `reporter_id !== auth()->id()`. |
| 7 | Status timeline | **B → diperbaiki** | Memakai `statusHistories` (benar), tetapi `note` internal ikut dirender ke Masyarakat. Diperbaiki. |
| 8 | Public response terlihat | A | `publicResponses` dimuat; `internalNotes` tidak dimuat di view citizen. |
| 9 | Internal note tersembunyi | A | Tertest. |
| 10 | Alasan penolakan terlihat | A | Public response diwajibkan `requiresPublicResponse()` + tertest. |
| 11 | Resolved / Closed visibility | A | Status + public response tampil; `closed` terminal. |
| 12 | Attachment authorization | A | Cek `reporter_id` + `attachment->complaint_id` (anti-IDOR). |
| 13 | Balasan Masyarakat saat `waiting_for_information` | **E** | **BLOCKED — BUSINESS DECISION REQUIRED** (lihat §13). |
| 14 | Route authorization | A | `auth` + `role:masyarakat`. |
| 15 | Input tampering | B → **diperbaiki via test** | Kode aman (array eksplisit, tidak mass-assign), coverage ditambah. |
| 16 | Referensi `$user->phone` | **C → diperbaiki** | Field tidak ada → selalu null. |
| 17 | Test IDOR/attachment | B → **diperbaiki** | Ditambah test A/B lintas-pengguna. |
| 18 | Paparan private storage via `/storage/{path}` | **B → diperbaiki** | Disk `local` share root dengan `private`; `serve` dinonaktifkan. |

---

## 4. Files Changed

| File | Perubahan |
|---|---|
| `resources/views/citizen/complaint/show.blade.php` | Timeline Masyarakat tidak lagi merender `statusHistories.note` (internal). |
| `app/Http/Controllers/Citizen/ComplaintController.php` | `reporter_phone` diset eksplisit `null` (hapus referensi `$user->phone` yang tidak ada). |
| `config/filesystems.php` | Disk `local`: `'serve' => false` (root privat yang sama dengan disk `private`). |
| `tests/Feature/CitizenComplaintExperienceTest.php` | **Baru** — 28 test citizen experience. |
| `SETUP_AND_DOCS.md` | Catatan implementasi Prompt 4 + TODO baru (blocked). |

Tidak ada file dihapus. Tidak ada migration ditambah/diubah. Tidak ada perubahan arsitektur.

---

## 5. Citizen Workflow Implemented / Verified

- **Dashboard:** ringkasan (Total/Diproses/Selesai/Ditolak) + laporan terbaru, semua milik pelapor (scoped query).
- **Pembuatan laporan:** kategori (menampilkan Dinas/Unit terkait) → judul → lokasi (opsional) → deskripsi → lampiran → submit. Status awal `submitted`; tanda terima + nomor referensi.
- **Riwayat:** daftar terpaginasi milik sendiri dengan nomor referensi, judul, kategori, status, tanggal, tautan detail.
- **Detail:** nomor registrasi, judul, kategori + Dinas, deskripsi, lokasi, tanggal, status, timeline (hanya status nyata), public response, lampiran.
- **Timeline:** dibangun dari `complaint_status_histories` nyata (bukan enumerasi enum); `closed` tidak menampilkan aksi reopen.

---

## 6. Authorization Changes

Tidak ada perubahan aturan otorisasi (sudah benar). **Verifikasi ulang:**
- Semua route `citizen.*` dilindungi `auth` + `role:masyarakat`.
- `citizen.complaint.show` — 403 jika bukan pemilik.
- `citizen.complaint.attachment` — 403 jika bukan pemilik ATAU attachment bukan milik complaint tsb.
- Masyarakat → route Operator/Admin/Super Admin = **403**.
- Guest → seluruh permukaan citizen = redirect `login`.

---

## 7. Security / IDOR Verification

| Skenario | Hasil | Test |
|---|---|---|
| Citizen A melihat detail complaint sendiri | ALLOW | `test_citizen_can_view_own_complaint_detail` |
| Citizen A melihat complaint B (by ID) | DENY (403) | `test_citizen_cannot_view_other_citizen_complaint_by_id` |
| Citizen A unduh lampiran sendiri | ALLOW | `test_citizen_can_download_own_attachment` |
| Citizen A unduh lampiran B | DENY (403) | `test_citizen_cannot_download_other_citizen_attachment` |
| Citizen A pasang lampiran B ke route complaint-nya | DENY (403) | `test_citizen_cannot_cross_link_attachment_from_other_complaint` |
| Lampiran hilang dari disk | 404 (bukan 500) | `test_missing_attachment_returns_not_found_not_500` |
| Akses langsung `/storage/...` tanpa signature | 404/403 | `test_private_attachment_is_not_served_via_public_storage_route_without_signature` |
| Citizen → route lampiran Operator | DENY (403) | `test_citizen_cannot_access_staff_attachment_route` |

---

## 8. Complaint Creation Verification

- Auth: hanya `masyarakat` terautentikasi (`authorize()` + middleware).
- Allowlist request: `category_id`, `title`, `description`, `location_text`, `attachments[]`.
- Status awal selalu `submitted`; `assigned_to` = null; `reporter_id` = user terautentikasi.
- Validasi server-side (title 5–255, description 10–5000, kategori aktif, lampiran jpg/png/pdf ≤5MB, maks 3).
- Initial status history + audit log ditulis dalam satu transaksi.

---

## 9. Status Timeline Verification

- Menggunakan `complaint_status_histories` nyata (`statusHistories` diurut `created_at`).
- Hanya status yang benar-benar terjadi yang tampil (dibuktikan: timeline `submitted→under_review` tidak menampilkan "Menunggu Informasi"/"Ditutup").
- `note` pada riwayat status (operasional internal) **tidak** dirender untuk Masyarakat.
- Tidak ada history palsu; tidak ada timeline berbasis enumerasi enum.

---

## 10. Public Response Verification

- Masyarakat melihat `complaint_notes` dengan `visibility = public_response`.
- Internal note (`visibility = internal`) tidak pernah dimuat/dirender untuk Masyarakat.
- Terbukti: respon publik tampil, catatan internal tidak tampil.

---

## 11. Rejection Reason Verification

- Transisi ke `rejected` mewajibkan `rejection_reason` (non-kosong) + `public_response_body` (non-kosong) — server-side.
- Alasan penolakan tersampaikan ke Masyarakat melalui **public response** (terlihat) — dibuktikan test.
- Status history tetap menyimpan alasan internal untuk jejak audit, tetapi tidak lagi bocor ke timeline Masyarakat.

---

## 12. Attachment Verification

- Disk `private` (root `storage/app/private`), nama file generated (40 char acak + ekstensi), MIME server-detected, checksum SHA-256.
- Download hanya via controller terotorisasi dengan ownership check ganda (`reporter_id` + `complaint_id`).
- `config/filesystems.php`: disk `local` `'serve' => false` → tidak ada route `/storage/{path}` untuk file privat.
- File tidak valid/hilang → 404 terkendali; nama asli disanitasi sebagai teks tampilan.

---

## 13. Waiting-for-Information Status

**BLOCKED — BUSINESS DECISION REQUIRED**

1. **Yang belum diputuskan:** apakah Masyarakat boleh memberikan informasi tambahan saat status `waiting_for_information`, ke mana datanya disimpan, dan apakah itu memicu transisi status.
2. **File/code terkait:** `app/Enums/ComplaintStatus.php` (transisi frozen), `app/Models/ComplaintNote.php` ( hanya `internal`/`public_response`, ditulis staf), `routes/web.php` (tidak ada route citizen untuk menambah catatan), `resources/views/citizen/complaint/show.blade.php`.
3. **Dampak:** Tanpa jalur resmi, Masyarakat tidak dapat menanggapi permintaan informasi dari Operator melalui sistem; alur `waiting_for_information → in_progress` tidak dapat dipicu dari sisi Masyarakat.
4. **Opsi (belum dipilih):** (a) tidak ada balasan citizen (komunikasi di luar sistem) — namun UI harus jelas; (b) menambah jalur catatan citizen (butuh kolom/visibility baru) → butuh keputusan skema; (c) menambah attachment balasan. Semua di luar scope tanpa keputusan resmi.
5. **Tidak diimplementasikan** sampai keputusan diberikan. Lifecycle status TIDAK diubah.

---

## 14. Database / Migration Status

- `php artisan migrate:status` → **seluruh 8 migration `Ran`**, tidak ada yang `Pending`.
- **Tidak ada migration baru.** Tidak ada `migrate:fresh`/drop/destructive. Skema tidak berubah.

---

## 15. Route Validation

- `php artisan route:list` → **32 route** (route `/storage/{path}` hilang setelah hardening — sesuai tujuan).
- Tidak ada `/petugas`, tidak ada `role:petugas`.
- Citizen routes valid; Operator routes valid; Admin monitoring-only (tanpa route mutasi); Super Admin valid.
- `php artisan route:cache` → sukses.

---

## 16. Test Results

| Tahap | Tests | Passed | Assertions | Fail |
|---|---|---|---|---|
| Baseline (sebelum) | 159 | 159 | 409 | 0 |
| Setelah perubahan | **187** | **187** | **475** | **0** |
| — dari test baru Prompt 4 | 28 | 28 | 67 | 0 |

Regression: **tidak ada**. Test lama tidak diubah agar hijau.

---

## 17. Build Results

- `npm run build` → **✓ built in 2.07s** (`app.js` 62.00 kB, `app.css` 83.60 kB).
- Peringatan non-fatal: plugin `laravel:fonts` "Optional `fontaine` package" — bukan error, build tetap sukses.
- `php artisan view:cache` → **Blade templates cached successfully**.
- `php artisan config:clear` / `view:clear` / `route:clear` dijalankan setelah validasi.

---

## 18. Documentation Changes

- `SETUP_AND_DOCS.md`: ditambah item TODO #8 (blocked: balasan citizen saat `waiting_for_information`) dan bagian "Catatan Implementasi Prompt 4".
- `prd.md` / `schema.md` / `architecture.md` / `design.md` / `rules.md`: **tidak diubah** (tidak ada perubahan workflow, skema, arsitektur, atau rule).
- `README.md`: tidak diubah.

---

## 19. Business Decisions Still Required

1. **Balasan Masyarakat saat `waiting_for_information`** — BLOCKED (lihat §13). Ini satu-satunya blocker Prompt 4.
2. (Pre-existing, di luar scope Prompt 4) `TODO` lain: daily report limit, kategori→dinas resmi, attachment rules, tracking verification, notification provider, retention/privacy policy.

---

## 20. Known Limitations

- Tidak ada public tracking endpoint (sesuai no-scope; belum ada keputusan).
- Tidak ada notifikasi, SLA, escalation (no-scope).
- Tidak ada mekanisme balasan citizen saat `waiting_for_information` (blocked).
- Super Admin CRUD belum dibangun (no-scope Prompt 4).
- Pint melaporkan style deviations **pre-existing** di seluruh codebase; tidak di-reformat massal untuk menghindari refactor besar (rules.md §9). File test baru Prompt 4 sudah dibersihkan dari unused import.

---

## 21. Final Verdict

**PARTIALLY COMPLETE — BLOCKED**

Semua P0/P1 (security, isolasi, creation, history, detail, timeline, public response, rejection/resolved/closed visibility, attachment, IDOR, input tampering, guest protection, lifecycle mutation block) **selesai dan tertest**. Verdict bukan `COMPLETE` karena satu requirement — mekanisme balasan Masyarakat pada status `waiting_for_information` — membutuhkan keputusan bisnis baru dan tidak boleh dikarang.

STOP. Tidak melanjutkan ke Prompt 5.
