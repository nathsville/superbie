# SUPERBIE — PROMPT 4A REPORT
## Business Decision Freeze — Citizen Response pada `waiting_for_information`

- **Tanggal eksekusi:** (Prompt 4A)
- **Jenis prompt:** AUDIT + BUSINESS DECISION FREEZE + REQUIREMENT SPECIFICATION
- **Scope:** Menentukan STATUS KEPUTUSAN RESMI untuk mekanisme balasan/tanggapan Masyarakat saat status `waiting_for_information`.
- **Aturan:** NO-GUESSING. Bila repository tidak memberi jawaban → `BUSINESS DECISION REQUIRED`.
- **Tidak ada** migration, code, route, UI, atau perubahan database yang dibuat (read-only inspection).

---

## 1. Executive Summary

Prompt 4A diajukan untuk menyelesaikan **satu-satunya blocker** dari Prompt 4: bagaimana Masyarakat memberikan informasi tambahan saat status `waiting_for_information`.

Setelah audit menyeluruh terhadap sumber resmi dan implementasi, **kesimpulan tegas:**

> **Repository SUPERBIE TIDAK memiliki business rule resmi untuk citizen response.** Fitur ini secara eksplisit dinyatakan **BELUM DIPUTUSKAN** oleh pemilik proyek.

Bukti kunci (DECISIVE):
- `README.md` §"Hal yang perlu dikonfirmasi sebelum produksi", baris: **"Apakah pelapor dapat membalas/menambahkan bukti setelah tiket dikirim."**
- Ini adalah pernyataan resmi bahwa keputusan tersebut **belum dibuat** dan wajib dikonfirmasi.

Karena itu, **seluruh pertanyaan bisnis H1–H12 ditandai `BUSINESS DECISION REQUIRED`**, kecuali item yang sudah FROZEN oleh Prompt 3B (status authority & transition matrix) yang tetap dipertahankan tanpa perubahan.

**Hasil audit tambahan (implementation evidence):**
- Tidak ada route citizen untuk menulis catatan/response.
- `ComplaintNote` hanya mendukung `visibility = internal | public_response`, dan seluruh penulisan note adalah staff-only.
- Transisi ke `waiting_for_information` **tidak mewajibkan** Operator memberi pertanyaan/alasan/batas waktu apa pun.

**Final Verdict:** **PARTIALLY COMPLETE — BUSINESS DECISION REQUIRED**
Prompt 4A **tidak dapat** menghasilkan FINAL FROZEN SPECIFICATION. Yang dihasilkan adalah **PARTIAL FROZEN SPECIFICATION** (§21) yang menandai setiap item yang belum diputuskan.

---

## 2. Source Audit (Langkah 1 / §F)

| Sumber | Relevansi ke citizen response | Temuan |
|---|---|---|
| **prd.md** | §2 peran; §7 F-009; §8 lifecycle; §4 TODO | F-009 hanya mendefinisikan `internal` vs `public_response` (ditulis staf). §4 TODO list TIDAK menyebut citizen response secara eksplisit, tetapi juga tidak pernah menetapkannya. |
| **schema.md** | §4.6 `complaint_notes`; §6 status; §10 citizen account | `complaint_notes.visibility` ∈ {`internal`, `public_response`}. **Tidak ada** visibility untuk catatan warga. §10 hanya mengatur akun & batas harian, bukan response. |
| **rules.md** | §1.5; §5; §10 | "Do not silently invent business rules… Use `TODO: Define requirement`." Ini mengunci: citizen response TIDAK boleh dikarang. |
| **architecture.md** | §5.1B; §6; §8 | Alur yang ada: Operator mengelola; Masyarakat membuat laporan & melihat. Tidak ada alur citizen→complaint write selain membuat laporan. |
| **design.md** | §3 (halaman citizen); §4–6 | Tidak ada halaman/komponen "berikan informasi tambahan". Tidak ada wireframe untuk citizen reply. |
| **README.md** | §"Hal yang perlu dikonfirmasi sebelum produksi" | **DECISIVE:** "Apakah pelapor dapat membalas/menambahkan bukti setelah tiket dikirim." → officially UNRESOLVED. |
| **SETUP_AND_DOCS.md** | §6 TODO; §7 (Prompt 4) | Sudah mencatat `BLOCKED — BUSINESS DECISION REQUIRED` untuk citizen response. |

