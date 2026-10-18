# WINDY/ — arsip legacy (JANGAN dipakai runtime)

Folder ini adalah **arsip sumber Google Apps Script** dari situs WINDY lama
(`BMKG.js`, `OpenWeather.js`, `WeatherAPI.js`, `Kode.js`, `Trigger.js`,
`Utils.js`, `Config.js`, `Database.js`, `appsscript.json`, `.clasp.json`)
beserta mockup HTML-nya (`index.html`, `dashboard.html`, `style.html`,
`logo.html`, `boundary.html`).

Seluruhnya **bukan bagian dari aplikasi SuperBie**: tidak ada build, tidak ada
deploy, dan tidak ada test yang membacanya.

Aturan yang berlaku:

- Modul cuaca SuperBie (`/cuaca`) adalah hasil migrasi — jangan menyalin kode
  atau memuat berkas dari folder ini ke aplikasi (lihat `PROGRESS.md` §Arsitektur
  cuaca dan `_superbie_audit/WINDY_MIGRATION_PLAN.md`).
- `boundary.html` adalah sisa eksperimen peta wilayah WINDY lama yang
  **dilarang dipakai/diadopsi** (aturan proyek: tanpa boundary custom untuk
  WINDY — lihat `rules.md`).

Berkas dipertahankan hanya sebagai jejak historis migrasi. Audit PROMPT_28
(paket higiene, 2026-10-18) memutuskan folder ini tetap ada **dengan penanda
ini** — bukan dihapus — karena ia adalah sumber migrasi yang terdokumentasi.
