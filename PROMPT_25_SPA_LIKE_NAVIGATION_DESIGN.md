# Prompt 25 — SPA-Like Navigation & Intelligent Caching Architecture (Discovery & Design)

## SuperBie — Lapor Pak Wali

**Mode:** READ-ONLY / DISCOVERY-FIRST / NO IMPLEMENTATION.
No source code, route, view, config, dependency, database, or infrastructure was changed. No file was created except this report. HEAD remains `bf75450`. `php artisan test` baseline untouched (551 tests / 2402 assertions).

**Final classification (§38):**

```text
READY WITH ARCHITECTURAL DECISIONS REQUIRED
```

---

## 1. Executive Summary

The repository is **already closer to the target than the prompt assumes**:

1. **Native Cross-Document View Transitions are already implemented** — `resources/css/app.css:230-253` contains `@view-transition { navigation: auto; }` plus `view-fade-in` / `view-fade-out` keyframes. In supporting browsers, *every normal full-page navigation already animates without any JavaScript.*
2. **Alpine 3.17.4** is initialized exactly once (`resources/js/app.js`, 72 bytes) and uses a **global `MutationObserver` on `document`** (`node_modules/alpinejs/dist/module.esm.js:222-225`). This means **any DOM region that is replaced will automatically re-initialize its `x-data` components** — no manual re-init, no duplicate `Alpine.start()`.
3. **The former custom SPA engine (`resources/js/spa.js`) is gone** (removed in Prompt 19, guarded by `SpaRemovalTest`). It must **not** be resurrected. Only two dead compiled-view artifacts in `storage/framework/views/*.php` still mention `SuperBieSPA` (stale Blade cache, not source).
4. **No per-page `<script>` exists** and `@stack('head')` / `@stack('scripts')` are **never pushed to** (0 `@push`). This makes a DOM-swap strategy unusually clean — there is no document-lifecycle script to break.
5. **All mutations are non-GET** (POST/PATCH/PUT/DELETE) and **logout is POST**. A navigation layer can therefore safely intercept **only GET link clicks** and never touch a mutation.

**The real decision is not "can we build it" — it is "how much of it is worth building, given that native view transitions already deliver most of the perceived speed, and that caching authenticated HTML is a genuine security risk."** The recommended architecture (§7) is a **minimal, in-memory, user-scoped progressive navigation layer layered on top of the existing native transitions**, with **NO shared/persistent HTML cache for authenticated pages by default**.

---

## 2. Current Frontend Architecture

| Asset | Evidence | Notes |
|---|---|---|
| JS entrypoint | `resources/js/app.js` (72 B) | `import Alpine from 'alpinejs'; window.Alpine = Alpine; Alpine.start();` — **single start** |
| No bootstrap.js | `Test-Path resources/js/bootstrap.js` → False | nothing else loads |
| CSS entrypoint | `resources/css/app.css` (9,504 B) | design tokens, `@keyframes page-enter/card-enter`, `[x-cloak]`, `prefers-reduced-motion` block, **`@view-transition`** |
| Build | `vite.config.js` — `laravel({ input: [css, js] })`, `@tailwindcss/vite`, `bunny('Instrument Sans')` | Vite 8, laravel-vite-plugin 3.1, Tailwind 4 |
| Alpine | `alpinejs ^3.17.4` (installed 3.17.4) | global MutationObserver |
| Optional dep | `@laravel/multiplex ^0.4.1` | optionalDependencies (D-3: KEEP) |
| Fonts | `<link>` Google Fonts Inter in `components/layouts/app.blade.php:8-10` **and** bunny in Vite | dual font source (note) |
| Inline `<script>` in views | **none** | clean DOM-swap surface |
| Inline `<style>` in views | `welcome.blade.php` (dead) + `admin/complaints.blade.php` | only 1 live page has inline CSS |
| Service worker | absent | `public/` = `.htaccess, favicon.ico, fonts-manifest.dev.json, hot, index.php, robots.txt` |
| Prefetch / speculation rules | absent | no `rel=prefetch`, no Speculation Rules |
| Explicit cache headers in app code | absent | no `Cache-Control`/`ETag`/`Vary` in `app/`, `routes/`, `config/` |
| Global lifecycle listeners | **none** | 0 matches for `DOMContentLoaded`, `window.onload`, `addEventListener`, `popstate`, `beforeunload`, `history.` in views/js |

**Architecture verdict:** Laravel modular monolith + Blade + Alpine + Tailwind + Vite + MySQL, server-rendered HTML, session auth. No SPA framework, no API layer, no Livewire, no Redis. **Frozen baseline intact.**

---

## 3. Current Route Inventory

`php artisan route:list` → **61 routes**. GET routes relevant to navigation:

### A. Public
| Method | URI | Name |
|---|---|---|
| GET | `/` | `home` |

### B. Guest auth
| Method | URI | Name |
|---|---|---|
| GET | `login` | `login` |
| GET | `register` | `register` |
| GET | `forgot-password` | `password.request` |
| GET | `reset-password/{token}` | `password.reset` |