**Kesimpulan Source Audit:** Tidak ada satu pun sumber resmi yang menetapkan citizen response. Sumber resmi justru **menyatakan hal ini terbuka.**

---

## 3. Implementation Audit (Langkah 1 / §F item 8–19)

| Artefak | Temuan |
|---|---|
| `app/Enums/ComplaintStatus.php` | 7 status frozen; `waiting_for_information → {in_progress, resolved}`. Tidak ada transisi yang dipicu Masyarakat. |
| `app/Models/Complaint.php` | Relasi `notes()`, `internalNotes()`, `publicResponses()`. **Tidak ada** relasi `citizenResponses()` / `replies()`. |
| `app/Models/ComplaintNote.php` | `visibility` cast ke `NoteVisibility` (`internal`, `public_response`). `author()` → `users.id`. Model tidak membedakan penulis staf vs warga. |
| `app/Enums/NoteVisibility.php` | Hanya 2 nilai. Tidak ada nilai untuk catatan warga. |
| `ComplaintStatusHistory` | Append-only; `changed_by` → `users.id`. Berisi transisi status, bukan response warga. |
| `Citizen\ComplaintController` | Hanya `create`, `store`, `show`, `downloadAttachment`. **Tidak ada** `reply`/`addInformation`/`storeResponse`. |
| `Staff\OperatorComplaintController` | `addNote` (staff-only), `updateStatus` (staff-only). `note` pada `updateStatus` bersifat `nullable`. `rejection_reason` hanya diwajibkan untuk `rejected`. |
| Citizen views (`citizen/complaint/show.blade.php`) | Menampilkan status, timeline, public response, lampiran. **Tidak ada** form balasan warga. |
| Operator views (`operator/complaint/show.blade.php`) | Operator dapat menambah note internal/publik & ubah status. Tidak ada tombol "minta informasi" khusus — hanya dropdown status. |
| `routes/web.php` | Citizen routes: dashboard, riwayat, buat, show, lampiran, profil. **Tidak ada** route response. Satu-satunya route penulisan catatan = `operator.complaint.add-note` (role `operator,super_admin`). |
| Migrations | `complaint_notes` (complaint_id, author_id, visibility, body, created_at). Tidak ada kolom untuk response warga, reply-to, atau arah komunikasi. |
| Policies/middleware | Tidak ada policy complaint. Otorisasi inline + middleware `role`. Tidak ada otorisasi untuk citizen response. |
| Tests | Tidak ada test citizen response. `ComplaintStatusLifecycleTest` menguji transisi `waiting_for_information → in_progress` **hanya** sebagai aksi Operator, bukan warga. |

**Kesimpulan Implementation Audit:** Tidak ada implementasi citizen response, dan tidak ada jejak bahwa sistem dirancang untuk itu.

---

## 4. Official Evidence (Kelas A — OFFICIAL BUSINESS RULE)

| # | Evidence | Sumber | Implikasi |
|---|---|---|---|
| A1 | 7 status resmi + 11 transisi; `closed` terminal; no reopen | prd.md §8, schema.md §6, Prompt 3B | **FROZEN** — tidak diubah. |
| A2 | Status authority: Operator YES, Super Admin YES, Admin NO, Masyarakat NO | prd.md §8, schema.md §6 | **FROZEN** — Masyarakat tidak boleh mengubah status. |
| A3 | Transisi `waiting_for_information → in_progress` dan `→ resolved` diizinkan | schema.md §6 | Ada jalur keluar, **dipicu Operator**, bukan warga. |
| A4 | `rejected` wajib reason + public response; `resolved` wajib public response | prd.md §8, schema.md §6 | Berlaku untuk aksi Operator. |
| A5 | Catatan punya jenis jelas `internal` / `public_response`; internal tidak pernah publik | prd.md F-009, schema.md §4.6 | Model komunikasi resmi = staf→(internal|publik). |
| A6 | Masyarakat hanya melihat laporan miliknya | prd.md §2, A-02 | Isolasi kepemilikan. |
| A7 | **"Apakah pelapor dapat membalas/menambahkan bukti setelah tiket dikirim"** = item yang harus dikonfirmasi | README.md §Hal yang perlu dikonfirmasi sebelum produksi | **DECISIVE: citizen response resmi UNRESOLVED.** |
| A8 | Dilarang mengarang business rule; gunakan `TODO: Define requirement` | rules.md §1.5, §10 | Mewajibkan status BLOCKED, bukan asumsi. |

