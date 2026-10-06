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

**Lapor Pak Wali** adalah modul pengaduan masyarakat berbasis web yang memungkinkan masyarakat menyampaikan laporan/keluhan, memperoleh nomor pelacakan, dan melihat perkembangan penanganan sesuai informasi yang diizinkan. Operator berwenang menggunakan panel internal untuk memeriksa, mengklasifikasikan, menugaskan, memperbarui status, dan memberi tanggapan terhadap laporan.

Modul ini merupakan bagian dari portal SuperBie yang direncanakan menghubungkan empat layanan: SIPHP, Parepare Weather Intelligence Dashboard, JDIH Kota Parepare, dan Command Center – Lapor Pak Wali. Pada MVP ini, hanya Lapor Pak Wali yang diimplementasikan. Tiga layanan lainnya tidak boleh dibuat sebagai fitur fungsional dalam fase ini.

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
| Masyarakat / Pelapor | Membuat akun, login, mengirim laporan, melihat dashboard dan riwayat laporan, melacak laporan | Panel masyarakat/pelapor terautentikasi |
| Operator | Meninjau laporan, mengubah kategori, menugaskan laporan, memperbarui status, menambah catatan internal, memberi respons publik, mengakses lampiran | Panel operator |
| Admin | Monitoring laporan dan audit log | Panel monitoring, tanpa akses pengelolaan |
| Super Admin | Mengelola seluruh bagian website sesuai role/permission, termasuk laporan, akun, kategori, konfigurasi, audit dan keamanan | Akses penuh |

> Catatan: peran operasional penanganan laporan dipegang oleh **Operator**. Peran historis `petugas` tidak lagi menjadi active role; baris data lama yang masih merujuk `petugas` dipertahankan apa adanya sampai ada keputusan bisnis khusus.

**Catatan privasi:** kode pelacakan bukan pengganti autentikasi untuk data sensitif. Halaman publik hanya boleh menampilkan informasi minimum yang disetujui.

## 3. Scope

### In scope — MVP
- Landing page Lapor Pak Wali dan penjelasan cara melapor.
- Registrasi, login, logout, dan reset password untuk masyarakat/pelapor.
- Dashboard masyarakat/pelapor dan riwayat laporan milik sendiri.
- Formulir pengaduan untuk masyarakat/pelapor yang sudah login.
- Pemilihan kategori laporan pada saat pengaduan.
- Pengarahan laporan ke dinas/unit terkait berdasarkan kategori yang dipilih.
- Validasi sisi server dan sisi antarmuka.
- Batas jumlah laporan harian untuk masyarakat/pelapor; nilai batas: `TODO: Define requirement`.
- Penyimpanan pengaduan dengan nomor referensi/kode pelacakan yang tidak mudah ditebak.
- Unggah lampiran sesuai batas yang dikonfigurasi.
- Halaman tanda terima setelah pengaduan berhasil dikirim.
- Pelacakan laporan menggunakan kode pelacakan dan verifikasi tambahan yang ditentukan kebijakan.
- Login, logout, dan reset password untuk seluruh akun internal (reset melalui email hanya jika layanan email dikonfigurasi).
- Dashboard panel Operator, Admin, dan Super Admin sesuai hak akses.
- Daftar pengaduan dengan pencarian, filter, pagination, dan pengurutan yang dibatasi sesuai peran.
- Detail pengaduan internal.
- Perubahan status dengan validasi transisi.
- Catatan internal dan tanggapan publik, dibedakan secara tegas.
- Penugasan laporan kepada Operator.
- Pengelolaan kategori laporan beserta dinas/unit terkait oleh Super Admin.
- Pengelolaan akun masyarakat, Operator, dan Admin oleh Super Admin.
- **Penetapan Dinas/Unit Operator (FINAL, D-2 / Prompt 21):** satu Operator mewakili **tepat satu** Dinas/Unit. **Super Admin** menetapkan/mengubah Dinas/Unit Operator melalui User Management; Operator tidak dapat menetapkan Dinas/Unit sendiri maupun Operator lain. Ditegakkan di server (middleware `role:super_admin` + Form Request `authorize()` + guard model layer). Operator tanpa unit tetap valid namun tidak melihat laporan apa pun (tanpa fallback global). Deaktivasi tidak menghapus assignment. D-2 tidak menambahkan batasan lain.
- Pengelolaan role dan permission oleh Super Admin.
- Pengelolaan konfigurasi operasional oleh Super Admin.
- Monitoring laporan dan audit log oleh Admin.
- Audit log untuk tindakan sensitif.
- Animasi UI konsisten untuk setiap komponen visual/interaktif, dengan dukungan reduced-motion.
- Responsive layout, aksesibilitas keyboard, empty/loading/error/success states.

