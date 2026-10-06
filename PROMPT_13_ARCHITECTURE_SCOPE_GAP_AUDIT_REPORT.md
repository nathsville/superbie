# Prompt 13 — Architecture & Scope Gap Audit

**Project:** SuperBie — Lapor Pak Wali
**Type:** READ-ONLY discovery / architecture & scope gap audit
**Scope:** Post-Prompt-12 state (Prompts 6–12 complete)
**Audit date context:** Laravel 13.17 / PHP 8.4.26 / MySQL (port 3307) / Blade + Alpine.js + Tailwind v4 + Vite
**Rule compliance:** No source file, migration, route, view, config, test, or database record was modified. The only file written is this report. A temporary `routes_audit.txt` created during route inspection was removed.

---

## 1. Executive Summary

The project is in a **healthy, coherent state**. The frozen domain rules (7 statuses, 11 transitions, `closed` terminal; 4 active roles; `petugas` never active) are implemented and protected at the server layer. Prompts 6–12 added master-data management, user management, an audit surface, configuration management, and a read-only "Semua Laporan" surface without schema churn (still 11 migrations, 0 pending).

Baseline integrity is **verified intact**: `438 tests / 1453 assertions / 0 failed / 0 skipped`, `npm run build` PASS, `61` routes listed (60 named + `/up`), `11 Ran / 0 Pending` migrations.

The audit surfaced **no P0 blocker**. Findings cluster into three groups:

1. **Authorization semantics that need a *business* decision, not a code fix** — the single most important one being **Operator complaint visibility is global** (`OperatorComplaintController::index` L43–45 has no Dinas/Unit scoping, and no per-complaint ownership check exists on any operator mutation). This is the top "BUSINESS DECISION REQUIRED" item.
2. **A `super_admin` role that is *read-only only at the `/super-admin/*` prefix*** — because the operator route group admits `super_admin` (`routes/web.php:62`), the "read-only" guarantee for Super Admin complaints is scoped to the new controller, not the role.
3. **Technical debt / dead code** — orphaned layouts (`F-7`), a live custom SPA engine contradicting the "no SPA" architecture (`F-6`), stale singular route names in the Super Admin dashboard, duplicate/unused methods, and a handful of latent correctness nits (non-transactional profile audit, TOCTOU on last-super-admin, `created_at` not fillable on append-only models).

**Final verdict: PASS WITH NOTES.**

---

## 2. Baseline Verification

| Check | Command | Result | Status |
|---|---|---|---|
| Test suite | `php artisan test` | `{"tests":438,"passed":438,"assertions":1453,"failed":0}` — 0 skipped | ✅ Matches baseline |
| Route list | `php artisan route:list` | `Showing [61] routes` (60 named + `up` health) | ✅ Matches |
| Migration status | `php artisan migrate:status` | 11 migrations, all `Ran`, 0 Pending | ✅ Matches |
| Production build | `npm run build` | `✓ built in 1.10s`; only the known non-blocking `fontaine` optional-package warning | ✅ PASS |
| `petugas` routes | `grep petugas routes/` | 0 matches | ✅ Matches |

**Baseline integrity: INTACT.** No test count changed, nothing was altered to chase numbers.

Additional baseline facts confirmed:
- `PetugasDashboardController.php` and `resources/views/petugas/dashboard.blade.php` still appear as working-tree deletions vs commit `bf75450` (pre-existing; not introduced by this audit). Neither is referenced anywhere (`grep` → 0 matches).
- All working-tree changes remain uncommitted; only git commit is `bf75450`.

---

## 3. Architecture Audit

### 3.1 Backend architecture

- **Modular monolith: CONFIRMED.** Single Laravel app, `bootstrap/app.php` with `web` + `commands` routing and `/up` health. No API gateway, no microservices.
- **Layering: mostly consistent.** Route → (middleware) → Controller → (FormRequest) → Model/Service → View. Business logic for multi-step use cases sits in `app/Services/` (`UserManagementService`, `AppSettingService`, `AuditLogService`, `DashboardCacheService`, `ComplaintRetentionService`).
- **Controller/service/model separation:** consistent for the "Prompt 9–12" surfaces. **Not uniform** elsewhere: `OperatorComplaintController` (523 lines) embeds all category/destination/assign/status/note logic inline rather than delegating to services — it is the closest thing to a **God Controller** (6 mutation actions + index/show/download, each re-implementing validation + audit inline). This is *acceptable* (each action is small and transactional) but it is the least uniform file in `app/`.
- **No repository/use-case/action abstraction.** `rules.md:29` explicitly says not to add abstractions without concrete benefit; architecture.md §4 note (L132) documents that no `Actions/`/`Policies/`/`Livewire/` dirs exist by design. Consistent with the rule.
- **Duplicated business logic:** (a) phone normalization is duplicated verbatim in `RegisteredUserController::normalizePhone` (L69–78) and `ProfileController::normalizePhone` (L92–101). (b) Status groupings are hardcoded inline in `DashboardCacheService` (L30, L71, L73, L116, L130, L171) instead of using the `ComplaintStatus` enum — drift risk. (c) The daily-limit "effective value" rule is duplicated in `StoreComplaintRequest::withValidator` (L76–79) and `AppSettingService::effectiveValue` (L95–101) — currently consistent, but two copies.
- **Unused service:** none — all 5 services are referenced (see §23). One **dead method** (`DashboardCacheService::clearAll()` L216–221) and one **unused injected dependency** (`UserObserver::$cacheService`) exist.
- **Dead code:** orphaned layouts + `welcome.blade.php` (§23), the dead `Soon` nav branch, stale singular route names (§7).
- **Circular dependency:** none observed. Observers depend on `DashboardCacheService`; no service depends on observers.
- **Domain boundary clarity:** generally clear (Complaint / Category / DinasUnit / User / AuditLog / AppSetting). The **weakest boundary is complaint visibility** — "which complaints may an operator see/act on?" is *not* modeled at all (no scope, no unit binding on `users`).

### 3.2 Frontend architecture

- **Blade + Alpine + Tailwind + Vite: CONFIRMED.** `vite.config.js` inputs `resources/css/app.css` + `resources/js/app.js`; Alpine imported in `app.js:1`.
- **F-6 `resources/js/spa.js` (490 lines): STILL ACTIVE and in conflict with the documented architecture.**
  - Imported and started **unconditionally** at `resources/js/app.js:2,8` (`initSPA({ cacheTTL: 60000 })`).
  - It intercepts internal link clicks and GET form submits (`spa.js:81–142`), fetches HTML with `X-SPA-Request: true` (`:214–219`), and swaps `#main-content` via `outerHTML` (`:331–384`), with a client cache + hover prefetch.
  - **Direct contradiction** with `architecture.md:13` ("tanpa membangun SPA dan API server terpisah") and `rules.md:16`.
  - **Functional dependency is LOW but non-zero:** the only UI it *drives* (cache pill `data-spa-cache-time`, "Segarkan" button `window.SuperBieSPA.refresh()`, `data-spa-refresh-icon`) lives **exclusively in the orphaned** `resources/views/layouts/dashboard.blade.php:92–108`. The **active** layout `resources/views/components/layouts/dashboard.blade.php` has **no** SPA UI — only `id="main-content"` (L90). So at runtime the engine silently swaps the container, but no user-visible control references it.
  - **Classification: technical debt, not an actual blocker.** The app works without it; but it is a live, unconditional behavior that contradicts the stated architecture and adds an un-audited navigation path.
  - **Additional overlap:** two competing view-transition systems — CSS `@view-transition { navigation: auto; }` (`app.css:240–243`) *and* JS `document.startViewTransition` (`spa.js:373`).

### 3.3 Duplicate / orphaned code

