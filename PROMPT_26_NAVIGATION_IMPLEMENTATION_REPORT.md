# Prompt 26 Implementation Report
## Progressive SPA-like Navigation + Intelligent In-Memory Caching

---

## Status

**PASS WITH DECISION REQUIRED**

The progressive navigation layer is implemented, wired into the application, and fully
covered by the existing suite plus 15 new server-contract/security tests. No security,
authorization, mutation, CSRF, authentication, Alpine, route, or database regression.

`DECISION REQUIRED` is retained **only** for the server-side HTTP cache-header policy for
authenticated HTML (Prompt 25 **ADR-25-8**), which Prompt 25 itself left as a decision and
which Prompt 26 §47 explicitly defers to ("sesuai keputusan arsitektur Prompt 25"). The
prohibition in §47 (never make authenticated HTML `public`) **is** respected.

---

## Baseline (Prompt 23 / Prompt 24)

| Metric | Before |
|---|---:|
| Tests | 551 |
| Assertions | 2402 |
| Routes | 61 |
| Build (`npm run build`) | PASS |

## After

| Metric | After | Delta |
|---|---:|---:|
| Tests | 566 | **+15** |
| Assertions | 2468 | **+66** |
| Routes | 61 | **0** |
| Build (`npm run build`) | PASS | — |

Assertion delta is fully explained: **+61** from the 15 new tests, **+5** from the existing
`SpaRemovalTest` which iterates every file under `resources/js/` and asserts 5 SPA needles
per file (one new file = +5). This is the anti-SPA guard working as intended.

---

## Implementation

1. **`resources/js/navigation.js`** (new) — the progressive GET navigation + in-memory
   page cache layer. Pure browser APIs only (`fetch`, `URL`, `DOMParser`, `Map`,
   `AbortController`, History API, View Transition API).
2. **`resources/js/app.js`** (modified) — imports and boots the layer **after**
   `Alpine.start()`. `Alpine.start()` is still called **exactly once**.
3. **`resources/views/components/layouts/app.blade.php`** (modified) — emits the
   server-derived `navigation-identity` meta (`@auth` only).
4. **`resources/views/components/layouts/dashboard.blade.php`** (modified) — adds the
   stable shell hooks `#primary-navigation`, `#page-heading`, `#page-actions`
   (`#main-content` already existed).
5. **`resources/css/app.css`** (modified) — minimal, non-blocking top progress bar
   (`#sb-nav-progress`) with a `prefers-reduced-motion` fallback.
6. **`tests/Feature/ProgressiveNavigationTest.php`** (new) — 15 tests / 61 assertions
   pinning the server-side security contract the client depends on.
7. **Docs** — `architecture.md` (§9.1 new + §12 note + §1 decision update),
   `README.md`, `rules.md`.

---

## Navigation Architecture

```
Browser
  ├── Normal navigation  → Laravel → Middleware → Authorization → Controller → Blade
  └── Progressive nav    → fetch(GET) → Laravel → Blade HTML → region swap → Alpine lifecycle
```

- **Intercepted:** same-origin `<a>` **GET** clicks (left button, no modifier keys).
- **Never intercepted:** POST/PUT/PATCH/DELETE, logout (POST form), `target="_blank"`,
  `download`, `mailto:`/`tel:`, hash-only links, external origins, file extensions
  (`.pdf/.xlsx/.csv/.zip/...`), attachment downloads (`/lampiran/`), auth pages, and any
  link marked **`data-no-navigation`** (opt-out).
- **Shell swap regions** (server-rendered, so active state and header are always
  server-authoritative — no client-side active-state reimplementation):
  - `#main-content` — page body
  - `#primary-navigation` — sidebar menu (fixes the per-page nav-slot differences, e.g.
    `str_starts_with($currentRoute, 'operator.complaint')`)
  - `#page-heading` — title + breadcrumb
  - `#page-actions` — header actions
  - `<title>` — updated from the response