---

## 5. Provisional / Unknown Evidence (Kelas E/F)

| # | Item | Status |
|---|---|---|
| E1 | Apakah warga boleh menambah informasi melalui sistem | **UNKNOWN → BUSINESS DECISION REQUIRED** |
| E2 | Bentuk response (teks/lampiran/keduanya) | **UNKNOWN** |
| E3 | Siapa yang boleh mengirim response | **UNKNOWN** |
| E4 | Siapa yang boleh melihat response warga | **UNKNOWN** |
| E5 | Boleh >1 response? Berulang? | **UNKNOWN** |
| E6 | Attachment untuk response warga | **UNKNOWN** |
| E7 | Response memicu transisi status? | **UNKNOWN** |
| E8 | Operator wajib memberi pertanyaan/alasan/batas waktu saat `waiting_for_information`? | **UNKNOWN** (implementasi saat ini: `note` nullable, tidak ada kewajiban) |
| E9 | Response masuk status history / audit log? | **UNKNOWN** |

> Catatan: `complaint_notes` (author_id + visibility) secara **teknis** bisa menyimpan catatan dari warga, tetapi **tidak ada keputusan resmi** yang menyatakan tabel ini dirancang untuk itu. Prompt 4A §E melarang mengasumsikan `public_response` = citizen response.

---

## 6. Frozen Existing Rules (tidak berubah oleh 4A)

1. Lifecycle status & 11 transisi (Prompt 3B). ✅ FROZEN
2. Status authority (Masyarakat NO; Operator YES; Admin NO; Super Admin YES). ✅ FROZEN
3. `closed` terminal, no reopen. ✅ FROZEN
4. `rejected` → reason + public response wajib; `resolved` → public response wajib. ✅ FROZEN
5. Catatan diklasifikasikan `internal` / `public_response`; internal tak pernah publik. ✅ FROZEN
6. Masyarakat wajib login; hanya melihat laporan sendiri. ✅ FROZEN

---

## 7. Citizen Response Business Decision (H1–H12)

> Semua di bawah ini **TIDAK dapat dijawab dari evidence resmi**. Setiap item = `BUSINESS DECISION REQUIRED`.

| ID | Pertanyaan | Keputusan | Alasan |
|---|---|---|---|
| **H1** | Apakah Masyarakat boleh memberi informasi? (A. via sistem / B. luar sistem / C. belum) | **C. Belum diputuskan** | README.md menandainya sebagai item yang harus dikonfirmasi. |
| **H2** | Bentuk response (teks / teks+lampiran / lampiran / mekanisme existing / belum) | **BUSINESS DECISION REQUIRED** | Tidak ada field/UI/route. |
| **H3** | Siapa yang boleh mengirim response | **BUSINESS DECISION REQUIRED** | Tidak ada otorisasi resmi. |
| **H4** | Siapa yang boleh melihat response | **BUSINESS DECISION REQUIRED** | Tidak ada visibility untuk catatan warga. §E melarang menyamakan dengan `public_response`. |
| **H5** | Boleh >1 response? | **BUSINESS DECISION REQUIRED** | Tidak ada model arah komunikasi. |
| **H6** | Boleh response berulang? | **BUSINESS DECISION REQUIRED** | Bergantung H5. |
| **H7** | Attachment diperbolehkan? | **BUSINESS DECISION REQUIRED** | Aturan lampiran sendiri masih TODO (prd §4, F-010). |
| **H8** | Response mengubah status? | **BUSINESS DECISION REQUIRED** (default jelas: **TIDAK otomatis**, karena status authority Masyarakat = NO) | Tidak boleh mengarang transisi otomatis. |
| **H9** | Masyarakat boleh memilih status? | **NO (FROZEN)** | prd §8 / schema §6: status authority Masyarakat = NO. |
| **H10** | Response masuk status history? | **BUSINESS DECISION REQUIRED** | Pisahkan response vs transisi status. |
| **H11** | Response masuk audit log? | **BUSINESS DECISION REQUIRED** | Audit log resmi untuk aksi sensitif; klasifikasi citizen response belum ditetapkan. |
| **H12** | Operator wajib memberi pertanyaan/alasan/batas waktu saat `waiting_for_information`? | **BUSINESS DECISION REQUIRED** | Implementasi saat ini: `note` nullable; tidak ada kewajiban server-side. |