- **Orphaned layouts (F-7): CONFIRMED DEAD.**
  - `resources/views/layouts/app.blade.php` and `resources/views/layouts/dashboard.blade.php` are **never referenced**. Every view uses `<x-layouts.app>` / `<x-layouts.dashboard>`, which resolve to `resources/views/components/layouts/*.blade.php`. Evidence: 0 occurrences of `@extends('layouts.*')` or `view('layouts.*')` anywhere; `PROMPT_7_DEVELOPMENT_DISCOVERY_REPORT.md:25` already flagged this.
  - The orphan `layouts/dashboard.blade.php` is a near-duplicate of the active component **but contains the SPA UI** the component lacks (L92–108).
- **Orphaned `welcome.blade.php`: CONFIRMED DEAD.** `routes/web.php:16–18` returns `view('public.landing')`, not `view('welcome')`. It is the stock Laravel scaffold (~223 lines incl. a 60 KB inline Tailwind blob).
- **Unused controllers:** none.
- **Unused services:** none.
- **Unused routes:** none (all 60 named routes map to real controllers/views).
- **Unused JS:** `spa.js` is *used* (loaded) but its UI is orphaned — see above.
- **Unused CSS:** stale Tailwind `@theme` tokens (`--color-twilight`, `--color-tealblue`, `--color-turquoise`, `--color-frosted`, `--color-cyanlight`, `--color-neutralbg`, `--color-cardborder`, `--font-family-jakarta`, `--font-family-jetbrains`) at `app.css:6–23` are never used; the comment claims they mirror a legacy CDN config that no live view uses.
- **Dead navigation:** the `@else` "Soon" branch in `super-admin/partials/nav.blade.php:24–31` is currently unreachable (all 7 routes exist).
- **Stale route names:** `super-admin/dashboard.blade.php:26` (`super-admin.user.create`) and `:121` (`super-admin.user.index`) — singular, non-existent; see §7.

---

## 4. Frontend Architecture

Covered in §3.2–3.3. Summary table:

| Aspect | Finding | Evidence |
|---|---|---|
| Blade | Used consistently | 42 view files, all `<x-layouts.*>` |
| Alpine.js | v3.17.4, started once | `app.js:1,4–5` |
| Tailwind | v4 via `@tailwindcss/vite` | `vite.config.js:4,17` |
| Vite | v8, single entry pair | `vite.config.js:9` |
| `spa.js` | Live, contradicts "no SPA" | `app.js:2,8`; `architecture.md:13` |
| Reduced-motion | Handled in CSS | `app.css:91–98`, `:240` |
| Reduced-motion gap | `spa.js` inline-opacity fade not covered | `spa.js:377–382` |
| Font mismatch | `body` uses Inter; Vite bundles Instrument Sans | `app.css:102` vs `vite.config.js:11–15` |
| Duplicate transitions | CSS `@view-transition` + JS `startViewTransition` | `app.css:240`; `spa.js:373` |

---

## 5. Role & Authorization Audit

Expected active roles: `masyarakat`, `operator`, `admin`, `super_admin`.

| Source | Finding |
|---|---|
| Enum | `app/Enums/UserRole.php` — exactly 4 cases; `values()` L31–34 is the source of truth; `petugas` explicitly absent |
| Middleware | `EnsureUserHasRole` (role gate, `abort(403)`), `EnsureUserIsActive` (global `web` append, `bootstrap/app.php:20–22`) |
| Route groups | `citizen.` `role:masyarakat`; `operator.` `role:operator,super_admin`; `admin.` `role:admin`; `super-admin.` `role:super_admin` |
| FormRequest authorization | `authorize()` present on all SuperAdmin requests + `StoreComplaintRequest` + `LoginRequest` |
| Blade conditions | Role-gated nav/links exist; hiding is *not* relied upon |
| Seed | `DatabaseSeeder` creates one account per role, no `petugas` |
| Hardcoded role strings | `User.php` helpers L72–92; `UserManagementService` L43,47,54; `DashboardCacheService` status arrays — duplication, not legacy role |
| Tests | `SuperAdminUserManagementTest` rejects `petugas`; `CitizenExperienceFinalTest` asserts no `petugas` route |

**`petugas` classification (every occurrence found):**

| # | Location | Classification |
|---|---|---|
| 1 | `UserRole.php:26` comment | 4. Comment |
| 2 | `StoreUserRequest.php:22`, `UpdateUserRequest.php:21` comments | 4. Comment |
| 3 | `UserManagementService.php:26` comment | 4. Comment |
| 4 | `public/landing.blade.php:94` ("petugas berwenang") | 5. Display text (generic noun, not a role) |
| 5 | `citizen/complaint/show.blade.php:169` ("Tanggapan Resmi Petugas / Instansi") | 5. Display text |
| 6 | `tests/Feature/CitizenExperienceFinalTest.php:296–305` | 2. Test-only (asserts no `petugas` route) |
| 7 | `tests/Feature/SuperAdminUserManagementTest.php:19,260–272` | 2. Test-only (asserts rejection) |
| 8 | `tests/Feature/CitizenComplaintFlowTest.php:264,270` | 2. Test-only (fixture prose) |
| 9 | `PetugasDashboardController.php` (working-tree delete) | 6. Dead code |
| 10 | `resources/views/petugas/dashboard.blade.php` (working-tree delete) | 6. Dead code |

**No occurrence of "actual legacy authorization" (category 7) was found.** There is no active `petugas` role, route, middleware branch, or Blade condition.

**Authorization gaps that ARE real:**

1. **Operator complaint visibility is global** (see §9). `OperatorComplaintController::index` (L43–45) queries `Complaint::query()` with no unit/ownership scope; `mine` (L70–71) is opt-in. No per-complaint ownership check exists on `show`/`updateCategory`/`updateDestination`/`assign`/`updateStatus`/`addNote`. → **BUSINESS DECISION REQUIRED** (§17).
2. **`super_admin` reaches all operator mutation routes.** `routes/web.php:62` admits `super_admin` to `/operator/*`; the read-only guarantee is only at `/super-admin/*`. This is arguably *intended* (`ComplaintStatus.php:11–13` says Super Admin has status authority), but it means "Super Admin cannot mutate complaints" is **not** a role-level truth.

---

## 6. Route Audit

**Total: 61 routes** (`route:list` = 60 named + `up`). Grouped:

| Group | Count | Notes |
|---|---|---|
| Public | 1 | `home` (`/` closure) |
| Auth (guest) | 8 | login/register/forgot/reset (+ store/update) |
| Auth (any) | 2 | `logout`, `dashboard` |
| Citizen (`masyarakat`) | 8 | dashboard, history, create/store, profile edit/update, show, attachment |
| Operator (`operator,super_admin`) | 9 | dashboard, index/show, category, destination, assign, status, note, attachment |
| Admin (`admin`) | 3 | dashboard, complaints, audit-log |
| Super Admin | 20 | dashboard, users(6), complaints(2), audit(2), config(3), dinas(6), categories.mapping(2), categories(6) |
| System | 1 | `up` health |

- **Duplicate routes:** none.
- **Naming consistency:** consistent except the **stale singular** names referenced *in views* (not routes): `super-admin.user.index` / `super-admin.user.create` (§7). Actual routes are plural.
- **Middleware:** every staff/citizen route carries `auth` + `role:*`; `active` is global.
- **HTTP methods:** read surfaces are GET-only; mutations are POST/PATCH/PUT/DELETE. Super Admin complaint surface is GET-only (verified: `SuperAdminComplaintManagementTest:412,416` assert 405 on mutation).
- **Route-model binding + numeric constraints:** `{complaint}`, `{attachment}`, `{user}`, `{category}`, `{dinas_unit}`, `{auditLog}` are bound; numeric `whereNumber()` present on citizen/operator attachments, users, categories, dinas, audit, super-admin complaints. **`config.{setting}` and `{category}/mapping` are string-bound** (guarded in-controller by `abort_unless(isManaged)` / `isManaged` check).
- **Stale route references / placeholder navigation:** see §7.
- **Routes without a test (8):** `login`, `login.store`, `register` (GET), `password.request`, `password.email`, `logout`, `admin.audit-log`, `super-admin.dinas.edit`. Partial: `super-admin.dinas.create` (negative-auth only).
- **Routes without a view / pointing at missing controller:** none.