### C. Authenticated (any role)
| Method | URI | Name |
|---|---|---|
| GET | `dashboard` | `dashboard` (302 redirect to role dashboard) |
| POST | `logout` | `logout` |

### D. Citizen — prefix `laporan`, name `citizen.`
| Method | URI | Name |
|---|---|---|
| GET | `/laporan/dashboard` | `citizen.dashboard` |
| GET | `/laporan/riwayat` | `citizen.history` |
| GET | `/laporan/buat` | `citizen.complaint.create` |
| GET | `/laporan/{complaint}` | `citizen.complaint.show` |
| GET | `/laporan/{complaint}/lampiran/{attachment}` | `citizen.complaint.attachment` (binary) |
| GET | `/laporan/profil` | `citizen.profile.edit` |

### E. Operator — prefix `operator`, name `operator.` (middleware `role:operator,super_admin`)
| Method | URI | Name |
|---|---|---|
| GET | `/operator/dashboard` | `operator.dashboard` |
| GET | `/operator/laporan` | `operator.complaint.index` |
| GET | `/operator/laporan/{complaint}` | `operator.complaint.show` |
| GET | `/operator/laporan/{complaint}/lampiran/{attachment}` | `operator.complaint.attachment` (binary) |

### F. Admin — prefix `admin`, name `admin.` (read-only)
| Method | URI | Name |
|---|---|---|
| GET | `/admin/dashboard` | `admin.dashboard` |
| GET | `/admin/laporan` | `admin.complaints` |
| GET | `/admin/audit-log` | `admin.audit-log` |

### G. Super Admin — prefix `super-admin`, name `super-admin.`
| Method | URI | Name |
|---|---|---|
| GET | `/super-admin/dashboard` | `super-admin.dashboard` |
| GET | `/super-admin/users` · `/create` · `/{user}/edit` | `users.index/create/edit` |
| GET | `/super-admin/laporan` · `/{complaint}` | `complaints.index/show` (read-only) |
| GET | `/super-admin/audit` · `/{auditLog}` | `audit.index/show` |
| GET | `/super-admin/config` · `/{setting}/edit` | `config.index/edit` |
| GET | `/super-admin/dinas` · `/create` · `/{dinas_unit}/edit` | `dinas.index/create/edit` |
| GET | `/super-admin/kategori` · `/create` · `/{category}/edit` · `/{category}/mapping` | `categories.index/create/edit/mapping.edit` |

**Prefixes are actual repository prefixes** (no assumptions). All ID segments are `whereNumber`-constrained.

---

## 4. Current Layout Architecture

Two **active** Blade layouts (anonymous components), plus two **dead** duplicates:

| Path | Status | Role |
|---|---|---|
| `resources/views/components/layouts/app.blade.php` | **ACTIVE** | full `<html>` document: `<head>`, `@vite`, `@stack('head')`, `<body>{{ $slot }}`, success/error toasts, `@stack('scripts')` |
| `resources/views/components/layouts/dashboard.blade.php` | **ACTIVE** | wraps `<x-layouts.app>`; shell = `<aside>` sidebar + sticky `<header>` + `<main id="main-content" class="page-enter">` |
| `resources/views/layouts/app.blade.php` | **DEAD** (orphan) | older duplicate; referenced by nothing |
| `resources/views/layouts/dashboard.blade.php` | **DEAD** (orphan) | older duplicate containing the old SPA cache-pill UI |

### Shell structure (active dashboard layout)
```text
<x-layouts.app>                      ← full document (head/body/vite/fonts/toasts)
  <div x-data="{ sidebarOpen: false }">
     <aside>                          ← sidebar (server-rendered active state)
        logo + {{ $navigation }} + user card + logout <form>
     </aside>
     <div>                            ← mobile overlay (x-show)
     <div>
        <header> {{ $header }} + {{ $breadcrumb }} + {{ $headerActions }} </header>
        <main id="main-content" class="page-enter"> {{ $slot }} </main>
     </div>
  </div>
</x-layouts.app>
```

**Navigation is injected per page** via `<x-slot:navigation>` — **56 occurrences** across views. The **active menu item is computed server-side**:
```blade
@php $route = request()->routeIs('citizen.*') ? request()->route()->getName() : ''; @endphp
<a class="{{ $route === 'citizen.dashboard' ? 'active' : '' }}" @if(...) aria-current="page" @endif>
```
**Implication:** the sidebar's `active`/`aria-current` state is **server-derived**. A truly persistent shell would require the client to recompute active state after navigation, *or* the whole shell must be replaced. This is a first-class design decision (§7, §26).

Layout usage: `28` views use `<x-layouts.dashboard>`, `8` use `<x-layouts.app>` (public/landing + auth pages + error page).

---

## 5. Current Alpine Lifecycle