- **Layout safety:** if the current page and the fetched page disagree on shell presence,
  the layer falls back to a **full browser navigation** (never a hybrid DOM).
- **History:** `pushState` on click, `popstate` on Back/Forward (renders without pushing →
  no loop). `history.scrollRestoration = 'manual'`; new nav → top, Back/Forward → restored
  from `history.state.scrollY`.
- **View Transitions:** `document.startViewTransition()` used when available (existing
  `::view-transition-*` CSS drives it), plain swap otherwise; skipped under
  `prefers-reduced-motion`. Never required for navigation to work.
- **Initial load / refresh / direct URL / bookmark:** always a normal Laravel request.

---

## Cache Architecture

- **Storage:** a single per-tab `Map` (`pageCache`). **No** `localStorage`,
  `sessionStorage`, `CacheStorage`, IndexedDB, or Service Worker.
- **Key:** `identityHash + '::' + pathname + search` (query strings are preserved and
  significant; hashes are excluded).
- **Identity:** read from `<meta name="navigation-identity">` —
  `hash_hmac('sha256', id . '|' . role . '|' . dinas_unit_id, APP_KEY)`, rendered
  `@auth` only. Guests have no identity → **caching disabled** (safe fallback per §18).
- **TTL:** 60 s (`CONFIG.cacheTTL`), configurable in one place.
- **Size:** max 10 entries, LRU eviction (`CONFIG.maxCacheEntries`).
- **Cacheable pages only:** must be 2xx HTML, non-auth, non-form path, **and** the fetched
  `#main-content` must contain **no non-GET form** (a mutation surface ⇒ never cached).
- **Never cached:** non-2xx, non-HTML, redirected-to-login, mutation-form pages, auth/form/
  attachment paths, `no-store` responses.
- **No stale-while-revalidate:** fresh network response **or** non-expired cache entry.
- **Invalidation:**
  - any mutation → full browser navigation (Laravel PRG) → new JS context → cache gone;
  - identity change detected in a response (role/unit change, re-login) → `clearNavigationCache()`;
  - session-expiry/auth redirect (401/419 or redirect to `/login`) → `clearNavigationCache()` + real navigation;
  - `pageshow` with `event.persisted` (bfcache restore) → `clearNavigationCache()`;
  - `clearNavigationCache()` is module-internal (not a global).

---

## Security

- Client cache is **never** an authorization layer; the server decides access and the
  client honours `403`/`404` by falling back to a real navigation.
- Cache is namespaced on three axes — **user id, role, `dinas_unit_id`** — so
  cross-user, cross-role and cross-unit reuse is impossible by construction; an identity
  change purges everything.
- No credentials, session ids, CSRF tokens or personal data are placed in cache keys or
  snapshots.
- Snapshot stores only `mainHTML`/`headingHTML`/`actionsHTML`/`navHTML`/`title`/`url`/
  `timestamp`/`identityKey`/`scrollY` — never the full document, never secrets.
- CSRF, session, middleware and route authorization are untouched.
- Fetches use `credentials: 'same-origin'`, `Accept: text/html` (so Laravel renders **HTML**
  errors, not JSON), and **do not** send `X-Requested-With`.

---

## Alpine Lifecycle

- `Alpine.start()` is called exactly once (asserted in tests).
- No second global MutationObserver is added; the existing Alpine observer re-initializes
  swapped nodes automatically.
- Old nodes are removed with their listeners; no manual re-bootstrap, no duplicate
  listeners, no `Alpine.start()` after swap.

---

## Prefetch

- Triggered on `mouseover`/`focusin` of links **inside `#primary-navigation`** only.
- Max **2 concurrent** requests; skipped when already cached/in-flight; best-effort only.
- Skips mutations, auth pages, downloads, external URLs, form pages and pages with
  mutation forms. Prefetched responses are identity-scoped, TTL-scoped, size-limited and
  cleared by `clearNavigationCache()`. Navigation works identically if prefetch fails.