---

## 7. Navigation Audit

### Public / citizen / operator / admin

| Navigation | Route | Status |
|---|---|---|
| Landing | `home` | Active |
| Login / Register | `login` / `register` | Active |
| Citizen Dashboard | `citizen.dashboard` | Active |
| Buat Laporan | `citizen.complaint.create` | Active |
| Riwayat | `citizen.history` | Active |
| Profil | `citizen.profile.edit` | Active |
| Operator Dashboard | `operator.dashboard` | Active |
| Operator Laporan | `operator.complaint.index` | Active |
| Admin Monitoring | `admin.dashboard` | Active |
| Admin Semua Laporan | `admin.complaints` | Active |
| Admin Audit Log | `admin.audit-log` | Active (route exists; **no test**) |

### Super Admin (`super-admin/partials/nav.blade.php`)

| Navigation | Route | `Route::has()` | Route exists | Status |
|---|---|---|---|---|
| Dashboard | `super-admin.dashboard` | Yes | Yes | Active |
| Semua Laporan | `super-admin.complaints.index` | Yes | Yes | Active |
| Kelola Pengguna | `super-admin.users.index` | Yes | Yes | Active |
| Kategori | `super-admin.categories.index` | Yes | Yes | Active |
| Dinas/Unit | `super-admin.dinas.index` | Yes | Yes | Active |
| Konfigurasi | `super-admin.config.index` | Yes | Yes | Active |
| Audit & Keamanan | `super-admin.audit.index` | Yes | Yes | Active |

- **Placeholder / disabled links:** `super-admin/partials/nav.blade.php:24–31` ("Soon") — **dead branch** (all routes exist). Same pattern in `super-admin/dashboard.blade.php:163–169` (quick-link fallback) — dead.
- **Stale route name → broken UX (medium):** `super-admin/dashboard.blade.php:26` guards `Route::has('super-admin.user.create')` → **false**, so the working "Tambah Akun" link never renders and the **disabled placeholder always renders** (L32–38). `:121` guards `Route::has('super-admin.user.index')` → **false**, so the "Kelola →" link **silently disappears**. Correct plural names exist at `:150` and in `nav.blade.php:9`.
- **Route without navigation:** none material.
- **Navigation without implementation:** none.

---

## 8. Super Admin Capability Matrix

Using only available business rules (no "Super Admin = everything" assumption):

| Capability | Status | Evidence |
|---|---|---|
| Dashboard | Implemented | `SuperAdminDashboardController`; `DashboardCacheService::getSuperAdminData` |
| User Management | Implemented | `SuperAdminUserController` (index/create/store/edit/update/toggle) |
| Category Management | Implemented | `SuperAdminCategoryController` (index/create/store/edit/update/toggle/destroy) |
| Dinas/Unit Management | Implemented | `SuperAdminDinasUnitController` |
| Category Mapping | Implemented | `SuperAdminCategoryMappingController` (edit/update sync) |
| Semua Laporan | Implemented (READ-ONLY) | `SuperAdminComplaintController` (index/show only) |
| Audit & Security | Implemented (READ-ONLY) | `SuperAdminAuditController` |
| Configuration | Implemented (READ+UPDATE, 1 key) | `SuperAdminConfigController`; `AppSettingService::MANAGED` |
| **Complaint mutation (via Super Admin surface)** | **Intentionally out of scope** | No mutation route under `/super-admin/laporan` |
| **Complaint mutation (via operator surface)** | **Reachable** | `routes/web.php:62` admits `super_admin`; `ComplaintStatus.php:11–13` says Super Admin may change status |
| Role/permission management (beyond 4 roles) | Undefined | No roles table; `rules.md`/`prd.md` list only 4 |
| Password management for other users | Undefined (explicitly not built) | `SuperAdminUserController` docblock L26 |
| MFA / security engine | Undefined / out of scope | Not present |

---

## 9. Complaint Workflow Audit

End-to-end lifecycle vs implementation:

```
Citizen creates complaint → submitted
   ✓ ComplaintController@store (transactional; ref code + history + audit)
submitted → under_review
   ✓ OperatorComplaintController@updateStatus (ComplaintStatus::allowedTransitions)
under_review → in_progress | waiting_for_information | rejected
   ✓ enum-enforced; rejected requires reason + public response
in_progress → waiting_for_information | resolved | rejected
   ✓ enum-enforced
waiting_for_information → in_progress | resolved
   ✓ enum-enforced (→ rejected FORBIDDEN)
resolved → closed ; rejected → closed ; closed → (terminal)
   ✓ enum-enforced
```

- **Actual transitions:** exactly the 11 frozen transitions; `ComplaintStatusLifecycleTest` (78 methods) covers all allowed + forbidden + terminal + no-reopen.
- **Authorization:** status authority = Operator + Super Admin (via `/operator/*`); Admin/citizen denied.
- **Validation:** status allowlist via `tryFrom` (L355–361); transition check (L365–369); rejection reason (L372–379); public response (L382–389).
- **History / audit:** status + `ComplaintStatusHistory` + `AuditLog` written in **one `DB::transaction`** (L391–445). Correct.
- **Assignment:** separate from destination (`assigned_to` vs `dinas_unit_id`); target must be active operator (L289–298).
- **Routing:** destination must be mapped to category + active-or-current (L216–237).
- **Category:** operator may change category to an active category (L152–154).
- **Notes:** internal vs public_response (L463); public response updates `public_updated_at` (L479).
- **Attachments:** private disk, IDOR-checked download (L512–514).
- **Citizen:** own-only view (L151–153); no reply mechanism; `waiting_for_information` display-only.
- **Admin:** monitoring only, no mutation routes.
- **Super Admin:** read-only surface; mutation only via operator routes.

**Dead ends / unreachable states:** none. All 7 statuses are reachable and all 11 transitions are reachable.
**Inconsistent authorization:** **YES** — the operator surface has *no* per-complaint scope (§5, §17).
**Missing UI:** none material for the frozen scope.
**Missing test:** `admin.audit-log`, `super-admin.dinas.edit` (see §20).
**Business-rule ambiguity:** SLA, escalation, reopen, operator scoping, notifications (see §17).
**Duplicate workflow / bypass path:** no duplicate; the only "bypass" is the intended Super-Admin-via-operator path.

---

## 10. Complaint Data Integrity

| Field | Assessment | Evidence |
|---|---|---|
| `category_id` | Stored per complaint; deactivation never rewrites it; hard-delete blocked when used | `ComplaintCategory::booted()` L72–78; `SuperAdminCategoryController` |
| `dinas_unit_id` | Stored actual destination (not derived from pivot); `ON DELETE SET NULL` defensive only; used units cannot be hard-deleted | migration `...100007`; `DinasUnit::booted()` |
| `assigned_to` | Separate from destination; nullable; `ON DELETE SET NULL` | migration `...100001` L20–23 |
| `reporter_id` | `NOT NULL`, `ON DELETE RESTRICT`; ownership scoped in queries | migration L24–26; `scopeForReporter` |
| `reporter_email` | Snapshot; not exposed publicly | `ComplaintController@store` L77 |
| `reporter_phone` | **Hardcoded `null`** with a `TODO` (no users phone column at the time) | `ComplaintController@store` L78–80 |
| `status` | Enum-cast; allowlist-validated | `Complaint.php:36`; `updateStatus` |
| `submitted_at` | `NOT NULL`, `useCurrent()`, indexed; retention reference | migration L38 |
| `resolved_at` | Set only on `resolved` | `updateStatus` L407 |