**Satu-satunya jawaban pasti: H9 = NO** (karena sudah frozen). Sisanya BLOCKED.

---

## 8. Authority Matrix

Status authority **FROZEN** (dari prd §8 / schema §6 — tidak diubah):

| Action | Masyarakat | Operator | Admin | Super Admin |
|---|---|---|---|---|
| Mengubah status complaint | **NO** | YES | NO | YES |

Authority untuk **citizen response** — **tidak dapat diisi tanpa keputusan**:

| Action | Masyarakat | Operator | Admin | Super Admin |
|---|---|---|---|---|
| Melihat request informasi (dari Operator) | **?** | **?** | **?** | **?** |
| Mengirim informasi tambahan | **?** | **?** | **?** | **?** |
| Mengubah response | **?** | **?** | **?** | **?** |
| Menghapus response | **?** | **?** | **?** | **?** |
| Melihat response | **?** | **?** | **?** | **?** |

→ `?` = **BUSINESS DECISION REQUIRED**. Tidak diisi berdasarkan asumsi (Prompt 4A §I & §O).

---

## 9. Response Visibility Matrix

| Nilai | Didefinisikan? | Sumber |
|---|---|---|
| `internal` | ✅ Resmi | prd F-009, schema §4.6 |
| `public_response` | ✅ Resmi | prd F-009, schema §4.6 |
| **citizen response** (nama/visibility) | ❌ **Belum ada** | — |

Matriks visibilitas untuk citizen response **tidak dapat dibuat** — belum ada model/visibility. `BUSINESS DECISION REQUIRED`.

---

## 10. Response Data Requirements

Audit schema existing (§J) — **tanpa perubahan**:

- `complaint_notes`: `complaint_id`, `author_id`, `visibility`(`internal|public_response`), `body`, `created_at`.
  - **Dapat** menyimpan teks dari warga secara teknis (author_id = warga), **tetapi** tidak ada nilai `visibility` untuk catatan warga dan tidak ada keputusan resmi → **jangan diasumsikan**.

**Kesimpulan:** Apabila keputusan resmi menetapkan response warga harus dibedakan dari note staf (sangat mungkin), maka:

> **SCHEMA CHANGE REQUIRED** — kemungkinan penambahan nilai `visibility` baru (mis. `citizen_response`) ATAU tabel/relasi terpisah (mis. `complaint_responses`), plus aturan arah komunikasi. **Tidak diimplementasikan di Prompt 4A.**

---

## 11. Attachment Requirements

- Aturan lampiran umum **masih TODO** (prd §4: "ukuran/jumlah/format lampiran"; F-010: "ekstensi, ukuran maksimum, jumlah maksimum, scanning malware").
- Belum ada keputusan apakah response warga boleh menyertakan lampiran.
- Jika ya → butuh keputusan: tipe, ukuran, jumlah, apakah bagian dari response, akses Operator, akses warga, retention.

→ **BUSINESS DECISION REQUIRED**. Tidak ada aturan baru dibuat.

