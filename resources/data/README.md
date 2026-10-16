# Batas Wilayah Kota Parepare (Kelurahan)

Berkas di direktori ini adalah **poligon batas wilayah** untuk keperluan
*fitur deteksi otomatis* Kecamatan/Kelurahan pada form "Buat Laporan"
(citizen) dan form "Ubah Lokasi" (operator) — yaitu saat pengguna menandai
titik lokasi di peta / memakai GPS.

| Berkas | Isi |
|---|---|
| `parepare-kelurahan.geojson` | 22 kelurahan (Polygon), ~33 KB |

Format: GeoJSON RFC 7946, WGS84 (CRS84), koordinat `[lng, lat]`,
presisi 5 desimal (~1 m), ring ter-*dedupe*.

Hanya **kelurahan** yang disertakan: poligon kelurahan di sumber jauh lebih
detail dan mencakup ~97,6% area kota (vs ~94,7% poligon kecamatan). Kecamatan
induk **diturunkan** dari prefiks kode kelurahan (`kecamatan_code`), sehingga
lebih akurat *dan* lebih kecil daripada menyimpan layer kecamatan terpisah.

## Sumber & Lisensi

- **Sumber:** [`cahyadsn/wilayah_boundaries`](https://github.com/cahyadsn/wilayah_boundaries)
  — *Data boundaries wilayah administrasi pemerintahan Indonesia sesuai
  Kepmendagri No 300.2.2-2430 Tahun 2025*.
- **Lisensi:** **MIT** — Copyright (c) 2024 Cahya DSN.
  Salinan teks lisensi: <https://github.com/cahyadsn/wilayah_boundaries/blob/main/LICENSE>.
- Berkas sumber: `db/kel/73/wilayah_boundaries_kel_73.72.sql`.
- Skrip pembuat: `_superbie_gis_phase_d/scripts/gen_parepare_boundaries.py`
  (di luar repo). Koordinat sumber `[lat, lng]` di-*swap* ke `[lng, lat]`.

## Sifat Data (PENTING)

- Batas ini bersifat **INDIKATIF**, bukan batas hukum definitif.
- Kode wilayah memakai **kode Kemendagri** (mis. `73.72.01.1004`) dan
  **cocok 100%** dengan `database/seeders/ParepareLocationSeeder.php`
  (nama **dan** kode; diverifikasi 22/22 kelurahan).
- Deteksi otomatis hanya **saran UX**. Pilihan Kecamatan/Kelurahan tetap
  **WAJIB** dan tetap **divalidasi di server** (`StoreComplaintRequest`);
  pengguna selalu dapat mengubahnya secara manual.
- Berkas ini menyimpan `code`/`name`/`kecamatan_code` saja; **id database
  disuntikkan saat runtime** oleh `App\Services\LocationBoundaryService`
  (fitur yang kodenya tidak punya baris master akan dibuang).

## Mengapa bukan sumber OSM (paket Phase D)?

Paket `_superbie_gis_phase_d` (turunan OpenStreetMap) berlisensi **ODbL**
dengan status distribusi *"BELUM DISETUJUI"*. Dataset di sini memakai sumber
**MIT** sehingga bebas hambatan lisensi, dengan kode yang cocok persis dengan
master wilayah aplikasi.

## Regenerasi

```powershell
python "D:\Kuliah\Semester 7\Magang\_superbie_gis_phase_d\scripts\gen_parepare_boundaries.py"
```