- **Historical routing preserved:** YES — destination is stored, not re-resolved; mapping changes never touch complaints.
- **Assignment separate from destination:** YES.
- **Mapping does not rewrite history:** YES (`CategoryMappingAndRoutingTest`).
- **Status history append-only:** YES by convention (`$timestamps=false`, no update path); model-level immutability is *not* enforced (a direct `->update()` is technically possible) — see §18/§23.
- **`closed` terminal:** YES (`ComplaintStatus::isTerminal`; `allowedTransitions()===[]`).
- **Hidden mutation:** none found. No observer mutates complaint fields.
- **Data-integrity nit:** `reporter_phone` is permanently `null` (TODO), so the "contact snapshot" column is never populated — documented, not a defect per the frozen rules.

---

## 11. Category / Dinas / Mapping Audit

- **Category active/inactive:** enforced server-side — inactive/nonexistent rejected in `StoreComplaintRequest` (L25–30); toggle route present.
- **Dinas/Unit active/inactive:** inactive not selectable for *new* destinations, but historical destination stays valid (`updateDestination` L232–237).
- **Category ↔ Dinas/Unit:** many-to-many via `category_dinas_unit` with `UNIQUE(category_id, dinas_unit_id)` (migration `...100006` L43).
- **Unique mapping:** enforced at DB level; duplicates collapsed in `SyncCategoryMappingRequest`.
- **FK:** pivot cascades on category/dinas delete; `complaints.dinas_unit_id` uses `SET NULL` (defensive only).
- **Historical destination:** `complaints.dinas_unit_id` (stored), never the pivot.
- **Operator routing validation:** mapped + active-or-current + exists (L216–237).
- **Citizen selection:** category only; `dinas_unit_id` from request is ignored.
- **`dinas_name` as a routing source?** **NO.** All uses are display-only fallback:
  - `citizen/complaint/create.blade.php:127` — fallback *after* `mapped_dinas`
  - `citizen/complaint/show.blade.php:74`, `operator/complaint/show.blade.php:79–80`, `admin/complaints.blade.php:394–395`, `admin/dashboard.blade.php:162` — display labels
  - `SuperAdminCategoryController:69,105` — legacy label persisted on create/update (allowed field)
  - Classification: **display text only; not a routing source.** Consistent with `schema.md:81,89`.

---

## 12. User Management Audit

| Aspect | Status | Evidence |
|---|---|---|
| Create | ✅ | `SuperAdminUserController@store` (L57–84) |
| Edit | ✅ | `@update` (L94–145) |
| Activate/deactivate | ✅ | `@toggleActive` (L153–179) |
| Role validation | ✅ | `StoreUserRequest`/`UpdateUserRequest` via `UserRole::values()` |
| Last active Super Admin protection | ⚠️ Present but **TOCTOU** | check at `@update` L102 / `@toggleActive` L158, **outside** the transaction opened at L111/L164; no `lockForUpdate` |
| Password hashing | ✅ | `Hash::make` L65 |
| No password update (admin flow) | ✅ | comment L112; `UpdateUserRequest` excludes password |
| No hard delete | ✅ | no DELETE route; deactivate only |
| Audit events | ✅ | `user.created/updated/role_changed/activated/deactivated` inside transactions |
| Authorization | ✅ | `role:super_admin` + `authorize()` |
| IDOR | ✅ | numeric binding; `SuperAdminUserManagementTest:93` |

**Stale route reference (exact locations):**
- `resources/views/super-admin/dashboard.blade.php:26` → `Route::has('super-admin.user.create')` (should be `super-admin.users.create`)
- `resources/views/super-admin/dashboard.blade.php:121` → `Route::has('super-admin.user.index')` (should be `super-admin.users.index`)

Not fixed (read-only audit). Impact: the dashboard's "Tambah Akun" button renders as a disabled "belum tersedia" placeholder, and the "Kelola →" link is silently dropped.

**Doc/behavior mismatch:** `UserManagementService` docblock L18–19 claims the rule "is evaluated against live DB state **inside a transaction**"; in fact the check runs *before* the transaction. → §23.

---

## 13. Audit & Security Audit

- **Read-only:** ✅ — `SuperAdminAuditController` exposes only `index`/`show`; no mutation routes.
- **Authorization:** ✅ `role:super_admin` + `authorize()`.
- **Filters:** ✅ action/subject/actor/date from DISTINCT real data (`AuditLogService::filterOptions` L130–161).
- **Pagination:** ✅ page size 30 (`AuditLogService:106`).
- **Detail:** ✅ `super-admin/audit/{auditLog}` numeric.
- **Secret redaction:** ✅ presentation-layer only (`redactMetadata` L172–190), applied in controller + view. **Redaction is key-based only** (matches key fragments, not values).
- **Audit integrity:** ✅ append-only by convention; no model-level guard against direct `->update()`.
- **Mutation absence:** ✅.
- **Unsupported security claims?** None found — the surface makes no MFA/scoring claims. `architecture.md:235` mentions "optional MFA later" (aspirational, not claimed as implemented).

---

## 14. Configuration Audit

- **`app_settings`:** exists (migration `...100004`), unseeded.
- **Managed registry:** `AppSettingService::MANAGED` (L34–42) — exactly one key: `daily_report_limit`.
- **`daily_report_limit`:** consumer = `StoreComplaintRequest::withValidator` (L76–79); default from `config/business_rules.php:17` (=5).
- **Fallback behavior:** numeric-positive override wins, else config default (`effectiveValue` L95–101) — mirrors consumer.
- **Audit:** `app_setting.updated`, `subject_type='app_setting'`, metadata `key/old_value/new_value` only, inside a transaction (`SuperAdminConfigController` L54–73).
- **Authorization:** `role:super_admin` + `UpdateAppSettingRequest::authorize()`.

| Concern | Finding |
|---|---|
| Config key used but not managed in UI | None — `daily_report_limit` is both used and managed |
| UI config without consumer | None — the registry requires a `consumer` |
| Dormant settings | None exposed. (`app_settings` table is otherwise empty; no dormant rows.) |
| Inconsistent defaults | None — default read from `config/business_rules.php` in both places |
| Undocumented settings | `app_settings` has no seeded/documented rows; fine (falls back to config) |
| Dead config key | `business_rules.retention.reference_column` (L40) is **never read** (service hardcodes `'submitted_at'`) — cosmetic |
| `AppSetting` model allows arbitrary keys | Guard lives only in the service; direct `AppSetting::create()` would bypass it |

**No configuration key was added** (compliant with Prompt 11).

---

## 15. Documentation vs Implementation