### Out of scope pada MVP
- SIPHP/informasi harga pasar.
- Dashboard cuaca.
- JDIH/dokumen hukum.
- Aplikasi native Android/iOS.
- Integrasi WhatsApp/SMS/email otomatis sebelum provider resmi ditetapkan.
- Peta/GIS, geocoding, dan pelacakan lokasi real-time.
- Tanda tangan digital, pembayaran, atau integrasi lintas instansi yang belum ditentukan.
- Analitik prediktif/AI untuk klasifikasi laporan.
- Microservices dan API gateway terpisah.

## 4. Asumsi dan TODO yang wajib dikonfirmasi

- **Assumption A-01:** masyarakat/pelapor wajib memiliki akun dan login sebelum membuat laporan.
- **Assumption A-02:** setiap masyarakat/pelapor dapat melihat dashboard dan riwayat laporan miliknya sendiri.
- **Assumption A-03:** terdapat batas jumlah laporan harian per akun masyarakat/pelapor; nilai batas: `TODO: Define requirement`.
- **Assumption A-04:** pelacakan publik menampilkan status umum dan waktu pembaruan, bukan catatan internal, data internal pribadi, atau informasi sensitif.
- **A-05 (OFFICIAL):** status awal adalah `submitted`; transisi harus mengikuti transition matrix resmi — lihat schema.md §6.
- `TODO: Define requirement` — status resmi, SLA, eskalasi, dan penutupan laporan.
  **Status resmi: DONE (finalized per Prompt 3B). SLA, eskalasi: masih pending.**
- `TODO: Define requirement` — ukuran/jumlah/format lampiran dan retensi file.
- `TODO: Define requirement` — verifikasi pelapor untuk tracking (misalnya kode + OTP atau data verifikasi lain).
- `TODO: Define requirement` — mekanisme notifikasi dan template pesan.
- `TODO: Define requirement` — kebijakan moderasi, laporan duplikat, spam, dan konten ilegal.
- `TODO: Define requirement` — ketentuan privasi, retensi, dan penghapusan sesuai kebijakan instansi.

## 5. Prioritas fitur (MoSCoW)

| ID | Fitur | Prioritas | Ketergantungan |
|---|---|---|---|
| F-001 | Halaman informasi Lapor Pak Wali | Must | Design system |
| F-002 | Registrasi/login dan pengiriman pengaduan oleh pelapor | Must | Users, session, database, validasi |
| F-003 | Nomor referensi dan tanda terima | Must | F-002 |
| F-004 | Pelacakan status publik yang dibatasi | Must | F-003, kebijakan privasi |
| F-005 | Autentikasi seluruh akun | Must | Users, session |
| F-006 | Dashboard sesuai peran | Must | F-005 |
| F-007 | Daftar, pencarian, filter, detail laporan | Must | F-006 |
| F-008 | Perubahan status dan riwayat | Must | F-007 |
| F-009 | Catatan internal dan tanggapan publik | Should | F-007 |
| F-010 | Upload lampiran aman | Should | F-002, storage |
| F-011 | Manajemen kategori dan dinas/unit | Should | F-005 |
| F-012 | Manajemen akun dan role/permission | Should | F-005 |
| F-013 | Audit log tindakan penting | Must | Semua tindakan internal |
| F-014 | Notifikasi eksternal | Won't Have (MVP) | Provider dan kebijakan |
| F-015 | Modul SIPHP/cuaca/JDIH | Won't Have (MVP) | Fase berikutnya |

## 6. User journeys

### 6.1 Mengirim pengaduan
1. Masyarakat/pelapor membuka halaman Lapor Pak Wali.
2. Jika belum memiliki akun, masyarakat/pelapor melakukan registrasi lalu login.
3. Masyarakat/pelapor membuka form pengaduan.
4. Sistem menjelaskan jenis informasi yang diperlukan dan peringatan privasi.
5. Masyarakat/pelapor memilih kategori laporan; sistem menampilkan dinas/unit terkait berdasarkan kategori.
6. Masyarakat/pelapor mengisi rincian dan menambahkan lampiran opsional.
7. Sistem menerapkan batas laporan harian yang telah dikonfigurasi.
8. Setelah laporan berhasil dikirim, sistem menampilkan tanda terima, nomor referensi, dan informasi dinas/unit tujuan.
9. Jika gagal, form mempertahankan input aman yang tidak sensitif dan memberi pesan yang jelas.

