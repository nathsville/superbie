# Product Requirements Document (PRD)
## SuperBie — Lapor Pak Wali | MVP Tahap 1

- **Status:** Draft implementasi MVP
- **Versi:** 1.0
- **Bahasa:** Indonesia
- **Produk induk:** SuperBie
- **Fokus tahap ini:** Command Center — Lapor Pak Wali
- **Sumber kebutuhan:** Konteks proposal SuperBie dan keputusan teknologi yang diberikan pemilik proyek.
- **Aturan ketidakpastian:** Semua aturan yang belum disebutkan secara jelas diberi label `TODO: Define requirement`.

---

## 1. Ringkasan Produk

**Lapor Pak Wali** adalah modul pengaduan masyarakat berbasis web yang memungkinkan masyarakat menyampaikan laporan/keluhan, memperoleh nomor referensi, dan melihat perkembangan penanganan **setelah login** (data laporan hanya dapat diakses oleh pemiliknya; tidak ada pelacakan publik). **Super Admin** melakukan validasi awal, mengoreksi kategori bila perlu, dan mengarahkan laporan ke Dinas/Unit. Operator berwenang menggunakan panel internal untuk meninjau, memperbarui status, menambah catatan internal, memberi tanggapan publik, dan mengajukan penyelesaian pada Dinas/Unit miliknya.

Modul ini merupakan bagian dari portal SuperBie yang direncanakan menghubungkan empat layanan: SIPHP, Parepare Weather Intelligence Dashboard (**WINDY**), JDIH Kota Parepare, dan Command Center – Lapor Pak Wali. **Perubahan lingkup disetujui (Phase 3, O-1):** selain Lapor Pak Wali, **WINDY — dashboard cuaca publik (baca-saja, tanpa login) pada landing page** kini termasuk dalam lingkup yang disetujui. **SIPHP dan JDIH tetap tidak dibangun** (hanya label navigasi). Implementasi WINDY tunduk pada gerbang rilis pada `_superbie_audit/WINDY_MIGRATION_PLAN.md`; **tidak ada** GIS/peta batas wilayah, dan **tetap empat role** (konfigurasi WINDY & sinkronisasi manual internal hanya Super Admin; sinkronisasi terjadwal bergantung host scheduler yang belum terverifikasi; Admin monitoring-only). **Keputusan pemilik Phase 4E (D-11…D-17):** horizon prakiraan **3 hari** dari **BMKG gratis** (tanpa penyedia berbayar); tampilan **WITA**; ambang lama = **nilai awal**; **tanpa impor historis Google Sheets**; sinkronisasi **terjadwal + manual**; patuhi **atribusi** penyedia. **Perubahan lingkup berikutnya (PHASE S — SIPHP, 2026-10-17, D-S1…D-S7):** modul **SIPHP** (informasi harga pasar + direktori retail) disetujui sebagai **rebuild native** di SuperBie dengan **input manual** oleh Super Admin dan halaman **publik `/siphp`**; JDIH tetap tidak dibangun; ikuti gerbang rilis `SIPHP_INTEGRATION_PLAN.md`.

### Tujuan utama
1. Menyediakan satu alur digital yang mudah diakses untuk pengiriman pengaduan masyarakat.
2. Memberikan tanda terima/nomor pelacakan agar pelapor dapat mengetahui perkembangan laporan.
3. Membantu operator mengelola, menindaklanjuti, dan mencatat perubahan status laporan secara tertib.
4. Menyediakan jejak audit untuk aktivitas penting panel internal.

### Problem statement
Masyarakat membutuhkan kanal pengaduan yang jelas dan mudah digunakan, sementara pengelola membutuhkan pencatatan laporan yang konsisten, kemampuan menindaklanjuti, serta visibilitas status penanganan. Detail proses operasional resmi yang belum tersedia harus dikonfirmasi, bukan diasumsikan.

## 2. Pengguna dan peran

| Peran | Kebutuhan | Akses MVP |
|---|---|---|
| Masyarakat / Pelapor | Membuat akun, login, mengirim laporan, melihat dashboard dan riwayat laporan **miliknya sendiri** | Panel masyarakat/pelapor terautentikasi |
| Operator | Meninjau laporan pada Dinas/Unit miliknya, memperbarui status, menambah catatan internal, memberi respons publik, memperbarui lokasi, mengajukan penyelesaian, mengakses lampiran | Panel operator (scope Dinas/Unit) |
| Admin | Monitoring laporan dan audit log | Panel monitoring, tanpa akses pengelolaan |
| Super Admin | Mengelola seluruh bagian website sesuai role, termasuk laporan, akun, kategori, Dinas/Unit, wilayah, konfigurasi, validasi awal, validasi penyelesaian, audit dan keamanan | Akses penuh |

> Catatan: peran operasional penanganan laporan dipegang oleh **Operator**. Peran historis `petugas` **bukan** active role (tidak dapat dipilih, tidak memiliki route/workflow aktif); baris data lama yang masih merujuk `petugas` dipertahankan apa adanya — **FINAL (PHASE 6B Decision 1 / PHASE 6C)**: retain as historical, tanpa reassignment dan tanpa penghapusan. Koreksi kategori dan routing Dinas/Unit dilakukan oleh **Super Admin** pada validasi awal, bukan oleh Operator.

**Catatan privasi:** masyarakat hanya dapat melihat laporan miliknya sendiri; **guest tidak dapat mengakses data laporan**. Tidak ada pelacakan publik.

## 3. Scope

### In scope — MVP
- Landing page Lapor Pak Wali dan penjelasan cara melapor.
- Registrasi, login, logout, dan reset password untuk masyarakat/pelapor.
- Dashboard masyarakat/pelapor dan riwayat laporan milik sendiri.
- Formulir pengaduan untuk masyarakat/pelapor yang sudah login.
- Pemilihan kategori laporan pada saat pengaduan.
- **Lokasi terstruktur wajib:** Kecamatan + Kelurahan (master wilayah dikelola Super Admin; sumber resmi = BPS). Koordinat (latitude/longitude) bersifat **opsional**.
- Validasi sisi server dan sisi antarmuka.
- Batas jumlah laporan harian untuk masyarakat/pelapor: **5 laporan / hari kalender** (timezone aplikasi, dapat dioverride via `app_settings.daily_report_limit`).
- Penyimpanan pengaduan dengan nomor referensi yang tidak mudah ditebak.
- Unggah lampiran: opsional, maks 10 file, maks 20 MB/file, format JPG/JPEG/PNG/PDF/MP4, tanpa malware scanning.
- Halaman tanda terima/detail setelah pengaduan berhasil dikirim (akses pemilik, login).
- Login, logout, dan reset password untuk seluruh akun internal (reset melalui email hanya jika layanan email dikonfigurasi).
- Dashboard panel Operator, Admin, dan Super Admin sesuai hak akses.
- Daftar pengaduan dengan pencarian, filter, pagination, dan pengurutan yang dibatasi sesuai peran.
- Detail pengaduan internal.
- Perubahan status dengan validasi transisi.
- Catatan internal dan tanggapan publik, dibedakan secara tegas.
- **Validasi awal oleh Super Admin** (koreksi kategori, pemilihan Dinas/Unit, penugasan Operator), dan **validasi penyelesaian** oleh Super Admin sebelum `resolved`.
- Pengelolaan kategori laporan beserta Dinas/Unit terkait oleh Super Admin.
- Pengelolaan akun masyarakat, Operator, dan Admin oleh Super Admin.
- **Penetapan Dinas/Unit Operator (FINAL, D-2 / Prompt 21):** satu Operator mewakili **tepat satu** Dinas/Unit. **Super Admin** menetapkan/mengubah Dinas/Unit Operator melalui User Management; Operator tidak dapat menetapkan Dinas/Unit sendiri maupun Operator lain. Ditegakkan di server (middleware `role:super_admin` + Form Request `authorize()` + guard model layer). Operator tanpa unit tetap valid namun tidak melihat laporan apa pun (tanpa fallback global). Deaktivasi tidak menghapus assignment. D-2 tidak menambahkan batasan lain.
- Pengelolaan role (penugasan 4 role tetap) oleh Super Admin.
- Pengelolaan konfigurasi operasional terbatas oleh Super Admin (saat ini `daily_report_limit`).
- Monitoring laporan dan audit log oleh Admin.
- Audit log untuk tindakan sensitif.
- Animasi UI konsisten untuk setiap komponen visual/interaktif, dengan dukungan reduced-motion.
- Responsive layout, aksesibilitas keyboard, empty/loading/error/success states.