| Topic | Documentation | Actual | Status | Severity |
|---|---|---|---|---|
| Stack | Laravel + Blade + Alpine + Tailwind + Vite + MySQL (`architecture.md:5–7`) | Confirmed | Match | — |
| Roles | 4 active; `petugas` historical (`prd.md:38`, `architecture.md:45`) | Confirmed | Match | — |
| Auth | Session auth, no JWT (`architecture.md:16,174`) | Confirmed | Match | — |
| Complaint lifecycle | 7 statuses / 11 transitions / closed terminal | Confirmed | Match | — |
| Complaint mutation by Super Admin | `prd.md:162` says Super Admin "mengelola laporan"; Prompt 12 built read-only | Read-only at `/super-admin/*`; mutation reachable via `/operator/*` | **Drift** | Medium |
| Operator scoping | `prd.md`/`rules.md` imply permission-based scope; no explicit unit rule | Global visibility, no scope | **Drift / undefined** | High (needs decision) |
| Config | `SETUP_AND_DOCS.md §6` daily limit = 5, overridable | Confirmed | Match | — |
| Audit | Append-only, redacted, read-only UI | Confirmed | Match | — |
| Frontend "no SPA" | `architecture.md:13`, `rules.md:16` | `spa.js` runs a client SPA engine | **Drift** | Medium |
| Docker | `SETUP_AND_DOCS.md:17` says Docker not used | No Docker files | Match (documented) | — |
| DB timezone | `architecture.md:196` "Asia/Makassar after confirmation" | `config/app.php:68` = `UTC` | **Drift** | Low |
| Locale | `prd.md:287` Bahasa Indonesia UI | `config/app.php:81` locale `en`; UI strings hardcoded ID | **Drift** | Low/Cosmetic |
| Fonts | `design.md:42` Inter | `body` Inter, but Vite bundles Instrument Sans | **Drift** | Low |
| Layouts | `architecture.md:110` lists `views/layouts/` | Real layouts are `views/components/layouts/`; `views/layouts/` orphaned | **Drift** | Low |
| `README.md` title | "Paket Dokumentasi Vibe Coding" | Accurate for docs set | Match | — |

No documentation was modified.

---

## 16. Requirement Gap Matrix

Source: `prd.md` §5 (MoSCoW) + §7 (F-001…F-013). "Test" = a dedicated regression test exists.

