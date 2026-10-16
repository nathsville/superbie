# PROMPT 27 — Dashboard Visual Redesign
## PHASE 1 — DISCOVERY & SECTION → DATA-SOURCE MAPPING (NO IMPLEMENTATION)

> **LC-2 CORRECTION NOTE (added by LC-2D, later than this document):** This is a dated discovery artifact. Its KPI data-source definitions predate the LC-2 lifecycle normalization and are **superseded**. The "active/backlog" KPI is now `submitted + in_progress` (NOT `whereNotIn('status', ['resolved','rejected','closed'])`), success = `resolved` (NOT `closed`), and legacy statuses (`under_review`, `waiting_for_information`, `closed`) are history-only. See `prd.md` §8 / F-014 and `schema.md` §6 for the current source of truth. The historical content below is retained unchanged for traceability.

> Status: **DISCOVERY ONLY**. No code changed. Awaiting approval before Phase 2.
> Reference = `design.html` (+ user screenshot) = **VISUAL TRUTH ONLY**.
> Repository / DB / security = **BUSINESS + DATA + AUTHORITY TRUTH**.

---

## 0. BASELINE (captured before any change)

| Item | Value |
|---|---|
| Tests | **566 passed / 566**, 0 failed, 0 skipped |
| Assertions | **2468** |
| Routes | **61** (pinned by `ProgressiveNavigationTest::test_route_inventory_is_unchanged`) |
| Build | `npm run build` PASS (Vite/Tailwind v4) |
| Migrations | `…100008` **Pending** in dev DB — will NOT be run |
| Dashboard data layer | `app/Services/DashboardCacheService.php` |
| Active layout tree | `resources/views/components/layouts/{app,dashboard}.blade.php` |
| Dead/orphan layout tree | `resources/views/layouts/{app,dashboard}.blade.php` (F-18-37) — **must not be edited** |

---

## 1. REFERENCE INVENTORY (`design.html`, 1536 lines)

Reference is a Tailwind-CDN standalone mockup ("Actionable Admin Monitoring & Decision Dashboard").
Palette: `twilight #03045E`, `tealblue #0077B6`, `turquoise #00B4D8`, `frosted #90E0EF`, `cyanlight #CAF0F8`.
Fonts: Plus Jakarta Sans + JetBrains Mono.

Sections (in document order):

| # | Reference section | Lines |
|---|---|---|
| A | Left sidebar rail (icon nav + settings/profile/logout) | 81–164 |
| B | Header (brand chip, H1, live badge, search, bell, profile pill) | 165–224 |
| C | KPI cards ×4 (sparkline / histogram / radial gauge / donut gauge) | 225–345 |
| D | Attention banner "Perlu Perhatian Admin Hari Ini" + 3 triage badges | 346–411 |
| E | Inflow vs Resolution dual chart + Net Resolution Gap | 412–570 |
| F | **SLA Risk & Waktu Tersisa** | 571–702 |
| G | Aging / Umur Laporan Aktif (waffle + bars) | 703–832 |
| H | **Choropleth map + treemap per kecamatan** | 833–965 |
| I | **Deteksi Lonjakan & Anomali (Z-Score 3σ)** | 966–1093 |
| J | Backlog & Kinerja per Dinas/Unit (OPD) | 1096–1232 |
| K | Top Problem Categories (lollipop, MoM %) | 1233–1328 |
| L | Monitoring table "Daftar Pemantauan Tiket Kritis & Atensi" | 1334–1448 |
| M | Audit feed + **Integritas Hash SHA-256** | 1449–1535 |

---

## 2. MASTER MAPPING — REFERENCE SECTION → SUPERBIE DATA SOURCE → VIEW → ROLE

Legend: ✅ implementable now (existing schema, no new rule) · ⛔ must NOT implement (no data / no business rule / forbidden).

### A. Sidebar rail → Sidebar (existing)
- **Data source:** nav items already defined per role — `resources/views/super-admin/partials/nav.blade.php` + inline nav arrays in `admin/dashboard`, `operator/dashboard`, `citizen/dashboard`; links guarded by `Route::has()`.
- **View:** `resources/views/components/layouts/dashboard.blade.php` (`<aside>`), nav slot `#primary-navigation`.
- **Roles:** all 4 — **same visual language, different menu set** (unchanged authorization).
- **Action:** restyle only. **No menu may be added that the role cannot access.**