### Out of scope pada MVP
- ~~SIPHP/informasi harga pasar.~~ **(PHASE S, 2026-10-17): DISETUJUI** — rebuild native, input manual, publik `/siphp` (D-S1…D-S7; gerbang di `SIPHP_INTEGRATION_PLAN.md`). *Modul ini dipindahkan dari daftar out-of-scope.*
- JDIH/dokumen hukum.
- **WINDY — dashboard cuaca publik (baca-saja, tanpa login):** **termasuk lingkup yang disetujui (Phase 3, O-1)**, tetapi **implementasinya hanya boleh dimulai setelah gerbang rilis terpenuhi** (lihat `_superbie_audit/WINDY_MIGRATION_PLAN.md`). Tanpa GIS/peta batas wilayah, tanpa role baru, tanpa `XFrameOptions ALLOWALL`, tanpa nilai sintetis/hardcode sebagai data aktual. **Keputusan pemilik Phase 4E (D-11…D-17):** prakiraan **3 hari** dari **BMKG gratis** (tanpa penyedia berbayar); tampilan **WITA**; ambang lama hanya **nilai awal** (satuan/periode hujan & data gelombang harus diperjelas); **tanpa impor historis Google Sheets**; sinkronisasi **terjadwal + manual** (manual oleh Super Admin; terjadwal bergantung host scheduler yang **belum terverifikasi**; Admin monitoring-only); patuhi **atribusi** BMKG/OWM; **tanpa** teknologi/Docker baru.
- Aplikasi native Android/iOS.
- **Pelacakan publik** (guest tidak dapat mengakses data laporan; masyarakat harus login untuk melihat laporan miliknya).
- Integrasi WhatsApp/SMS/email otomatis/notifikasi eksternal sebelum provider resmi ditetapkan. (**Catatan PHASE N4:** *notifikasi in-app* — bell + halaman `/notifikasi` di dalam aplikasi — **sudah diimplementasikan**; yang tetap di luar scope adalah notifikasi **eksternal** via email/SMS/WhatsApp.)
- Peta/GIS, geocoding, dan pelacakan lokasi real-time.
- Tanda tangan digital, pembayaran, atau integrasi lintas instansi yang belum ditentukan.
- Analitik prediktif/AI untuk klasifikasi laporan.
- Microservices dan API gateway terpisah.
- Granular permission manager (di luar 4 role tetap).
- SLA/eskalasi (belum difinalkan sebagai business rule).

## 4. Asumsi dan TODO yang wajib dikonfirmasi

- **Assumption A-01:** masyarakat/pelapor wajib memiliki akun dan login sebelum membuat laporan.
- **Assumption A-02:** setiap masyarakat/pelapor dapat melihat dashboard dan riwayat laporan miliknya sendiri.
- **Assumption A-03:** batas jumlah laporan harian per akun masyarakat/pelapor = **5 laporan / hari kalender** (FINAL, Prompt 5B; dapat dioverride via `app_settings.daily_report_limit`).
- **A-04 (FINAL):** **tidak ada pelacakan publik**. Guest tidak dapat mengakses data laporan; masyarakat harus login dan hanya melihat laporan miliknya.
- **A-05 (OFFICIAL):** status awal adalah `submitted`; transisi harus mengikuti transition matrix resmi — lihat schema.md §6.
- `TODO: Define requirement` — SLA, eskalasi.
  **Status resmi: DONE (finalized per Prompt 3B; lifecycle dinormalisasi LC-2 — FINAL). Penutupan manual `resolved → closed` DIHENTIKAN (LC-2): `resolved` dan `rejected` adalah hasil akhir bisnis; `closed` menjadi status legacy (histori). SLA, eskalasi: masih pending (belum difinalkan sebagai business rule; jangan mengarang angka SLA/overdue).**
- **Lampiran: FINAL (Prompt 5B)** — opsional; maks 10 file; maks 20 MB/file; format JPG/JPEG/PNG/PDF/MP4; tanpa malware scanning; file privat.
- `TODO: Define requirement` — kebijakan moderasi, laporan duplikat, spam, dan konten ilegal.
  **Catatan (PHASE 3 Bagian 2 — ulasan tertulis):** fitur ulasan tertulis sudah diimplementasikan (opsional, immutable, terlihat oleh pelapor + Operator/Super Admin). **Kebijakan moderasi ulasan tetap BELUM DITETAPKAN** — teks disimpan apa adanya tanpa moderasi. Keputusan ini sengaja dibiarkan terbuka; jangan mengarang kebijakan moderasi (rules.md §1.5).
- **Privasi/retensi: FINAL (Prompt 5B / 5B.1)** — masyarakat hanya melihat laporan miliknya; guest dilarang; retensi 5 tahun sejak `complaints.submitted_at` → permanent deletion (tanpa archive/grace period).
- `TODO: Define requirement` — **kebijakan retensi tiket Help Desk** (PROMPT TAHAP 2). Tiket bantuan **tidak** termasuk dalam purge retensi 5 tahun laporan (tiket bukan child dari `complaints`); jika laporan terkait di-purge, `help_tickets.related_complaint_id` menjadi `NULL` dan tiket tetap ada. Kebijakan retensi/penghapusan tiket bantuan sendiri **belum ditetapkan** — jangan mengarang angka retensi tiket.
- `TODO: Define requirement` — **SLA/eskalasi tiket Help Desk** (PROMPT TAHAP 2). Tidak ada SLA, target waktu respons, atau eskalasi otomatis yang diimplementasikan; jangan mengarang angka.
- `TODO: Define requirement` — **notifikasi Help Desk** (PROMPT TAHAP 2). Tiket bantuan **tidak** memicu notifikasi in-app: tabel `notifications` yang ada berjangkar wajib pada `complaint_id` (FK NOT NULL) dan `NotificationService` bersifat complaint-scoped, sehingga reuse akan mengubah scope/skema. Ini sengaja ditunda sebagai pekerjaan lanjutan (bukan diimplementasikan pada tahap ini).
- `TODO: Define requirement` — **notifikasi/percakapan lanjutan balasan ulasan** (STAGE 3). Balasan resmi Super Admin terhadap ulasan **tidak** memicu notifikasi dan **bukan** percakapan berantai (satu balasan, create-once). Jika di masa depan dibutuhkan notifikasi balasan atau thread percakapan, itu keputusan terpisah yang belum ditetapkan — jangan mengarang mekanismenya di tahap ini.

