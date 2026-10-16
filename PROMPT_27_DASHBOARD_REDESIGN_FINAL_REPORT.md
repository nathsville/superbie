# PROMPT 27 — SUPERBIE DASHBOARD VISUAL REDESIGN
## FINAL IMPLEMENTATION REPORT (Phases 2–7)

> **LC-2 CORRECTION NOTE (added by LC-2D, later than this document):** This is a dated implementation report. Several KPI/aggregation definitions below were written against the pre-LC-2 lifecycle and are **superseded** by the LC-2 lifecycle normalization (LC-2C). In particular: "active" = `submitted + in_progress` (legacy statuses `under_review` / `waiting_for_information` / `closed` are history-only); success = `resolved`; the `categoryBacklogDistribution` split is now `active` / `resolved` / `rejected` / `legacy` (the earlier `rejected_closed` bucket was retired); `statusFlow` and `categoryStatusMatrix` stages are the active statuses plus `resolved`; `dinasStatusBreakdown` groups only the active statuses. See `prd.md` §8 / F-014 and `schema.md` §6 for the current source of truth. The historical content below is retained unchanged for traceability.

Reference = `design.html` + `screenshot-reference.png` (**visual truth**).
Repository / DB / security = **business, data, authority truth**. No dummy data.

---

## 1. FILES CHANGED

**Modified**
- `app/Services/DashboardCacheService.php` — added data-derived analytics (aging, category & Dinas distribution, monthly trend, attention summary).
- `resources/views/super-admin/dashboard.blade.php` — Command Center redesign.
- `resources/views/admin/dashboard.blade.php` — Command Center redesign (monitoring only).
- `resources/views/operator/dashboard.blade.php` — scoped Command Center redesign.
- `resources/views/citizen/dashboard.blade.php` — citizen-oriented redesign (design system only).

**Added (Blade components — no new dependency)**
- `resources/views/components/dashboard/kpi-card.blade.php`
- `resources/views/components/dashboard/section-card.blade.php`
- `resources/views/components/dashboard/bar-row.blade.php`
- `resources/views/components/dashboard/trend-chart.blade.php`
- `resources/views/components/dashboard/attention-banner.blade.php`
- `resources/views/components/dashboard/monitoring-row.blade.php`
- `resources/views/components/dashboard/audit-item.blade.php`
- `resources/views/components/dashboard/empty-state.blade.php`

**Added (test + docs)**
- `tests/Feature/CommandCenterAnalyticsTest.php` — new regression guard (5 tests).
- `PROMPT_27_DASHBOARD_REDESIGN_DISCOVERY_MAPPING.md` — Phase 1 mapping.
- `PROMPT_27_DASHBOARD_REDESIGN_FINAL_REPORT.md` — this report.

**Not touched:** routes, middleware, controllers, models, enum, migrations, `navigation.js`, `app.js`, layout shell hooks, dead layout tree (`resources/views/layouts/*`).

---

## 2. VISUAL SECTIONS IMPLEMENTED
Sidebar (existing shell), header + context, KPI cards (responsive 4/2/1), Attention "Perlu Perhatian", main trend (inflow vs resolution + net gap), secondary analytics (status, users, category, Dinas/Unit), Aging "Umur Laporan Aktif", critical monitoring list, audit feed. Same design system across roles.

## 3. DATA SOURCE PER SECTION
| Section | Source |
|---|---|
| KPI Total/Aktif/Selesai/Hari Ini | `Complaint` counts |
| KPI Pengguna | `User::count()`, `roleBreakdown` |
| KPI Audit | `AuditLog::whereDate('created_at', today())` |
| Attention | `whereNull('assigned_to')` + oldest active by `submitted_at` |
| Trend | `submitted_at` vs `resolved_at` monthly; gap = inflow − resolved |
| Aging | `submitted_at` buckets 0–1 / 2–3 / 4–7 / >7 hari |
| Category | `groupBy('category_id')` → `complaint_categories.name` |
| Dinas/Unit | `groupBy('dinas_unit_id')` → `dinas_units.name` (null → "Belum ada Dinas/Unit") |
| Monitoring | active complaints: code, category, location, Dinas, age, status |
| Audit | `AuditLog` real action strings (redaction preserved) |

## 4. ROLE-SPECIFIC DIFFERENCES
- **Super Admin:** global — KPIs + user distribution + Dinas/Unit + category + aging + trend + monitoring + audit.
- **Admin:** monitoring-only — KPIs + attention + trend + category + Dinas/Unit + aging + monitoring + audit. **No management actions.**
- **Operator:** all analytics **scoped to `operator.dinas_unit_id`** (hybrid rule); no cross-unit data. No global fallback (unit-less operator → empty).
- **Masyarakat:** citizen-oriented (own reports only), design system only — no command-center analytics.

## 5. RESPONSIVE BEHAVIOR
KPI grid `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`; analytics `grid-cols-1 lg:grid-cols-2/3`; tables/cards use `min-w-0` + `truncate`; no horizontal overflow.

## 6. SECURITY IMPACT
**None.** Authorization, middleware, roles, operator scoping, audit, CSRF, rate limiting, auth untouched. UI is not an authorization source; server remains authority. Operator analytics reuse `visibleToOperator()` — the single visibility source of truth.

## 7. BUSINESS-RULE IMPACT
**None.** No new status/transition, no new rule, no SLA. Only factual counts + date arithmetic on existing columns.

## 8. TESTS BEFORE
566 tests / 2468 assertions / 0 failed / 0 skipped.

## 9. TESTS AFTER
**571 tests / 2534 assertions / 0 failed / 0 skipped** (+5 tests, +66 assertions from the new guard). All pre-existing tests still pass.

## 10. BUILD RESULT
`npm run build` PASS (Tailwind v4 / Vite). No new dependency.

## 11. ROUTE COUNT
**61** (unchanged — pinned by `ProgressiveNavigationTest`).

## 12. MIGRATION STATUS
All migrations `Ran`. **No new migration.** `…100008` now `Ran` in dev (pre-existing state; not triggered by this task).

## 13. KNOWN LIMITATIONS
- Trend/aging depend on `submitted_at`/`resolved_at` being populated; sparse data → honest empty state.
- Distribution limited to top N (category 5, Dinas 6) to keep cards scannable.
- No historical backfill beyond existing rows.

## 14. ITEMS INTENTIONALLY NOT IMPLEMENTED
- **SLA / SLA risk / compliance** — undefined rule (+ test guard forbids the string).
- **Geographic map / region treemap** — no geo data (`location_text` is free text).
- **Anomaly / Z-score / spike detection** — no threshold rule.
- **Integrity hash / SHA-256** — not a feature (+ test guard).
- Fake numbers, names, regions, OPDs, percentages, trends, forecasts.
- New migration / dependency / SPA / route / API.

---

## ADDENDUM — SHARED SHELL RESTYLE (SIDEBAR ICON RAIL + NAVBAR)

Reference = `Banking Dashboard UI – Modern Finance Web App Design.jpg` (**visual truth only**).
Shapes/layout cloned; colors stay the repository's blue/teal brand tokens.

**Modified**
- `resources/views/components/layouts/dashboard.blade.php` — sidebar rebuilt as a narrow floating icon rail (desktop) / full drawer (mobile); navbar rebuilt (logo link, page heading, functional search, honest notification placeholder, display-only profile, `#page-actions`).
- `resources/views/admin/dashboard.blade.php`, `admin/complaints.blade.php`, `admin/audit-log.blade.php` — nav slots converted to rail.
- `resources/views/operator/dashboard.blade.php`, `operator/complaint/index.blade.php`, `operator/complaint/show.blade.php` — nav slots converted to rail.
- `resources/views/citizen/dashboard.blade.php`, `citizen/history.blade.php`, `citizen/complaint/create.blade.php`, `citizen/complaint/show.blade.php`, `citizen/profile/edit.blade.php` — nav slots converted to rail.
- `resources/views/super-admin/partials/nav.blade.php` — nav array now carries icons; renders via the rail component; disabled placeholders kept (no dead links).
- `resources/css/app.css` — added `.nav-rail-link` / `.nav-rail-icon` styles (icon square on `lg+`, labelled row in the mobile drawer). **`.nav-link` left untouched.**

