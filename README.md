# Paket Dokumentasi Vibe Coding — Lapor Pak Wali

Paket ini adalah fondasi dokumentasi untuk pengembangan tahap pertama **SuperBie – Lapor Pak Wali**, dengan fokus hanya pada fitur pengaduan masyarakat. Modul SIPHP, Parepare Weather Intelligence Dashboard, dan JDIH tidak dibangun pada fase ini; arsitektur boleh menyiapkan ruang ekspansi, tetapi jangan membuat fitur atau tabel modul tersebut sekarang.

## Isi paket

- `prd.md` — kebutuhan produk, scope MVP, user journey, acceptance criteria.
- `architecture.md` — arsitektur Laravel, alur sistem, keamanan, deployment.
- `schema.md` — sumber utama model data, tabel, relasi, validasi, dan kontrak endpoint.
- `design.md` — design system dan standar animasi UI.
- `rules.md` — aturan implementasi yang wajib diikuti AI coding assistant/developer.
- `PRODUCTION_OPERATIONS.md` — runbook operasi produksi (scheduler, queue, health, backup/restore, monitoring, CI, deployment) dengan status **IMPLEMENTED / REQUIRED / DECISION REQUIRED** (Prompt 23).

Versi dokumen yang telah diperbarui mengikuti perubahan kebutuhan: akun masyarakat/pelapor wajib login, Operator sebagai penangan operasional laporan, Admin hanya monitoring, dan Super Admin memiliki akses penuh. Peran historis `petugas` tidak lagi menjadi active role.

## Cara menggunakan untuk vibe coding

1. Letakkan kelima dokumen ini di root repository.
2. Berikan `rules.md` sebagai instruksi permanen kepada AI coding assistant.
3. Minta AI membaca `prd.md`, `architecture.md`, `schema.md`, dan `design.md` sebelum mengubah kode.
4. Implementasikan per irisan vertikal kecil: setup proyek → autentikasi masyarakat/internal → dashboard masyarakat → formulir pengaduan → pelacakan → panel Operator/Admin/Super Admin → pengujian.
5. Setelah setiap tahap, jalankan test dan review diff. Jangan meminta AI membuat seluruh aplikasi dalam satu perubahan besar.
6. Jika kebutuhan belum dipastikan, catat sebagai `TODO: Define requirement`; jangan mengarang aturan layanan publik.

## Keputusan teknologi utama

- Laravel monolith, Blade, Alpine.js, Tailwind CSS, dan MySQL. (Livewire **tidak** dipakai — tidak ada dependensi Livewire; interaktivitas ringan memakai Alpine.js.)
- Navigasi internal **progressive enhancement** (Prompt 26): `resources/js/navigation.js` meng-intercept klik GET internal via `fetch()` lalu menukar region shell (`#main-content`, `#primary-navigation`, `#page-heading`, `#page-actions`) dengan HTML Blade dari server, plus cache in-memory per-tab yang identity-scoped (TTL 60 s, maks 10 entri). Ini **bukan** SPA: tidak ada router/state framework, Service Worker, `localStorage`, atau API baru; Laravel tetap source of truth, mutasi tetap dinavigasi penuh, dan authorization tetap server-side. Jika JavaScript gagal, aplikasi tetap berjalan normal.
- Laravel session authentication untuk masyarakat/pelapor dan seluruh panel internal. Masyarakat/pelapor wajib login sebelum mengirim pengaduan.
- Lampiran disimpan melalui Laravel Storage abstraction, bukan path publik mentah.
- Animasi diterapkan secara konsisten ke seluruh komponen UI yang terlihat dan komponen interaktif, dengan fallback aksesibilitas `prefers-reduced-motion`.
- Tidak memakai React/Node.js API terpisah karena kebutuhan pengguna secara eksplisit memilih Laravel. Tidak memakai Redis, queue server, CDN, object storage eksternal, atau microservices pada MVP kecuali kebutuhan dan infrastruktur telah disetujui.

## Hal yang perlu dikonfirmasi sebelum produksi