## 5. Prioritas fitur (MoSCoW)

| ID | Fitur | Prioritas | Ketergantungan |
|---|---|---|---|
| F-001 | Halaman informasi Lapor Pak Wali | Must | Design system |
| F-002 | Registrasi/login dan pengiriman pengaduan oleh pelapor | Must | Users, session, database, validasi |
| F-003 | Nomor referensi dan tanda terima | Must | F-002 |
| F-004 | Akses laporan oleh pemilik (login) — tanpa pelacakan publik | Must | F-003, kebijakan privasi |
| F-005 | Autentikasi seluruh akun | Must | Users, session |
| F-006 | Dashboard sesuai peran | Must | F-005 |
| F-007 | Daftar, pencarian, filter, detail laporan | Must | F-006 |
| F-008 | Perubahan status dan riwayat | Must | F-007 |
| F-009 | Catatan internal dan tanggapan publik | Should | F-007 |
| F-010 | Upload lampiran aman | Should | F-002, storage |
| F-011 | Manajemen kategori dan dinas/unit | Should | F-005 |
| F-012 | Manajemen akun dan role/permission | Should | F-005 |
| F-013 | Audit log tindakan penting | Must | Semua tindakan internal |
| F-014 | Notifikasi eksternal (email/SMS/WhatsApp) | Won't Have (MVP) | Provider dan kebijakan |
| F-014A | Notifikasi in-app (bell + halaman notifikasi, PHASE N4) | Should | F-006, F-008 |
| F-016 | Ulasan tertulis pada rating kepuasan (opsional, immutable, terlihat pelapor + staff) | Should | F-007 |
| F-017 | Help Desk / Pusat Bantuan (tiket bantuan + percakapan, ditangani Super Admin) | Should | F-005, F-006 |
| F-018 | Balasan resmi Super Admin terhadap ulasan masyarakat (create-once, immutable) | Should | F-016 |
| F-015 | Modul SIPHP/JDIH | **SIPHP: Should (PHASE S — disetujui 2026-10-17); JDIH: Won't Have (MVP)** | Rebuild native SuperBie; input manual Super Admin; publik `/siphp` + pintu landing; tanpa role/teknologi baru; gerbang `SIPHP_INTEGRATION_PLAN.md` |
| F-019 | **WINDY — dashboard cuaca publik (baca-saja, tanpa login) + konfigurasi/sinkronisasi manual Super Admin** | **Should (lingkup disetujui Phase 3, O-1; keputusan pemilik Phase 4E D-11…D-17; tunduk pada gerbang rilis)** | Prakiraan **3 hari** dari **BMKG gratis** (D-11); bentuk respons API (O-3); host scheduler (O-7, D-16); penyimpanan waktu (O-15); satuan/periode hujan & data gelombang (O-14); keputusan kebijakan (O-2/O-8/O-9/O-10/O-12/O-16). **Tanpa impor historis Sheets (D-15).** |

## 6. User journeys

### 6.1 Mengirim pengaduan
1. Masyarakat/pelapor membuka halaman Lapor Pak Wali.
2. Jika belum memiliki akun, masyarakat/pelapor melakukan registrasi lalu login.
3. Masyarakat/pelapor membuka form pengaduan.
4. Sistem menjelaskan jenis informasi yang diperlukan dan peringatan privasi.
5. Masyarakat/pelapor memilih kategori laporan; sistem menampilkan Dinas/Unit terkait (dari mapping kategori) sebagai informasi. Masyarakat **tidak** memilih Dinas/Unit.
6. Masyarakat/pelapor mengisi rincian, memilih **Kecamatan dan Kelurahan (wajib)**, koordinat opsional, dan menambahkan lampiran opsional.
7. Sistem menerapkan batas laporan harian yang telah dikonfigurasi (5/hari kalender).
8. Setelah laporan berhasil dikirim, sistem menampilkan tanda terima/nomor referensi. Laporan masuk ke **antrean validasi awal Super Admin** (status `submitted`); Dinas/Unit tujuan akhir ditetapkan Super Admin saat validasi.
9. Jika gagal, form mempertahankan input aman yang tidak sensitif dan memberi pesan yang jelas.

### 6.2 Dashboard dan riwayat masyarakat/pelapor
1. Masyarakat/pelapor login.
2. Sistem menampilkan dashboard milik pengguna.
3. Dashboard menampilkan ringkasan laporan milik pengguna dan akses ke riwayat laporan.
4. Masyarakat/pelapor memilih laporan untuk melihat detail dan perkembangan yang diizinkan.

### 6.3 Melihat laporan sendiri (tanpa pelacakan publik)
1. Pelapor login.
2. Sistem menampilkan hanya laporan milik pengguna (scoped ke `reporter_id`).
3. Pelapor membuka detail laporan untuk melihat status, timeline, respons publik, dan lampiran yang diizinkan.
4. **Guest tidak dapat mengakses data laporan** (diarahkan login); tidak ada endpoint pelacakan publik.

### 6.4 Operator mengelola laporan
1. Operator login.
2. Sistem memeriksa session, status akun, Dinas/Unit, dan permission.
3. Operator hanya melihat laporan pada Dinas/Unit miliknya (yang sudah lolos validasi awal).
4. Operator membuka detail, meninjau data/lampiran, lalu memperbarui status, menambah catatan internal, memberi respons publik, memperbarui lokasi, dan mengajukan penyelesaian sesuai permission. Operator **tidak** mengubah kategori, tujuan Dinas/Unit, atau penugasan.
5. Server memvalidasi transisi status dan menyimpan perubahan beserta riwayat/audit dalam transaksi.
6. UI memperlihatkan hasil berhasil atau error yang dapat ditindaklanjuti.

### 6.5 Admin melakukan monitoring
1. Admin login.
2. Admin melihat dashboard monitoring.
3. Admin memantau laporan dan audit log.
4. Admin tidak memiliki akses pengelolaan selain monitoring.

### 6.6 Super Admin mengelola website
1. Super Admin login.
2. Super Admin melihat dashboard penuh.
3. Super Admin dapat mengelola laporan (validasi awal + validasi penyelesaian), akun masyarakat, Operator, Admin, role (penugasan), kategori, Dinas/Unit + mapping, wilayah (Kecamatan/Kelurahan), konfigurasi operasional terbatas, audit log, dan keamanan.

## 7. Functional requirements

### F-001 — Halaman informasi
- Tampilkan identitas layanan, tujuan, petunjuk, tombol mulai melapor, dan kontak resmi hanya jika disediakan. (Tanpa tautan pelacakan — pelacakan publik tidak ada.)
- Jangan menampilkan angka statistik, testimoni, logo resmi, atau klaim layanan yang belum diberikan/diotorisasi.
- Acceptance criteria:
  - Given pengunjung membuka halaman, when halaman selesai dimuat, then informasi inti dan CTA tampil dengan baik di mobile dan desktop.
  - Given animasi dinonaktifkan melalui preferensi sistem, when halaman dibuka, then konten tetap tersedia tanpa gerakan non-esensial.
  - Given data/konfigurasi opsional tidak tersedia, then komponen terkait tidak menampilkan data palsu.