---

## Error Handling

| Case | Behaviour |
|---|---|
| Non-2xx (403/404/429/5xx) | No cache; hand off to browser (`location.assign`) so the server renders the error page |
| 401 / 419 | `clearNavigationCache()` + real navigation |
| Auth redirect (→ `/login`) | `clearNavigationCache()` + real navigation |
| Non-HTML content type | Real navigation |
| Network / timeout / parse failure | Real navigation (current page never destroyed before a valid response) |
| Shell/layout mismatch | Real navigation |
| Rapid navigation | Previous `fetch` aborted via `AbortController`; abort is not treated as an error |

The current page is **never** cleared before a validated response is parsed.

---

## Files Changed

### Added
```
resources/js/navigation.js
tests/Feature/ProgressiveNavigationTest.php
```

### Modified
```
resources/js/app.js
resources/css/app.css
resources/views/components/layouts/app.blade.php
resources/views/components/layouts/dashboard.blade.php
architecture.md
README.md
rules.md
```

### Deleted
```
(none by Prompt 26)
```

> `resources/views/layouts/dashboard.blade.php` shows as modified in `git status`, but that
> is the **pre-existing** F-18-37 dead-code cleanup from an earlier prompt (the file
> contains **none** of the Prompt 26 hooks). `resources/js/spa.js` shows as staged-deleted
> from Prompt 19. Neither is a Prompt 26 change.

---

## Tests Added

`tests/Feature/ProgressiveNavigationTest.php` — 15 tests / 61 assertions:
identity meta present for authenticated / absent for guests; identity is a 64-hex digest
(not the raw triple); identity differs across users, roles, and Dinas/Unit; identity
changes when an operator's unit changes; shell hooks present on shell pages and absent on
auth pages; `navigation.js` exists and is bootstrapped by `app.js`; `Alpine.start()` called
exactly once; **no** forbidden storage / Service Worker in code (comments stripped); not the
removed SPA engine; GET-only + no mutation submission + identity-keyed cache +
`clearNavigationCache`; auth/logout/download/form-path exclusions present; pages remain
fully server-rendered; route inventory unchanged (61).

---

## Regression

| Check | Result |
|---|---|
| Full suite | **566 / 566 pass, 0 failed, 0 skipped** |
| Operator Dinas/Unit scoping | PASS |
| Last active Super Admin | PASS |
| Complaint submission rate limit | PASS |
| Authentication rate limit | PASS |
| Operator lifecycle authorization | PASS |
| Session invalidation / password change | PASS |
| Attachment IDOR / Super Admin global visibility | PASS |
| `SpaRemovalTest` (no SPA reintroduction) | PASS |
| `ProductionOperationsTest` | PASS |

---

## Performance

**Browser-level benchmark is NOT available in this environment** (no headless browser / JS
runtime, and adding one would require a new package — forbidden). The table below therefore
contains only an **honest server-side reference**, not a client navigation benchmark.

| Scenario | Before | After | Improvement |
|---|---:|---:|---:|
| Full navigation (server round-trip, `/login`) | ~571 ms* | ~571 ms* | 0% (unchanged path) |
| Cache miss (client fetch + swap) | N/A | **not measured** | — |
| Cache hit (in-memory) | N/A | **not measured** | — |
| Prefetch + navigation | N/A | **not measured** | — |

\* Local `php artisan serve` on Windows/Laragon; includes framework boot, not network/browser
paint. Not comparable to a real deployment.

Measured, non-fabricated facts that ARE available:
- Built `app` bundle: **60.1 KB** (JS), containing the navigation layer.
- Navigation layer adds no new HTTP request on cache hit; it reuses Blade HTML already
  fetched — the win is avoided full-document reload, not a server change.
- `npm run build`: PASS. `public/build/assets/app-*.js` contains the layer; **0** hits for
  `localStorage|sessionStorage|CacheStorage|serviceWorker`.