### B. Header → Header (existing)
- **Data source:** `$title`, `$header`, `$breadcrumb` slots; `auth()->user()->name`, `->role`.
- **View:** `components/layouts/dashboard.blade.php` (`#page-heading`, `#page-actions`) + each dashboard view.
- **Roles:** all 4.
- **Note:** header text per task §17 (e.g. "Command Center — Admin") is an *example*; **`DashboardCachingTest` requires the literal string `Dashboard Admin` on `/admin/dashboard`** → keep it (document `<title>` + heading).

### C. KPI — Total Laporan ✅
- **Data source:** `Complaint::count()` (SA/admin) · `operatorScopedQuery()->count()` (operator) · `Complaint::forReporter($id)->count()` (citizen).
- **View/service:** `DashboardCacheService::get{SuperAdmin,Admin,Operator,Citizen}Data()` → `super-admin/dashboard`, `admin/dashboard`, `operator/dashboard`, `citizen/dashboard`.
- **Roles:** all 4, **scoped** (operator = own unit; citizen = own reports).

### D. KPI — Laporan Baru Hari Ini ✅
- **Data source:** `Complaint::whereDate('submitted_at', today())->count()`.
- **Roles:** `admin`, `super_admin` (and operator scoped variant).
- **Note:** exists as `laporanHariIni` for admin only; add scoped variant for operator.

### E. KPI — Laporan Aktif / Backlog ✅
- **Data source:** `Complaint::whereNotIn('status', ['resolved','rejected','closed'])->count()` (`laporanAktif`).
- **Roles:** operator, admin, super_admin.

### F. KPI — Selesai Tuntas ✅
- **Data source:** `Complaint::byStatus('resolved')->count()` (`laporanSelesai`).
- **Roles:** operator, admin, super_admin.
- **Note:** the reference shows a 97.1% "SPM ratio" — the **percentage is a valid derived ratio** only if labelled factually (e.g. "X dari Y laporan"); **no SPM/SLA claim**.

### G. KPI — Total Pengguna / Pengguna Baru ✅
- **Data source:** `User::count()` (`totalPengguna`), `User::whereDate('created_at', today())->count()` (`penggunaBaru`), `roleBreakdown`.
- **Roles:** `super_admin` only.

### H. KPI — Aktivitas Audit ✅
- **Data source:** `AuditLog::whereDate('created_at', today())->count()` (`auditHariIni`).
- **Roles:** `super_admin`, `admin`.

### I. Attention / "Perlu Perhatian" ✅ (with strict limits)
- **Allowed signals (all real, no new rule):**
  - **Belum ditugaskan** → `whereNull('assigned_to')->whereNotIn('status', ['resolved','rejected','closed'])` (operator already has `belumDitugaskan`; **add to admin**).
  - **Umur tertinggi** → active complaints ordered by `submitted_at` ASC.
  - **Backlog tertinggi per Dinas/Unit** → derived from section J counts.
  - **Kategori terbanyak** → derived from section K counts (a *count*, not a "spike").
- **⛔ NOT allowed:** "Lewat SLA", "SLA risk", anomaly/spike thresholds (`Z-Score`, "lonjakan +38%") — SLA undefined + anomaly needs an undefined business rule.
- **Roles:** operator, admin, super_admin.
- **Empty state:** `Belum ada laporan yang perlu perhatian.` (required by `P0RemediationRegressionTest`).

### J. Trend — Inflow vs Resolution ✅ (factual aggregation)
- **Data source:** monthly `Complaint::whereNotNull('submitted_at')->selectRaw("DATE_FORMAT(submitted_at,'%Y-%m') m, count(*)")->groupBy('m')` (inflow) vs `resolved_at` (resolution). **Net Gap = inflow − resolution** (arithmetic on real rows).
- **Roles:** operator (scoped), admin, super_admin.
- **Guard:** if a period has no rows → honest empty state; **no fabricated % growth / MoM / forecast**.
- **Render:** pure Blade + inline SVG bars/lines (no chart library — repo proves none needed).