### F-002 — Registrasi, autentikasi, dan pengiriman pengaduan
- Masyarakat/pelapor harus login sebelum membuat laporan.
- Satu akun masyarakat/pelapor dapat melihat dashboard dan riwayat laporan miliknya sendiri.
- Terapkan batas jumlah laporan harian: **5 laporan / hari kalender** (FINAL, Prompt 5B; override via `app_settings.daily_report_limit`).
- Pada form laporan, kategori harus dapat dipilih dan setiap kategori memiliki Dinas/Unit terkait yang telah dikonfigurasi.
- Setelah kategori dipilih, UI menampilkan Dinas/Unit terkait sebagai informasi. Masyarakat **tidak** memilih Dinas/Unit; tujuan aktual ditetapkan Super Admin pada validasi awal.
- **Kecamatan + Kelurahan wajib** pada setiap laporan baru (koordinat opsional).
- Laporan masuk ke antrean validasi awal Super Admin sebelum ditangani Dinas/Unit.
- Validasi seluruh field di server; frontend hanya membantu UX.
- Tolak file berbahaya, tipe tidak diizinkan, ukuran berlebih, dan input tidak valid.
- Terapkan rate limit dan perlindungan spam yang dapat dikonfigurasi.
  - **Rate limit login (FINAL, Prompt 20):** kebijakan **TIDAK berubah** (D-5) — key `email|ip` (per akun, bukan IP saja), ambang 5 percobaan, jendela = decay default framework (60 detik), hit saat gagal / clear saat sukses, event `Lockout`. Prompt 20 hanya mengubah **semantik HTTP** kondisi terlampaui: kini **HTTP 429 (Too Many Requests)** dengan header `Retry-After` native, bukan lagi error validasi 422. Kredensial salah tetap error validasi (bukan 429).
  - **Rate limit registrasi (DECISION REQUIRED):** tidak ada kebijakan (ambang, jendela, scope key, captcha/lockout) di repository → **tidak diimplementasikan**; dicatat sebagai `DECISION REQUIRED` (Prompt 20). Jangan mengarang nilai.
  - **Rate limit lupa-password/reset (DECISION REQUIRED):** tidak ada kebijakan HTTP → **tidak diimplementasikan**; dicatat sebagai `DECISION REQUIRED` (Prompt 20). Hanya `throttle` broker per-email (60 detik, `config/auth.php`) yang berlaku. Jangan mengarang nilai.
  - **Rate limit pengiriman laporan (FINAL, Prompt 17):** 5 pengiriman / 10 menit / pengguna terautentikasi → HTTP 429 saat terlampaui. Diterapkan di server oleh named rate limiter Laravel `complaint-submission` (scope key = user id, bukan IP), pada route pengiriman laporan saja. Ini adalah proteksi keamanan/anti-abuse dan **terpisah** dari batas laporan harian; satu pengiriman dengan berapa pun lampiran tetap dihitung **satu** pengiriman. Nilai berada di `config/business_rules.php`.
- Simpan pengaduan dan metadata lampiran secara konsisten.
- Acceptance criteria:
  - Given pelapor belum login, when mencoba membuat laporan, then diarahkan untuk login/registrasi.
  - Given pelapor telah login dan belum melewati batas harian, when form valid dikirim, then satu pengaduan tersimpan (status `submitted`, antrean validasi awal) dan tanda terima dihasilkan.
  - Given kategori dipilih, then Dinas/Unit terkait (dari mapping) ditampilkan sebagai informasi (bukan dipilih pelapor).
  - Given batas laporan harian tercapai, then pengiriman baru ditolak dengan pesan yang dapat ditindaklanjuti.
  - Given validasi gagal, when form dikirim, then field error spesifik ditampilkan tanpa membuat record.
  - Given request duplikat akibat double-click/retry, then sistem mencegah pengiriman ganda sejauh idempotency/request token dapat diterapkan.

### F-002A — Dashboard dan riwayat masyarakat/pelapor
- Hanya laporan milik akun yang sedang login yang ditampilkan.
- Dashboard menampilkan ringkasan dan riwayat laporan yang relevan.
- Detail laporan menampilkan informasi yang boleh dilihat pelapor.

### F-002B — Hak akses peran internal
- Operator menangani pengelolaan operasional laporan pada Dinas/Unit miliknya, sesuai permission (status, catatan internal, respons publik, lokasi, pengajuan penyelesaian, lampiran). Operator **tidak** mengubah kategori, tujuan Dinas/Unit, atau penugasan.
- **Operator ↔ Dinas/Unit (FINAL, D-2 / Prompt 21):** satu Operator mewakili tepat satu Dinas/Unit; **Super Admin** yang menetapkan/mengubahnya melalui User Management. Operator tidak dapat self-assign atau mengubah assignment Operator lain (ditegakkan server-side, bukan sekadar UI). Operator tanpa unit tidak melihat laporan apa pun (tanpa fallback global). Deaktivasi tidak menghapus assignment.
- Admin hanya memiliki akses monitoring laporan dan audit log.
- Super Admin memiliki akses penuh terhadap fungsi website yang tersedia.
- Seluruh aksi tetap harus dilindungi authorization di server.

### F-003 — Nomor referensi
- Nomor referensi unik, tidak berurutan secara mudah ditebak, dan tidak mengandung informasi pribadi.
- **Catatan:** kolom legacy `complaints.tracking_secret_hash` **tidak lagi diisi** saat pembuatan (**DEPRECATED** — PHASE 6C Decision 4) dan **tidak pernah diverifikasi** karena tidak ada endpoint pelacakan publik. Nilai historis dipertahankan apa adanya; tidak ada penghapusan kolom.
- Acceptance criteria: kode yang berhasil dibuat unik; tidak dicatat di log.

### F-004 — Akses laporan oleh pemilik (tanpa pelacakan publik)
- **FINAL:** pelacakan publik **tidak diimplementasikan**. Guest tidak dapat mengakses data laporan.
- Masyarakat harus **login** dan hanya dapat melihat laporan **miliknya sendiri** (scoped ke `reporter_id`).
- Detail laporan menampilkan status, timeline, respons publik, dan lampiran milik pelapor; catatan internal tidak pernah ditampilkan.
- Acceptance criteria: guest diarahkan login; pelapor lain tidak dapat mengakses laporan bukan miliknya (403).

### F-005 — Autentikasi seluruh akun
- Gunakan Laravel authentication/session; password di-hash melalui mekanisme Laravel.
- Login/logout harus dilindungi CSRF; rate limit login dan respons generik pada kredensial salah.
  - Rate limit login (FINAL, Prompt 20): key `email|ip`, ambang 5, jendela 60 detik (default framework), hit saat gagal / clear saat sukses; kondisi terlampaui → **HTTP 429** dengan `Retry-After` native. Kredensial salah tetap error validasi (bukan 429). Kebijakan tidak berubah (D-5).
  - Rate limit registrasi dan lupa-password: `DECISION REQUIRED` (belum ada kebijakan; tidak diimplementasikan).
- Masyarakat/pelapor wajib login untuk membuat laporan dan mengakses dashboard/riwayat miliknya.
- Akun internal Operator, Admin, dan Super Admin menggunakan session authentication.
- Acceptance criteria: guest tidak dapat mengakses area terautentikasi; akun nonaktif tidak dapat login; logout menginvalidasi session.

### F-006/F-007 — Dashboard dan daftar laporan
- Ringkasan dashboard dihitung dari database, tidak menggunakan angka dummy.
- Daftar laporan menggunakan pagination server-side, filter allowlist, pencarian dibatasi, dan sorting allowlist.
- Empty state, loading state, error state, dan retry harus tersedia.
- Acceptance criteria: filter tidak dapat menambah kolom sort arbitrer; operator hanya melihat/menindaklanjuti data sesuai permission.