---

## 12. Status Transition Impact

- Transition matrix **TIDAK diubah** (frozen).
- **Tidak boleh** ada asumsi `citizen submit → waiting_for_information → in_progress` otomatis.
- Bila H8 diputuskan "otomatis", itu **perubahan perilaku** yang memerlukan keputusan resmi + kemungkinan penyesuaian audit/history. Belum diputuskan.
- **Default yang aman & konsisten dengan frozen rules:** response warga **tidak** mengubah status; Operator yang memicu transisi (opsi B/C di prompt) — **namun ini pun belum diputuskan resmi**, karena H1 ("boleh memberi informasi?") sendiri masih terbuka.

→ **BUSINESS DECISION REQUIRED**.

---

## 13. Status History Impact

- Citizen response ≠ status transition. **Jangan** mencatat response sebagai baris `complaint_status_histories` kecuali ada rule resmi.
- Keputusan apakah response menghasilkan event terpisah: **BUSINESS DECISION REQUIRED** (prompt H10 opsi B/C).

---

## 14. Audit Log Impact

- Audit log existing mencatat aksi sensitif (aktor, action, subject, metadata allowlist, IP, UA).
- Apakah citizen response = aksi sensitif yang harus diaudit: **BUSINESS DECISION REQUIRED** (prompt H11).
- Jika diputuskan perlu audit, action key baru (mis. `complaint.citizen_response_added`) harus didefinisikan. **Tidak diimplementasikan.**

---

## 15. Security Requirements (dokumentasi saja)

Tanpa memutuskan fitur, berikut requirement keamanan **wajib** jika citizen response diimplementasikan (Prompt 4B harus memenuhinya):

1. **Ownership/IDOR:** warga hanya boleh menambah/melihat response pada complaint miliknya (`reporter_id === auth()->id()`), diverifikasi server-side.
2. **Authorization role:** role `masyarakat`; staf mengikuti matriks akses yang diputuskan.
3. **Status precondition:** hanya boleh submit saat status sesuai keputusan (mis. `waiting_for_information`), diverifikasi server-side (bukan hanya UI).
4. **Mass assignment allowlist:** request hanya menerima field yang diizinkan (mis. `body`, `attachments[]`); **tolak/abaikan** `status`, `assigned_to`, `visibility`, `author_id`, `reporter_id`.
5. **Status tampering:** warga tidak dapat menyetel status/assigned_to (sudah terbukti aman untuk pembuatan laporan; harus sama untuk response).
6. **Attachment access:** validasi MIME/ekstensi/ukuran/jumlah; simpan di disk `private`; unduh via controller terotorisasi dengan cek kepemilikan ganda.
7. **Isolation:** response internal/`public_response` staf tetap tidak boleh tercampur/tersamar dengan response warga.
8. **Cross-user access:** response warga A tidak boleh terlihat/diubah warga B.
9. **Auditability:** sesuai keputusan H11.
10. **Rate limiting:** mencegah spam response (nilai = keputusan).

---

## 16. UX Requirements (dokumentasi saja)

Jika fitur diputuskan (prompt §L), detail page warga **perlu**:
- Menampilkan status `Menunggu Informasi` + pesan/pertanyaan Operator (jika H12 mewajibkan).
- Tombol "Berikan Informasi" (hanya saat status mengizinkan).
- Textarea + (opsional, sesuai H7) upload lampiran.
- Riwayat komunikasi dengan timestamp.
- Konfirmasi/feedback sukses & error.
- Empty/loading/error states sesuai `design.md`.
- Aksesibilitas (label, focus, `prefers-reduced-motion`).

Catatan kritis: **pesan/pertanyaan Operator** yang harus ditampilkan pun belum dijamin ada (lihat H12) — implementasi saat ini tidak mewajibkannya.

Seluruh UX di atas **bergantung pada keputusan H1–H12** → belum dapat difinalkan.

---

## 17. Edge Cases (analisis, tanpa implementasi)