### 6.2 Dashboard dan riwayat masyarakat/pelapor
1. Masyarakat/pelapor login.
2. Sistem menampilkan dashboard milik pengguna.
3. Dashboard menampilkan ringkasan laporan milik pengguna dan akses ke riwayat laporan.
4. Masyarakat/pelapor memilih laporan untuk melihat detail dan perkembangan yang diizinkan.

### 6.3 Melacak laporan
1. Pelapor membuka halaman pelacakan.
2. Memasukkan kode referensi dan faktor verifikasi sesuai kebijakan.
3. Server mencari laporan menggunakan nilai yang telah dinormalisasi dan memverifikasi hash secret bila digunakan.
4. Sistem menampilkan hanya data publik yang diizinkan.
5. Jika tidak cocok, tampilkan pesan generik yang tidak membocorkan keberadaan laporan.

### 6.4 Operator mengelola laporan
1. Operator login.
2. Sistem memeriksa session, status akun, dan permission.
3. Operator melihat daftar laporan yang dapat diakses.
4. Operator membuka detail, meninjau data/lampiran, lalu mengubah kategori, menugaskan laporan, memperbarui status, menambah catatan internal, memberi respons publik, dan mengakses lampiran sesuai permission.
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
3. Super Admin dapat mengelola laporan, akun masyarakat, Operator, Admin, role/permission, kategori, konfigurasi operasional, audit log, dan keamanan.

## 7. Functional requirements

### F-001 — Halaman informasi
- Tampilkan identitas layanan, tujuan, petunjuk, tombol mulai melapor, tautan pelacakan, dan kontak resmi hanya jika disediakan.
- Jangan menampilkan angka statistik, testimoni, logo resmi, atau klaim layanan yang belum diberikan/diotorisasi.
- Acceptance criteria:
  - Given pengunjung membuka halaman, when halaman selesai dimuat, then informasi inti dan CTA tampil dengan baik di mobile dan desktop.
  - Given animasi dinonaktifkan melalui preferensi sistem, when halaman dibuka, then konten tetap tersedia tanpa gerakan non-esensial.
  - Given data/konfigurasi opsional tidak tersedia, then komponen terkait tidak menampilkan data palsu.

### F-002 — Registrasi, autentikasi, dan pengiriman pengaduan
- Masyarakat/pelapor harus login sebelum membuat laporan.
- Satu akun masyarakat/pelapor dapat melihat dashboard dan riwayat laporan miliknya sendiri.
- Terapkan batas jumlah laporan harian; nilai batas: `TODO: Define requirement`.
- Pada form laporan, kategori harus dapat dipilih dan setiap kategori memiliki dinas/unit tujuan yang telah dikonfigurasi.
- Setelah kategori dipilih, UI menampilkan dinas/unit tujuan laporan.
- Laporan dikirim ke alur penanganan dinas/unit yang terkait dengan kategori.
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
  - Given pelapor telah login dan belum melewati batas harian, when form valid dikirim, then satu pengaduan tersimpan dan tanda terima dihasilkan.
  - Given kategori dipilih, then dinas/unit terkait ditampilkan dan menjadi tujuan laporan.
  - Given batas laporan harian tercapai, then pengiriman baru ditolak dengan pesan yang dapat ditindaklanjuti.
  - Given validasi gagal, when form dikirim, then field error spesifik ditampilkan tanpa membuat record.
  - Given request duplikat akibat double-click/retry, then sistem mencegah pengiriman ganda sejauh idempotency/request token dapat diterapkan.

### F-002A — Dashboard dan riwayat masyarakat/pelapor
- Hanya laporan milik akun yang sedang login yang ditampilkan.
- Dashboard menampilkan ringkasan dan riwayat laporan yang relevan.
- Detail laporan menampilkan informasi yang boleh dilihat pelapor.

### F-002B — Hak akses peran internal
- Operator menangani pengelolaan operasional laporan sesuai permission, termasuk perubahan kategori dan penugasan.
- **Operator ↔ Dinas/Unit (FINAL, D-2 / Prompt 21):** satu Operator mewakili tepat satu Dinas/Unit; **Super Admin** yang menetapkan/mengubahnya melalui User Management. Operator tidak dapat self-assign atau mengubah assignment Operator lain (ditegakkan server-side, bukan sekadar UI). Operator tanpa unit tidak melihat laporan apa pun (tanpa fallback global). Deaktivasi tidak menghapus assignment.
- Admin hanya memiliki akses monitoring laporan dan audit log.
- Super Admin memiliki akses penuh terhadap fungsi website yang tersedia.
- Seluruh aksi tetap harus dilindungi authorization di server.

