# Paket Dokumentasi Vibe Coding — Lapor Pak Wali

Paket ini adalah fondasi dokumentasi untuk pengembangan tahap pertama **SuperBie – Lapor Pak Wali**, dengan fokus hanya pada fitur pengaduan masyarakat. Modul SIPHP, Parepare Weather Intelligence Dashboard, dan JDIH tidak dibangun pada fase ini; arsitektur boleh menyiapkan ruang ekspansi, tetapi jangan membuat fitur atau tabel modul tersebut sekarang.

## Isi paket

- `prd.md` — kebutuhan produk, scope MVP, user journey, acceptance criteria.
- `architecture.md` — arsitektur Laravel, alur sistem, keamanan, deployment.
- `schema.md` — sumber utama model data, tabel, relasi, validasi, dan kontrak endpoint.
- `design.md` — design system dan standar animasi UI.
- `rules.md` — aturan implementasi yang wajib diikuti AI coding assistant/developer.

Versi dokumen yang telah diperbarui mengikuti perubahan kebutuhan: akun masyarakat/pelapor wajib login, Petugas ditambahkan, Admin hanya monitoring, dan Super Admin memiliki akses penuh.

## Cara menggunakan untuk vibe coding

1. Letakkan kelima dokumen ini di root repository.
2. Berikan `rules.md` sebagai instruksi permanen kepada AI coding assistant.
3. Minta AI membaca `prd.md`, `architecture.md`, `schema.md`, dan `design.md` sebelum mengubah kode.
4. Implementasikan per irisan vertikal kecil: setup proyek → autentikasi masyarakat/internal → dashboard masyarakat → formulir pengaduan → pelacakan → panel Petugas/Operator/Admin/Super Admin → pengujian.
5. Setelah setiap tahap, jalankan test dan review diff. Jangan meminta AI membuat seluruh aplikasi dalam satu perubahan besar.
6. Jika kebutuhan belum dipastikan, catat sebagai `TODO: Define requirement`; jangan mengarang aturan layanan publik.

## Keputusan teknologi utama

- Laravel monolith, Blade, Livewire, Alpine.js, Tailwind CSS, dan MySQL.
- Laravel session authentication untuk masyarakat/pelapor dan seluruh panel internal. Masyarakat/pelapor wajib login sebelum mengirim pengaduan.
- Lampiran disimpan melalui Laravel Storage abstraction, bukan path publik mentah.
- Animasi diterapkan secara konsisten ke seluruh komponen UI yang terlihat dan komponen interaktif, dengan fallback aksesibilitas `prefers-reduced-motion`.
- Tidak memakai React/Node.js API terpisah karena kebutuhan pengguna secara eksplisit memilih Laravel. Tidak memakai Redis, queue server, CDN, object storage eksternal, atau microservices pada MVP kecuali kebutuhan dan infrastruktur telah disetujui.

## Hal yang perlu dikonfirmasi sebelum produksi

- Konfirmasi batas jumlah laporan harian per akun masyarakat/pelapor.
- Konfirmasi identitas profil masyarakat/pelapor yang wajib.
- Konfirmasi pembagian akses Petugas, Operator, Admin monitoring, dan Super Admin penuh.
- Kanal resmi dan batas waktu respons/penyelesaian pengaduan.
- Kategori, dinas/unit tujuan, pemetaan kategori → dinas/unit, dan aturan penugasan.
- Jenis, ukuran maksimum, dan jumlah lampiran.
- Apakah pelapor dapat membalas/menambahkan bukti setelah tiket dikirim.
- Kebijakan publikasi status dan data yang boleh dilihat tanpa login.
- Kebijakan retensi, penghapusan, dan ekspor data.
- Email/SMS/WhatsApp notification provider dan kredensial resmi.