**Stated explicitly: a real browser performance benchmark has not been produced.**

---

## Route Integrity

`php artisan route:list` → **61 routes** (unchanged). Asserted in
`test_route_inventory_is_unchanged`.

## Database Integrity

No migration created, no schema change, no seed change, no destructive operation.
`migrate:status` unchanged (`…100008` remains **Pending** — intentional deploy-time step).

---

## Known Limitations

1. **Browser behaviour is not covered by automated tests.** The repo has no JS test runner
   and Prompt 26 forbids adding packages. Cache hit/miss, Back/Forward, and Alpine re-init
   are verified via source-contract tests + server-contract tests, not a browser runner.
2. **bfcache.** A browser may restore an authenticated page (and this `Map`) after logout.
   Mitigated by clearing the cache on `pageshow` (`persisted`) and re-validating on the next
   interaction; full mitigation needs `Cache-Control: private, no-store` on authenticated
   responses → **DECISION REQUIRED (ADR-25-8)**.
3. **Mid-session unit change window.** If an operator's `dinas_unit_id` changes while a tab
   is open, stale entries can persist up to the 60 s TTL until the next navigation response
   reveals the new identity (which purges the cache). Same-user freshness issue, not a
   cross-user leak.
4. **GET filter forms** (`<form method="GET">`) still perform a full navigation — only
   `<a>` links are intercepted (deliberate, conservative scope).
5. **Auth/`no-store` HTML headers** remain at the pre-Prompt-26 status quo.

---

## Deferred Items

- **ADR-25-8** — `Cache-Control` policy for authenticated HTML (`private, no-store`?):
  `DECISION REQUIRED` (carried from Prompt 25).
- **ADR-25-9** — prefetch scope: implemented conservatively (primary nav only, 2 concurrent);
  confirm or widen.
- **ADR-25-4** — shell strategy: resolved as "persistent `<aside>`/shell element, server-rendered
  region swap"; no full-body swap.
- **ADR-25-10** — build the layer: **resolved (built)**.
- All **Prompt 24 P1** operational items remain open (backup/restore, deployment target,
  scheduler trigger, monitoring/alerting, RPO/RTO, registration/forgot-password abuse
  policy, F-20-01 reset enumeration, credential rotation).

---

## Security Checklist (final)

```
[x] no localStorage
[x] no sessionStorage
[x] no CacheStorage
[x] no Service Worker
[x] no SPA package (React/Vue/Inertia/Livewire/Turbo)
[x] no new API architecture
[x] no authorization moved client-side
[x] no credential cached
[x] no cross-user cache (identity-scoped key)
[x] no cross-role cache (role in identity)
[x] no cross-unit cache (dinas_unit_id in identity)
[x] mutation clears cache (full PRG navigation + explicit clear)
[x] logout clears cache (full navigation + pageshow clear)
[x] login clears cache (full navigation; identity change purges)
[x] 401/419 clears cache
[x] no mutation interception (GET-only)
[x] no CSRF change
[x] no route authorization change
[x] no database change
```

---

## Final Verdict

**PASS WITH DECISION REQUIRED.**

SuperBie now has:

```
Laravel Blade + Alpine.js + Native View Transitions
      + Progressive GET Navigation
      + In-Memory Page Cache
      + Identity-Aware Cache Isolation
      + Mutation Invalidation
      + Conservative Prefetch
      + Progressive Enhancement
```

…without becoming an SPA. Laravel remains the source of truth; the client cache is an
optimization layer only; authorization stays server-side; all existing security boundaries
are frozen. The single open item is the server-side HTTP cache-header policy for
authenticated HTML (**ADR-25-8**, `DECISION REQUIRED`), which is why the verdict is not a
bare PASS — and per §86, "production ready" is **not** claimed while Prompt 24 operational
decisions remain unresolved.