### F-008 — Status dan riwayat
- Setiap perubahan status menghasilkan riwayat: status lama/baru, aktor, waktu, dan catatan jika diberikan.
- Transisi status ditetapkan sebagai konfigurasi domain dan tidak boleh diubah hanya dari UI.
- **DONE (Prompt 3B; dinormalisasi LC-2):** daftar status dan transisi resmi — lihat schema.md §6 dan `app/Enums/ComplaintStatus.php`. Lifecycle aktif: `submitted → in_progress → {resolved | rejected}` (3 transisi); `under_review`, `waiting_for_information`, `closed` adalah status legacy (histori).
- `rejected` wajib menyertakan alasan penolakan (non-kosong) dan respons publik (non-kosong).
- `resolved` wajib menyertakan respons publik (non-kosong).
- Tidak ada reopen workflow. `resolved` dan `rejected` adalah hasil akhir bisnis (final); tidak ada transisi keluar.
- Acceptance criteria: status ilegal ditolak di server; update dan riwayat tersimpan atomik; perubahan gagal tidak meninggalkan riwayat palsu.

### F-016 — Ulasan tertulis pada rating kepuasan
- Ulasan bersifat **opsional** dan **immutable** (dibuat sekali bersama rating).
- Hanya pemilik laporan yang boleh membuat ulasan, hanya saat laporan `resolved`, dan hanya untuk laporannya sendiri.
- Teks divalidasi server-side (nullable, string, maks 5000) dan selalu di-escape saat ditampilkan.
- Terlihat oleh pelapor (detail laporan sendiri) dan Operator/Super Admin (detail laporan, read-only).
- Tidak masuk analitik rating (tetap agregat) dan tidak disalin ke metadata audit.
- Kebijakan moderasi **belum ditetapkan** (`TODO: Define requirement`).
- Acceptance criteria: kepemilikan/eligibilitas diverifikasi di server; ulasan tidak dapat diforged via body request; teks tidak bocor ke pengguna/laporan lain; catatan internal tetap tidak terlihat pelapor.

### F-009 — Catatan internal dan tanggapan publik
- Setiap catatan memiliki jenis yang jelas (`internal` atau `public_response`).
- Catatan internal **tidak pernah** ditampilkan kepada Masyarakat (di halaman detail pelapor maupun di mana pun).
- Acceptance criteria: tampilan pelapor hanya memuat jenis yang boleh dilihat; authorization diperiksa di server.

### F-010 — Lampiran
- Simpan file melalui Laravel Storage private disk atau lokasi di luar web root.
- Validasi MIME, ekstensi, ukuran, dan nama file; nama asli tidak digunakan sebagai path.
- Unduh file melalui controller terotorisasi.
- **FINAL (Prompt 5B):** opsional; maks 10 file; maks 20 MB/file; format JPG/JPEG/PNG/PDF/MP4; tanpa malware scanning; file privat tanpa akses publik langsung.
- Acceptance criteria: file tidak diakses hanya dengan menebak URL; file tidak valid ditolak; nama file aman.

### F-011/F-012 — Pengaturan, akun, dan role
- Super Admin dapat mengelola kategori dan Dinas/Unit terkait, akun masyarakat, Operator, dan Admin, serta penugasan 4 role tetap dan konfigurasi operasional terbatas (saat ini `daily_report_limit`).
- Admin hanya dapat melakukan monitoring laporan dan audit log; tidak memiliki akses pengelolaan.
- Operator mengelola laporan pada Dinas/Unit miliknya sesuai permission; **tidak** mengubah kategori atau penugasan.
- Masyarakat/pelapor hanya dapat mengelola akun dan laporan miliknya sendiri.
- Tidak boleh ada self-escalation privilege.
- Granular permission manager **belum ada** (di luar scope); hanya 4 role tetap.
- Acceptance criteria: seluruh tindakan sensitif dicatat dalam audit log dan akses ditolak jika role/permission tidak sesuai.

### F-013 — Audit log
- Catat aktor, aksi, jenis/ID entitas, waktu, dan metadata minimal.
- Jangan mencatat password, session ID, tracking secret, token, atau isi laporan sensitif secara berlebihan.
- Audit log tidak boleh diedit melalui UI normal.

### F-014 — Dashboard, KPI, dan cache (LC-2C)
- Seluruh angka dashboard berasal dari database (tanpa angka dummy) dan mengikuti lifecycle LC-2 (lihat §8).
- **KPI workflow aktif** menghitung status aktif `submitted` + `in_progress` saja; status legacy dan hasil akhir **tidak** dihitung sebagai aktif.
- **Statistik hasil akhir sukses** menghitung `resolved` (bukan `closed`); **statistik penolakan** menghitung `rejected` (tidak digabung dengan legacy).
- Status legacy dapat tetap tampil pada visualisasi yang mempertahankan semua status, dengan label **"historis"** yang jelas; legacy tidak pernah dibaca sebagai tahap aktif.
- Scope per peran: Masyarakat = hanya laporan miliknya; Operator = hanya Dinas/Unit-nya (via `complaints.dinas_unit_id → DinasUnit::operator()`, bukan `assigned_to`); Admin = monitoring global; Super Admin = global. Tidak ada query yang melewati scope ini.
- Cache dashboard: TTL 60 detik; scope tetap terpisah di kunci (`dashboard:citizen:{id}`, `dashboard:operator:{scope}:{version}`, `dashboard:admin`, `dashboard:superadmin`). Invalidasi melalui observer (complaint, user, kategori, dinas/unit, rating) — tanpa event/queue baru.
- Acceptance criteria: dashboard semua peran render tanpa error meski terdapat data legacy; angka konsisten antara cache-hit dan cache-miss; scope/otorisasi tidak berubah.

## 8. Official complaint lifecycle (LC-2 — FINAL)

**Status aktif (lifecycle):** `submitted`, `in_progress`.
**Hasil akhir bisnis:** `resolved`, `rejected`.
**Legacy (histori saja, tidak dapat dicapai oleh transisi aktif):** `under_review`, `waiting_for_information`, `closed`.

Tujuh nilai enum/DB tetap ada demi kompatibilitas histori dan audit; hanya **empat** yang termasuk lifecycle aktif. Tidak ada migrasi atau mass update untuk mengubah data legacy.

| Code | Label Publik | Kategori | Keterangan |
|---|---|---|---|
| `submitted` | Diajukan | **Aktif** | Status awal setiap laporan baru |
| `in_progress` | Sedang Diproses | **Aktif** | Laporan sedang ditangani (ditetapkan oleh validasi awal Super Admin) |
| `resolved` | Selesai | **Hasil akhir bisnis** | Masalah telah ditangani; respons publik wajib |
| `rejected` | Ditolak | **Hasil akhir bisnis** | Laporan ditolak; alasan penolakan + respons publik wajib |
| `under_review` | Sedang Ditinjau | **Legacy** | Histori saja — tidak dapat dicapai oleh transisi aktif |
| `waiting_for_information` | Menunggu Informasi | **Legacy** | Histori saja — tidak dapat dicapai oleh transisi aktif |
| `closed` | Ditutup | **Legacy** | Histori saja — penutupan manual dihentikan |