### K. SLA Risk & Waktu Tersisa ⛔
- **Reason:** SLA is **not an approved business rule**; `P0RemediationRegressionTest::test_admin_dashboard_claims_no_sla` asserts `assertDontSee('SLA')` and `assertDontSee('Terlambat')`.
- **Decision:** NOT implemented. Replaced by the factual **Umur Laporan** (section L).

### L. Aging / Umur Laporan Aktif ✅
- **Data source:** buckets computed from `submitted_at` of **active** complaints:
  `0–1 hari`, `2–3 hari`, `4–7 hari`, `>7 hari` (task §9/§11). Pure date math on stored timestamps.
- **Roles:** operator (scoped), admin, super_admin.
- **Render:** horizontal bars (Blade + Tailwind width %, no JS lib).
- **Guard:** never label as SLA.

### M. Choropleth map + treemap per kecamatan ⛔
- **Reason:** **no geographic/region column exists.** `complaints.location_text` is free text; there is no kecamatan/district entity in schema. Rendering a map would require invented regions → forbidden.
- **Decision:** NOT implemented. If a per-`location_text` view is ever wanted, it must be approved separately.

### N. Anomaly / Spike detection (Z-Score) ⛔
- **Reason:** requires a threshold business rule (σ / "lonjakan") that does **not** exist. `rules.md` defines none.
- **Decision:** NOT implemented. Only plain counts (top categories) are shown.

### O. Backlog per Dinas/Unit ✅
- **Data source:** `Complaint::selectRaw('dinas_unit_id, count(*) c')->groupBy('dinas_unit_id')` joined to `dinas_units.name` (real names only; null unit → "Belum ada Dinas/Unit").
- **Roles:** `super_admin`, `admin` (global). **Operator: only its own `dinas_unit_id`** (no cross-unit leakage).
- **Render:** ranking + progress bars.

### P. Top Categories ✅
- **Data source:** `Complaint::selectRaw('category_id, count(*) c')->groupBy('category_id')` joined to `complaint_categories.name` (real categories only).
- **Roles:** operator (scoped), admin, super_admin.
- **Render:** count + bar; **no MoM %, no "lonjakan" label**.

### Q. Critical monitoring table ✅
- **Data source:** active complaints — `reference_code`, `category.name`, `location_text`, `dinas_unit.name`, age from `submitted_at`, `status` (+label/badge).
- **Service:** reuse/extend `laporanPerhatian` (admin) and `laporanTerbaru` (all).
- **Roles:** operator (scoped), admin, super_admin. Column "Umur Laporan" required on admin complaint surfaces (already exists on `/admin/laporan`).
- **Guard:** no SLA column.

### R. Audit feed ✅
- **Data source:** `AuditLog::with('actor')->orderByDesc('created_at')->limit(n)`; **real action strings only**:
  `complaint_created`, `complaint.status_updated`, `complaint.assigned`, `complaint.category_updated`, `complaint.destination_updated`, `complaint.note_added`, `profile_updated`, `app_setting.updated`, `dinas_unit.{created,updated,deleted}`, `complaint_category.{created,updated,activated,deactivated,deleted}`, `category_mapping.{updated,dinas_added,dinas_removed}`, `auth.login`, `auth.register`.
- **Roles:** `super_admin`, `admin` (existing `auditTerbaru`).
- **Guard:** keep `AuditLogService` redaction; **no invented events**.

### S. Integritas Hash SHA-256 ⛔
- **Reason:** not a real SuperBie feature; `P0RemediationRegressionTest` asserts `assertDontSee('SHA-256')`, `assertDontSee('Integritas Hash')`.
- **Decision:** NOT implemented.

---

## 3. FEASIBILITY SUMMARY

| Capability | Status | Data basis |
|---|---|---|
| KPI cards (all variants) | ✅ | existing counts |
| Attention / priority | ✅ (limited) | unassigned + age + backlog + top category |
| Trend inflow vs resolution | ✅ | `submitted_at` / `resolved_at` monthly |
| Aging buckets | ✅ | `submitted_at` |
| Category distribution | ✅ | `category_id` counts |
| Dinas/Unit distribution | ✅ | `dinas_unit_id` counts |
| Critical monitoring table | ✅ | active complaints |
| Audit feed | ✅ | `audit_logs` |
| SLA / SLA risk / compliance | ⛔ | undefined rule + test guard |
| Geographic map / treemap by region | ⛔ | no geo data |
| Anomaly / Z-score / spike | ⛔ | no threshold rule |
| Integrity hash / SHA-256 | ⛔ | not a feature + test guard |

