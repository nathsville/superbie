# Panduan Kontribusi — SuperBie "Lapor Pak Wali"

Dokumen ini menjelaskan cara menyiapkan lingkungan, menjalankan, menguji, dan
berkontribusi pada proyek ini. Bacalah sampai bagian **Aturan yang FROZEN**
(§7) sebelum mengubah kode apa pun.

- **Repo:** `https://github.com/nathsville/superbie` (private — minta akses ke owner)
- **Stack:** Laravel 13 (PHP 8.4) · MySQL · Blade · Alpine.js · Tailwind 4 · Vite
- **Arsitektur:** monolith. **Tidak memakai Docker.**

---

## 1. Prasyarat

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | **8.4.x** (repo diuji di 8.4.26; minimal 8.3) | ekstensi wajib: `mbstring, intl, pdo_mysql, fileinfo, gd, exif, zip` |
| Composer | 2.x | untuk `composer install` |
| Node.js + npm | **Node 22 LTS** | Vite 8 + Tailwind 4 |
| MySQL | 8.x | **harus listen di port `3307`** (lihat §10) |
| Git | terbaru | |

Di Windows, cara paling praktis adalah **Laragon** (atau XAMPP/MySQL manual).
Tidak diperlukan Docker.

---

## 2. Setup Awal

```bash
git clone https://github.com/nathsville/superbie.git
cd superbie

# 1) Dependency PHP — pakai lock agar versi identik
composer install

# 2) Environment
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate

# 3) Buat DUA database MySQL (lihat §10 untuk port):
#    - superbie          -> aplikasi (dev)
#    - superbie_testing  -> test suite
#    lalu sesuaikan DB_USERNAME / DB_PASSWORD di .env

# 4) Skema database (jalankan SEMUA migration)
php artisan migrate
# opsional: data contoh untuk pengembangan lokal
php artisan migrate --seed

# 5) Aset frontend
npm ci
npm run build                 # atau: npm run dev  (Vite dev server di port 5173)

# 6) Jalankan aplikasi
php artisan serve             # http://127.0.0.1:8000
```

> `DB_USERNAME` / `DB_PASSWORD` **tidak** ada di `phpunit.xml` (sengaja) — dibaca
> dari `.env` yang git-ignored. Gunakan kredensial MySQL **lokal Anda sendiri**.
> Jangan pernah menulis kredensial nyata ke file yang ter-track git.

---

## 3. Menjalankan Test

```bash
php artisan test                       # seluruh suite
php artisan test --filter=NamaTest     # satu test tertentu
```

- Baseline saat ini: **566 test / 2468 assertion, 0 gagal**.
- Test memakai MySQL **`superbie_testing`** (bukan SQLite). Database itu **harus
  ada** dan MySQL **harus jalan** sebelum menjalankan test.
- **Jangan** menjalankan dua proses `php artisan test` bersamaan — keduanya
  memakai database test yang sama dan akan saling merusak.

---

## 4. Akun Development

Hanya terisi bila Anda menjalankan `php artisan migrate --seed`. Semua password:
`password123`.

| Peran | Email |
|---|---|
| Masyarakat | `warga1@superbie.local`, `warga2@superbie.local` |
| Operator | `operator@superbie.local` |
| Admin | `admin@superbie.local` |
| Super Admin | `superadmin@superbie.local` |

> Data hasil seed adalah **fixture pengembangan** (kategori/dinas/complaint
> contoh), **bukan daftar resmi**. Daftar resmi diisi oleh Super Admin melalui
> dashboard.

---

## 5. Alur Kolaborasi Git

1. **Jangan commit langsung ke `main`.** Buat branch fitur:
   ```bash
   git checkout -b feat/nama-singkat
   ```
   Prefiks yang dipakai: `feat/`, `fix/`, `docs/`, `test/`, `chore/`, `refactor/`.
2. Commit dengan pesan jelas (disarankan *Conventional Commits*):
   ```
   feat(operator): tambah filter status pada daftar laporan
   fix(auth): perbaiki pesan error reset password
   docs: perbarui panduan kontribusi
   ```
3. Push branch lalu buka **Pull Request** ke `main`.
4. Tunggu CI hijau (lihat §9) sebelum merge.
5. **Aktifkan branch protection** untuk `main`
   (Settings → Branches → require PR + require status check `Tests & asset build`).

Set identitas git di mesin Anda:

```bash
git config --global user.name "Nama Anda"
git config --global user.email "email@anda"
```

---

## 6. Konvensi Kode

- Ikuti gaya Laravel yang sudah ada. **Jangan** melakukan reformat massal dengan Pint
  pada file yang tidak Anda ubah.
