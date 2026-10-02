# SuperBie — Command Center "Lapor Pak Wali"

Aplikasi web pengaduan masyarakat Pemerintah Kota Parepare berbasis Laravel monolith dengan arsitektur peran bertingkat: **Masyarakat**, **Petugas**, **Operator**, **Admin**, dan **Super Admin**.

---

## 1. Persyaratan Sistem

- Docker Desktop (dengan Docker Compose v2+)
- Port `8080` (Web), `3307` (MySQL), dan `5173` (Vite) tersedia di host

---

## 2. Cara Menjalankan Aplikasi via Docker

### Langkah 1: Clone & Siapkan Environment

```bash
# Salin konfigurasi environment
cp .env.example .env
```

Pastikan variabel database di `.env` sesuai dengan `docker-compose.yml`:
```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=superbie
DB_USERNAME=superbie
DB_PASSWORD=superbie_password
```

### Langkah 2: Jalankan Docker Compose

```bash
# Build dan jalankan seluruh container (app, webserver, db, node)
docker compose up -d --build
```

Container yang berjalan:
- `superbie_app`: PHP 8.3-FPM + ekstensi lengkap + Composer
- `superbie_nginx`: Nginx 1.25 Alpine (port `8080`)
- `superbie_db`: MySQL 8.0 (port `3307`)
- `superbie_node`: Node 20 Alpine (Vite dev server di port `5173`)

### Langkah 3: Setup Awal Database & Kunci Aplikasi

```bash
# Generate app key
docker compose exec app php artisan key:generate

# Jalankan migrasi dan seeder
docker compose exec app php artisan migrate --seed
```

### Langkah 4: Buka Aplikasi di Browser

Buka `http://localhost:8080` di browser Anda.

---

## 3. Akun Development untuk Pengujian

Semua akun menggunakan password: `password123`

| Peran | Email | Akses & Kemampuan |
|---|---|---|
| **Masyarakat** | `warga1@superbie.local` | Dashboard sendiri, riwayat sendiri, buat laporan |
| **Masyarakat** | `warga2@superbie.local` | Dashboard sendiri (terisolasi dari warga 1) |
| **Petugas** | `petugas1@superbie.local` | Hanya laporan yang ditugaskan kepada petugas 1 |
| **Petugas** | `petugas2@superbie.local` | Hanya laporan yang ditugaskan kepada petugas 2 |
| **Operator** | `operator@superbie.local` | Monitoring operasional, penugasan, review laporan |
| **Admin** | `admin@superbie.local` | **Monitoring saja** (tanpa aksi pengelolaan) |
| **Super Admin** | `superadmin@superbie.local` | Akses penuh seluruh modul dan sistem |

---

## 4. Cara Menjalankan Automated Tests

```bash
# Jalankan seluruh test suite di dalam container Docker
docker compose exec app php artisan test

# Atau jalankan test spesifik otorisasi dashboard
docker compose exec app php artisan test --filter=DashboardAuthorizationTest
```

---

## 5. Struktur Project

```
superbie/
├── app/
│   ├── Enums/
│   │   ├── ComplaintStatus.php       # Status laporan & transisi
│   │   ├── NoteVisibility.php        # internal vs public_response
│   │   └── UserRole.php              # Definisi 5 peran sistem
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                 # Login, Register, Password Reset
│   │   │   ├── Citizen/              # Dashboard & riwayat masyarakat
│   │   │   ├── Staff/                # Dashboard Petugas, Operator, Admin, Super Admin
│   │   │   └── DashboardRedirectController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureUserHasRole.php # Otorisasi peran sisi server
│   │   │   └── EnsureUserIsActive.php# Blokir akun nonaktif
│   │   └── Requests/Auth/            # Form Requests dengan rate limiting
│   └── Models/                       # User, Complaint, ComplaintCategory, dll.
├── database/
│   ├── migrations/                   # Skema database MySQL sesuai schema.md
│   └── seeders/DatabaseSeeder.php    # Akun dev & data contoh
├── docker/
│   └── nginx/default.conf            # Proteksi private storage & PHP-FPM
├── resources/
│   ├── css/app.css                   # Design tokens, animasi, reduced-motion
│   └── views/
│       ├── layouts/                  # Base app & dashboard layout
│       ├── auth/                     # Login, register, forgot-password
│       ├── public/landing.blade.php  # Landing page
│       ├── citizen/dashboard.blade.php
│       ├── petugas/dashboard.blade.php
│       ├── operator/dashboard.blade.php
│       ├── admin/dashboard.blade.php # Monitoring only
│       └── super-admin/dashboard.blade.php
├── tests/Feature/
│   └── DashboardAuthorizationTest.php# Test otorisasi & isolasi data
├── Dockerfile
└── docker-compose.yml
```

---

## 6. Daftar TODO — Requirement yang Belum Ditentukan

Sesuai aturan **"DOCUMENTATION FIRST / DO NOT INVENT"**, berikut hal-hal yang belum ditentukan secara resmi dalam spesifikasi dan ditandai sebagai `TODO`:

1. `TODO: Define daily report limit` — Angka batas laporan harian per akun masyarakat belum ditentukan. Tabel `app_settings` telah disiapkan agar nilai dapat dikonfigurasi tanpa perubahan kode.
2. `TODO: Define official category → dinas/unit mapping` — Daftar kategori resmi Pemerintah Kota Parepare dan dinas/unit tujuannya belum ditetapkan. Data yang tersedia adalah *development fixture* untuk kebutuhan demo.
3. `TODO: Define official statuses, SLA, and workflow transitions` — Status saat ini (`submitted`, `under_review`, `in_progress`, `waiting_for_information`, `resolved`, `rejected`, `closed`) adalah usulan teknis sementara.
4. `TODO: Define attachment rules` — Format file yang diizinkan, ukuran maksimum per file, jumlah file maksimum, dan kebijakan pemindaian malware belum ditetapkan.
5. `TODO: Define tracking verification factors` — Kebijakan verifikasi pelacak (apakah kode + OTP, kode + nomor telepon, atau secret hash) belum ditetapkan.
6. `TODO: Define notification provider` — Integrasi notifikasi (Email/SMS/WhatsApp) belum memiliki provider dan kredensial resmi.
7. `TODO: Define retention & privacy policy` — Kebijakan retensi dan penghapusan data pengaduan, log audit, dan lampiran belum ditetapkan.