| # | Skenario | Expected behavior | Business decision status | Security impact |
|---|---|---|---|---|
| 1 | Buka `waiting_for_information` yang sudah pernah direspons | ? (boleh respons lagi?) | **BUSINESS DECISION REQUIRED** (H6) | Perlu cegah spam/duplikasi |
| 2 | Operator meminta informasi 2 kali | ? | **BUSINESS DECISION REQUIRED** (H5/H6/H12) | — |
| 3 | Warga kirim response setelah status `in_progress` | ? | **BUSINESS DECISION REQUIRED** (H8 precondition) | Harus ditolak server-side bila tak diizinkan |
| 4 | Buka form setelah `resolved` | ? | **BUSINESS DECISION REQUIRED** | Form harus tidak muncul/ditolak |
| 5 | Submit dua kali (double submit) | ? | **BUSINESS DECISION REQUIRED** | Idempotency/dedup |
| 6 | Response ke complaint milik warga lain | **DENY (403)** | Security requirement (konsisten aturan existing) | IDOR |
| 7 | Warga coba ubah status via request | **DENY/IGNORE** | FROZEN (H9 = NO) | Status tampering |
| 8 | Attachment response invalid | **DENY** (validasi server) | Aturan lampiran = **BUSINESS DECISION REQUIRED** | Upload abuse |
| 9 | Complaint sudah `closed` | **DENY** (terminal) | FROZEN (no reopen) — response ke closed = belum diputuskan | — |
| 10 | Akun warga menjadi `inactive` | **DENY login/session invalid** | FROZEN (`EnsureUserIsActive`) | — |

Hanya #6, #7, #9(partial terminal), #10 yang punya landasan resmi; sisanya BLOCKED.

---

## 18. Database Impact

- **Tidak ada** perubahan database di Prompt 4A (read-only).
- `migrate:status`: seluruh 8 migration `Ran`, **tidak ada Pending**.
- Analisis: keputusan citizen response **hampir pasti membutuhkan SCHEMA CHANGE** (nilai `visibility` baru dan/atau tabel/relasi response baru) — **SCHEMA CHANGE REQUIRED**, namun tidak diimplementasikan sampai keputusan resmi.

---

## 19. Required Changes for Prompt 4B (kondisional, belum boleh dikerjakan)

Prompt 4B **hanya dapat dimulai** setelah keputusan resmi. Daftar perubahan yang **mungkin** dibutuhkan (belum final):

1. (Jika H1 = ya) Migration: nilai `visibility` baru atau tabel `complaint_responses` (+ FK, index). → **menunggu keputusan skema**
2. Model/relasi untuk response warga (arah komunikasi jelas, terpisah dari note staf).
3. Route citizen (`citizen.complaint.response.store`) + authorization (owner + precondition status).
4. Form Request dengan allowlist + validasi.
5. Controller/Livewire action + transaksi (response + opsional audit/history sesuai keputusan).
6. UI detail page warga (form, riwayat, states, aksesibilitas, animasi).
7. (Jika H7 = ya) Dukungan attachment response pada disk `private` + otorisasi unduh.
8. Tests: ownership/IDOR, precondition status, tampering, visibility, attachment, edge cases.
9. Update dokumentasi (prd/schema/rules) **setelah** keputusan resmi.

Semua **DI BLOKIR** sampai keputusan §20 diberikan.

---

## 20. Business Decisions Still Required

**BLOCKED — BUSINESS DECISION REQUIRED** untuk seluruh poin berikut (tidak boleh ditebak):

1. **H1** — Apakah Masyarakat boleh memberi informasi tambahan melalui sistem SuperBie? (ya/tidak)
2. **H2** — Bentuk response (teks / teks+lampiran / lampiran / mekanisme lain).
3. **H3** — Siapa yang boleh mengirim response (hanya pemilik / lain).
4. **H4** — Siapa yang boleh melihat response (pemilik+staf / semua warga / publik / lainnya).
5. **H5 & H6** — Boleh banyak & berulang? Jika ya → mendesain skema di 4B (bukan 4A).
6. **H7** — Attachment response (boleh/tidak; tipe/ukuran/jumlah/akses/retensi).
7. **H8** — Apakah response memicu transisi status? (default usulan: **tidak otomatis**, tetapi tetap butuh keputusan).
8. **H10** — Apakah response dicatat di status history (event terpisah)?
9. **H11** — Apakah response masuk audit log?
10. **H12** — Apakah Operator **wajib** memberi pertanyaan/alasan/batas waktu saat `waiting_for_information`?