**Transisi resmi: 3 edge (matrix lengkap di schema.md §6):**
`submitted → in_progress`; `in_progress → resolved`; `in_progress → rejected`.

**Dua jalur utama:** berhasil `submitted → in_progress → resolved`; ditolak `submitted → in_progress → rejected`.

**Kewenangan:** Operator dan Super Admin (YA), Admin dan Masyarakat (TIDAK). **Operator tidak dapat menyetel `resolved` secara langsung** — validasi penyelesaian oleh Super Admin yang menjalankan `in_progress → resolved`. Tidak ada penutupan otomatis oleh sistem. Tidak ada reopen.

**Perlakuan laporan historis (legacy):**
- Baris berstatus legacy **dipertahankan** (tanpa konversi/migrasi) dan tetap dapat dibaca sesuai permission.
- Status legacy **terkunci untuk perubahan status** (tidak ada transisi masuk/keluar) — tetapi **bukan** kunci mutasi menyeluruh: tindakan non-status (catatan internal, respons publik, perubahan lokasi) tetap mengikuti permission dan aturan aplikasi yang berlaku.
- Audit log dan status history historis **tidak** ditulis ulang agar menyerupai workflow baru.

**Rating:** hanya dapat diberikan saat status `resolved` (owner-only, 1–5, sekali). `closed` dan status lain tidak memenuhi syarat.

**Ulasan tertulis (review) — PHASE 3 Bagian 2 (additive):**
- **Opsional (nullable):** pelapor boleh menyertakan ulasan tertulis saat memberikan rating. Ulasan kosong/spasi diperlakukan sebagai tidak ada ulasan (`NULL`). Rating tanpa ulasan tetap sah.
- **Melekat pada rating yang sama:** ulasan disimpan pada baris `complaint_ratings` yang sama (`review_text`), sehingga otomatis mewarisi seluruh aturan rating — owner-only, hanya saat status `resolved`, dibuat **sekali**, dan **immutable** (tidak ada endpoint ubah/hapus ulasan).
- **Tidak dapat diforged:** `complaint_id`/`user_id` tidak pernah dibaca dari body request; kepemilikan berasal dari `complaints.reporter_id` + route model binding. Ulasan tidak dapat dibuat untuk pelapor lain atau laporan lain, dan tidak dapat dibuat pada laporan non-`resolved`.
- **Validasi server-side:** `review_text` nullable, string, maks 5000 karakter (via `StoreComplaintRatingRequest`). Teks disimpan apa adanya dan **selalu di-escape** oleh Blade (HTML tidak dipercaya).
- **Visibilitas:** pelapor (halaman detail miliknya, read-only) + Operator/Super Admin (halaman detail laporan, **read-only**). Admin tidak memiliki halaman detail laporan, jadi ulasan tidak terekspos ke Admin.
- **Audit:** event `complaint.rating_submitted` menambahkan metadata `has_review` (boolean) saja — **teks ulasan tidak pernah** disalin ke metadata audit.
- **Analitik:** ulasan **tidak** masuk Rating Analytics (tetap aggregate-only, D-4.8) — tidak ada record individual/drill-down/ekspor ulasan.
- **Moderasi:** **BELUM DITETAPKAN** — `TODO: Define requirement` (lihat §4). Tidak ada logika moderasi yang diimplementasikan; teks disimpan apa adanya.
- **Balasan resmi (STAGE 3):** Super Admin dapat memberikan **satu balasan resmi** terhadap ulasan tertulis masyarakat (lihat F-018). Balasan **bukan** percakapan berantai dan **bukan** balasan masyarakat; masyarakat hanya melihatnya (read-only).

### F-018 — Balasan resmi Super Admin terhadap ulasan masyarakat (STAGE 3)
- **Tujuan & batasan:** Super Admin dapat memberikan **satu balasan resmi** atas ulasan tertulis masyarakat pada sebuah laporan. Balasan bersifat **create-once** dan **immutable** (tidak ada fitur ubah/hapus). Bukan percakapan berantai, bukan notifikasi, bukan moderasi.
- **Penyimpanan:** kolom baru `complaint_ratings.admin_response` (**TEXT nullable**, migration additive + reversible `2025_01_11_100021_...`). Balasan menumpang pada baris rating yang sama (1:1), sehingga otomatis ikut terhapus saat rating/laporan di-purge retensi. **Tidak** ada tabel/model baru. `NULL` = belum ada balasan.
- **Tidak mengubah apa pun selain balasan:** `rating` dan `review_text` **tidak pernah** diubah; status laporan, lifecycle, KPI, dan Rating Analytics **tidak** terpengaruh (analytics tetap aggregate-only).
- **Prasyarat balasan:** (1) rating ada; (2) rating memiliki ulasan tertulis non-kosong; (3) balasan belum pernah dibuat. Jika tidak ada ulasan teks, form balasan **tidak** ditampilkan dan balasan ditolak server-side.
- **Hak akses:** **hanya Super Admin** (`role:super_admin`). Admin, Operator, dan Masyarakat **ditolak server-side** (middleware + Form Request `authorize()`), termasuk pada request HTTP langsung. Admin tidak memiliki halaman detail laporan; Operator hanya melihat rating/ulasan **read-only** (tanpa kontrol balasan).
- **Anti-forgery / IDOR:** laporan berasal dari route model binding; rating di-resolve dari laporan tersebut server-side. `rating_id`/`complaint_id`/`user_id` **tidak pernah** dibaca dari request. Identitas pembalas berasal dari user terautentikasi.
- **Validasi:** `admin_response` wajib, string, maks 5000 karakter, di-trim (kosong/spasi ditolak). Teks disimpan apa adanya dan **selalu di-escape** oleh Blade.
- **Create-once (race-safe):** penulisan memakai `UPDATE ... WHERE admin_response IS NULL` di dalam transaksi (row-locked) — request kedua menghasilkan 0 baris terpengaruh dan ditolak, sehingga balasan pertama tidak pernah tertimpa.
- **Audit:** event `complaint.review_responded` dengan metadata `reference_code`, `complaint_id`, `rating_id`, dan `has_response: true` — **teks ulasan dan balasan tidak pernah** disalin ke metadata audit.
- **Route:** `POST /super-admin/balasan-ulasan/{complaint}` (`super-admin.review-response.store`), **di luar** prefix read-only `/super-admin/laporan` agar invarian read-only monitoring tetap utuh.
- **Visibilitas:** pelapor melihat balasan (read-only) pada detail laporan miliknya sendiri; Super Admin melihat form (sebelum balasan) atau tampilan read-only (sesudah balasan). Tidak ada placeholder balasan palsu bila belum ada.
- **Acceptance criteria:** otorisasi server-side; create-once & immutable; rating/ulasan/status/KPI/analytics tidak berubah; IDOR ditutup; escaping Blade; audit tanpa isi teks; Admin/Operator/Masyarakat ditolak.