- **Migration baru = file baru.** Jangan pernah mengubah migration lama yang sudah ada.
- Gunakan `composer install` dan `npm ci` (mengikuti lock file) — bukan
  `composer update` / `npm install` bebas — agar versi identik dengan CI.
- Line ending dinormalisasi ke **LF** oleh `.gitattributes` (`* text=auto eol=lf`).
  Di Windows, biarkan Git yang mengurus konversi.

---

## 7. Aturan yang FROZEN (JANGAN DIUBAH)

Bagian ini adalah keputusan yang sudah final. Melanggarnya akan merusak
konsistensi sistem dan membuat test gagal.

- **Peran:** hanya **4** peran — `masyarakat`, `operator`, `admin`, `super_admin`.
  Peran `petugas` bersifat historis dan **tidak boleh dihidupkan kembali**.
- **Status & transisi laporan:** **FROZEN** (7 status, 11 transisi; `closed`
  bersifat terminal). **Jangan** mengubah `app/Enums/ComplaintStatus.php`.
- **Otorisasi:** memakai middleware `role:*` / `active` + Form Request
  `authorize()` + pengecekan inline di controller. **Tidak** memakai class
  Policy/Gate.
- **Dilarang menambah:** Docker, Livewire, React, Vue, Inertia, penulisan ulang
  SPA, Sanctum/JWT, API gateway, microservices, atau paket/arsitektur baru.
- **Dilarang (DB):** `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`
  destruktif, `DROP`/`TRUNCATE`/`DELETE` massal, atau `ALTER` destruktif.
- **Jangan** melemahkan atau menghapus test agar CI hijau.
- Aturan bisnis final yang tidak boleh diubah: batas laporan **5/hari kalender**,
  lampiran **maks 10 file / 20 MB**, retensi **5 tahun** sejak `submitted_at`
  (permanent deletion). **SLA belum ditetapkan** — jangan menampilkan/menghitung
  nilai SLA apa pun.

---

## 8. Keamanan

- **Jangan pernah** commit `.env` (sudah masuk `.gitignore`). Gunakan `.env.example`
  sebagai template.
- **Rotasi kredensial DB pengembangan** yang bocor: nilainya ada di commit lama
  dan tertulis plaintext di `PROMPT_18_SECURITY_ARCHITECTURE_AUDIT.md`. Selama repo
  private risikonya kecil, tetapi rotasi tetap langkah yang benar; setelah rotasi,
  perbarui `.env` lokal Anda.
- Jangan menaruh secret apa pun di kode, test, atau workflow CI.
- Pastikan repo tetap **private** selama riwayat git masih memuat kredensial lama.

---

## 9. CI (GitHub Actions)

Workflow `.github/workflows/ci.yml` berjalan pada **push ke `main`** dan pada
**Pull Request**. Cakupannya: `composer install` → `npm ci` → `npm run build` →
`php artisan migrate` → `php artisan test`, plus audit dependency (informatif).
Workflow ini **secret-free** dan **tidak melakukan deployment**.

Nama status check yang harus lulus: **`Tests & asset build`**.

---

## 10. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| Test gagal connect ke MySQL | `phpunit.xml` memaksa `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=superbie_testing`. Jalankan MySQL di **port 3307**, atau set env var OS yang menimpa nilai tersebut. |
| `Unknown database 'superbie_testing'` | Buat database test terlebih dahulu. |
| `table already exists` / `doesn't exist` saat test | Ada dua proses `php artisan test` jalan bersamaan — jalankan satu saja. |
| Error kolom `users.dinas_unit_id` tidak ada (di DB dev) | Jalankan `php artisan migrate` (ada migration yang belum diterapkan). |
| Aset/CSS/JS tidak muncul | Jalankan `npm run build` (atau `npm run dev`). |
| `CRLF will be replaced by LF` | Normal — diurus oleh `.gitattributes`. |

---

## 11. Keputusan Tim yang Masih Terbuka

Hal-hal berikut belum diputuskan dan relevan menjelang produksi (tidak
menghambat pengembangan lokal): target deployment, pemicu scheduler, backup +
RPO/RTO, monitoring/alerting, kebijakan abuse untuk registrasi & forgot-password,
dan remediasi enumerasi reset password (F-20-01). Lihat
`PRODUCTION_OPERATIONS.md` dan laporan audit terkait.

---

Dengan berkontribusi pada repo ini, Anda setuju untuk mengikuti aturan di atas.
Jika ragu apakah suatu perubahan melanggar §7, **tanyakan dulu** sebelum
mengerjakannya.