| Req | Implementation | Test | Status |
|---|---|---|---|
| F-001 Landing page | `public/landing.blade.php` | Indirect | Done |
| F-002 Register/login + submit | Auth controllers + `ComplaintController@store` | Yes (register/store); login/register GET untested | Done (partial test) |
| F-003 Reference + receipt | `LPW-YYYYMMDD-XXXX` in `store`; show page | Yes | Done |
| F-004 Public tracking | **Not built** (FINAL: not required) | `CitizenExperienceFinalTest` asserts absence | Done (by decision) |
| F-005 Auth all accounts | Session auth, inactive blocked | Yes | Done |
| F-006 Dashboard per role | 4 dashboards + redirect | Yes | Done |
| F-007 List/search/filter/detail | Operator/Admin/Super Admin lists | Yes | Done |
| F-008 Status + history | Enum + transactional history | Yes (78 tests) | Done |
| F-009 Internal notes + public responses | `ComplaintNote` + visibility | Yes | Done |
| F-010 Attachment upload | Private disk + validation | Yes | Done |
| F-011 Category + Dinas/Unit mgmt | Super Admin CRUD + mapping | Yes | Done |
| F-012 Account + role mgmt | Super Admin user management | Yes | Done |
| F-013 Audit log | `AuditLog` + observers + surfaces | Yes | Done |
| F-014 External notifications | Not built (Won't Have) | — | Out of scope (by decision) |
| F-015 SIPHP/weather/JDIH | Not built (Won't Have) | — | Out of scope (by decision) |

**UNDEFINED items carried from PRD (not implemented, not invented):**
- SLA / escalation / closure timing (`prd.md:91` — SLA pending).
- Tracking verification factor (`prd.md:94`).
- Moderation / duplicate / spam policy (`prd.md:96`).
- Privacy/retention/export for PII, IP, user-agent (`prd.md:97`).
- Official Category & Dinas/Unit lists (`schema.md:87,117`).
- Success metrics numeric targets (`prd.md:300`).
- Operator complaint scope (§17).

---

## 17. Undefined Business Rules

| Area | Classification | Evidence |
|---|---|---|
| Official Category list | Undefined | `schema.md:87`; seed is dev fixtures |
| Official Dinas/Unit list | Undefined | `schema.md:117`; `SETUP_AND_DOCS.md §6.10` |
| SLA | Undefined (explicitly) | `prd.md:91`; `AdminDashboardController:70–72` documents omission |
| Escalation | Undefined | `prd.md:91` |
| Reopen | Defined (not supported) | `ComplaintStatus.php:88–89` |
| Citizen reply/evidence | Defined (not allowed) | `SETUP_AND_DOCS.md §8` |
| Public tracking | Defined (not required) | `README.md:41` |
| Notifications | Defined (not required) | `README.md:43` |
| Password management (admin-for-user) | Undefined / out of scope | `SuperAdminUserController` docblock L26 |
| MFA | Undefined | `architecture.md:235` "later" |
| Super Admin complaint mutation | **Conflicted** | `prd.md:162` says "mengelola laporan"; Prompt 12 made the surface read-only |
| **Operator complaint visibility/scoping** | **Undefined** | `OperatorComplaintController:index` L43–45 global |
| Roles/permissions beyond 4 | Undefined | no roles table |
| Retention | Defined (5 yr, permanent) | `config/business_rules.php:38–41` |
| Daily limit | Defined (5/day) | `config/business_rules.php:17` |
| Attachments | Defined (10 × 20 MB; jpg/jpeg/png/pdf/mp4) | `config/business_rules.php:22–26` |
| Identity (NIK/HP/Alamat) | Defined | `config/business_rules.php:30–32` |

### BUSINESS DECISION REQUIRED

**Question:** Should an Operator be able to see and act on **all** complaints, or only those relevant to a Dinas/Unit?

**Current behavior:** `OperatorComplaintController::index` (`app/Http/Controllers/Staff/OperatorComplaintController.php:43–45`) loads `Complaint::query()` with no unit/ownership filter; `mine` (L70–71) is opt-in. `show`, `updateCategory`, `updateDestination`, `assign`, `updateStatus`, `addNote` (L92–501) have **no per-complaint authorization** — any operator can view internal notes and mutate any complaint by id.

**Concern:** No scoping by Dinas/Unit or assignment; there is also **no `dinas_unit_id` on `users`**, so operators are not bound to a unit at the schema level.

**Impact:** Potential overexposure of complaint data (including internal notes and reporter PII) and over-broad mutation authority across all units.

**Required decision:**
- A. Keep global operator visibility (current).
- B. Scope operators to their Dinas/Unit (requires a new `users`→unit association — a schema change).
- C. Scope operators to assigned complaints only.
- D. Other explicit rule.

*This audit does not select an option.*

### BUSINESS DECISION REQUIRED

**Question:** Is Super Admin *intended* to be able to mutate complaints (status/category/routing/assignment/notes)?

**Current behavior:** The `/super-admin/*` complaint surface is read-only (Prompt 12), **but** `routes/web.php:62` admits `super_admin` to `/operator/*`, so the role *can* mutate. `ComplaintStatus.php:11–13` states Super Admin has status authority.

**Concern:** `prd.md:162` ("Super Admin dapat mengelola laporan") conflicts with the Prompt 12 read-only guarantee.

**Impact:** Ambiguity about whether the read-only surface is a permanent product rule or a prompt-scoped constraint.

**Required decision:**
- A. Super Admin mutates via the operator surface (current) — read-only `/super-admin/laporan` is a monitoring view only.
- B. Super Admin must have a dedicated mutation surface (future work).
- C. Super Admin is monitoring-only and the operator-surface admission is unintended.

---

## 18. Security Gap Audit

| Area | Finding | Severity |
|---|---|---|
| Authentication | Session-based; login rate-limited (`LoginRequest` 5 attempts); generic errors; inactive accounts logged out in login + middleware | ✅ Good |
| Authorization | Middleware `role:*` + FormRequest `authorize()`; no policies (by design) | ✅ Good |
| **Operator over-broad access** | No per-complaint ownership/scope; global visibility incl. internal notes | **High** (see §17) |
| IDOR | Attachments checked (`complaint_id` match); numeric bindings; SuperAdmin surfaces keyed | ✅ Good |
| CSRF | Laravel CSRF on all state-changing forms | ✅ Good |
| Mass assignment | Broad `$fillable` on `User` (`role`,`is_active`,`password`) and `Complaint` (`status`,`assigned_to`,`dinas_unit_id`) — no live exploit (explicit arrays used), but no in-model defense | Medium |
| Validation | Server-side everywhere; category active check; status allowlist; dates strict | ✅ Good |
| Password handling | `hashed` cast; `Hash::make`; no plaintext; reset via Laravel | ✅ Good |
| Session | Regenerate on login; invalidate + regenerate token on logout/inactive | ✅ Good |
| Sensitive data exposure | Internal notes never in citizen views; audit metadata redacted at presentation | ✅ Good |
| File access | Private disk (`serve => false`); authorized download only | ✅ Good |
| Audit integrity | Append-only by convention; **no model guard**; redaction is key-based only | Low |
| **Rate limiting** | Only **login** is rate-limited. **No** throttle on complaint submission or any other endpoint; `routes/web.php` has **zero** `throttle` middleware | **Medium** |
| Role escalation | Registration hardcodes `masyarakat`; user mgmt role-validated; no self-escalation path found | ✅ Good |
| Inactive account access | Global `EnsureUserIsActive` + per-role middleware both log out | ✅ Good |
| Route protection | All staff routes `auth` + `role:*` | ✅ Good |
| **TOCTOU last-super-admin** | Check outside transaction, no row lock → concurrent requests could zero out active super admins | Medium |
| Non-transactional profile audit | `ProfileController@update` L71–83 saves user then inserts audit **outside** a transaction | Low/Medium |

**No Critical findings.** No finding allows unauthorized access to data belonging to another *citizen* (citizen ownership checks are strict), and no unauthenticated mutation path exists.

---

## 19. Attachment Security

| Aspect | Finding | Evidence |
|---|---|---|
| Upload authorization | `role:masyarakat` + `StoreComplaintRequest::authorize()` | route + request |
| Upload validation | `mimes` + `max` from `config/business_rules.php`; max 10 files | `StoreComplaintRequest:37–42` |
| Filename handling | Generated `Str::random(40)` + client extension; original name stored separately as untrusted display text | `ComplaintController@store:95,103` |
| Storage disk | `private` (root `storage/app/private`), `serve => false` → no public `/storage/{path}` route | `config/filesystems.php:33–49` |
| Path exposure | Paths never rendered as URLs; served via controller | `downloadAttachment` |
| MIME validation | `mimes:` by extension; server-detected `getMimeType()` stored | `:104` |
| Size validation | `max:20480` KB (20 MB) | `:41` |
| Download authorization | Citizen: `reporter_id` + `complaint_id` match (L172–174); Operator: `complaint_id` match (L512–514) | controllers |
| IDOR | Both download paths verify attachment↔complaint linkage | tests |
| Inactive user | Blocked globally by `EnsureUserIsActive` | `bootstrap/app.php:20–22` |
| Missing-file handling | 404 | `:177–179`, `:517–519` |
| **Residual risk** | `public/storage` symlink **not present** (good); no malware scanning (by decision) | `Test-Path public/storage` = False |

No change made.

---

## 20. Test Coverage Gap

- **Total:** 438 test methods (437 Feature + 1 Unit) across 20 classes. No skipped/incomplete tests. One trivial assertion (`tests/Unit/ExampleTest.php:12` `assertTrue(true)`).
- **Unit tests:** none beyond the stub.

**Routes NOT covered (8) + 1 partial:**

| Route | Gap |
|---|---|
| `login` (GET) | No test renders the login page |
| `login.store` (POST) | No test of authentication flow/rate-limit |
| `register` (GET) | Only POST covered |
| `password.request` (GET) | Untested |
| `password.email` (POST) | Untested |
| `logout` (POST) | Untested |
| `admin.audit-log` | No test hits `/admin/audit-log` |
| `super-admin.dinas.edit` | No test renders the edit form |
| `super-admin.dinas.create` | Partial (negative-auth only) |

**Critical areas genuinely UNTESTED:**
- **Rate limiting** — no `throttle`/`RateLimiter`/`429` assertions anywhere in `tests/`.
- **Generic mass-assignment contract** — behavioral injection is tested, but no test asserts `$fillable`/`$guarded`.
- **Operator complaint scoping** — cannot be tested because the rule is undefined.

Well-covered (contrary to assumption): status transitions, operator mutations, attachment IDOR, inactive-account access, retention command, daily limit, IDOR across Super Admin surfaces.

---

## 21. Performance Audit

| Concern | Finding | Evidence |
|---|---|---|
| N+1 | Dashboards loop `ComplaintStatus::cases()` issuing one `COUNT` per status (7 each) — cached 60 s | `DashboardCacheService:60–66,105–111,155–161` |
| N+1 (config) | `AppSettingService::managed()` → `effectiveValue()` → `AppSetting::get()` per key, no caching | `AppSettingService:62–80`; `AppSetting.php:18–22` |
| Unbounded query | `OperatorComplaintController:106` (operator list), `:78` (categories); `SuperAdminComplaintController:100,101` (master data); audit context `:133–139` (per-complaint audit rows) | controllers |
| Missing pagination | Only the above small master-data / per-complaint sets; all main lists paginate (15/20/30) | controllers |
| Expensive dashboard query | Full-table `COUNT`s, no index hints, but cached | `DashboardCacheService` |
| Unnecessary eager loading | None obvious; lists eager-load what they render | controllers |
| Repeated query | `StoreComplaintRequest` runs an extra `COUNT` per submission (daily limit) + `AppSetting::get` | `:76–87` |
| Full table scan (obvious) | Leading-wildcard `LIKE '%…%'` on `title`/`reference_code`/`description` with **no index on `title`** | `OperatorComplaintController:50`, `AdminDashboardController:43`, `SuperAdminComplaintController:50` |
| Frontend bundle | `app.js` = 62 kB (gzip 21.5 kB) incl. the SPA engine; CSS 69 kB | build output |
| Missing index (title) | No `title` index; search cannot use an index | migration `...100001` |

No optimization performed.

---

## 22. Database / Schema Audit

- **Migrations:** 11, all `Ran`, 0 pending. Ordered by FK dependency.
- **FKs:** deliberate — complaints: category `SET NULL`, assigned `SET NULL`, reporter `RESTRICT`; support tables `RESTRICT`; pivot `CASCADE`; audit actor `SET NULL`.
- **Unique constraints:** `users.email/nik/phone_number`, `complaint_categories.slug`, `complaints.reference_code`, `dinas_units.code`, `category_dinas_unit(category_id,dinas_unit_id)`, `app_settings.key`.
- **Indexes:** composite `(status,submitted_at)`, `(category_id,submitted_at)`, `(reporter_id,submitted_at)`, `(assigned_to,status,submitted_at)`, `(dinas_unit_id,status)`, `(complaint_id,created_at)` ×2, `(actor_id,created_at)`, plus single-column `status`/`submitted_at`/`action`/`created_at` — matches `schema.md §5`. **No `title` index** (search path).
- **Enum/string consistency:** statuses/roles/visibility stored as VARCHAR + PHP backed enums (per `schema.md:270`). Consistent.
- **Nullable fields:** `nik`/`phone_number`/`address` nullable for legacy rows (non-destructive). Consistent with `schema.md:59–61`.
- **Soft deletes:** none (retention = permanent deletion). Consistent.
- **Timestamps:** `complaint_status_histories`/`complaint_notes`/`audit_logs` have `created_at` only (`$timestamps=false`), default `useCurrent()`. **`created_at` is NOT in `$fillable`** on these models, yet writers pass it explicitly → silently dropped, masked by the DB default. Consistent with `schema.md:177,189,204`.
- **Historical data safety:** category/dinas hard-delete blocked at model layer; FKs `SET NULL` are defensive-only.

**schema.md vs migrations mismatch:** none material. Every table/column/index in `schema.md` §4–5 exists in migrations, and vice-versa.

---

## 23. Technical Debt

| Finding | Evidence (file:line) | Impact | Priority |
|---|---|---|---|
| Orphaned layouts `layouts/app.blade.php`, `layouts/dashboard.blade.php` | 0 references; real = `components/layouts/*` | Confusion / dead code | P3 |
| Orphaned `welcome.blade.php` (~223 lines, 60 KB inline CSS) | `routes/web.php:16–18` returns `public.landing` | Dead weight | P3 |
| `spa.js` live SPA engine contradicting architecture | `app.js:2,8`; `architecture.md:13` | Behavioral drift, un-audited nav | P2 |
| SPA UI only in the orphan layout | `layouts/dashboard.blade.php:92–108` | Confusing dead feature | P3 |
| Stale singular route names in dashboard | `super-admin/dashboard.blade.php:26,121` | Broken/disabled UX | P2 |
| Dead "Soon" nav/quick-link branches | `nav.blade.php:24–31`; `dashboard.blade.php:163–169` | Dead code | P3 |
| Dead method `DashboardCacheService::clearAll()` | L216–221 (duplicate of `clearGlobalDashboardCaches`) | Dead code | P3 |
| Unused injected `UserObserver::$cacheService` | `UserObserver.php:11–13` | Dead code / smell | P3 |
| Dead config `business_rules.retention.reference_column` | L40 never read | Dead config | P3 |
| Hardcoded status arrays duplicating enum | `DashboardCacheService:30,71,73,116,130,171` | Drift risk | P2 |
| Duplicated phone normalization | `RegisteredUserController:69–78` + `ProfileController:92–101` | Duplication | P3 |
| `created_at` not fillable but passed by writers | `AuditLog`/`ComplaintStatusHistory`/`ComplaintNote` models | Silent timestamp drop | P2 |
| Broad `$fillable` on `User`/`Complaint` | `User.php:21–30`, `Complaint.php:15–31` | Defense-in-depth gap | P2 |
| `AppSetting` allows arbitrary keys at model level | `AppSetting.php:9–13` | Guard only in service | P3 |
| TOCTOU last-super-admin | `SuperAdminUserController:102/111,158/164` | Race → zero super admins | P2 |
| Non-transactional profile audit | `ProfileController:71–83` | Audit/DB inconsistency | P2 |
| Docblock falsely says "inside a transaction" | `UserManagementService:18–19` | Misleading | P3 |
| `--force` doc/behavior mismatch | `PurgeExpiredComplaints:17,23` vs L32 | Misleading help | P3 |
| Stale/unused CSS tokens | `app.css:6–23` | Dead CSS | P3 |
| Two view-transition systems | `app.css:240` + `spa.js:373` | Overlap | P3 |
| Font mismatch (Inter vs Instrument Sans) | `app.css:102` vs `vite.config.js:11–15` | Inconsistent | P3 |
| `reporter_phone` permanently null (TODO) | `ComplaintController:78–80` | Unused column value | P3 |
| Timezone UTC vs Asia/Makassar doc | `config/app.php:68` vs `architecture.md:196` | Doc drift | P3 |

No cleanup performed.

---

## 24. Placeholder / Unfinished Features

| Marker | Location | Classification |
|---|---|---|
| "Soon" disabled nav span | `nav.blade.php:27–30` | 3. Obsolete placeholder (all routes exist) |
| "Soon" quick-link fallback | `dashboard.blade.php:164–169` | 3. Obsolete placeholder |
| Disabled "Tambah Akun" span | `dashboard.blade.php:32–38` | 1→3. Intentional guard, but **always renders** due to stale route name (should be an active link) |
| `TODO: Define requirement` | `ComplaintController:79`; migrations `...100001:14,27`, `...100002:12`, `...100004:12`; `PasswordResetController:14` | 1. Intentional (documentation-first) |
| "SLA not computed" note | `AdminDashboardController:70–72` | 1. Intentional (honest omission) |
| NIK read-only inputs | `citizen/profile/edit.blade.php:109,131` | 4. Harmless UI (immutable by rule) |
| `:disabled` submit buttons | `create.blade.php:251`, `profile/edit.blade.php:244` | 4. Harmless (loading state) |
| `<option disabled>` placeholders | `operator/complaint/show.blade.php:283,366` | 4. Harmless UI |
| Orphan `welcome.blade.php` scaffold | `resources/views/welcome.blade.php` | 3. Obsolete (Laravel scaffold) |

No unfinished *required* feature was found within the frozen scope.

---

## 25. Fake / Invented Data Audit

**Result: CLEAN in all live (routed) views.** No fake statistics, officer names, dinas, categories, SLA numbers, or fabricated metrics.

Evidence:
- `admin/dashboard.blade.php:6–12` documents the data-integrity rule (real DB counts / honest empty states only).
- All dashboard numbers are DB-derived (`DashboardCacheService`) or arithmetic on real counts (e.g. `super-admin/dashboard.blade.php:93`).
- Names/dinas/categories are relations with `'—'`/`'Belum ditentukan'` fallbacks.
- `admin/complaints.blade.php:401` shows "Umur Laporan" (factual age from `submitted_at`), explicitly **not** an SLA claim.
- Seed data is **clearly marked** development fixtures (`DatabaseSeeder:59–60,74,84–86,140–142`), incl. sample NIK/phone `7373000000000001` etc. — labelled "NOT official/real data".
- The only fabricated content is in the **orphan** `welcome.blade.php` (Laravel scaffold, unreachable).
- Input `placeholder="Contoh: …"` strings are guidance, not rendered data.

---

## 26. Dependency Audit

**Composer (`composer.json`):**
- Require: `php ^8.3`, `laravel/framework ^13.17`, `laravel/tinker ^3.0`. Clean.
- Require-dev: faker, `laravel/pail`, **`laravel/pao`**, pint, mockery, collision, phpunit. `laravel/pao` (v1.1.5) is an uncommon dev tool; **not inconsistent with the architecture** but worth noting as non-essential. No Livewire/Inertia/Sanctum/JWT.

**npm (`package.json`):**
- devDeps: `@tailwindcss/vite`, `concurrently`, `laravel-vite-plugin`, `tailwindcss`, `vite`. Clean.
- deps: `alpinejs`. Clean.
- **optionalDependencies: `@laravel/multiplex ^0.4.1`** — pulls **React 19 / react-reconciler** transitively into `package-lock.json` (lines 104, 1483–1504). This is an **optional** dependency of the Laravel Vite plugin toolchain, not a runtime dependency of the app; the built bundle contains no React. Still, a React presence in the lockfile conflicts conceptually with the "no React" constraint (`rules.md:17`).
- No Livewire/Inertia/React/Vue in *application* code or `package.json` direct deps.

**Documentation vs installed packages:** consistent; no doc claims a package that isn't installed.

No install/update/remove performed.

---

## 27. Environment / Setup Audit

| Aspect | `.env.example` / config | Actual runtime | Status |
|---|---|---|---|
| DB | `mysql`, port `3307`, `superbie` | MySQL (Laragon) | Match |
| SQLite | not used | not used | Match |
| Docker | absent (`SETUP_AND_DOCS.md:17`) | no Docker files | Match |
| `APP_NAME` | `Laravel` | `Laravel` (default) | Drift (cosmetic — should be app name) |
| `APP_LOCALE` | `en` | `en` | Drift (UI is hardcoded ID) |
| `APP_TIMEZONE` | not set | `config/app.php` `UTC` | Drift vs `architecture.md:196` |
| `SESSION_DRIVER` | `database` | `database` | Match |
| `QUEUE_CONNECTION` | `database` | `database` (unused) | Match |
| `CACHE_STORE` | `database` | `database` | Match |
| `MAIL_MAILER` | `log` | `log` | Match (email reset not functional locally) |
| Storage link | `storage:link` defined | `public/storage` **absent** | OK (attachments private) |
| Build | `npm run build` | PASS | Match |
| Test DB | `superbie_testing` (MySQL, port 3307) | confirmed via `phpunit.xml` | Match |
| `.env` committed? | not tracked | not tracked (only `.env.example`) | Match |

No environment change performed.

---

## 28. Production Readiness Snapshot

### Architecture — **Needs Work**
Evidence: sound modular monolith and clear layering, but a live custom SPA engine (`spa.js`) contradicts the documented "no SPA" architecture (`app.js:2,8`), and orphaned layouts persist (F-6/F-7).

### Security — **Needs Work**
Evidence: strong auth/authorization/IDOR/CSRF/attachment posture, but **Operator access is unscoped** (§17), **rate limiting exists only for login** (no throttle on submission), and a **TOCTOU** on last-super-admin can zero out admins.

### Functional — **Ready**
Evidence: all in-scope PRD requirements (F-001…F-013) implemented; frozen lifecycle enforced; no dead-end states. Ambiguities (SLA, operator scope, Super Admin mutation) are *undefined business rules*, not broken features.

### Testing — **Ready**
Evidence: 438 tests / 1453 assertions / 0 failed / 0 skipped; exhaustive lifecycle + IDOR + authorization coverage. Gaps are concentrated in auth plumbing and rate limiting.

### Documentation — **Needs Work**
Evidence: docs are thorough and mostly accurate, but drift exists on operator scoping, Super Admin mutation, frontend SPA, timezone, locale, fonts, and layout paths (§15).

### Deployment — **Needs Work**
Evidence: Laragon/MySQL path documented, `npm run build` PASS, migrations clean; but `APP_DEBUG`/timezone/locale are unset for production, `storage:link` is not run, the scheduler requires an external cron (`routes/console.php:20–22`), and there is no backup/restore drill evidence.

---

## 29. Prioritized Findings

### P0 — Blocker
**None.** No unauthorized access to citizen data, no data corruption path, no broken critical workflow, no requirement contradiction that blocks development.

### P1 — High
| # | Finding | Evidence |
|---|---|---|
| P1-1 | **Operator complaint visibility/authorization is global & undefined** — no scope, no per-complaint ownership check; internal notes/PII exposed across units | `OperatorComplaintController:43–45,92–501` |
| P1-2 | **No rate limiting on complaint submission or any non-login endpoint** | `routes/web.php` (0 `throttle`); only `LoginRequest` throttles |
| P1-3 | **TOCTOU on last-active-super-admin** — check outside transaction, no lock; concurrent requests can zero active super admins | `SuperAdminUserController:102/111,158/164` |

### P2 — Medium
| # | Finding | Evidence |
|---|---|---|
| P2-1 | `spa.js` live SPA engine contradicts documented architecture | `app.js:2,8`; `architecture.md:13` |
| P2-2 | Stale singular route names break/disable Super Admin dashboard controls | `super-admin/dashboard.blade.php:26,121` |
| P2-3 | Super Admin complaint mutation reachable via `/operator/*` — conflicts with `prd.md:162` | `routes/web.php:62` |
| P2-4 | `created_at` not fillable on append-only models (silent timestamp drop) | `AuditLog`/`ComplaintStatusHistory`/`ComplaintNote` |
| P2-5 | Non-transactional profile update + audit | `ProfileController:71–83` |
| P2-6 | Broad `$fillable` on `User`/`Complaint` (no in-model guard) | `User.php:21–30`, `Complaint.php:15–31` |
| P2-7 | Hardcoded status arrays duplicating the enum | `DashboardCacheService:30,71,73,116,130,171` |
| P2-8 | No `title` index; leading-wildcard search scans | migrations; search controllers |

### P3 — Low (technical debt / polish)
Orphaned layouts (F-7); orphaned `welcome.blade.php`; dead `Soon` branches; dead `clearAll()`; unused `UserObserver::$cacheService`; dead `retention.reference_column`; duplicated phone normalization; stale CSS tokens; dual view-transition systems; font mismatch; `AppSetting` arbitrary-key model path; misleading docblocks; `--force` doc mismatch; `APP_NAME`/locale/timezone defaults; `reporter_phone` null; `laravel/pao` + React-in-lockfile; orphaned `petugas` deleted files.

---

## 30. Recommended Roadmap

| Priority | Candidate Work | Reason | Dependency | Risk |
|---|---|---|---|---|
| P0 | *(none)* | No blocker | — | — |
| P1 | Decide + implement Operator complaint scoping | Closes the largest authorization gap | **Business decision (§17)**; may need a `users`↔unit association (schema change) | Medium |
| P1 | Add rate limiting to complaint submission (and tracking if ever added) | `rules.md:48` requires it; currently absent | None | Low |
| P1 | Make last-super-admin check transactional (lock/inside tx) | Prevents zero-admin state | None | Low |
| P2 | Resolve Super Admin complaint-mutation intent | `prd.md:162` vs Prompt 12 | **Business decision (§17)** | Low |
| P2 | Fix stale singular route names in Super Admin dashboard | Broken/disabled UX | None | Low |
| P2 | Retire `spa.js` (F-6) or formally document it | Architecture drift | Decision on nav behavior | Medium |
| P2 | Add `title` index; consider search strategy | Performance | Migration review | Low |
| P2 | Narrow `$fillable` / add in-model guards; make append-only timestamps explicit | Defense in depth, data fidelity | None | Low |
| P3 | Remove orphaned layouts + `welcome.blade.php` (F-7) | Dead code | None | Low |
| P3 | Clean dead branches/methods/config; unify phone normalization; align fonts/tokens | Maintainability | None | Low |
| P3 | Align timezone/locale/`APP_NAME` with docs | Doc drift | Ops decision | Low |

**No roadmap item was implemented.**

---

## 31. Prompt 14 Recommendation

> **Is the project ready for the next implementation prompt?**

### **B. YES, BUT — a business decision is required first**

There is **no P0 blocker** (option A's precondition is met on the security/data-integrity front), but the most valuable next implementation step depends on **undefined business rules**, above all:

1. **Operator complaint visibility/scoping** (§17, P1-1) — cannot be implemented without a decision; option B would require a schema change (a `users`↔Dinas/Unit association), which touches a frozen "no new migration unless truly required" constraint.
2. **Super Admin complaint mutation intent** (§17, P2-3) — decides whether any future mutation surface is built.

Once these decisions are made, the P1 security items (rate limiting, transactional last-super-admin) can proceed immediately without further input.

---

## 32. Final Verdict

```text
PASS WITH NOTES
```

The audit completed successfully and the **baseline integrity is intact** (`438/1453/0/0`; 61 routes; 11 migrations / 0 pending; build PASS). The project has **no P0 blocker**. It carries **3 P1 (High)** findings — all centered on Operator authorization scope, missing rate limiting, and a last-super-admin race — plus a set of P2/P3 technical-debt items and documented business-rule gaps.

Per the Prompt 13 rules, `FAIL` is reserved for an audit that cannot be completed or a broken baseline; neither applies. The correct verdict for a completed audit with P1/P2/P3 findings is **PASS WITH NOTES**.

**No source code, migration, route, view, config, test, or database record was modified. No finding was fixed. No business rule was decided. HARD STOP — awaiting review.**