### F-017 — Help Desk / Pusat Bantuan
- **Tujuan & batasan:** sistem **tiket bantuan** terpisah dari laporan masyarakat. Cakupan: kendala registrasi/login/akun, penggunaan aplikasi, pengiriman laporan, status/pelacakan laporan, dan kendala teknis lain. Help Desk **tidak menggantikan, mengubah, atau menjadi jalur alternatif** untuk memproses status laporan utama — referensi laporan hanya konteks, dan tiket **tidak pernah** mengubah status/kategori/routing/catatan laporan.
- **Hak akses per role:**
  - **Masyarakat:** membuat tiket, membaca tiket miliknya, mengirim pesan pada tiket miliknya yang masih dapat dibalas, membuka kembali tiket `resolved`, melihat status/waktu.
  - **Super Admin:** melihat & menangani **seluruh** tiket, mengirim balasan resmi, mengubah status sesuai transisi, melihat riwayat percakapan.
  - **Operator:** **tidak** menangani Help Desk pada tahap ini (hak akses laporan tidak berubah).
  - **Admin:** **tidak** memiliki akses Help Desk (monitoring saja); tidak dapat membalas/mengubah tiket. Penolakan **server-side** (middleware `role:super_admin`), bukan sekadar menyembunyikan menu.
- **Model tiket & pesan:** `help_tickets` (nomor referensi unik server-generated `HD-YYYYMMDD-XXXX`, `user_id` pemilik, `subject`, `category`, `description`, `related_complaint_id` opsional, `status`, `resolved_at`, timestamps) dan `help_ticket_messages` (append-only: `help_ticket_id`, `user_id` pengirim, `body`, timestamps). Tidak ada endpoint ubah/hapus pesan.
- **Status tiket & transisi (eksplisit):** `open`, `in_progress`, `waiting_for_user`, `resolved`, `closed`.
  - Matrix: `open → in_progress|waiting_for_user|resolved|closed`; `in_progress → waiting_for_user|resolved|closed`; `waiting_for_user → in_progress|resolved|closed`; `resolved → in_progress (reopen)|closed`; `closed → (terminal)`.
  - **Balasan biasa** hanya pada `open`/`in_progress`/`waiting_for_user`. `resolved` **tidak** menerima balasan biasa — hanya **buka kembali** (transisi tervalidasi `resolved → in_progress`, pesan wajib). `closed` **terminal** (tidak menerima pesan/buka kembali) — tiket tertutup tidak pernah menerima pesan diam-diam.
  - **Kewenangan:** Super Admin menjalankan seluruh transisi melalui aksi status eksplisit; masyarakat hanya menjalankan edge **reopen** `resolved → in_progress` (dengan pesan wajib).
- **Aturan referensi laporan:** `related_complaint_id` opsional; **wajib milik pembuat tiket** (divalidasi server-side terhadap `complaints.reporter_id`). Referensi laporan milik orang lain **ditolak tanpa mengungkapkan keberadaannya** (pesan identik untuk "tidak ada" dan "bukan milik Anda"). Membuat/membalas tiket **tidak** mengubah status laporan. Halaman Help Desk **tidak** menampilkan catatan internal atau data internal laporan (hanya nomor referensi/judul/kategori untuk konteks).
- **Aturan akses percakapan:** pesan masyarakat hanya ke tiket miliknya; balasan penangan hanya oleh Super Admin. Pengirim **selalu** ditentukan dari pengguna terautentikasi (`user_id`/`sender_id` tidak pernah dibaca dari request), sehingga masyarakat tidak dapat menyamar sebagai Super Admin.
- **Validasi & keamanan:** seluruh input divalidasi server-side (kategori allowlist; judul maks 180; deskripsi/pesan maks 5000; body kosong/spasi ditolak & di-trim); escaping Blade (XSS tersimpan dicegah); CSRF; transaksi database untuk operasi multi-tulis; audit metadata hanya memuat aksi/aktor/referensi/panjang — **isi pesan/deskripsi tidak pernah** disalin ke audit. Endpoint tulis dilindungi named rate limiter `help-desk-write` (anti-abuse, keyed pada user id).
- **Fitur yang belum dibuat:** moderasi konten, lampiran pada tiket, notifikasi tiket, SLA/eskalasi, penutupan/penghapusan tiket otomatis, dan kebijakan retensi tiket (lihat §4 `TODO: Define requirement`).
- **Acceptance criteria:** otorisasi role diverifikasi server-side; akses lintas-pemilik/IDOR ditolak (404/403); status ilegal ditolak; tiket tidak mengubah laporan; pesan di-escape; audit tidak memuat isi pesan; Admin/Operator ditolak di server.

**Pembatasan "Perbarui Status" selama validasi penyelesaian — PHASE N6A / F-1 (FINAL):**
- Setelah Operator menekan **"Ajukan Penyelesaian"**, complaint berada pada `review_state = pending_completion_validation` (status tetap `in_progress`). Selama kondisi ini, **Operator tidak dapat menggunakan "Perbarui Status"** — tidak boleh mengubah status apa pun (termasuk `rejected`), ditegakkan **server-side sebelum mutasi apa pun** (UI hanya menyembunyikan opsi), dan tidak mengubah `review_state`. (Status `waiting_for_information` dan `closed` adalah legacy dan tidak lagi menjadi opsi aktif.)
- Opsi "Perbarui Status" **kembali tersedia** setelah Super Admin mengembalikan pengajuan (`review_state = completion_returned_to_operator`).
- **Super Admin tidak terpengaruh** (G7 tetap berlaku pada endpoint yang sama). Tidak ada status baru dan tidak ada transisi baru.

**Antrian Validasi Penyelesaian — PHASE N6C / N6B-1 (FINAL, Opsi A):**
- Completion Validation Queue (`super-admin.completion-validation.index`) **hanya** menampilkan complaint dengan `status = in_progress` **DAN** `review_state = pending_completion_validation`. Status lain (`resolved`, `rejected`, `closed`, dst.) **dikecualikan** meski `review_state` masih `pending_completion_validation`. Ini adalah aturan **baca/query saja** — tidak pernah mengubah `review_state`.
- **Interaksi G7 (disengaja):** jika Super Admin melakukan direct resolve (G7, `in_progress → resolved` melalui `operator.complaint.update-status`), G7 **tidak** mengubah `review_state`; complaint tersebut **tidak lagi ditampilkan** dalam Completion Validation Queue karena statusnya bukan `in_progress`. Approve/Return tetap punya guard server-side sendiri (`status = in_progress` + `review_state = pending_completion_validation`, baris terkunci, stale-state re-check) — filter antrian **bukan** batas otorisasi.

**Aksi Validasi Penyelesaian memerlukan KEDUA kondisi — PHASE N6E / N6D-1 (FINAL):**
- **Approve** dan **Return** hanya dapat dilakukan dari `status = in_progress` **DAN** `review_state = pending_completion_validation`. Guard dijalankan **sebelum mutasi apa pun** dan di-*re-assert* pada baris **terkunci** di dalam transaksi.
- Baris `pending_completion_validation` yang statusnya **bukan** `in_progress` (mis. hasil direct resolve G7 `resolved + pending_completion_validation`, atau baris `rejected`/`closed`) **ditolak**: tidak ada mutasi, audit, `completion_return_reason`, penghapusan `completion_submitted_at`, perubahan `review_state`, maupun notifikasi M6/M7.
- Halaman detail validasi penyelesaian tetap dapat diakses untuk baris *stale* tersebut (agar deep-link M5 tidak 404) tetapi ditampilkan **read-only** (tanpa aksi Approve/Return, dengan keterangan "Tidak Lagi Dapat Divalidasi").