**Initialization model (verified):**
- `Alpine.start()` is called **exactly once** in `app.js`.
- Alpine registers a **global MutationObserver** on `document` (`subtree, childList, attributes, attributeOldValue`) — `module.esm.js:222-225`. **Newly inserted nodes with `x-data` are auto-initialized.**
- `[x-cloak] { display: none !important }` is defined (`app.css:53-55`).

**All `x-data` roots (live views only):**

| File:line | Component | State | Re-init risk after DOM swap |
|---|---|---|---|
| `components/layouts/app.blade.php:23,49` | success/error toast | `show` + `x-init` timeout | auto (MutationObserver) |
| `components/layouts/dashboard.blade.php:2` | mobile sidebar | `sidebarOpen` | **lost if shell replaced** (shell-level state) |
| `citizen/complaint/create.blade.php:73` | category→dinas live preview | `submitting`, `selectedCategoryId`, `categories` (`Js::from`) | auto; re-hydrates from server-rendered HTML |
| `citizen/profile/edit.blade.php:67` | save button | `saving` | auto |
| `operator/complaint/show.blade.php:270` | status transition form | `selectedStatus`, getters for conditional required fields | auto |
| `super-admin/dinas/edit.blade.php:15` | flash alert | `x-data` (empty) | auto |
| `super-admin/users/_form.blade.php:18` | role→dinas field toggle | `role` | auto |