**Added**
- `resources/views/components/dashboard/nav-rail-link.blade.php` — reusable rail link (icon + `.sr-only`/drawer label, `title`, `aria-current`/`aria-disabled`, `$attributes` merge).
- `resources/views/citizen/partials/nav.blade.php` — shared citizen nav (dedupe of 5 identical blocks).

**Navbar search — functional, per role (no new route)**
| Role | Wired? | Endpoint | Param |
|---|---|---|---|
| admin | yes | `admin.complaints` | `q` |
| super_admin | yes | `super-admin.complaints.index` | `q` |
| operator | yes | `operator.complaint.index` | `search` |
| masyarakat | hidden | — (no searchable list) | — |

**Accessibility / contract preserved**
- DOM hooks `#primary-navigation`, `#page-heading`, `#page-actions`, `#main-content` kept intact; POST logout form + CSRF kept.
- Labels remain in the DOM (`.rail-label`, visually clipped on the collapsed rail) so `assertSee('Semua Laporan')` + route-URL assertions still pass.
- Notifications = disabled placeholder (SuperBie has no notification system) → **no fabricated unread badge**.

**Rail open/close behaviour (desktop `lg+`)**
- The rail is a narrow icon strip by default and **expands on CLICK — never on hover**: the navbar menu button (`toggleRail()`, with `.stop` so it is not re-collapsed by the outside-click handler) or a click on the rail's own surface (`onRailSurfaceClick()`, ignoring clicks on links/buttons/forms) expands it.
- A **second click**, the in-rail **collapse button**, or a **click outside** (`collapseRail()`, wired to the main area) collapses it again.
- A small **pin toggle** in the sidebar header keeps the rail **permanently open**; `aria-pressed` reflects state. When pinned, the collapse/outside actions are no-ops.
- Width uses a smooth `width` transition (`--motion-slow`, `--ease-standard`), enabled only after Alpine applies the persisted state (`.sb-rail-ready`) so a pinned rail does not animate on first paint.
- Mobile keeps the off-canvas drawer (`sidebarOpen`) unchanged.

**Layout: docked flex sibling (no overlay on desktop)**
- Desktop is a **Flexbox** row: the root is `lg:flex lg:items-start`; the rail is `lg:sticky lg:top-3 lg:shrink-0` (docked sibling, **not** `position: fixed`/`absolute`) and the content is `min-w-0 flex-1`. The rail's `width` change **reflows the content beside it**, so the rail never covers the title, KPI cards or charts.
- `position: fixed` + translate (off-canvas drawer) applies **only below `lg`** (mobile); the desktop rail is `lg:translate-x-0` and docked.
- The earlier "fake docking" via `padding-left` on the content (`lg:pl-[6.25rem]` / `lg:pl-[17.5rem]`) was **removed** — the content width now tracks the rail automatically. `DashboardShellSidebarTest` asserts this.

**Pin persistence (localStorage)**
- The pinned preference is stored under the namespaced key **`sb.sidebar.pinned`** (`'1'`/`'0'`), read in Alpine `init()` and written in `togglePin()`.
- Access is wrapped in `try/catch`, so a blocked or unavailable storage degrades to a session-only toggle (no crash).
- **Scope guard:** this is a UI preference only. It lives in the Blade/Alpine shell — **not** in `resources/js/navigation.js`, whose no-web-storage rule (never cache authenticated HTML) stays enforced by `ProgressiveNavigationTest`. `DashboardShellSidebarTest` additionally asserts the preference never appears in the navigation module.

**Verification after this round:** **577 tests / 2646 assertions / 0 failed / 0 skipped** (incl. `tests/Feature/DashboardShellSidebarTest.php` — 6 tests: click-to-toggle, no hover state, docked flex layout, toggle propagation); `npm run build` PASS; **route count 61** (unchanged); no new migration/dependency; `navigation.js` / `app.js` untouched.

---

## ADDENDUM 2 — CLICKABLE PROFILE + RAIL ACCOUNT/LOGOUT SWAP

Requested changes: (1) make the topbar profile interactive (link to the profile page, hover feedback, `cursor: pointer`); (2) move the rail's account block from the bottom **up to the top** (below the brand header, above `#primary-navigation`) and move **Logout** to the rail's bottom footer.

**Blocking finding (resolved with the user)**
- Only citizens had a profile page (`citizen.profile.edit`). Staff (admin / operator / super_admin) had **no** profile destination, so a staff topbar link would have been a **dead link** (a false affordance). The user **approved adding one real, read-only staff profile route + page**.

**Added (approved)**
- `routes/web.php` — `GET /profil` → `staff.profile` (`role:operator,admin,super_admin`). **Read-only, GET only** — no mutation route, so role/Dinas-Unit assignment stays Super-Admin-only (Prompt 9/21). Route count **61 → 62**.
- `app/Http/Controllers/Staff/StaffProfileController.php` — renders the authenticated staff member's own record; resolves Dinas/Unit lazily (operators only).
- `resources/views/staff/profile/show.blade.php` — bento read-only account page (identity card, `Informasi Akun` details, read-only notice). Dinas/Unit row is rendered **only for operators** (Prompt 15); nothing is fabricated.
- `tests/Feature/StaffProfileTest.php` — 6 tests: all staff roles reachable + real data rendered, operator shows its unit, admin has no unit row, citizen 403, guest redirect, no `/profil` mutation route.

**Changed**
- `resources/views/components/layouts/dashboard.blade.php`
  - Added `$profileRoute` = `citizen.profile.edit` (masyarakat) / `staff.profile` (staff) — **every role links to a real page**.
  - **Topbar profile** is now an `<a class="sb-topbar-profile … cursor-pointer">` (was a static `<div>`), with hover feedback.
  - **Rail account** (`a.sb-rail-profile`) moved **above `#primary-navigation`** (outside the swapped slot) and links to the profile page.
  - **Logout** moved to a bottom `div.sb-rail-footer` (still a real POST form + CSRF).
- `resources/css/app.css` — replaced `.sb-rail-user` with `.sb-rail-profile` (centred avatar collapsed / row expanded), added `.sb-rail-footer`, `.sb-rail-logout` (circular icon collapsed / labelled full-width expanded), `.sb-topbar-profile` hover.
- `tests/Feature/DashboardShellSidebarTest.php` — 2 new tests pin the clickable profile link per role and the profile-above-nav / logout-footer-below-nav ordering (`.sb-rail-user` must be gone).
- `tests/Feature/ProgressiveNavigationTest.php` — route inventory pin updated 61 → 62 (with the rationale).

**Verification after this round:** **591 tests / 2739 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (61 baseline + 1 approved read-only staff profile); no new migration/dependency; `navigation.js` / `app.js` untouched; authorization/middleware/roles/operator scoping unchanged.

---

## ADDENDUM 3 — RAIL ORDER REVISED (PROFILE BACK TO THE BOTTOM)

Requested revision: the account row returns to the **bottom** of the rail, with **Logout directly ABOVE it**, and **no account block under the logo**. Final order top → bottom: **[Logo] → [Menu utama] → [spacer] → [Logout] → [Profil]**.