**Rating Analytics — PHASE 4 Bagian 2 (D-4.1..D-4.15, FROZEN):**
- **Penonton:** Admin = global; Super Admin = global; Operator = HANYA Dinas/Unit sendiri; Masyarakat = ditolak (403); Guest = redirect login. Otorisasi **server-side** (bukan sekadar menu disembunyikan).
- **Scope Operator:** bersumber dari `users.dinas_unit_id` (bukan `complaints.assigned_to`). Parameter `dinas_unit_id` dari browser diabaikan. Operator tanpa unit = kosong (tanpa fallback global). Operator tidak pernah melihat perbandingan antar-Dinas.
- **Metric:** jumlah rating; rata-rata rating; distribusi 1–5; `resolved_without_rating`; rating per Dinas/Unit; rating per kategori; tren rating per bulan; tingkat kepuasan. Tidak ada metric tambahan (tanpa ranking/leaderboard/score/SLA/sentimen/AI).
- **Tingkat kepuasan:** rating 1–3 = tidak puas, 4–5 = puas; `COUNT(rating 4,5) / COUNT(seluruh rating) × 100%`. Laporan tanpa rating **tidak** masuk denominator.
- **Resolved tanpa rating (D-4.5, OPTION A, FINAL):** agregat **current-state** — complaint yang status **SAAT INI** = `resolved` dan **belum** punya baris `complaint_ratings`. **Tidak** mengikuti filter periode rating (tidak memakai `complaint_ratings.created_at` — memang tidak ada baris rating — dan tidak memakai `complaints.resolved_at`); mengubah periode rating **tidak** mengubah nilai ini. Tetap mengikuti filter dimensi yang relevan (Dinas/Unit, Kategori, Kecamatan, Kelurahan) + scope Operator. Bukan rating negatif, bukan sinyal ketidakpuasan, dan **tidak** masuk denominator satisfaction rate; `rejected`/`closed` tidak dihitung.
- **Basis waktu:** seluruh metric rating memakai `complaint_ratings.created_at`. Preset periode: 6 bulan, 12 bulan (bulan kalender), tahun berjalan, dan rentang kustom (validasi `start <= end`, tidak unbounded).
- **Filter:** Periode, Dinas/Unit, Kategori, Kecamatan, Kelurahan — semuanya benar-benar memengaruhi agregat rating. Kelurahan dibatasi oleh Kecamatan terpilih (validasi server-side).
- **Privasi:** agregat saja. Tidak ada nama/NIK/telepon/alamat pelapor, record rating individual, drill-down, halaman detail, atau ekspor data individual. Tidak ada endpoint rating individual.
- **Resolved → closed:** rating yang sudah valid tetap dihitung setelah laporan menjadi `closed`.
- **Retensi:** data live saja — tanpa snapshot/arsip/agregat permanen; rating ikut terhapus pada purge 5 tahun.
- **Empty state:** memakai `x-dashboard.empty-state`; tidak pernah menampilkan 0% seolah-olah masyarakat tidak puas.
- **Tanpa ekspor:** tidak ada CSV/Excel/PDF/tombol/endpoint ekspor.
- **Cache:** TTL 60s mengikuti konvensi dashboard. Pembuatan rating memicu invalidasi cache analitik rating (observer); tidak mengandalkan TTL saja.
- **Host:** ditampilkan pada dashboard Admin, Super Admin, dan Operator yang sudah ada — tanpa route baru, tanpa halaman baru, tanpa migration.

### F-020 — Modul SIPHP: informasi harga pasar + direktori retail (PHASE S)
- **Tujuan:** menyajikan **informasi harga barang per pasar** dan **direktori retail** Kota Parepare di dalam portal SuperBie; menggantikan fungsi situs SIPHP lama di dalam portal.
- **Keputusan owner (D-S1…D-S7):** rebuild **native** (D-S1); **input manual** oleh **Super Admin** (D-S2/D-S5); tampilan **publik baca-saja** di `/siphp` tanpa login + pintu masuk di landing (D-S3); **direktori retail termasuk** (D-S4a); **tidak ada kanal pengaduan kedua** — keluhan pasar lewat Lapor Pak Wali (D-S4c); harga disimpan **per tanggal** (riwayat) dan halaman publik menampilkan harga terbaru + tanggalnya (D-S6).
- **Cakupan data:** pasar (Lakessi, Senggol, Labukkang, Sumpang Minangae, Wekke'e), kategori barang (pokok/subsidi/penting), barang (diinput staf), harga, retail.
- **Non-goals:** tanpa scraping/iframe/sinkronisasi dengan `siphp-parepare.com`; tanpa role/teknologi baru; tanpa GIS; JDIH tetap tidak dibangun.
- **Aturan tampilan:** jangan tampilkan angka default/sintetis; keadaan kosong = "Data harga belum tersedia"; tampilkan tanggal pembaruan harga.
- **Acceptance criteria:** publik dapat membaca harga/retail tanpa login; hanya Super Admin yang dapat menulis (server-side `role:super_admin` + Form Request `authorize()`); harga tersimpan sebagai riwayat unik per (pasar × barang × tanggal); audit tercatat; tanpa data sintetis; gerbang rilis `SIPHP_INTEGRATION_PLAN.md` diikuti.

## 9. Non-functional requirements

- **Security:** CSRF, authorization server-side, input validation, output escaping, rate limiting, secure session/cookie, private file storage, audit.
- **Performance:** pagination server-side; hindari N+1; eager loading; index berdasarkan query nyata; optimasi aset build.
- **Availability:** MVP monolith dengan backup terjadwal dan prosedur restore yang diuji. **Status (Prompt 23): backup dan restore BELUM diimplementasikan** — hanya *capability* (perintah `mysqldump`/`mysql`) yang tersedia; kebijakan backup, **RPO/RTO**, dan prosedur restore adalah `DECISION REQUIRED`. Lihat `PRODUCTION_OPERATIONS.md` §7.
- **Accessibility:** navigasi keyboard, label input, focus visible, semantic HTML, kontras memadai, pesan error terhubung ke field.
- **Responsive:** mulai dari mobile, lalu tablet/desktop.
- **Maintainability:** modular monolith; controller tipis; domain logic pada action/service yang relevan; test automated.
- **Localization:** Bahasa Indonesia sebagai bahasa UI MVP; teks UI dikumpulkan di satu tempat bila praktis.
- **Animation:** seluruh komponen UI yang terlihat/interaktif mempunyai state/transisi animasi yang konsisten. Gerakan tidak boleh menghalangi input, memperlambat alur pengaduan, atau menyebabkan motion sickness. `prefers-reduced-motion` wajib dihormati.

## 10. Metrics keberhasilan MVP

Gunakan metrik operasional yang dapat diukur setelah implementasi, bukan angka target yang dikarang:
- Jumlah pengaduan berhasil terkirim.
- Rasio pengiriman gagal karena validasi/server.
- Median waktu respons halaman dan endpoint penting.
- Jumlah laporan yang statusnya diperbarui.
- Jumlah error 5xx dan percobaan spam yang dibatasi.
- Hasil uji aksesibilitas, keamanan, dan restore backup.

`TODO: Define requirement` — target numerik, periode pengukuran, dan pemilik metrik.

## 11. Out-of-scope dan kriteria rilis
Rilis MVP tidak dianggap selesai sebelum pengujian alur utama, authorization, upload, tracking privacy, status history, responsive, reduced-motion, backup/restore, dan dokumentasi setup lulus. Jangan mengklaim “production-ready” hanya karena aplikasi dapat dijalankan secara lokal. **Catatan (Prompt 23):** backup/restore, CI, monitoring, dan target deployment **belum selesai** (lihat `PRODUCTION_OPERATIONS.md`); karena itu status proyek tetap **NOT PRODUCTION READY**.