**Conclusion:** every component is a **self-contained `x-data` on an element**, hydrated from **server-rendered HTML** (no client-only state that must survive navigation, except the shell's `sidebarOpen`, which is intentionally ephemeral). **DOM replacement is Alpine-safe** provided `Alpine.start()` is never called twice. The former `spa.js` called `Alpine.start()`/re-init concerns; that engine is removed.

---

## 6. SPA Feasibility Assessment

| Dimension | Assessment | Evidence |
|---|---|---|
| Server render speed | Good (small app, cached dashboards) | `DashboardCacheService`, 61 routes |
| Asset re-download on nav | **Already avoided** | Vite hashed assets + browser cache |
| Page transition animation | **Already present** | native `@view-transition` (CSS) |
| DOM swap compatibility | **High** | no per-page scripts; Alpine MutationObserver |
| Mutation safety | **High** | all mutations non-GET; logout POST |
| Auth/session | server-side; session cookie + CSRF in HTML | `StartSession`, `PreventRequestForgery` |
| Cache risk | **High for authenticated HTML** | pages embed `_token`, user name/role, scoped data |
| IDOR/role isolation | enforced server-side | Prompt 15/21 middleware |
| Query-string pages | many (filters, pagination, `?refresh=1`) | 7 GET filter forms + `->links()` in 9 views |

**Feasibility: YES** — technically safe to build a progressive navigation layer. **But the marginal performance benefit over the existing native view transitions is modest**, and the security cost of caching authenticated HTML is real. This drives the recommendation.

---

## 7. Recommended Navigation Architecture

### Primary recommendation — **Option A: lightweight custom progressive navigation layer, layered ON TOP of the existing native Cross-Document View Transitions** (do **not** remove the native CSS).

```text
Click internal <a href> (GET, same-origin, not excluded)
        │
        ├─ if same URL → no-op
        ├─ if excluded (download/logout/login/register/forgot/reset/hash-only/target=_blank/modifier-key) → default navigation
        │
        ▼
  fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        │
        ├─ 200 HTML → parse with DOMParser
        │        → extract <main id="main-content"> (+ <title>, header/breadcrumb, flash region)
        │        → replace region  (Alpine MutationObserver re-inits components)
        │        → update sidebar active state client-side (or replace shell region)
        │        → history.pushState({}, '', url)
        │        → scroll restore + focus management
        │
        ├─ 302/303 (e.g. /dashboard) → follow redirect target
        ├─ 401/419 → clear navigation cache + full navigate to /login
        ├─ 403/404/429/500 → render the returned error page (region swap) + status
        └─ network error → if safe cached snapshot → show it + "data may be stale" banner; else error state
```

### Options evaluated (per §9)
| Option | Verdict |
|---|---|
| **A — custom fetch + pushState + DOMParser** | **RECOMMENDED (progressive enhancement)** — smallest footprint, no package, uses existing Blade HTML, Alpine-safe |
| **B — HTML fragment navigation** | Partially recommended as an *optimization* (region swap of `#main-content`) rather than a separate fragment API; keeps one Blade source of truth |
| **C — Turbo-style** | Reference only. **Do NOT install Turbo** (forbidden: no new packages). |
| **D — Browser-native enhancement** | **Already in use** (`@view-transition`). Keep. Optionally add **Speculation Rules** for prefetch/prerender (pure browser, no package). |

### Key architectural decisions required (see §26)
1. **Shell strategy:** (a) replace only `#main-content` and recompute sidebar active state client-side, or (b) replace the whole authenticated shell region. Because the sidebar's active state is **server-derived**, option (a) requires a small client-side active-state sync; option (b) is simpler and still avoids asset re-download + keeps transitions. **Recommendation: start with (b) — replace the `<main>` region + the header, keep `<aside>` static, and add a tiny active-state sync; fall back to (b)-full if the sync proves brittle.**
2. **Whether to build the JS layer at all vs. rely on native transitions + Speculation Rules.** Measure first (§22).

---

## 8. Cache Architecture

Four **distinct** layers (never conflate — §32):

| Layer | What | Where | Recommendation |
|---|---|---|---|
| **L1 — Browser asset cache** | hashed CSS/JS/fonts/icons | browser HTTP cache | Vite hashes → `Cache-Control: public, max-age=31536000, immutable` (verify server config; **not currently set in app code**) |
| **L2 — Page navigation cache** | fetched HTML snapshots | **in-memory `Map` only** (JS) | **user+role+URL+query keyed; never persisted; cleared on logout/login/401/419/mutation** |
| **L3 — Application data cache** | Laravel `Cache` + `DashboardCacheService` | `database` store | **keep as-is**; do not add Redis |
| **L4 — DB query cache** | MySQL buffer pool | DB server | out of scope |

**Hard rules:**
- **Never** place authenticated HTML in `localStorage`, `sessionStorage`, `CacheStorage`, or any shared/server cache.
- **Never** send `Cache-Control: public` on an authenticated response.
- Navigation cache is **ephemeral** (page lifetime of the tab).

---

## 9. Page Cache Matrix

Classification legend: **NAV** = eligible for SPA navigation; **CACHE** = eligible for the in-memory page cache; **SCOPE** = cache key must include user/role; **SWR** = stale-while-revalidate; **INVAL** = invalidate on mutation.

| Page (route) | Role | NAV | CACHE | SCOPE | TTL | SWR | INVAL |
|---|---|---|---|---|---|---|---|
| `/` landing | public | yes | yes (anon) | no | session | yes | no |
| `login` | guest | **no** | **no** | n/a | — | — | — |
| `register` | guest | **no** | **no** | n/a | — | — | — |
| `forgot-password` | guest | **no** | **no** | n/a | — | — | — |
| `reset-password/{token}` | guest | **no** | **no** | n/a | — | — | — |
| `dashboard` (redirect) | auth | yes (follow) | no | yes | — | — | — |
| `laporan/dashboard` | citizen | yes | yes | yes | ~60 s | yes | on complaint create |
| `laporan/riwayat` (+`?page`) | citizen | yes | yes | yes | ~60 s | yes | on complaint create/status |
| `laporan/buat` (form) | citizen | yes | **no** (CSRF) | yes | — | — | — |
| `laporan/{complaint}` | citizen | yes | **conditional** | yes | ~30 s | **no** (freshness) | on status/public response |
| `laporan/{complaint}/lampiran/{attachment}` | citizen | **no** (binary) | no | yes | — | — | — |
| `laporan/profil` (form) | citizen | yes | **no** (CSRF) | yes | — | — | — |
| `operator/dashboard` | operator | yes | yes | yes | ~60 s | yes | on assignment/status |
| `operator/laporan` (+filters) | operator | yes | yes | yes | ~30 s | yes | on any complaint mutation |
| `operator/laporan/{complaint}` | operator | yes | **conditional** | yes | ~15 s | **no** | on any mutation of that complaint |
| `admin/dashboard` (+`?refresh=1`) | admin | yes | yes | yes | ~60 s | yes | global complaint changes |
| `admin/laporan` (+filters) | admin | yes | yes | yes | ~30 s | yes | global complaint changes |
| `admin/audit-log` | admin | yes | **conditional** | yes | ~30 s | yes | on any audited mutation |
| `super-admin/dashboard` | super_admin | yes | yes | yes | ~60 s | yes | broad |
| `super-admin/users` (+`?page`) | super_admin | yes | yes | yes | ~30 s | yes | on user create/update/toggle |
| `super-admin/users/create` (form) | super_admin | yes | **no** | yes | — | — | — |
| `super-admin/users/{user}/edit` (form) | super_admin | yes | **no** | yes | — | — | — |
| `super-admin/laporan` (+filters) | super_admin | yes | yes | yes | ~30 s | yes | complaint changes |
| `super-admin/laporan/{complaint}` | super_admin | yes | conditional | yes | ~15 s | no | that complaint |
| `super-admin/audit` (+filters) | super_admin | yes | conditional | yes | ~30 s | yes | audited mutations |
| `super-admin/audit/{auditLog}` | super_admin | yes | conditional | yes | ~60 s | yes | append-only |
| `super-admin/config` (+edit) | super_admin | yes | **no** (form on edit) | yes | — | — | on config update |
| `super-admin/dinas` (+create/edit) | super_admin | yes | index only | yes | ~60 s | yes | dinas mutations |
| `super-admin/kategori` (+create/edit/mapping) | super_admin | yes | index only | yes | ~60 s | yes | category/mapping mutations |

**Default posture: cache only read-only list/dashboard pages; never cache form pages; never cache detail pages with `SWR`.**

---

## 10. Cache Key Strategy

**Browser page-navigation cache key (in-memory `Map`):**
```text
key = userId + '|' + role + '|' + dinas_unit_id(if operator) + '|' + pathname + '|' + sorted(queryString)
```
Rationale:
- `userId` + `role` → **Threat 1–3** isolation (Prompt 15/21 scope depends on `role` and `dinas_unit_id`).
- `dinas_unit_id` → **Threat 2** (Operator Unit A vs Unit B — scope is unit-derived).
- `pathname` + normalized query → pagination/filter/search correctness.
- Never include volatile tokens (`_token`) as a key component; form pages are not cached.

**Forbidden keys:** IP-based, session-id-based-in-URL, or any key that omits user identity for an authenticated page.

---

## 11. Cache Invalidation Matrix

| Mutation (route) | Affected navigation cache |
|---|---|
| create complaint (`citizen.complaint.store`) | clear **all** (citizen dashboard/history + every staff list) |
| operator status update (`operator.complaint.update-status`) | clear all (detail + all lists + dashboards) |
| operator category change (`operator.complaint.update-category`) | clear all |
| operator destination change (`operator.complaint.update-destination`) | clear all |
| operator assign (`operator.complaint.assign`) | clear all (operator dashboards) |
| operator add note (`operator.complaint.add-note`) | clear that complaint detail + lists |
| attachment upload (part of store) | clear all |
| profile update (`citizen.profile.update`) | clear citizen pages + shell (name) |
| user create/update (`super-admin.users.store/update`) | clear users list + all operator caches (scope may change) |
| user activate/deactivate (`users.toggle`) | clear users list + operator dashboards + **invalidate session if self/inactive** |
| dinas/unit CRUD (`super-admin.dinas.*`) | clear dinas list + operator scope caches |
| category CRUD / mapping (`super-admin.categories.*`) | clear category list + mapping + complaint views |
| config update (`super-admin.config.update`) | clear super-admin config + complaint-create (daily limit) |
| login / logout | **clear ALL + reset cache identity** |

**Simplest safe default: on ANY successful mutation, clear the entire in-memory navigation cache.** Granular invalidation is a later optimization, not a correctness requirement.

---

## 12. Authentication & Cache Security

| Event | Design rule |
|---|---|
| login | clear navigation cache **before** first authenticated render; bind cache to new `userId`/`role` |
| logout (POST) | **purge entire cache + full navigate**; never serve cached authenticated page |
| session expiry (401/419) | purge cache; redirect to `login` |
| inactive account | server already logs out + invalidates session (`EnsureUserIsActive`, `EnsureUserHasRole`); navigation layer must treat the resulting redirect as authoritative |
| password change | `logoutOtherDevices()` + `auth.session` invalidate other sessions server-side; cache cleared on the resulting logout |
| browser Back after logout | **must not** reveal cached authenticated HTML → cache cleared on logout + `Cache-Control: private, no-store` on authenticated HTML |

**Threat 1 (User A → User B same browser):** cache is keyed by `userId` and **purged on logout/login**; B never sees A's snapshot.
**Threat 4 (inactive user):** server denies on next request; any cached snapshot is purged on the 401/redirect.

---

## 13. CSRF & Cache Security

**Facts:**
- Every authenticated page that contains a form embeds `@csrf` → `<input name="_token">` (23 `@csrf` occurrences). The token is **session-bound**.
- `PreventRequestForgery` accepts `_token` input **or** `X-CSRF-TOKEN` / `X-XSRF-TOKEN` headers.
- A cached page's stale `_token` → **419** on submit.

**Rules:**
1. **Do NOT cache form pages** (`create`/`edit`/`profile`/`config.edit`). They are excluded from the page cache.
2. **Prefer header-based CSRF** for any AJAX the layer performs: read the current token from `<meta name="csrf-token">` (**would need adding once to the layout** — a future implementation step) or from the just-fetched HTML, and send `X-CSRF-TOKEN`.
3. **On 419:** clear cache, refresh the page (full navigate), do **not** silently retry a mutation.
4. Never strip/alter `_token` during DOM swap; the swapped HTML carries a fresh token from the server.

---

## 14. Back/Forward Strategy

- `history.pushState({ key, url }, '', url)` on successful navigation.
- `popstate` → if the target URL is in the in-memory cache **and** the identity key matches → render snapshot instantly; else `fetch` + render.
- **Refresh** → normal full navigation (Laravel is source of truth).
- **Direct URL** → full server render (layer only enhances in-app clicks).
- **Snapshot safety:** snapshots are per-identity and purged on auth change; a `popstate` to an authenticated URL after logout falls through to a **full navigation** (server → login), never a cached snapshot.
- **Scroll restore:** store `scrollY` per history entry; restore on `popstate`.

---

## 15. Form Handling Strategy

| Aspect | Rule |
|---|---|
| Method | **Never intercept** POST/PATCH/PUT/DELETE. Only GET link clicks are enhanced. |
| CSRF | unchanged; `_token` preserved; header CSRF for any AJAX |
| Validation (422) | server-rendered errors must render correctly; after a form POST the server **redirects back** with flash + `$errors`, so the layer only needs to render the redirected page |
| Redirects | follow 302/303 to the target and render it |
| Flash | flash lives in session; the fetched post-redirect page already contains the flash markup → rendering the fetched HTML shows it |
| Multi-part upload | untouched (normal browser form submit) |
| Progressive enhancement | **if JS is off, everything still works** (plain forms) |

**Note:** the dashboard layout's top-level toasts read `session('success')`/`session('error')`; per-page views also render their own flash banners (e.g. `operator/complaint/index.blade.php:31-36`). The navigation layer must replace the flash region too (or rely on the per-page banners that live inside `#main-content`).

---

## 16. Error Handling Strategy

| Status | Behavior |
|---|---|
| **401 / 419** | purge cache → full navigate to `login` |
| **403** | render the returned error page in-region (or full navigate); do not retry |
| **404** | render the app's 404 (in-region or full navigate) |
| **422** | not expected for GET; for form flows use normal submit |
| **429** | render the app's `errors/429` view (exists) with `Retry-After`; do not auto-retry |
| **500 / 503** | render a friendly error state; keep the shell; do not cache the error |
| **Non-HTML response** | abort enhancement → `window.location.assign(url)` |

Only `429.blade.php` exists under `resources/views/errors/`; other error statuses use Laravel's default rendering. **Reported as a gap, not fixed here.**

---

## 17. Network Failure Strategy

```text
fetch() throws
   │
   ├─ snapshot exists for this URL AND identity matches AND page is SWR-safe
   │        → render snapshot + non-blocking banner "Menampilkan versi tersimpan; data mungkin belum terbaru"
   │
   └─ otherwise
            → render inline error state inside #main-content ("Gagal memuat. Coba lagi." + Retry button)
```
**No infinite retry. No background storm.** A single manual Retry. Never show a stale snapshot for `SWR = no` pages (complaint detail, audit detail).

---

## 18. Alpine Compatibility Strategy

**Verified mechanism:** Alpine 3.17.4 global MutationObserver auto-initializes inserted `x-data` nodes.
**Rules:**
1. `Alpine.start()` is called **once** (already true) — the navigation layer must **never** call it again.
2. After DOM swap, Alpine re-inits new components automatically; **no manual `Alpine.initTree`** needed (would double-init).
3. The only shell state is `sidebarOpen` (in `<aside>`); if the shell is not replaced, its state survives; if replaced, it resets to `false` (acceptable).
4. Any component holding **client-only** state that must survive navigation would need `Alpine.store`/`persist` — **none currently require this**.
5. Do **not** re-run inline `x-init` timers twice (toast timeouts) — they are re-created with the swapped region; the old nodes are discarded.

---

## 19. Prefetch Strategy

**Recommended (conservative):**
- Prefetch **only** on `hover`/`focus`/`mousedown` of **primary navigation links** (sidebar items), **debounced**, **GET-only**, **same-origin**, **not** for excluded pages.
- **Never** prefetch: mutations, logout, login/register/forgot/reset, attachment downloads, form pages.
- Prefetch results go into the same **in-memory, user-scoped** cache.
- Prefer **Speculation Rules API** (`<script type="speculationrules">` with `"eagerness": "conservative"`/`"moderate"`, `"where": { "href_matches": [...] }`) — **pure browser, no package**, and it can also *prerender*. Note: prerender of authenticated pages has session/CSRF considerations → start with **prefetch only**, prerender later (or never).
- **Cap** concurrency (e.g. ≤2 in-flight).

---

## 20. HTTP Cache Strategy

| Content | Recommended headers | Current state |
|---|---|---|
| Vite hashed assets (`/build/*`) | `Cache-Control: public, max-age=31536000, immutable` | **not set in app code** → verify web-server config (deploy-time) |
| Public HTML (`/`) | `Cache-Control: public, max-age=…` or `no-cache` | unset |
| **Authenticated HTML** | **`Cache-Control: private, no-store`** (+ `Vary: Cookie`) | **unset** — Laravel sets no cache header on session responses |
| Sensitive HTML (audit, complaint detail) | `private, no-store` | unset |
| Attachment downloads | `private, no-store` | handled per response |

**Do NOT add `public` to authenticated responses.** If server-side HTML caching is ever considered, `Vary: Cookie` is mandatory (not recommended here). **Note:** `StartSession` does not set a cache header; rely on explicit `private, no-store` on authenticated responses (a future, low-risk hardening step — but out of scope for this read-only prompt).

---

## 21. Service Worker Assessment

```text
NOT RECOMMENDED (for now)
```
Reasons:
- The goal is **SPA-like navigation + caching**, not offline/PWA.
- A SW introduces a **persistent, cross-tab, potentially cross-identity** cache — directly hostile to Threat 1–5.
- Cache invalidation, version skew, and "logout still shows cached page" are the classic SW failure modes.
- No offline requirement exists.
- Native View Transitions + a small in-memory layer already cover the target UX.

If offline is ever required, that is a **separate product decision** with its own threat model.

---

## 22. Performance Strategy

**Do not claim numeric improvements without benchmarks.** Metrics to measure **before/after**:
- navigation latency (click → content painted);
- server TTFB (from `Server-Timing`/network panel);
- DOM update time;
- cache hit / miss ratio;
- JS execution time of the layer;
- memory footprint of the in-memory cache;
- number of network requests per navigation.

**Conceptual model (§26 of prompt):**
- *Before:* click → full document request → Blade render → HTML → CSS/JS work → paint.
- *After (with layer):* click → (cached|fetch) HTML → region swap → paint.
**Actual gain depends on server TTFB vs. browser parse cost — measure, then decide whether to keep the layer.** Given native view transitions already exist and assets are already cached, the gain may be small for a small app.

---

## 23. Security Threat Model

| # | Threat | Risk | Impact | Mitigation |
|---|---|---|---|---|
| 1 | User A cached HTML → logout → User B login | High | Cross-user data leak | purge cache on logout/login; identity-keyed; `private, no-store` |
| 2 | Operator Unit A cached list → Operator Unit B | High | Cross-unit leak | cache key includes `dinas_unit_id`; purge on login |
| 3 | Super Admin cached audit → Admin login | High | Privilege-data leak | purge on login; role in key; server 403/404 on direct fetch |
| 4 | Inactive user cached dashboard | Medium | Stale access display | server denies next request; purge on 401/redirect |
| 5 | Session expired → Back → cached sensitive page | High | Data exposure after expiry | purge on 401/419; `no-store`; Back falls through to full nav when identity changed |
| 6 | Mutation → stale cached authorization state | Medium | Misleading state | **clear all cache on any mutation** |
| 7 | Cached HTML contains CSRF/user/complaint/audit data | High | Token/data exposure | in-memory only; no persistence; no shared cache; purge on auth change |

**Priority order (§33):** Security > Correctness > Freshness > Performance > Animation.

---

## 24. Test Strategy

**Navigation:** internal link enhanced; external link not; same-URL no-op; query string preserved; hash links; Back; Forward; Refresh (full nav); direct URL (full nav).
**Cache:** hit; miss; invalidation on mutation; stale behavior; **user isolation (A vs B)**; **role isolation**; **unit isolation**.
**Authentication:** login clears cache; logout purges; session expiry → login; password change; inactive account.
**Authorization:** citizen / operator / admin / super_admin; cross-unit operator → 404; direct fetch of out-of-scope URL → server 404 (never cached).
**Forms:** 422 rendering; CSRF `_token` present; 419 handling; 429 handling.
**Errors:** 401/403/404/500 render correctly in-region.
**Alpine:** modal/dropdown/toast/form-state after navigation; **no duplicate init**; sidebar active state correct after nav.
**Regression:** existing 551 tests must stay green (Prompt 15–24 suites).

**Note (from Prompt 24):** the test suite globally disables CSRF (`TestCase::setUp` → `withoutMiddleware(PreventRequestForgery::class)`). Any CSRF-related navigation test must not rely on that bypass for its security claim.

---

## 25. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Serving stale authenticated HTML | High | in-memory only; purge on auth change; no-store |
| Cross-user/role/unit leakage via cache | High | identity+role+unit in key; purge on login/logout |
| 419 from cached `_token` | Medium | don't cache form pages; header CSRF; full nav on 419 |
| Sidebar active state wrong after swap | Medium | client-side active sync **or** replace shell |
| Double Alpine init | Medium | never call `Alpine.start()` again |
| Flash message lost after swap | Medium | swap flash region / rely on in-content banners |
| Prefetch load on server | Low | hover/focus only, debounced, capped |
| Added JS = new attack surface | Medium | tiny, no eval, no third-party, CSP-friendly |
| Perceived complexity vs. benefit | Medium | measure first; native transitions may suffice |
| Duplicate font loading (Google + bunny) | Low | pre-existing; note only |

---

## 26. Architecture Decision Record (ADR)

| ID | Decision | Status |
|---|---|---|
| ADR-25-1 | Use a **custom lightweight progressive navigation layer** (Option A) as progressive enhancement; **do not** install Turbo/any package | PROPOSED |
| ADR-25-2 | **Keep** native Cross-Document View Transitions (`@view-transition`) | PROPOSED |
| ADR-25-3 | Page cache = **in-memory only**, identity-keyed; **no** localStorage/sessionStorage/CacheStorage/SW | PROPOSED |
| ADR-25-4 | **Shell strategy:** replace `<main>` + header, keep `<aside>`; add minimal active-state sync (**DECISION REQUIRED**: sync vs full-shell replace) | DECISION REQUIRED |
| ADR-25-5 | **Do not cache form pages**; header-based CSRF for AJAX | PROPOSED |
| ADR-25-6 | **Clear entire cache on any mutation** (granular later) | PROPOSED |
| ADR-25-7 | **No Service Worker** | PROPOSED |
| ADR-25-8 | Authenticated responses should send `Cache-Control: private, no-store` (**DECISION REQUIRED** on exact header policy) | DECISION REQUIRED |
| ADR-25-9 | Prefetch: hover/focus, GET-only, primary nav, capped; consider Speculation Rules (prefetch first) | PROPOSED |
| ADR-25-10 | **Build order:** measure first; implement layer only if it beats native transitions on a real metric | DECISION REQUIRED |

---

## 27. Files That Would Need Modification (future implementation)

> None modified in Prompt 25. Listed for planning only.

| File | Purpose |
|---|---|
| `resources/js/app.js` | bootstrap the navigation layer after `Alpine.start()` |
| `resources/js/navigation.js` (**new**) | the navigation layer (fetch/pushState/DOMParser/cache) |
| `resources/css/app.css` | loading indicator styles; keep `@view-transition` |
| `resources/views/components/layouts/app.blade.php` | add `<meta name="csrf-token">`, top progress bar, cache/stale banner hooks |
| `resources/views/components/layouts/dashboard.blade.php` | stable hooks for region swap + active-state attributes (`data-nav-item`) |
| `bootstrap/app.php` | **only if** a cache-header policy is adopted (deploy-time) |
| `tests/Feature/**` | navigation/cache/security regression tests |

---

## 28. Files That Must NOT Be Modified (by this initiative)

| Path | Reason |
|---|---|
| `app/Enums/ComplaintStatus.php` | FROZEN (7 statuses / 11 transitions) |
| `app/Enums/UserRole.php` | FROZEN (4 roles; no `petugas`) |
| `config/business_rules.php` | single source of truth for frozen rules |
| `routes/web.php` route **semantics**, middleware, authorization | frozen security model |
| `app/Http/Middleware/*`, Form Requests, controller authorization | frozen authorization model |
| `bootstrap/app.php` CSRF/session/auth wiring | frozen security model (cache headers aside) |
| `config/auth.php`, login rate-limit policy | frozen (D-5) |
| **Do not recreate** `resources/js/spa.js` or any SPA engine | removed in Prompt 19; `SpaRemovalTest` guards |
| **Do not add** Redis, Livewire, React/Vue, Inertia, API layer, SW | forbidden architecture |

---

## 29. Implementation Phases (future, NOT in Prompt 25)

```text
Phase 0  Measurement & decisions (ADR-25-4, ADR-25-8, ADR-25-10)
Phase 1  Navigation foundation (GET intercept + fetch + region swap + pushState)
Phase 2  Shell & active-state strategy (persistent aside + sync)
Phase 3  In-memory, identity-keyed page cache (+ purge on auth change)
Phase 4  Authentication safety (logout/login/401/419 purge; no-store)
Phase 5  Mutation invalidation (clear-all on success)
Phase 6  Alpine lifecycle verification (no double init; component tests)
Phase 7  Prefetch (hover/focus, capped; optional Speculation Rules)
Phase 8  Performance verification (before/after metrics)
Phase 9  Security regression (threat model tests 1–7)
Phase 10 Production verification (headers, cross-browser, reduced-motion)
```
**None of these phases is executed in Prompt 25.**

---

## 30. Final Recommendation

1. **Do NOT resurrect `spa.js`.** Build a **new, minimal, well-tested** progressive navigation layer only after **Phase 0 measurement** proves a benefit over the **already-present native View Transitions**.
2. **Layer, don't replace:** keep Blade server-rendered, keep Laravel as source of truth, keep Alpine single-start.
3. **Cache conservatively:** in-memory, identity+role+unit keyed, **no persistence**, **no SW**, **no shared/server HTML cache**, **no caching of form pages**, **clear-all on mutation**, **purge on any auth change**.
4. **Intercept GET only.** Never touch mutations, logout, auth pages, or attachment downloads.
5. **Treat every cache read as a security boundary** — Threat 1–7 are the acceptance tests.
6. **If measurement shows the native transitions already meet the UX goal, ship the native path alone** — that is a legitimate and secure outcome.

---

## 31. Final Classification

```text
READY WITH ARCHITECTURAL DECISIONS REQUIRED
```

**Discovery is complete** (frontend, routes, layouts, Alpine lifecycle, session/CSRF, error/flash surfaces all mapped). Implementation is **not** blocked by missing information — it is blocked by **four explicit decisions**:

1. **Shell strategy** — persistent `<aside>` + client active-state sync vs. full-shell replace (ADR-25-4).
2. **Whether to build the JS layer at all** vs. native transitions + Speculation Rules, pending measurement (ADR-25-10).
3. **HTTP cache-header policy** for authenticated HTML (`private, no-store`) (ADR-25-8).
4. **Prefetch scope** (hover/focus, capped) and whether to use Speculation Rules (ADR-25-9).

No implementation was performed. Repository state unchanged (HEAD `bf75450`).

**— END OF PROMPT 25 DESIGN —**