**Changed**
- `resources/views/components/layouts/dashboard.blade.php`
  - Removed the account link from the top of the rail (it no longer sits below the brand header); `#primary-navigation` now starts directly after the header divider.
  - `div.sb-rail-footer` (below `#primary-navigation`, still outside the swapped slot) now stacks **Logout first, then the account link** (the account link is the rail's very last element and remains a real link to the profile page).
  - `#primary-navigation` keeps `flex-1`, acting as the spacer that pushes the footer to the bottom.
- `resources/css/app.css` — `.sb-rail-footer` is now a vertical flex column (`gap: 0.25rem`); `.sb-rail-profile` desktop margins moved into the footer padding (collapsed = centred avatar, expanded = row); logout unchanged (circular icon collapsed / labelled full-width expanded).
- `tests/Feature/DashboardShellSidebarTest.php` — the ordering test now pins `[nav] → [footer] → [logout] → [profile]` and asserts the profile is no longer above the navigation.

**Verification after this round:** **591 tests / 2741 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 4 — ADMIN COMPLAINTS TABLE READABILITY

Requested: cleaner hierarchy/contrast, roomier table rows with striping, a single tidy filter row, and a professional Aksi button.

**Changed** (`resources/views/admin/complaints.blade.php`, page-scoped `<style>` + markup only — no shared CSS, no route, no controller change)
1. **Hierarchy & contrast** — new row classes: `.ml-title` (bold, real `margin-bottom` before the reporter line), `.ml-reporter` (`#64748B`, up from `#94A3B8`), `.ml-ref` (`#334155` on `#F1F5F9`), `.ml-date`/`.ml-time`, `.ml-muted`. Secondary text darkened for legibility.
2. **Row spacing & layout** — `td` vertical padding raised `0.75rem → 0.9375rem`; row divider `#F1F5F9 → #E2E8F0`; **subtle zebra striping** (`tr:nth-child(even)` → `#FAFBFC`) with the hover tint kept.
3. **Filter bar** — replaced the wrapping flex row with a single `.filter-grid` (search grows, fixed-width Status/Kategori/Urutkan, buttons aligned `end`); collapses to 2 columns `< 1024px` and 1 column `< 560px`.
4. **Aksi column** — literal `Detail >` replaced with a clean ghost/secondary pill (`.btn-detail`: white bg, hairline border, eye icon + "Lihat").

**Honest-UI note (user-approved):** Admin is monitoring-only and has **no detail route**, so the Aksi affordance stays **non-interactive** (`cursor: default`, tooltip "Admin hanya memantau (read-only) …") — never a dead link. `P0RemediationRegressionTest` now asserts `Route::has('admin.complaints.show') === false` and that no per-complaint admin URL is rendered.

**Verification after this round:** **593 tests / 2751 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency; SLA still absent (`Umur Laporan` retained).

---

## ADDENDUM 5 — SIDEBAR FOOTER PROPORTIONS (TOGGLE + GROUP)

Requested: shrink the expand chevron, put it in a small round button, and tidy the footer into one balanced group with an icon-only Logout when collapsed.

**Root cause of the oversized chevron:** the old chevron sat inside the profile row carrying `.rail-label`, whose expanded rule (`.sb-rail-expanded .rail-label`) sets `width/height: auto` at higher specificity than Tailwind's `w-4 h-4` — so the SVG grew to its intrinsic box when the rail opened.

**Changed** (`resources/views/components/layouts/dashboard.blade.php`, `resources/css/app.css`)
- New footer group: `.sb-rail-footer` → `.sb-rail-footer-actions` (Logout + toggle) → `.sb-rail-profile` (account row). Order stays **[Menu] → [spacer] → [Logout + toggle] → [Profil]**, all outside `#primary-navigation`.
- **Toggle** — dedicated `.sb-rail-expand` **round button** (`2rem`, `border-radius: 9999px`) with a **16px** `.sb-rail-expand-icon` (`.rail-label` no longer touches it). It reuses the existing `toggleRail()` handler, is `:disabled` while pinned, rotates 180° when expanded, and is **desktop-only** (`display: none` → `inline-flex` at `lg`).
- **Balanced spacing** — footer `gap: 0.375rem`, action-row `gap: 0.375rem`, tighter padding; collapsed = centred compact column (circular icon-only Logout `2.25rem`, round toggle, centred avatar) so it no longer wastes vertical space.
- **Logout label** — hidden automatically when collapsed via the existing `.rail-label` clipping (icon-only); the full "Logout" label shows when expanded.
- `tests/Feature/DashboardShellSidebarTest.php` — added `test_rail_footer_groups_logout_toggle_and_profile` pinning the group, the round toggle in the footer (below the nav), and that it never reuses the removed `sb-rail-toggle` / `sb-rail-collapse` names.

**Verification after this round:** **594 tests / 2762 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 6 — DECISION-ORIENTED TREND CHART (ADMIN)

Requested: refocus the admin "Tren Laporan" chart on decisions — three metrics, quick summary cards, clear legend, and hover tooltips.

**Data (`app/Services/DashboardCacheService.php`)**
- `monthlyTrend()` now also returns a factual **`backlog`** per month — a running balance of `Σ masuk − Σ selesai` over the displayed window. Pure arithmetic on two real counts (no SLA, no forecast, no percentage). Shared by admin/operator/super_admin; existing keys (`inflow`/`resolved`/`gap`) are unchanged.

**New component (`resources/views/components/dashboard/trend-lines.blade.php`)**
- Multi-line inline SVG (**no chart library / no dependency**): **Masuk (blue `#2563EB`)**, **Selesai (green `#0F766E`)**, **Akumulasi Belum Selesai (amber `#F59E0B`)**.
- Y axis uses a **rounded "nice" max** with 5 labelled gridlines (real values); X axis shows the real month labels.
- **Legend** for all three lines below the chart.
- **Hover tooltips**: one transparent band per month reveals a guide line + a dark tooltip listing **Masuk / Selesai / Belum selesai** for that month (CSS-only `:hover`, reduced-motion aware, no JS).

**Admin card (`resources/views/admin/dashboard.blade.php`)**
- Title → **"Tren Masuk vs Selesai vs Belum Selesai"**; sub-description → "Perbandingan laporan masuk, laporan selesai, dan penumpukan belum selesai per bulan."
- **3 summary cards above the chart**: Total Masuk (bulan ini), Total Selesai (bulan ini), and **Pertumbuhan Belum Selesai** with a prominent indicator — red "+n · menumpuk" when the backlog grows, green "menyusut" when it shrinks, neutral "stabil" otherwise (factual delta, never a %).
- Keeps the bento look (borderless white `soft-card`, deep radius, soft shadow); the empty-state path is preserved.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 3 new: cumulative backlog is factual, the chart is decision-oriented (title/subtitle/3 cards/legend, no SLA), and tooltip bands render.

**Verification after this round:** **597 tests / 2784 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 7 — "BEBAN DINAS/UNIT × STATUS" STACKED BAR (ADMIN)

Requested: turn the admin "Beban per Dinas/Unit" list into a **stacked horizontal bar chart** broken down by active status, with sorting, legend and hover tooltips.

**Data (`app/Services/DashboardCacheService.php`)**
- New `dinasStatusBreakdown()` returns, per Dinas/Unit, the **active-only** counts grouped by the four non-terminal statuses (`submitted` / `under_review` / `in_progress` / `waiting_for_information`) plus the row `total`. Rows are **sorted by active load descending** (highest backlog first). Real counts only — no SLA, no forecast.
- `dinasDistribution()` (total-load version) is untouched and still used by the Super Admin dashboard; the Admin `getAdminData()` now exposes `dinasStatusBreakdown` instead.

**New component (`resources/views/components/dashboard/dinas-stacked-bar.blade.php`)**
- **Stacked horizontal bars** — each Dinas/Unit row is `name | stacked bar | total`; bar width is a factual proportion of the busiest row, and each status segment is proportional to its share of that row.
- **Status colours** mirror `ComplaintStatus::color()` (single source of truth for the colour name): Diajukan `#3B82F6`, Sedang Ditinjau `#EAB308`, Sedang Diproses `#6366F1`, Menunggu Informasi `#F97316`.
- **Total** shown at the right end of each row.
- **Legend** of the four active statuses above the chart.
- **Hover tooltip** per segment: `"<Dinas> - <Status>: n Laporan"` (CSS-only `:hover`, reduced-motion aware, no JS/dependency).

**Admin card (`resources/views/admin/dashboard.blade.php`)**
- Title → **"Beban Dinas/Unit × Status"**; sub-description → "Distribusi status laporan aktif yang sedang ditangani oleh masing-masing Dinas/Unit."; honest empty state → "Belum ada laporan aktif per Dinas/Unit."

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 2 new: the breakdown counts only active statuses and sorts by load; the stacked chart renders the title/subtitle, segments, tooltip text and status legend (no SLA).

**Verification after this round:** **599 tests / 2802 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 8 — "DISTRIBUSI UMUR LAPORAN AKTIF" (ADMIN)

Requested: give the admin aging card a firmer, decision-oriented read on handling timeliness.

**New component (`resources/views/components/dashboard/aging-distribution.blade.php`)**
- Horizontal bars for the four factual age buckets (`0–1 hari`, `2–3 hari`, `4–7 hari`, `>7 hari`) computed from real `submitted_at` values by the existing `agingBuckets()` service method (no new data, no SLA).
- The oldest bucket (`>7 hari`) is **visually distinguished** (rose bar + rose count) and, **only when its count is > 0**, carries an inline **"⚠ Perlu Perhatian"** badge next to the number.
- **Absolute counts** on the right of every row.
- **Hover tooltip** per row showing the bucket's share of the active total (e.g. `"4–7 hari: 3 laporan • 42% dari aktif"`) — CSS-only `:hover`, reduced-motion aware, no JS/dependency.

**Admin card (`resources/views/admin/dashboard.blade.php`)**
- Title → **"Distribusi Umur Laporan Aktif"**; sub-description → "Pantauan lama waktu penanganan laporan yang masih berstatus aktif."; empty state unchanged ("Belum ada laporan aktif.").
- The shared `bar-row` usage was replaced here only; the Operator/Super Admin aging cards are untouched.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 2 new: the four buckets render with the attention badge + tooltip share, and the badge is absent when the `>7 hari` bucket is 0.

**Verification after this round:** **601 tests / 2822 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 9 — "KATEGORI DENGAN BELUM SELESAI TERTINGGI" (ADMIN)

Requested: shift the admin category card from raw totals to an accumulation (backlog) view.

**Data (`app/Services/DashboardCacheService.php`)**
- New `categoryBacklogDistribution()` returns, per category: `total` (masuk), `active` (belum selesai = non-terminal), `resolved`, and `rejected_closed` — all real counts from `status`. Rows are **sorted by active backlog descending** (ties broken by total). Exposed as `kategoriBacklog` on `getAdminData()`; the shared `kategoriTeratas` (used by Operator/Super Admin) is untouched.

**New component (`resources/views/components/dashboard/category-backlog.blade.php`)**
- **Dual horizontal bars** per category: a soft **grey** bar for *Total Laporan Masuk* (comparison) and an **orange** bar for *Belum Selesai* (contrast). Widths are factual proportions of the busiest category.
- Right-side label shows **"N aktif dari M total"**.
- **Hover tooltip** lists *Total masuk / Sudah selesai / Masih aktif* (and rejected/closed when present) — CSS-only `:hover`, reduced-motion aware, no JS/dependency.

**Admin card (`resources/views/admin/dashboard.blade.php`)**
- Title → **"Kategori dengan Belum Selesai Tertinggi"**; sub-description → "Peringkat kategori laporan berdasarkan akumulasi laporan aktif yang belum tuntas."; empty state unchanged.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 2 new: total-vs-active split with correct backlog sorting, and the dual-bar markup + tooltip figures render (no SLA).

**Verification after this round:** **603 tests / 2839 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency. (One intermittent failure during the run was a pre-existing faker slug collision in `SuperAdminComplaintManagementTest`, unrelated to this change — green on re-run.)

---

## ADDENDUM 10 — "KATEGORI × STATUS (HEATMAP BOTTLENECK)" (ADMIN)

Requested: add a new matrix component to spot process bottlenecks at a glance.

**Data (`app/Services/DashboardCacheService.php`)**
- New `categoryStatusMatrix()` returns, per category, the real count for each of the five displayed statuses (`submitted` / `under_review` / `in_progress` / `waiting_for_information` / `resolved`) plus `total` and `active`. Rows are **sorted by active backlog descending**. Exposed as `kategoriMatrix` on `getAdminData()`. No SLA, no forecast.

**New component (`resources/views/components/dashboard/category-status-heatmap.blade.php`)**
- A compact **matrix table**: rows = categories, columns = statuses (short headers: Diajukan / Ditinjau / Diproses / Menunggu Info / Selesai).
- **Dynamic heat colouring** per cell — `0` = neutral `#F8FAFC`; otherwise an orange ramp `rgba(234,88,12, α)` scaled `0.14 → 0.92` against the busiest cell, with auto text contrast. A small **intensity legend** (Rendah → Tinggi) sits under the table.
- **Hover tooltip** per cell: `"<Kategori> - <Status>: n Laporan"` (CSS-only `:hover`, reduced-motion aware, no JS/dependency).
- Compact + responsive (`overflow-x-auto` guard, short headers, truncating category labels) so it fits the dashboard grid without heavy horizontal scroll.

**Admin card (`resources/views/admin/dashboard.blade.php`)**
- New full-width bento card after ROW D: title → **"Kategori × Status (Heatmap Bottleneck)"**; sub-description → "Matriks konsentrasi status laporan per kategori untuk mengidentifikasi titik penumpukan proses."; honest empty state.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 2 new: the matrix counts per status and sorts by backlog; the heatmap renders the title/subtitle, column headers, cell tooltip and intensity legend (no SLA).

**Verification after this round:** **605 tests / 2858 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 11 — "LAPORAN BELUM DITUGASKAN" STANDALONE KPI + AGE BREAKDOWN (ADMIN)

Requested: promote the "Belum Ditugaskan" indicator into a standalone KPI card with an age breakdown and a quick-access action.

**Data (`app/Services/DashboardCacheService.php`)**
- Extended `attentionSummary()` with `belumDitugaskanTotal` + `belumDitugaskanBuckets` (`0–1 hari` / `2–3 hari` / `>3 hari`). The buckets are the **factual age since `submitted_at`** of reports where `assigned_to` IS NULL and status is active — **not an SLA**. The raw `belumDitugaskan` count and `laporanTertua` keys are unchanged (existing consumers unaffected).

**New component (`resources/views/components/dashboard/unassigned-kpi.blade.php`)**
- Standalone bento KPI card: big headline count ("N Laporan"), a prominent **"⚠ Perlu Segera Ditindaklanjuti"** badge when count > 0 (calm "Semua Sudah Ditugaskan" state when 0), title **"Laporan Belum Ditugaskan"**, sub-description "Laporan masuk yang belum mendapatkan alokasi Dinas/Unit atau operator."
- **Age-of-delay micro bars** (0–1 / 2–3 / >3 hari); the `>3 hari` bucket gets a rose accent + its own "Perlu Perhatian" badge when > 0.
- Quick-access CTA **"Buka Monitoring Laporan →"** pointing to the **real** filtered list `/admin/laporan?assigned=unassigned`.

**Real filter (no dead link)**
- `AdminDashboardController::complaints()` now honours `assigned=unassigned` (`whereNull('assigned_to')` + active-only). The admin complaints page shows the matching filter chip + preserves it via a hidden field. No new route.

**Admin dashboard (`resources/views/admin/dashboard.blade.php`)**
- New ROW A.2 renders the standalone card; the duplicate inline "belum ditugaskan" alert row was removed from the "Perlu Perhatian" card (the oldest-report row stays).

**Tests** — 3 new in `CommandCenterAnalyticsTest` (bucket split + excludes assigned; card badge/buckets/action render; calm state when none) and 1 new in `P0RemediationRegressionTest` (the `assigned=unassigned` filter returns only unassigned-active reports).

**Verification after this round:** **609 tests / 2886 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 12 — "BOTTLENECK STATUS LAPORAN" FUNNEL (ADMIN)

Requested: a funnel-like chart showing where reports pile up across the workflow stages.

**Data (`app/Services/DashboardCacheService.php`)**
- New `statusFlow()` returns real counts for the five displayed stages (`submitted` / `under_review` / `in_progress` / `waiting_for_information` / `resolved`) plus `active_total`, `flow_total`, and the auto-detected **`bottleneck`** (busiest ACTIVE stage; earliest stage wins ties). Each stage carries its `share` (active stages → % of the active pile; `resolved` → % of all reports) and a matching `share_label`. No SLA, no forecast.

**New component (`resources/views/components/dashboard/status-flow.blade.php`)**
- **Funnel-style horizontal bars** per stage, coloured via `ComplaintStatus::color()` (enum stays the single source of truth for the colour name).
- The busiest active stage gets an amber accent + **"⚠ Titik Penumpukan Utama"** badge (`bsl-bottleneck`) and a ring on its bar.
- **Exact count at the right of each bar**; **hover tooltip** shows the stage's share of the active pile (CSS-only `:hover`, reduced-motion aware, no JS/dependency).

**Admin dashboard (`resources/views/admin/dashboard.blade.php`)**
- ROW D.2 became a two-up bento row: the new bottleneck funnel (`lg:col-span-5`) beside the existing Category × Status heatmap (`lg:col-span-7`). Honest empty state when there is no data.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 3 new: stage counts + bottleneck detection + active share (50.0%); card renders the five bars, the flag and the tooltip; no flag when there are zero active reports.

**Verification after this round:** **612 tests / 2909 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 13 — "PERSENTASE PENYELESAIAN PER KATEGORI" (ADMIN)

Requested: a per-category completion progress list to expose the lagging sectors.

**Data (`app/Services/DashboardCacheService.php`)**
- New `categoryCompletion()` returns, per category, real `total` + `resolved` counts, a factual `percent` (resolved/total, mirroring the dashboard-wide "Tingkat Penyelesaian" KPI) and a `tier` (`high` > 70 / `mid` 40–70 / `low` < 40). Rows are **sorted by completion ascending** so the most lagging categories surface first. Exposed as `kategoriPenyelesaian` on `getAdminData()`. No SLA, no forecast.

**New component (`resources/views/components/dashboard/category-completion.blade.php`)**
- Per-category **progress bar** with a **dynamic colour tier** (green `#22C55E` / amber `#F59E0B` / rose `#EF4444`) driven only by the factual ratio.
- Right side shows the **ratio** (`X/Y`) and a tinted **percentage chip**.
- **Hover tooltip**: `"<Kategori>: X dari Y laporan selesai"` (CSS-only `:hover`, reduced-motion aware, no JS/dependency).

**Admin dashboard (`resources/views/admin/dashboard.blade.php`)**
- New full-width bento row (ROW D.3) after the bottleneck row: title → **"Persentase Penyelesaian per Kategori"**; sub-description → "Rasio perbandingan laporan selesai terhadap total laporan masuk pada masing-masing kategori."; honest empty state.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 2 new: ratio + tier + lagging-first sort; the card renders the ratio, percentage and tooltip breakdown (no SLA).

**Verification after this round:** **614 tests / 2926 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 14 — "DINAS/UNIT DENGAN LAPORAN BERUMUR PANJANG" (ADMIN)

Requested: monitor the units holding the longest-stalled reports.

**Data (`app/Services/DashboardCacheService.php`)**
- New `dinasLongAging()` counts **ACTIVE reports older than 7 days** per Dinas/Unit, using the same `>7 hari` cutoff as `agingBuckets()` (so the two cards agree). Rows are **sorted by long-age load descending**; each row carries the unit's **oldest** such report (`reference_code`, `category`, `age_days`) for the tooltip. Unrouted complaints (`dinas_unit_id` NULL) are grouped honestly as "Belum ada Dinas/Unit". Exposed as `dinasLongAging` on `getAdminData()`. No SLA, no forecast.

**New component (`resources/views/components/dashboard/dinas-long-aging.blade.php`)**
- **Horizontal bars** with a warning (rose) accent; absolute count at the right of each bar.
- **"⚠ Perlu Perhatian"** badge (`dla-attention`) only when a unit has **more than 3** long-age reports.
- **Hover tooltip**: unit name + long-age count + the oldest report's reference code, category and age (CSS-only `:hover`, reduced-motion aware, no JS/dependency).

**Admin dashboard (`resources/views/admin/dashboard.blade.php`)**
- New full-width bento row (ROW D.4): title → **"Dinas/Unit dengan Laporan Berumur Panjang"**; sub-description → "Jumlah laporan aktif yang belum tuntas dan telah melebihi masa penanganan 7 hari."; honest empty state.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 3 new: only >7-day ACTIVE reports counted + resolved excluded + descending sort + oldest payload; the card renders bars, the >3 warning badge and the oldest-report tooltip; the badge is absent at exactly 3.

**Verification after this round:** **617 tests / 2947 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 15 — "TREN TINGKAT PENYELESAIAN" (ADMIN)

Requested: a monthly completion-rate line/area chart to track handling effectiveness over time.

**Derivation (no new query)**
- The rate is computed in the component from the **existing `trenBulanan`** series: `rate = (resolved in month ÷ inflow in month) × 100`. A month with **zero inflow has no factual rate**, so it is honestly left as a gap (never a fabricated 0%). New guard `$hasCompletionTrend` drives the honest empty state.

**New component (`resources/views/components/dashboard/completion-trend.blade.php`)**
- **Inline-SVG line + soft area chart** (teal `#0F766E`), no chart library/dependency. **Y axis fixed 0%–100%** (labels 0/25/50/75/100 %).
- **Headline** shows the latest month's rate prominently, plus a **growth badge vs the previous non-null month** (green ↑ `+N%` / red ↓ `N%` / neutral "stabil").
- **Hover tooltip** per month: `"<Bulan>: <rate>% — X dari Y laporan selesai"` (CSS-only `:hover`, reduced-motion aware, no JS).
- Null-rate months break the line into honest segments (no misleading interpolation).

**Admin dashboard (`resources/views/admin/dashboard.blade.php`)**
- New full-width bento row (ROW B.2) right after the main trend row: title → **"Tren Tingkat Penyelesaian"**; sub-description → "Perkembangan persentase tingkat penyelesaian laporan dari bulan ke bulan."; honest empty state.

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 2 new: monthly rate + growth badge + tooltip + 0–100% axis; honest empty state when there is no inflow.

**Verification after this round:** **619 tests / 2966 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 16 — FINAL LAYOUT POLISH (ADMIN): AUDIT FEED + DONUT REMOVAL

Requested: final pass to clean up the audit feed and tighten the bento grid.

**1. "Aktivitas Audit Terkini" activity feed (`resources/views/components/dashboard/audit-item.blade.php`)**
- Reworked into a **timeline/activity-feed list** (explicitly NOT a chart): avatar initial + **actor name** + **role badge**, then the factual **action** (mono chip) and **relative time** on one line.
- Card header now has an explicit **"Lihat Semua Audit Log →"** action linking to the full `admin.audit-log` page (top-right).

**2. Donut chart removed + grid reorganised (`resources/views/admin/dashboard.blade.php`)**
- Deleted the **"Tingkat Penyelesaian" donut** card (its info is already covered by the KPI cards + "Tren Tingkat Penyelesaian").
- The freed `lg:col-span-4` slot now hosts the **"Dinas/Unit dengan Laporan Berumur Panjang"** card (moved up from the old full-width ROW D.4, which was removed to avoid duplication). Unused `$rate` var dropped.
- Orphaned `resources/views/components/dashboard/donut-gauge.blade.php` deleted (no remaining references).

**3. Final grid balance** — every row now sums to 12 columns (B: 8+4, C: 5+4+3, D: 4+4+4, D.2: 5+7, E: 7+5), keeping the bento grid dense, symmetric and gap-free; responsive behaviour unchanged (single column on mobile, `lg:grid-cols-12` on desktop).

**Tests (`tests/Feature/CommandCenterAnalyticsTest.php`)** — 2 new: the audit feed renders actor/role/action/relative-time + the "Lihat Semua Audit Log →" action; the completion donut is gone and its space hosts the long-age card exactly once (no duplicate).

**Verification after this round:** **621 tests / 2974 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 17 — FIX: SPURIOUS `GET /admin/%EF%BF%BD` 404 ON "MONITORING LAPORAN"

Reported symptom: opening the admin "Monitoring Laporan" page (`/admin/laporan`) logged a console 404 for `GET http://localhost:8000/admin/%EF%BF%BD`. `%EF%BF%BD` is the URL-encoded form of **U+FFFD (REPLACEMENT CHARACTER)** — so the browser was requesting a URL whose single path segment was the replacement character.

**Root cause — a malformed inline `style` attribute, not a bad file.**
The three filter `<select>` elements carried an inline attribute of the form `style="…background-image:url(\"data:image/svg+xml,…\");…"`. HTML does **not** treat `\"` as an escape sequence, so the parser terminated the attribute value at the **first** `"` after `url(`. The browser was left holding the CSS fragment:

```css
background-image:url(\
```

A **trailing backslash inside a CSS `url()` token is an escape with nothing to escape**, which the CSS tokenizer resolves to **U+FFFD**. The browser then treated that token as a **relative URL** and resolved it against the page's directory (`/admin/`) → `GET /admin/%EF%BF%BD` → 404.

(This is why the earlier byte/UTF-8 scans of every source, compiled-view and build file came back clean: the replacement character was **generated by the browser's CSS parser at runtime**, never present on disk.)

**Fix (`resources/views/admin/complaints.blade.php`)**
1. The chevron data-URI now lives in the page stylesheet as a single `.filter-select` rule (a stylesheet has no HTML-attribute quoting problem), and all three `<select>`s use `class="filter-input filter-select"` — the malformed inline attributes are gone.
2. **Secondary fix (quirks mode):** the page `<style>` block was emitted **before `<!DOCTYPE html>`**, which forces the document into quirks mode and can distort the bento grid. It is now wrapped in `@push('head') … @endpush` so it renders inside `<head>` (the layout already exposes `@stack('head')`). No other page emitted pre-doctype content.

**Tests (`tests/Feature/P0RemediationRegressionTest.php`)** — 1 new (`test_admin_complaints_select_styles_do_not_emit_a_relative_url`): the rendered HTML must never contain the truncated `url(` + backslash token, all three `<select>`s opt into `filter-select`, and the page stylesheet must render **after** the doctype (guards against the quirks-mode regression).

**Verification after this round:** **622 tests / 2980 assertions / 0 failed / 0 skipped**; `npm run build` PASS; **route count 62** (unchanged); no new migration/dependency.

---

## ADDENDUM 18 — PROMPT 2/4: ADMIN DASHBOARD BACKEND & DATA (NO UI CHANGE)

Backend-only pass on the Admin dashboard data source. **No Blade/layout/chart change** in this round (UI restructure is Prompt 3/4).

**1. New monitoring metric — `belumDinasUnit` (`app/Services/DashboardCacheService.php` → `attentionSummary()`)**
- Exact definition: count of **ACTIVE** complaints (`status NOT IN ('resolved','rejected','closed')`) where **`dinas_unit_id IS NULL`**. Active uses the existing shared definition; no new status, no data mutation.
- `assigned_to` and `dinas_unit_id` stay **separate concepts**: `belumDitugaskan` (no operator) and `belumDinasUnit` (no routing destination) are reported independently — a report may have one without the other. Age buckets (`0–1` / `2–3` / `>3 hari`) are provided for the new pile too (`belumDinasUnitBuckets`), mirroring the existing unassigned buckets. Factual age, not SLA.

**2. Ordering fix — `laporanPerhatian`**
- `orderByDesc('submitted_at')` → **`orderBy('submitted_at')`** (ASC) on the Admin path only, so the card labelled "berumur tertinggi" now lists the **longest-active report first** (previously newest-first). Operator/Super Admin lists were already ASC and unchanged.

**3. N+1 fix — eager load `dinasUnit`**
- `laporanTerbaru` and `laporanPerhatian` (Admin, Operator, Super Admin — all render the same `monitoring-row` component) now `with(['reporter','category','assignee','dinasUnit'])`. Accessing `$item->dinasUnit?->name` no longer triggers a query per row.

**4. Query consolidation — one `GROUP BY status`**
- New `statusCounts()` issues a **single** `SELECT status, COUNT(*) … GROUP BY status`, normalised to the frozen enum. `statusBreakdown` (7 counts), `statusFlow()` (5 counts) and the `laporanAktif`/`laporanSelesai` KPIs are all derived from it (was ~14 separate `COUNT(*)`). `statusFlow()`'s count/share/bottleneck output shape is unchanged (Phase 7).
- `agingBuckets()` and the two `attentionSummary()` age piles now use one conditional-aggregation query each via the shared `ageBucketCounts()` helper (`SUM(CASE WHEN …)` — the pattern already used elsewhere in this service) instead of one `COUNT(*)` per bucket. Business results identical.

**5. Removed unused Admin query — `kategoriTeratas`**
- No longer computed on the Admin path (the Admin dashboard never rendered it). The shared `categoryDistribution()` method is **kept** for the Operator & Super Admin dashboards — nothing global was deleted.

**6. `dinasLongAging()` — deferred (documented)**
- Left as-is: the per-unit tooltip needs the **oldest report per unit**, which a plain `GROUP BY` cannot express without a window function/subquery; the working set is already constrained to active reports older than 7 days. A `GROUP BY` rewrite would add queries + raw SQL for no measured benefit. Deferred to Prompt 4.

**7. Cache invalidation — master data (Phase 11)**
- New `ComplaintCategoryObserver` + `DinasUnitObserver` (registered in `AppServiceProvider`, mirroring `ComplaintObserver`/`UserObserver`) call `clearGlobalDashboardCaches()` on `saved`/`deleted`, so a Category/Dinas rename no longer stays stale for up to 60s. Cache key/TTL/store unchanged (`dashboard:admin`, 60s, `CACHE_STORE` untouched); `belumDinasUnit` rides in the existing `attention` payload — **no new cache key**.

**8. "Selesai" definition — unchanged (business decision required)**
- `resolved` still = "Selesai"; `closed` is terminal but not counted as Selesai (and is neither Aktif). No query changed. Flagged for an explicit business decision (Prompt 4+).

**Query counts (dev DB: complaints=11, dinas_units=5, categories=5, audit_logs=30; `CACHE_STORE=database`)**

| Path | Before | After |
|---|---|---|
| `getAdminData` cache-miss | 54 | **37** |
| `getAdminData` cache-hit | 17 | **19** |

The cache-hit rise (+2) is the two new `dinasUnit` eager loads, which remove the row-by-row N+1. Net: ~14 status counts → 1, 4 aging counts → 1, 6 attention-bucket counts → 2, minus the unused category query.

**Tests**
- `tests/Feature/CommandCenterAnalyticsTest.php` — 7 new: `belumDinasUnit` counts active-without-Dinas only; it is independent from operator assignment; `laporanPerhatian` oldest-first; oldest kept when over the limit; `dinasUnit` eager-loaded on both lists; `kategoriTeratas` absent from the Admin payload; `statusBreakdown` matches real counts after consolidation.
- `tests/Feature/DashboardCachingTest.php` — 2 new: Admin cache cleared on Category change and on Dinas/Unit change.
- No existing test weakened or removed.

**Verification after this round:** **631 tests / 3009 assertions / 0 failed / 0 skipped**; `php artisan route:list` **62 routes** (unchanged); `php artisan view:cache` OK; `npm run build` PASS. **No migration created**; no new dependency; no mutation on the Admin path (read-only SELECTs only); authorization/middleware unchanged.

---

Reference = visual truth · Repository = business truth · Database = data truth · Security = authority.
No fabrication. No "production ready" claim — this is a verified local redesign, not a production sign-off.

---

## ADDENDUM 19 — PROMPT 3/4: ADMIN DASHBOARD UI RESTRUCTURE (DECISION-ORIENTED)

**Scope:** Admin dashboard Blade + admin-only dashboard components + copy/tests. No backend logic, no schema, no auth, no routes changed.

### 1. UI changes

| File | Change |
|---|---|
| `resources/views/admin/dashboard.blade.php` | Full re-layout to ROW 1–8 (see structure below). Removed standalone "Perlu Perhatian" card, "Distribusi Status Laporan" card, and the 3 quick trend tiles. |
| `resources/views/components/dashboard/attention-center.blade.php` | **NEW.** Consolidated Attention Center ("Perlu Segera Ditindaklanjuti") — 3 real signals + one CTA + honest all-clear state. |
| `resources/views/components/dashboard/unassigned-kpi.blade.php` | **DELETED** (superseded by the Attention Center). |
| `resources/views/components/dashboard/trend-lines.blade.php` | Series renamed "Akumulasi Belum Selesai" → "Backlog" (legend, tooltip, aria-label). |
| `resources/views/components/dashboard/status-flow.blade.php` | Display re-order by volume descending (largest first); bottleneck flag unchanged. |
| `resources/views/components/dashboard/completion-trend.blade.php` | Rebuilt from full-width chart → compact "Tingkat Penyelesaian" insight (headline % + delta + tiny sparkline). |
| `resources/views/components/dashboard/empty-state.blade.php` | Added backward-compatible `tone` prop (`success` = calm "✓ all clear"). |
| `tests/Feature/CommandCenterAnalyticsTest.php` | Copy assertions updated; 6 new tests (attention signals, all-clear, belumDinasUnit, long-aging calm, thin-data render). |
| `tests/Feature/P0RemediationRegressionTest.php` | Stale comment updated only. |

### 2. Final dashboard structure

- **ROW 1 — KPI:** Total Laporan · Masuk Hari Ini · Selesai · Aktif.
- **ROW 2 — Perlu Segera Ditindaklanjuti (Attention Center):** belum ditugaskan · aktif > 7 hari · belum punya Dinas/Unit → CTA "Buka Monitoring Laporan →"; all-clear state when every signal is 0.
- **ROW 3 —** Tren Laporan & Backlog (line: Masuk/Selesai/Backlog) + Bottleneck Status Laporan (horizontal bars, volume desc).
- **ROW 4 —** Beban Dinas/Unit Berdasarkan Status (stacked bar, 4 active statuses) + Dinas/Unit dengan Laporan Berumur Panjang + Distribusi Umur Laporan Aktif.
- **ROW 5 —** Kategori × Status (heatmap) + Kategori dengan Backlog Tertinggi.
- **ROW 6 —** Tingkat Penyelesaian (compact insight) + Persentase Penyelesaian per Kategori.
- **ROW 7 —** Pemantauan Laporan Aktif (oldest-first) + Aktivitas Audit Terkini.
- **ROW 8 —** Laporan Terbaru (secondary, 5 rows).

### 3. Removed / consolidated components

- **"Distribusi Status Laporan"** — removed from the Admin view only (redundant with Bottleneck Status). The `statusBreakdown` backend payload is **kept** (still serves Operator & Super Admin).
- **"Perlu Perhatian"** (old feature card) — folded into the single Attention Center (no second alert centre).
- **"Tren Tingkat Penyelesaian"** — restructured into the compact "Tingkat Penyelesaian" insight (small card, not a big full-width chart).
- **"Laporan Belum Ditugaskan"** standalone KPI — folded into the Attention Center's first signal (still links to the real `?assigned=unassigned` filter).

### 4. New components

- **`attention-center`** — renders `belumDinasUnit` (and belum-ditugaskan, and active > 7 hari). Tiles with count 0 are never shown as warnings; if all are 0 the card shows "Tidak ada masalah utama yang terdeteksi".

### 5. Responsive

- Desktop: 12-col grid (7/5, 5/4/3, 7/5, 4/8, 7/5). Tablet/mobile: everything stacks in the documented order (KPI → Attention → Trend → Bottleneck → Dinas/Unit → Aging → Kategori → Completion → Active → Audit → Latest). No horizontal page overflow (charts are `w-full h-auto` SVG; the heatmap keeps its `overflow-x-auto` wrapper).

### 6. Accessibility

- Attention signals carry `aria-label`; the clickable signal is a real `<a>`. Charts keep `role="img"` + descriptive `aria-label`. Heatmap cells expose text labels + legend (not colour-only). Progress bars keep textual `resolved/total` + %. Empty states are text. The new sparkline has an `aria-label` naming its month range.

### 7. Tests

**635 passed · 0 failed · 3025 assertions** (was 631/3009; +7 new, −3 replaced).

### 8. Validation

- `php artisan test` → PASS (635/635).
- `php artisan view:cache` → OK.
- `npm run build` → PASS (pre-existing `fontaine` warning only).

### 9. Regression

- Operator, Super Admin, and Citizen dashboards unchanged (their components untouched; `empty-state` prop is backward-compatible). `route:list` = **62** (unchanged). No migration, no new dependency.

### 10. Known limitations

- The standalone "Distribusi Umur Laporan Aktif" card is **kept** (not in the removal list) and placed as a third card in ROW 4; ROW 6 is split into the insight + per-category list to keep the completion rate small. Both are documented deviations from the literal row wording, made to avoid losing information.
- "Selesai" still = `resolved` only (`closed` neither Selesai nor Aktif) — unchanged, pending a business decision.
- `dinasLongAging()` optimisation still deferred.

---

## ADDENDUM 20 — PROMPT 4/4: FINAL POLISH, VISUAL QA, PERFORMANCE QA & REGRESSION

Scope: **Admin dashboard only** + its admin-only components. No feature, no business rule,
no schema, no route, no dependency, no lifecycle change. No test weakened or removed.

### 1. Final dashboard structure (unchanged)

ROW 1 KPI (Total / Masuk Hari Ini / Selesai / Aktif) · ROW 2 Perlu Segera Ditindaklanjuti
(single Attention Center) · ROW 3 Tren Laporan & Backlog + Bottleneck Status Laporan ·
ROW 4 Beban Dinas/Unit × Status + Dinas/Unit Berumur Panjang + Distribusi Umur Laporan Aktif ·
ROW 5 Kategori × Status + Kategori dengan Backlog Tertinggi · ROW 6 Tingkat Penyelesaian +
Persentase Penyelesaian per Kategori · ROW 7 Pemantauan Laporan Aktif + Aktivitas Audit Terkini ·
ROW 8 Laporan Terbaru (secondary). Structure kept exactly as Prompt 3/4.

### 2. QA fixes applied (4 targeted, all in scope)

| # | Issue found (Phase) | Fix |
|---|---|---|
| A | **Misleading sparkline** (Phase 4/33): the "Aktif" KPI card rendered `mini-bars` of the monthly **inflow** series under an *active-reports* label — the visual did not represent the metric. | Removed the sparkline from the "Aktif" card (it was decorative, not factual for "aktif"). Total Laporan keeps its inflow sparkline. No new query. |
| B | **Mobile label readability** (Phase 17/18): the trend SVG's fixed `720×280` viewBox scaled its 11px axis labels to ~4.6px at 320px. | Wrapped the trend SVG in `overflow-x-auto` and gave it `min-w-[600px]` — same local-scroll pattern the heatmap already uses. Page-level horizontal overflow stays 0; desktop is unchanged (`w-full`). |
| C | **A11y number hidden** (Phase 20): Attention Center count spans were `aria-hidden="true"` on **all** tiles, but only the link tile carried an `aria-label`; the two informational tiles exposed no number to screen readers. | `aria-hidden` on the count now applies **only** when a link `aria-label` already announces it. Informational tiles expose the real number. |
| D | **Number-format consistency** (Phase 4): the "Total Laporan" hint printed raw integers (`$laporanAktif`) while the KPI value used `number_format`. | Hint now uses `number_format()` to match. |

### 3. Dead-code cleanup (Phase 30)

- Deleted `resources/views/components/dashboard/area-chart.blade.php` and
  `delta-pill.blade.php` — both had **0 references** in source (only stale compiled views
  in `storage/framework/views`). Verified no dynamic `x-dashboard.$var` usage exists.
- No unused variables/imports remained in `admin/dashboard.blade.php` after Fix A
  (`$inflowSeries` is still used by the Total card; `$hasTrend`, `$hasCompletionTrend`,
  `$longAge` all still consumed).

### 4. Data QA (all values backend-derived; verified against real dev DB)

- **KPI**: Total = `Complaint::count()`; Masuk Hari Ini = `whereDate(submitted_at, today)`;
  Selesai = `statusCounts['resolved']`; Aktif = sum of the 4 non-terminal statuses. No duplicate figures.
- **Attention**: `belumDitugaskan` (`assigned_to IS NULL`) and `belumDinasUnit`
  (`dinas_unit_id IS NULL`) are **distinct concepts**, kept separate in label, count and copy.
  Zero counts are never rendered as warnings; all-zero → "Tidak ada masalah utama yang terdeteksi".
- **Backlog**: series value comes from the backend (`monthlyTrend()` running balance of real
  inflow − resolved); never recomputed in Blade. Legend/tooltip/aria say "Backlog" (not "Belum Selesai").
- **Bottleneck**: real per-stage counts; display re-ordered volume-DESC (flag passed by value).
- **Dinas/Unit**: stacked segments sum to the real active total; `>7 hari` long-aging = active &
  `submitted_at <= now()->subDays(8)`; empty → calm "✓ Tidak ada laporan aktif berumur panjang."
- **Category**: backlog = active per category (not total); heatmap cells print the real number
  (never colour-only) + legend; completion = `resolved / total` (never `+closed`), `total = 0` rows skipped backend-side → no divide-by-zero.
- **Completion insight**: delta shown only when a previous month with `inflow > 0` exists; delta = 0 renders "stabil dari bulan sebelumnya" (never a fake "+0%"); no comparison → only the percentage.

### 5. Empty-data matrix (Phase 16) — all 9 cases PASS

A no complaints · B complaints but none active · C active all with Dinas/Unit ·
D active without Dinas/Unit · E no report >7 days · F all one status · G one category ·
H one Dinas/Unit · I all metrics 0. Verified via a temporary probe test (8 assertions-blocks,
48 assertions, deleted after use): **no NaN / undefined / Infinity / division-by-zero / broken layout / false warning**.

### 6. Accessibility QA (Phase 20)

- Heading hierarchy: one `<h1>` (topbar greeting) + `<h2>` section headings; no skipped levels.
- Interactive elements are native `<a>` / `<button>`; the clickable Attention signal is a real `<a href>`; informational tiles are `<div>` with exposed text.
- Charts carry `role="img"` + descriptive `aria-label` (trend, completion sparkline); heatmap + stacked bars expose per-cell/per-segment `aria-label` text; progress bars print `resolved/total` + `%`.
- Global `:focus-visible` outline (2px) + `@media (prefers-reduced-motion: reduce)` block disable card-enter/stagger/tooltip/view-transition animation.

### 7. Performance QA (Phase 21/22) — measured on the real dev DB

Dataset: `complaints=11`, `dinas_units=5`, `categories=5`, `audit_logs=30`, `CACHE_STORE=database`.

| Path | Prompt 2 baseline | Prompt 4/4 |
|---|---|---|
| `getAdminData` cache-miss (cold, no `?refresh`) | 37 | **37** |
| `getAdminData` cache-hit (warm) | 19 | **19** |
| **Blade render** (view → HTML, everything pre-loaded) | — | **0 queries** |

- **No query in Blade**: rendering `admin.dashboard` with the payload issues **0** DB queries.
- **No N+1**: `laporanTerbaru` / `laporanPerhatian` / `auditTerbaru` all eager-load
  (`reporter`, `category`, `assignee`, `dinasUnit` / `actor`); lists render from the pre-loaded collections.
- (My first measurement read 38 because it forced `?refresh=1`, which adds one `Cache::forget` DELETE — not a data query.)

### 8. Security QA (Phase 24)

- Admin dashboard stays behind `auth` + `role:admin`; `DashboardAuthorizationTest` proves
  Masyarakat → 403, Operator → 403, Super Admin → 403 (admin cannot reach super-admin).
- Admin surface is **read-only**: no `admin.complaints.show` / `admin.complaints.assign`
  route exists; `test_admin_dashboard_is_read_only` asserts loading the dashboard changes
  nothing (`assertDatabaseCount('complaints', 1)`, status + `assigned_to` unchanged).
- No POST/PUT/PATCH/DELETE admin route added. Audit feed shows actor/role/action/time only —
  no password/NIK/phone/address/token/IP in the **dashboard** feed (IP stays on the audit-log page only).

### 9. Regression (Phase 25) — all green

Admin dashboard · CommandCenterAnalyticsTest · DashboardCachingTest · DashboardAuthorizationTest ·
P0RemediationRegressionTest · Operator dashboards · Super Admin dashboard · Citizen dashboards ·
complaint flow · category/Dinas routing · audit — **no failure**. Operator/Super-Admin/Citizen
views and components untouched by Prompt 4/4.

### 10. Tests

```
Tests:      635
Assertions: 3015
Failures:   0
Skipped:    0
```

The −10 assertions vs Prompt 3/4's 3025 is **expected and benign**: deleting the 2 dead
components removes 2 files × 5 assertions from `SpaRemovalTest::test_no_live_source_file_references_the_spa_engine`
(which iterates every file under `resources/views`). No assertion was weakened or removed.

### 11. Validation

- `php artisan test` → **PASS** (635/635, 3015 assertions, 0 failed, 0 skipped).
- `php artisan view:cache` → **OK**.
- `npm run build` → **PASS** (only the pre-existing non-fatal `fontaine` optional-package warning).
- `php artisan route:list` → **62** (unchanged).
- `php artisan migrate:status` → all existing migrations `Ran`; **no new migration** (git shows none).

### 12. Git scope (Phase 31)

All Prompt 4/4 changes are confined to the Admin dashboard + its admin-only components:
`resources/views/admin/dashboard.blade.php`, `components/dashboard/trend-lines.blade.php`,
`components/dashboard/attention-center.blade.php`, and the deletion of the 2 dead
`components/dashboard/` files. (The wider working tree also holds the earlier Prompt 1–3
shell/profile/dashboard work — all previously documented; no unrelated file was modified or
removed by Prompt 4/4.)

### 13. Known limitations (honest, no fabrication)

- **"Selesai" vs `closed`** — still `resolved` only; `closed` is terminal but neither Selesai
  nor Aktif. **Business Decision Pending** (unchanged, not silently altered).
- **`dinasLongAging()`** — still uses the per-unit "oldest report" read (no window function);
  optimisation deferred (working set already bounded to active > 7 days).
- **`fontaine` build warning** — pre-existing, non-fatal (optional package not installed).
- **No JS/DOM test runner** — frontend QA asserts server-rendered HTML only; interactive
  behaviour (Alpine rail, hover tooltips) is verified structurally, not in a real browser.

### 14. Final recommendation

**READY FOR PRODUCTION REVIEW** — Admin dashboard is data-correct, decision-oriented,
responsive, accessible, read-only, query-bounded (cache-miss 37 / cache-hit 19 / 0 Blade
queries), and regression-clean. No blocker found in scope. The only open item is the
**business decision on `closed` vs Selesai**, which is explicitly deferred, not a defect.

Reference = visual truth · Repository = business truth · Database = data truth · Security = authority.
No fabrication. No "production ready" claim — this is a verified local QA pass, not a production sign-off.

**END PROMPT 4/4 — STOP.**