**Sudah pasti (tidak perlu keputusan):** H9 — Masyarakat **tidak boleh** memilih status (FROZEN).

---

## 21. PARTIAL FROZEN SPECIFICATION

> Karena §20 masih terbuka, **tidak boleh** dibuat FINAL FROZEN SPECIFICATION. Berikut status setiap komponen.

| # | Komponen | Status |
|---|---|---|
| 1 | **Trigger** (kapan form muncul) | ⛔ BLOCKED — bergantung H1, H8 |
| 2 | **Actor** (siapa mengirim) | ⛔ BLOCKED — H3 |
| 3 | **Permission** | ⛔ BLOCKED — H3/H4 (kecuali: bukan hak mengubah status → FROZEN NO) |
| 4 | **Preconditions** | ⛔ BLOCKED — H8/H12 |
| 5 | **Input** | ⛔ BLOCKED — H2 |
| 6 | **Attachment** | ⛔ BLOCKED — H7 |
| 7 | **Visibility** | ⛔ BLOCKED — H4 (tidak ada visibility resmi untuk catatan warga) |
| 8 | **Storage** | ⛔ BLOCKED — kemungkinan SCHEMA CHANGE REQUIRED |
| 9 | **Multiple response behavior** | ⛔ BLOCKED — H5/H6 |
| 10 | **Edit/delete behavior** | ⛔ BLOCKED — belum diputuskan |
| 11 | **Status impact** | ⛔ BLOCKED — H8 (default usulan: tidak otomatis) |
| 12 | **Status authority** | ✅ FROZEN — Masyarakat NO; Operator YES; Admin NO; Super Admin YES |
| 13 | **Status history behavior** | ⛔ BLOCKED — H10 |
| 14 | **Audit behavior** | ⛔ BLOCKED — H11 |
| 15 | **Notification behavior** | ✅ OUT OF SCOPE (no notification) |
| 16 | **Validation** | ⛔ BLOCKED — H2/H7 (prinsip: server-side, allowlist) |
| 17 | **Security** | ✅ Prinsip wajib terdokumentasi (§15); detail teknis BLOCKED |
| 18 | **Edge cases** | ⛔ BLOCKED — §17 |
| 19 | **UI requirements** | ⛔ BLOCKED — §16 |
| 20 | **Acceptance criteria** | ⛔ BLOCKED — tidak dapat difinalkan |

---

## 22. Final Verdict

### PARTIALLY COMPLETE — BUSINESS DECISION REQUIRED

Prompt 4A **tidak dapat** menyelesaikan business freeze karena sumber resmi proyek **secara eksplisit menyatakan topik ini belum diputuskan**:

> README.md — "Hal yang perlu dikonfirmasi sebelum produksi": *"Apakah pelapor dapat membalas/menambahkan bukti setelah tiket dikirim."*

Sebagai konsekuensi (dan sesuai `rules.md` §1.5/§10 serta Prompt 4A §O NO-GUESSING):
- Tidak ada FINAL FROZEN SPECIFICATION.
- Yang dihasilkan: **PARTIAL FROZEN SPECIFICATION** (§21).
- H1–H8, H10–H12 = **BUSINESS DECISION REQUIRED**; hanya H9 (Masyarakat tidak boleh ubah status) yang FROZEN.
- **Tidak ada** migration, code, route, UI, atau perubahan database (read-only inspection: `route:list`, `migrate:status`).

**STOP.** Tidak melanjutkan ke Prompt 4B, tidak mengimplementasikan citizen response, tidak membuat migration/route/UI. Menunggu keputusan/review berikutnya.