**New DB columns/migrations required: NONE.** All ✅ items derive from existing columns.

---

## 4. TEST CONTRACTS THAT BIND THE REDESIGN (must not break)

1. **61 routes** — do NOT add routes (`ProgressiveNavigationTest`).
2. DOM hooks must remain: `#main-content`, `#primary-navigation`, `#page-heading`, `#page-actions`; `navigation-identity` meta; `navigation.js` untouched; `Alpine.start()` exactly once.
3. `/admin/dashboard` must contain: `Dashboard Admin`; `Belum ada laporan masuk.`; `Belum ada laporan yang perlu perhatian.`; view vars `totalLaporan`, `laporanAktif`, `laporanSelesai`.
4. `/admin/dashboard` + `/admin/laporan` must NOT contain: `SLA`, `Terlambat` (`assertDontSee`, case-sensitive, unescaped).
5. `/admin/dashboard` must NOT contain: `Drs. M. Sanusi`, `Lowokwaru`, `Klojen`, `Kedungkandang`, `4 OPD Utama`, `2.740`, `Net Backlog`, `SHA-256`, `Integritas Hash`.
6. Citizen dashboard: own complaints only; view vars `totalLaporan`, `laporanProses`, `laporanSelesai`, `laporanDitolak`.
7. Operator dashboard: unit-scoped, no cross-unit leakage.
8. Cache keys preserved: `dashboard:citizen:{id}`, `dashboard:operator:{scope}:{version}`, `dashboard:admin`, `dashboard:superadmin`.
9. `/admin/laporan` keeps `Umur Laporan`; KPI array must not reintroduce `sla_risk`.

---

## 5. PROPOSED DESIGN SYSTEM (Phase 2 — for approval)

Reuse existing CSS classes in `resources/css/app.css` (`stat-card`, `empty-state`, `status-badge`, `nav-link`, `card-enter`, `stagger-item`, `ui-animated`, `table-row-hover`, `page-enter`) and add a small set of **anonymous Blade components** under `resources/views/components/dashboard/`:

`kpi-card`, `section-card`, `badge`, `progress-bar`, `aging-bar`, `attention-banner`, `monitoring-table`, `audit-item`, `empty-state`.

No new dependency, no chart library, no SPA.

---

## 6. OPEN DECISIONS (need approval)

1. **Font:** reference uses *Plus Jakarta Sans*; repo `app.css` body uses *Inter* (design.md). Adopt Jakarta for dashboards, or keep Inter?
2. **Palette:** adopt reference palette (`twilight/tealblue/turquoise/frosted/cyanlight` — already present in `app.css @theme`), or keep design.md tokens (`#1D4ED8`/`#0F766E`)?
3. **Scope:** confirm "dashboard" = the 4 dashboard pages (+ shell), and that `/admin/laporan`, `/super-admin/*` list pages keep their current structure (only receive the shared components).
4. **Admin header text:** keep literal `Dashboard Admin` (test-bound) while adding a "Command Center — Admin" context line — confirm OK.
5. **Operator attention/trend/aging:** confirm operator dashboard gains scoped attention + trend + aging + category distribution (all unit-scoped).

---

## 7. NOT IMPLEMENTED (intentional, per rules)
- SLA anything (undefined rule).
- Geographic map / region treemap (no geo data).
- Anomaly / Z-score / spike detection (no threshold rule).
- Integrity hash / SHA-256 (not a feature).
- Any fake number, name, region, OPD, %, trend, or forecast.
- Any new migration / dependency / SPA / route.

---

## 8. NEXT STEP
On approval of this mapping (and decisions §6), proceed to **Phase 2 — Design System**, then Phase 3 Super Admin → 4 Admin → 5 Operator → 6 Responsive → 7 Testing.