- ~~Konfirmasi batas jumlah laporan harian per akun masyarakat/pelapor.~~ **FINAL (Prompt 5B): 5 laporan / hari kalender.**
- ~~Konfirmasi identitas profil masyarakat/pelapor yang wajib.~~ **FINAL (Prompt 5B): Nama, NIK (16 digit, unique, immutable), Nomor HP (unique, lokal+internasional), Alamat (free text) — semuanya wajib.**
- Konfirmasi pembagian akses Operator, Admin monitoring, dan Super Admin penuh.
- Kanal resmi dan batas waktu respons/penyelesaian pengaduan.
- Kategori, dinas/unit tujuan, pemetaan kategori → dinas/unit, dan aturan penugasan. **FINAL (Prompt 5C & 6):** Mapping Kategori ↔ Dinas/Unit adalah **many-to-many** (`category_dinas_unit`), dikelola **Super Admin**. Super Admin mengelola **master Kategori** (`super-admin/kategori`: create/edit/**activate-deactivate**) dan **master Dinas/Unit**. Operator memilih **tujuan Dinas/Unit aktual** dari daftar Dinas/Unit yang terhubung dengan kategori laporan (hanya yang aktif untuk penugasan baru). Tujuan aktual **disimpan** pada `complaints.dinas_unit_id` — **bukan** dari pivot mapping (mapping ≠ historical destination), sehingga histori stabil terhadap perubahan mapping. **Kategori/Dinas yang sudah pernah digunakan pada laporan tidak dapat dihapus (Prompt 6 §6 / 5C.1) — nonaktifkan sebagai gantinya; laporan lama tetap mempertahankan kategori & tujuan.** Kategori nonaktif ditolak server-side untuk laporan baru. `dinas_name` legacy **bukan** sumber routing (hanya label tampilan). Daftar resmi Kategori/Dinas/Unit **belum tersedia** — seed berisi *sample/development*.
- ~~Jenis, ukuran maksimum, dan jumlah lampiran.~~ **FINAL (Prompt 5B): opsional; JPG/JPEG/PNG/PDF/MP4; maks 20 MB/file; maks 10 file; tanpa malware scanning.**
- ~~Apakah pelapor dapat membalas/menambahkan bukti setelah tiket dikirim.~~ **FINAL (Prompt 4B): TIDAK.** Masyarakat/pelapor **tidak** dapat membalas, menanggapi, atau menambahkan bukti setelah laporan dikirim. Tidak ada form respons, tidak ada endpoint/route respons, tidak ada catatan lanjutan dari Masyarakat, dan tidak ada transisi status yang dipicu Masyarakat. Status `waiting_for_information` hanya **ditampilkan** kepada Masyarakat; status tetap dikendalikan Operator/Super Admin sesuai transition matrix.
- Kebijakan publikasi status dan data yang boleh dilihat tanpa login. **FINAL (Prompt 5B): public tracking tidak diperlukan; guest tidak dapat mengakses data laporan.**
- ~~Kebijakan retensi, penghapusan, dan ekspor data.~~ **Retention FINAL (Prompt 5B.1): 5 tahun sejak `complaints.submitted_at` → permanent deletion (termasuk data anak complaint, berkas lampiran fisik, dan audit log terkait complaint). Tidak ada archive/soft-delete/grace period. Akun user & master data tidak ikut terhapus.**
- ~~Email/SMS/WhatsApp notification provider dan kredensial resmi.~~ **FINAL (Prompt 5B): notifikasi tidak diperlukan untuk MVP.**

## Operasi produksi (Prompt 23)

Lihat **`PRODUCTION_OPERATIONS.md`**. Ringkas:

- **IMPLEMENTED:** health endpoint `GET /up`; scheduler harian `complaints:purge-expired` (02:00); konfigurasi queue `database` (belum ada job yang dikirim); CI GitHub Actions bebas-secret (`.github/workflows/ci.yml`) yang hanya menjalankan test + build.
- **REQUIRED (bukan kode di repo ini):** trigger `schedule:run` oleh host; backup database + lampiran; prosedur restore yang diuji; konfigurasi produksi `.env`.
- **DECISION REQUIRED (tidak dikarang):** target deployment; mekanisme scheduler; kebijakan backup (frekuensi/retensi/destinasi/enkripsi); **RPO/RTO**; monitoring & alerting; trusted proxy (D-6). CI platform (GitHub) juga belum dapat dipastikan karena `origin` repo lokal menunjuk ke proyek lain.
- **ROTATION REQUIRED:** kredensial DB dev yang terekspos di git history harus dirotasi (bukan ditulis ulang history-nya).
