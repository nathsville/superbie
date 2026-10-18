# Rencana Integrasi JDIH di SuperBie — Tautan Keluar + Halaman Pemberitahuan

**Status:** DISETUJUI (permintaan owner 2026-10-18) · **Implementasi ✅ (2026-10-18, uncommitted)** · **Tanggal:** 2026-10-18
**Pengambil keputusan:** pemilik proyek (owner)

Dokumen terkait:

- `prd.md`, `rules.md`, `design.md`, `architecture.md`, `schema.md` — sumber kebenaran; status lama "JDIH tidak dibangun (label navigasi saja)" diperbarui bersama implementasi ini.
- `SIPHP_INTEGRATION_PLAN.md` — pola integrasi sebelumnya (modul native, PHASE S).
- `PROGRESS.md` — catatan progres.

---

## 1. Latar belakang dan keputusan

Portal SuperBie direncanakan menghubungkan empat layanan: SIPHP, WINDY, JDIH, dan Command Center — Lapor Pak Wali (`prd.md` §1). SIPHP (PHASE S) sudah dibangun sebagai modul native; **JDIH Kota Parepare** (`https://jdih.pareparekota.go.id/`) kini dihubungkan sebagai **tautan keluar** — tanpa membangun ulang modulnya.

**Keputusan (2026-10-18):**

| ID | Keputusan | Sumber |
|---|---|---|
| **D-J1** | **Hyperlink keluar saja.** Tanpa scraping, iframe/embed, API, atau sinkronisasi; SuperBie tidak menyimpan/menyalin apa pun dari JDIH dan **tidak ada tabel/model JDIH**. | owner |
| **D-J2** | **Halaman pemberitahuan lebih dulu.** Visitor **tidak pernah diarahkan otomatis**; halaman internal `/jdih` menyatakan alamat tujuan, dan tautan keluar hanya terbuka dari **aksi eksplisit** visitor (tanpa meta refresh / JS redirect / timer). | owner |
| **D-J3** | **Tautan keluar** membuka **tab baru** dengan `rel="noopener noreferrer"` + catatan screen-reader; alamat tujuan ditampilkan sebagai teks biasa. | engineering (konvensi tautan eksternal repo) |
| **D-J4** | **Pintu masuk:** landing — pintu footer "Dokumen hukum" + daftar Layanan "Dokumen hukum (JDIH)". Tab nav landing **tidak diubah** (memuat tiga tautan, satu baris pada `lg`). | engineering |
| **D-J5** | **Tanpa role/teknologi/skema baru** — tetap empat role; halaman publik tanpa login; tanpa perubahan `schema.md`. | mengikuti `rules.md` §2 |

## 2. Ruang lingkup

**Termasuk:**

- Halaman publik `GET /jdih` (`jdih.index`) — pemberitahuan + **satu** aksi keluar eksplisit.
- Pintu masuk di landing (pintu footer + daftar Layanan).
- Exclusion progressive navigation (`navigation.js`) — halaman full-chrome seperti `/cuaca` dan `/siphp`.
- Test kontrak: `JdihNoticePageTest` + inventaris route & exclusion di `ProgressiveNavigationTest`.

**Tidak termasuk (non-goals):** modul/dokumen JDIH di SuperBie; penyimpanan/penyalinan dokumen; integrasi data; redirect otomatis; perubahan skema; role baru; teknologi/queue/Docker baru.

## 3. Implementasi

- `app/Http/Controllers/PublicJdihController.php` — invokable; URL tujuan = satu konstanta `DESTINATION` (satu sumber untuk halaman + test).
- `resources/views/public/jdih.blade.php` — sheet editorial mengikuti `design.md` §9 (type ladder 13/17, tone band `bg-surface-sunken`, tanpa garis/shadow); alamat tujuan sebagai teks; satu tombol keluar; tautan "Kembali ke beranda".
- `resources/js/navigation.js` — `JDIH_PATH` / `isJdihPath` dikecualikan sebagai **destination** dan sebagai **halaman saat ini** (pola sama dengan `/cuaca` dan `/siphp`).
- `resources/views/public/landing.blade.php` — pintu footer "Dokumen hukum" + `<li>` daftar Layanan "Dokumen hukum (JDIH)"; **tidak ada** tautan eksternal langsung dari landing.

## 4. Gerbang rilis (verifikasi)

| # | Gerbang | Status |
|---|---|---|
| G1 | Halaman `/jdih` publik (200, tanpa login) + memuat alamat tujuan | ✅ `JdihNoticePageTest` |
| G2 | Tanpa redirect otomatis (tanpa meta refresh / JS redirect) | ✅ `JdihNoticePageTest` |
| G3 | Tautan keluar `target="_blank"` + `rel="noopener noreferrer"`, tepat satu | ✅ `JdihNoticePageTest` |
| G4 | Landing menautkan ke `/jdih` (2 pintu), bukan langsung ke situs eksternal | ✅ `JdihNoticePageTest` |
| G5 | Inventaris route (137) + exclusion navigasi terkunci | ✅ `ProgressiveNavigationTest` |