### F-003 — Nomor referensi
- Nomor referensi unik, tidak berurutan secara mudah ditebak, dan tidak mengandung informasi pribadi.
- Secret pelacakan dibuat dengan CSPRNG dan disimpan dalam bentuk hash jika secret tersebut dipakai sebagai bearer credential.
- Acceptance criteria: kode yang berhasil dibuat unik; halaman tanda terima memberi instruksi penyimpanan yang jelas; secret tidak dicatat di log.

### F-004 — Pelacakan publik
- Tampilkan hanya status publik, waktu pembaruan, nomor referensi, dan informasi minimal yang disetujui.
- Jangan pernah menampilkan catatan internal, attachment privat, kredensial, atau data personal yang tidak diperlukan.
- Acceptance criteria: kode salah menghasilkan respons generik; request dibatasi rate; hasil tidak mengungkap laporan lain.

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
- **DONE (Prompt 3B):** daftar status dan transisi resmi — lihat schema.md §6 dan `app/Enums/ComplaintStatus.php`.
- `rejected` wajib menyertakan alasan penolakan (non-kosong) dan respons publik (non-kosong).
- `resolved` wajib menyertakan respons publik (non-kosong).
- Tidak ada reopen workflow. Setelah `closed`, complaint bersifat final.
- Acceptance criteria: status ilegal ditolak di server; update dan riwayat tersimpan atomik; perubahan gagal tidak meninggalkan riwayat palsu.

### F-009 — Catatan internal dan tanggapan publik
- Setiap catatan memiliki jenis yang jelas (`internal` atau `public_response`).
- Catatan internal tidak pernah muncul di halaman pelacakan publik.
- Acceptance criteria: endpoint/view publik hanya memuat jenis yang boleh dipublikasikan; authorization diperiksa di server.

### F-010 — Lampiran
- Simpan file melalui Laravel Storage private disk atau lokasi di luar web root.
- Validasi MIME, ekstensi, ukuran, dan nama file; nama asli tidak digunakan sebagai path.
- Unduh file melalui controller terotorisasi.
- `TODO: Define requirement` — ekstensi, ukuran maksimum, jumlah maksimum, dan scanning malware.
- Acceptance criteria: file tidak diakses hanya dengan menebak URL; file tidak valid ditolak; nama file aman.

### F-011/F-012 — Pengaturan, akun, dan role
- Super Admin dapat mengelola kategori dan dinas/unit terkait, akun masyarakat, Operator, dan Admin, serta role/permission dan konfigurasi operasional.
- Admin hanya dapat melakukan monitoring laporan dan audit log; tidak memiliki akses pengelolaan.
- Operator mengelola laporan sesuai permission, termasuk kategori dan penugasan.
- Masyarakat/pelapor hanya dapat mengelola akun dan laporan miliknya sendiri.
- Tidak boleh ada self-escalation privilege.
- Acceptance criteria: seluruh tindakan sensitif dicatat dalam audit log dan akses ditolak jika role/permission tidak sesuai.

### F-013 — Audit log
- Catat aktor, aksi, jenis/ID entitas, waktu, dan metadata minimal.
- Jangan mencatat password, session ID, tracking secret, token, atau isi laporan sensitif secara berlebihan.
- Audit log tidak boleh diedit melalui UI normal.

## 8. Official complaint lifecycle (finalized per Prompt 3B)

Status resmi (7 status):

| Code | Label Publik | Keterangan |
|---|---|---|
| `submitted` | Diajukan | Status awal setiap laporan baru |
| `under_review` | Sedang Ditinjau | Operator sedang menelaah laporan |
| `in_progress` | Sedang Diproses | Laporan sedang ditangani |
| `waiting_for_information` | Menunggu Informasi | Operator membutuhkan info tambahan dari Masyarakat |
| `resolved` | Selesai | Masalah telah ditangani; respons publik wajib |
| `rejected` | Ditolak | Laporan ditolak; alasan penolakan + respons publik wajib |
| `closed` | Ditutup | Final. Terminal — tidak ada transisi keluar. Tidak ada reopen. |

Transisi resmi: 11 transisi — lihat schema.md §6 untuk matrix lengkap.

Kewenangan: Operator dan Super Admin (YA). Admin dan Masyarakat (TIDAK).

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
