# Prompt 18 — Comprehensive Security & Architecture Gap Audit

**Project:** SuperBie — Lapor Pak Wali
**Type:** READ-ONLY discovery / security & architecture gap audit
**Audit scope:** entire repository (docs, `app/`, `routes/`, `config/`, `database/`, `resources/`, `tests/`, `composer.*`, `package*.json`, `.env.example`, Docker/CI)
**Stack context:** Laravel 13.17 / PHP 8.4.26 / MySQL (port 3307) / Blade + Alpine.js + Tailwind v4 + Vite / monolith
**Audit date context:** post-Prompt-17 state

**Rule compliance (read this first):**
No source file, migration, route, view, config, test, or database record was modified. No `migrate`, `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`, `DROP`, `TRUNCATE`, `DELETE`, or `ALTER` was executed. No package was installed. No business rule, status enum, or transition was changed. The **only** file written by this prompt is this report. Where a rule is genuinely undefined, this report records `DECISION REQUIRED` rather than choosing a value.

**Verdict (summary):** **NOT PRODUCTION READY.** The core domain logic is coherent, well-tested (492/492), and the frozen rules are enforced server-side, but material security, configuration, and architecture-drift gaps remain (see §28–§33).

---

## 1. Executive Summary

The application is a **modular monolith** with clean layering (`app/Http`, `app/Models`, `app/Services`, `app/Enums`, `app/Observers`) and a strong server-side authorization posture: every protected route passes through `role:*` middleware, complaint ownership is re-checked inline, and the frozen complaint lifecycle (7 statuses / 11 transitions / `closed` terminal) is enforced in the domain layer. Prompt 15/16/17 additions are present and their regression suites pass.

However, this audit surfaces **findings that block a "production ready" claim**:

1. **A live client-side SPA engine** (`resources/js/spa.js`, 490 lines, auto-started) contradicts the frozen "no SPA / no separate API server" architectural constraint and is loaded unconditionally across the authenticated area. (§21, F-18-01)
2. **Plaintext database credentials committed** in a git-tracked `phpunit.xml` (`DB_USERNAME=ruthless`, `DB_PASSWORD=Ruthless*77`). (§12, F-18-02)
3. **Timezone misconfiguration**: `config/app.php` hardcodes `'timezone' => 'UTC'` while `.env` sets `APP_TIMEZONE=Asia/Makassar`; the value is **dead**. All daily-limit and retention boundaries therefore compute in UTC, not WITA. (§12/§16, F-18-03)
4. **No rate limiting** on registration or password-reset request; login limiting uses a 422 validation response (not 429) and keys on `email|ip`. (§11, F-18-04)
5. **No email verification**, no session invalidation on password change, and default-weak production defaults shipped in `.env.example` (`APP_DEBUG=true`, `APP_NAME=Laravel`, `APP_LOCALE=en`, no `SESSION_SECURE_COOKIE`). (§5/§12, F-18-05…F-18-07)
6. **Operator lifecycle mutations are under-constrained** (any active category; any mapped unit including one the operator does not own; any active operator across units). These may be intentional, but no approved rule was found → `DECISION REQUIRED`. (§16, F-18-09)
7. **Documentation drift** on public tracking, Policies/Gates, and receipt/tracking pages still describes features that are intentionally not built. (§22, F-18-11)
8. **Unusual/unverified dependencies** (`laravel/pao`, `laravel/agent-detector`, npm `@laravel/multiplex`) and **no CI**; dependency vulnerability scan could not be run cleanly. (§13/§24, F-18-08)

No **P0** (exploitable-now, data-loss, or auth-bypass) finding was confirmed. Findings are graded P1–P3 with a `DECISION REQUIRED` register (§33).

---

## 2. Scope, Method & Rules of Engagement

**In scope (audited):**
- `app/` — controllers, form requests, middleware, models, services, observers, enums, providers.
- `routes/web.php`, `routes/console.php`, `bootstrap/app.php`.
- `config/*` (app, session, auth, filesystems, cache, business_rules, logging, database).
- `database/migrations/*`, `database/factories/*`, `database/seeders/*`.
- `resources/js/*`, `resources/views/**`, `resources/css/*`.
- `tests/**`, `phpunit.xml`, `composer.json`/`composer.lock`, `package.json`/`package-lock.json`.
- `.env.example`, `.gitignore`, docs (`README.md`, `prd.md`, `rules.md`, `architecture.md`, `design.md`, `schema.md`, `SETUP_AND_DOCS.md`).

**Method:** direct read of every file area above; `grep` for security sinks; read-only Artisan commands (`route:list`, `migrate:status`, `test`). Four background exploration sub-agents failed/cancelled, so the sweep was performed directly.

**Forbidden actions (honored):** any DDL/DML mutation, any `migrate*`/`db:*` mutation, any package install, any code edit, any business-rule change.

**Severity scale:** P0 = critical (auth bypass / data loss / RCE / live secret exposure); P1 = high (security control missing on a sensitive surface); P2 = medium (hardening/architecture/config gap); P3 = low (hygiene, dead code, docs drift).

**Status vocabulary:** `CONFIRMED`, `PRE-EXISTING`, `INTRODUCED BY PROMPT X`, `ALREADY MITIGATED`, `NOT APPLICABLE`, `DECISION REQUIRED`.

---

## 3. Repository Baseline (read-only verification)

| Check | Command | Result |
|---|---|---|
| Test suite | `php artisan test` | **492 passed / 0 failed / 0 skipped / 1638 assertions** (42.8 s) |
| Route count | `php artisan route:list` | **61 routes** |
| Migration state | `php artisan migrate:status` | `…100005`–`…100007` **Ran**; `2025_01_01_100008_add_dinas_unit_id_to_users_table` **Pending** (intentionally not applied) |
| Frontend build | `npm run build` | **PASS** (one pre-existing non-blocking warning; exit code success) |
| Git HEAD | `git log --oneline -3` | single commit `bf75450` |
| Working tree | `git status --porcelain` | ≈ **121 entries** (all changes uncommitted) |
| Docker / CI | `Test-Path Dockerfile / docker-compose.yml / .github` | **absent** (all `False`) |

**Baseline verdict:** reproducible, green, and consistent with the Prompt 15/16/17 reports. Migration `100008` pending is expected and documented (Prompt 15).

---

## 4. Threat Model & Trust Boundaries

**Trust boundaries:**
1. **Guest ↔ authenticated** — enforced by `auth` middleware (`routes/web.php:35,44,68,85,94`).
2. **Citizen ↔ staff** — enforced by `role:masyarakat` / `role:operator,super_admin` / `role:admin` / `role:super_admin` (`routes/web.php:44,68,85,94`).
3. **Operator ↔ Operator (per-unit scope)** — enforced per-complaint by `authorizeComplaintAccess()` (`OperatorComplaintController.php:555–568`) and at query level by `Complaint::scopeVisibleToOperator()` (`Complaint.php:131–149`).
4. **Authenticated user ↔ own data** — enforced inline for citizen complaints (`Citizen/ComplaintController.php:151`) and profile.

**Primary assets:** complaint content + attachments (private disk), community identity (NIK/HP/Alamat), audit trail, master data (categories/dinas/mappings), user accounts.

**Principal threats considered:** IDOR/privilege escalation, mass assignment, CSRF, session fixation/hijacking, brute force/abuse, secret leakage, unsafe file upload, TOCTOU on privileged mutations, information disclosure via errors/logs, supply-chain.

**Boundary finding:** the client-side SPA engine (§21) is a **new trust boundary that was never designed** — it fetches and caches authenticated HTML client-side and swaps DOM nodes. It does not bypass server-side authorization, but it is architecturally outside the frozen design.

---

## 5. Authentication & Session Security

**Implemented correctly:**
- Session regeneration on login: `AuthenticatedSessionController.php:23` (`$request->session()->regenerate()`).
- Inactive-account lockout at login and on every request: `AuthenticatedSessionController.php:27–30`, `EnsureUserIsActive.php:17–23`.
- Generic password-reset response to prevent enumeration: `PasswordResetController.php:33–34`.
- `password` + `remember_token` hidden from serialization: `User.php:39–42`.
- `password => 'hashed'` cast: `User.php:53`.

**Findings:**

- **F-18-05 (P2) — No email verification.** `MustVerifyEmail` is commented out (`app/Models/User.php:5`), `User` does not implement the contract (`User.php:12`), and no `verified` middleware is used. Registration grants full access immediately. Status: `CONFIRMED` / `PRE-EXISTING`. Whether email verification is required is not stated in the frozen rules → `DECISION REQUIRED` (§33, D-4).
- **F-18-06 (P2) — Password change does not invalidate other sessions.** `ProfileController::update()` re-hashes the password (`ProfileController.php:62`) but performs no `Auth::logoutOtherDevices()` / session invalidation, so an attacker holding a stolen session remains authenticated after the victim rotates their password. The password-reset flow rotates `remember_token` (framework default) but does not kill existing sessions either. Status: `CONFIRMED` / `PRE-EXISTING`.
- **F-18-07 (P2) — Session cookie hardening not set for production.** `config/session.php:172` `'secure' => env('SESSION_SECURE_COOKIE')` (unset in `.env.example`), `:185` `http_only` default true (good), `:202` `same_site` = `lax`. Without `SESSION_SECURE_COOKIE=true` on HTTPS, the session cookie can be sent over plaintext. `.env.example:30–34` ships no `SESSION_SECURE_COOKIE`. Status: `CONFIRMED`.
- **Not applicable:** no Sanctum/JWT/token API; auth is session-only by design (consistent with the frozen "monolith, no API gateway" rule).

---

## 6. Authorization Model & IDOR Audit

**Mechanism:** middleware `role:*` (`EnsureUserHasRole.php:36–38`, 403) + Form Request `authorize()` + **inline controller checks**. There are **no Policy/Gate classes** (`glob app/Policies/**` → 0; `PROMPT_14` §A.4 confirms).

**IDOR checks verified:**
- Citizen complaint `show`: ownership re-checked inline → `abort(403)` if `reporter_id !== auth()->id()` (`Citizen/ComplaintController.php:151`).
- Citizen attachment download: scoped through the complaint ownership chain.
- Operator `show`/`updateCategory`/`updateDestination`/`assign`/`updateStatus`/`addNote`/`downloadAttachment`: each calls `authorizeComplaintAccess()` (`OperatorComplaintController.php:155, 293, …`) which returns **404** for out-of-scope operators (`:566–567`).
- Super Admin `users/{user}`, `audit/{auditLog}`, `dinas/{dinas_unit}`, `kategori/{category}`: numeric-bound (`whereNumber`) and gated by `role:super_admin`.
- Route-model binding on `{complaint}` uses `whereNumber` (`routes/web.php:58,61`) → non-numeric segments 404 before hitting the controller.

**Findings:**
- **No P0/P1 IDOR found.** Operator cross-unit access returns 404; citizen cross-user access returns 403. HTTP conventions are consistent (`PROMPT_15` §4).
- **F-18-10 (P3) — Policy/Gate drift.** `rules.md:25` instructs "Use Policies/Gates for authorization", but no Policy/Gate exists and `architecture.md:43,132` documents their intentional absence. The rule text and the architecture doc contradict each other. Status: `PRE-EXISTING` / doc drift.

---

## 7. Middleware Audit

| Middleware | File | Registered | Behavior |
|---|---|---|---|
| `EnsureUserHasRole` | `app/Http/Middleware/EnsureUserHasRole.php` | alias `role` (`bootstrap/app.php:16`) | 403 on role mismatch; logs out inactive users (`:28–34`) |
| `EnsureUserIsActive` | `app/Http/Middleware/EnsureUserIsActive.php` | alias `active` (`:17`) **and** appended to `web` group (`:20–22`) | logs out + session invalidate on inactive |

**Findings:**
- **F-18-12 (P3) — `active` alias is unused on routes.** Because `EnsureUserIsActive` is already appended to the whole `web` group (`bootstrap/app.php:20–22`), the `active` alias (`:17`) is dead configuration. Harmless but confusing. Status: `CONFIRMED` / `PRE-EXISTING`.
- **F-18-13 (P3) — No trusted-proxy configuration.** `bootstrap/app.php` has no `trustProxies()`. Behind a reverse proxy, `$request->ip()` (used in audit logs and the login throttle key, `LoginRequest.php:66`) records the proxy IP, weakening IP-based controls and audit fidelity. Status: `CONFIRMED`; whether a proxy will front the app is `DECISION REQUIRED` (§33, D-6).
- **F-18-14 (P3) — No `config/cors.php`.** Not required for a same-origin monolith; noted as `NOT APPLICABLE` unless an external origin is introduced.

---

## 8. Route Inventory & Access Control Matrix

61 routes total. Middleware groups:

| Prefix | Middleware | Notes |
|---|---|---|
| `/`, `/login`, `/register`, `/forgot-password`, `/reset-password` | `guest` (`routes/web.php:22`) | public |
| `/dashboard`, `/logout` | `auth` (`:35`) | role-routed via `DashboardRedirectController` |
| `/laporan/*` | `auth`, `role:masyarakat` (`:44`) | citizen; POST `buat` adds `throttle:complaint-submission` (`:53`) |
| `/operator/*` | `auth`, `role:operator,super_admin` (`:68`) | scope-guarded per complaint |
| `/admin/*` | `auth`, `role:admin` (`:85`) | monitoring only, no mutation routes (`:89`) |
| `/super-admin/*` | `auth`, `role:super_admin` (`:94`) | user mgmt, read-only laporan/audit, config, master data |

**Findings:**
- **F-18-15 (P2) — No throttle on `POST /register` or `POST /forgot-password`.** `routes/web.php:27` (register) and `:30` (forgot-password) have no `throttle:` middleware. `POST /login` is limited inside `LoginRequest` (§11). Registration is therefore open to scripted account creation and the reset-request endpoint to email-bombing/enumeration-probing (the response is generic, but the *action* is unthrottled). Status: `CONFIRMED` / `PRE-EXISTING`.
- **F-18-16 (P3) — `POST /login` rate limit returns 422, not 429.** `LoginRequest.php:57–61` throws `ValidationException` (HTTP 422). Convention-wise, rate limits elsewhere (Prompt 17) return **429**. Status: `CONFIRMED` / `PRE-EXISTING` (inconsistent convention, not a vulnerability).

---

## 9. Form Request / Validation Audit

**Implemented:** `StoreComplaintRequest` (attachments, daily limit), `StoreUserRequest`/`UpdateUserRequest` (role/dinas), auth requests, profile validation, master-data requests. Validation is server-side and uses explicit rule arrays — **no `$request->all()` mass-assignment sinks were found** (`grep` across `app/`).

**Findings:**
- **F-18-17 (P3) — Daily-limit TOCTOU (pre-existing, not exploitable to bypass by one).** The daily limit is checked in `StoreComplaintRequest::withValidator()` (`:73–87`, using `today()`) *before* the insert in `ComplaintController::store()` (`:59`). Two simultaneous submissions can both pass the check and create a 6th complaint for the day. Impact is bounded (off-by-a-few, not unlimited) and the submission rate limiter (Prompt 17) narrows the window. Status: `PRE-EXISTING`. See §17.
- **F-18-18 (P3) — Reference-code generation uses a `do/while … exists()` loop** (`Citizen/ComplaintController.php:63–66`) — a race can generate a duplicate under concurrency; the DB `UNIQUE` constraint on `reference_code` is the backstop (would surface as a 500). Status: `PRE-EXISTING`.

---

## 10. File Upload & Storage Security

**Implemented (verified):**
- Rules: max **10** files, **20 MB** each, mimes `jpg,jpeg,png,pdf,mp4` — sourced from `config/business_rules.php:34–38` and enforced in `StoreComplaintRequest`.
- Storage: private disk; `config/filesystems.php:33–37` sets `local` root to `storage_path('app/private')` and **`serve` is disabled on purpose** to avoid registering a public `/storage/{path}` route; no `public/storage` symlink (`.gitignore:20`; `Test-Path public/storage` absent).
- Stored filename is randomized: `Str::random(40) . '.' . $file->getClientOriginalExtension()` (`Citizen/ComplaintController.php:95`); original name kept only as metadata.
- Download authorization: citizen path checks ownership; operator path checks `authorizeComplaintAccess()` (`OperatorComplaintController.php:527,555–568`); missing file → 404 (`:538`).

**Findings:**
- **F-18-19 (P3) — Extension derived from client input.** `getClientOriginalExtension()` (`ComplaintController.php:95`) is used for the stored filename extension. The mime whitelist in the Form Request mitigates this; MIME is also recorded from `getMimeType()` (`:104`). Low risk given the whitelist, but extension is client-influenced. Status: `PRE-EXISTING`. Malware scanning is explicitly **not required** (frozen) → `NOT APPLICABLE`.
- **F-18-20 (P3) — `checksum_sha256` computed via `hash_file` on the temp path** (`ComplaintController.php:97`) — fine, noted for completeness.

---

## 11. Rate Limiting & Abuse Protection

**Implemented (Prompt 17):**
- Named limiter `complaint-submission`: `5 / 10 min / authenticated user`, key = user id (`AppServiceProvider.php:48–64`), values from `config/business_rules.php:28–29`.
- Applied to the single POST route (`routes/web.php:53`) at the route boundary (before validation) → 6th attempt returns **429** with a friendly message (`AppServiceProvider.php:54–63`, view `errors/429.blade.php`).
- Login limiter: `LoginRequest.php:49` (`5` attempts, key = `email|ip`, `:66`) → 422 (§16).

**Findings:**
- **F-18-04 (P2) — Registration and password-reset request are unthrottled** (see F-18-15). Status: `CONFIRMED`.
- **F-18-16 (P3) — Login limit returns 422, not 429** (see §8). Status: `CONFIRMED`.
- **F-18-21 (P3) — Login throttle key includes IP.** `email|ip` (`LoginRequest.php:66`) means an attacker rotating IPs gets 5 attempts per IP per email, and shared-IP users (NAT) can lock each other out. Prompt 17 deliberately chose user-id (not IP) for complaint submission; login was left as-is. Status: `PRE-EXISTING` / `DECISION REQUIRED` if a policy change is wanted (§33, D-5).
- **Public tracking rate limit (`rules.md:48`)** is referenced but **no public tracking route exists** → `NOT APPLICABLE` (absence is the frozen decision; `PROMPT_17` §note confirms).

---

## 12. Secrets & Configuration Hygiene

**Findings:**
- **F-18-02 (P1) — Plaintext DB credentials committed in `phpunit.xml`.** Lines `:30–31` set `DB_USERNAME=ruthless`, `DB_PASSWORD=Ruthless*77`; the file is git-tracked and **not** ignored (`.gitignore:1–27` does not list `phpunit.xml`). `.env` itself is correctly ignored (`.gitignore:3`). This leaks a real local DB credential into version control. Status: `CONFIRMED` / `PRE-EXISTING`. Remediation is a **config/secret change** → out of scope for this read-only audit, flagged for follow-up.
- **F-18-03 (P1/P2) — Timezone dead config.** `config/app.php:68` hardcodes `'timezone' => 'UTC'`; it does **not** read `env('APP_TIMEZONE')`, while `.env:5` sets `APP_TIMEZONE=Asia/Makassar`. Consequence: `today()` in the daily-limit check (`StoreComplaintRequest.php:85–87`) and the retention cutoff (`submitted_at`, `config/business_rules.php:50–52`) are evaluated in **UTC**, not WITA — a boundary-correctness bug for a Makassar government service. Status: `CONFIRMED` / `PRE-EXISTING`. Which timezone is authoritative is a **DECISION REQUIRED** (§33, D-1).
- **F-18-22 (P2) — Weak production defaults in `.env.example`.** `APP_NAME=Laravel` (`:1`), `APP_DEBUG=true` (`:4`), `APP_LOCALE=en` (`:7`) and `APP_FAKER_LOCALE=en_US` (`:9`), no `SESSION_SECURE_COOKIE`, `LOG_LEVEL=debug` (`:21`). A deploy that copies `.env.example` verbatim ships debug mode + English locale. `.env` (untracked) corrects name/locale/timezone but those corrections are **not** reflected in the example. Status: `CONFIRMED`.
- **F-18-23 (P3) — `.env` vs `.env.example` drift.** `.env` sets `APP_NAME="SuperBie - Lapor Pak Wali"`, `APP_TIMEZONE=Asia/Makassar`, `APP_LOCALE=id`; none appear in `.env.example`. New environments will silently differ. Status: `CONFIRMED`.
- **Secrets not committed elsewhere:** `grep` found no hardcoded keys/tokens in `app/`; `.env` is ignored; audit redaction exists (`AuditLogService.php:172–203`).

---

## 13. Dependency & Supply-Chain Audit

**Findings:**
- **F-18-08 (P3) — Unusual / unverified dev & optional dependencies.**
  - `composer.json:16` `laravel/pao` `^1.0.6` (and its transitive `laravel/agent-detector`) — not a standard Laravel dev dependency; purpose undocumented in the repo.
  - `package.json:16–18` `@laravel/multiplex` as an **optionalDependency** — the source of the pre-existing `npm run build` warning.
  - Status: `CONFIRMED` / `PRE-EXISTING`. Whether these are required is `DECISION REQUIRED` (§33, D-3).
- **F-18-24 (P2) — Dependency vulnerability scan NOT verified.** `composer audit` could not run cleanly (composer.phar emits PHP 8.4 deprecation noise); `npm audit` was not run. **The audit makes no claim about CVE status of any dependency.** Status: `CONFIRMED` (gap in verification, not a confirmed vuln).
- Runtime deps are minimal and expected: `laravel/framework ^13.17`, `laravel/tinker ^3.0`, `alpinejs ^3.17.4`. No Sanctum/JWT/Inertia/Livewire/React/Vue present — **consistent with frozen constraints.**

---

## 14. Database, Migrations & Schema Integrity

**Migration inventory (12 files):**

| File | Purpose |
|---|---|
| `0001_01_01_000000_create_users_table` | users |
| `0001_01_01_000001_create_cache_table` | cache |
| `0001_01_01_000002_create_jobs_table` | jobs |
| `2024_01_01_100000_create_complaint_categories_table` | categories |
| `2024_01_01_100001_create_complaints_table` | complaints |
| `2024_01_01_100002_create_complaint_support_tables` | attachments/history/notes |
| `2024_01_01_100003_create_audit_logs_table` | audit_logs |
| `2024_01_01_100004_create_app_settings_table` | app_settings |
| `2025_01_01_100005_add_identity_fields_to_users_table` | NIK/HP/Alamat |
| `2025_01_01_100006_create_dinas_units_and_pivot_table` | dinas_units + `category_dinas_unit` |
| `2025_01_01_100007_add_dinas_unit_id_to_complaints_table` | complaints.dinas_unit_id |
| `2025_01_01_100008_add_dinas_unit_id_to_users_table` | users.dinas_unit_id (**Pending**) |

**Findings:**
- **No destructive operations found** in migrations (no `dropColumn`/`dropTable`/`truncate`).
- **F-18-25 (P3) — Migration `100008` is Pending in the working DB.** Expected (Prompt 15 documented it as unapplied). Production must apply it before operator scoping works. Status: `CONFIRMED` / `NOT APPLICABLE` to code correctness.
- **F-18-26 (P3) — `UNIQUE(slug)` on categories is the only backstop** for the slug race (`SuperAdminCategoryController.php:187–196`); `uniqueSlug()` loops on `exists()`. Same TOCTOU class as reference codes. Status: `PRE-EXISTING`.
- **Retention/purge:** `routes/console.php:25` schedules `complaints:purge-expired` `dailyAt('02:00')`; the schedule is defined but **only runs if `schedule:run` is driven by cron** (noted in `:20–22`). No cron/CI provision exists in-repo → `DECISION REQUIRED` for ops (§33, D-7).

---

## 15. Mass Assignment & Data Integrity

**Findings:**
- **No `$request->all()` mass-assignment sinks.** All writes use explicit arrays or `$request->validated()` (e.g., `ComplaintController.php:99–107`, `SuperAdminUserController.php:64–74`).
- `User::$fillable` (`User.php:22–32`) includes `role`, `is_active`, `dinas_unit_id` — but these are only ever set from validated, server-controlled input (role changes gated to Super Admin; non-operator roles force `dinas_unit_id = null`, `SuperAdminUserController.php:113–116`). No client-supplied `role` reaches a citizen-facing write path.
- `password => 'hashed'` cast (`User.php:53`) is idempotent — **no double-hash** issue (verified).
- **F-18-27 (P3) — NIK immutability relies on the model guard**, documented at `ProfileController.php:68–70`; consistent with the frozen rule.

**Verdict:** mass-assignment posture is **sound** (`NOT APPLICABLE` as a finding).

---

## 16. Business Rule Enforcement Consistency

**Verified consistent with frozen rules:**
- Daily limit 5/calendar day via `AppSetting` override + `config/business_rules.php:18` (`StoreComplaintRequest.php:76–79`).
- Attachments 10/20MB/MP4 (`business_rules.php:34–38`).
- Retention 5 years from `submitted_at`, permanent delete (`business_rules.php:50–52`).
- Statuses/transitions in `app/Enums/ComplaintStatus.php` (untouched; not modified by this audit).
- `daily_report_limit` is the only managed setting exposed (`SuperAdminConfigController`).

**Findings:**
- **F-18-09 (P2) — Operator lifecycle mutations are under-constrained.**
  - `updateCategory` accepts **any active category** (`OperatorComplaintController.php:157–164`).
  - `updateDestination` accepts **any mapped/active unit**, including a unit the operator does not belong to (`:240–252`).
  - `assign` accepts **any active operator, cross-unit** (`:302–312`).
  These may be intended (a staff workflow), but no approved rule states the allowed set. Because inventing a restriction would be a business decision, this is recorded as **`DECISION REQUIRED`** (§33, D-2), not as a defect.
- **F-18-28 (P3) — Timezone affects rule boundaries** (see F-18-03). Cross-referenced, not duplicated.
- **No invented SLA / no escalation / no notification** — `AdminDashboardController.php:70–72` explicitly documents the absence of SLA. Consistent with the frozen "SLA undefined" decision. `NOT APPLICABLE`.

---

## 17. Concurrency & TOCTOU Analysis

| Mutation | Protection | Residual risk |
|---|---|---|
| Last-active-Super-Admin removal | `UserManagementService::wouldRemoveLastActiveSuperAdmin(lock: true)` inside `DB::transaction` with `SELECT … FOR UPDATE` + `orderBy('id')` (`UserManagementService.php:77–81`); called at `SuperAdminUserController.php:120–126` | **ALREADY MITIGATED (Prompt 16)** |
| Daily limit (citizen submit) | Pre-insert check only (`StoreComplaintRequest.php:85–87`) | **F-18-17 (P3)** off-by-N under concurrency |
| `reference_code` uniqueness | `do/while exists()` (`ComplaintController.php:63–66`) + DB `UNIQUE` | **F-18-18 (P3)** rare 500 on collision |
| Category slug uniqueness | `uniqueSlug()` loop (`SuperAdminCategoryController.php:187–196`) + DB `UNIQUE` | **F-18-26 (P3)** rare 500 on collision |
| Operator dashboard cache version bump | `Cache::increment`-style bump (`DashboardCacheService.php:280–283`, `UserObserver.php:20,26`) — non-atomic read-modify-write possible | **F-18-29 (P3)** possible stale operator dashboard for one TTL |

**Prompt 16 verification:** the authoritative last-super-admin check runs **inside** the transaction immediately before the mutation with a row lock; non-super-admin targets skip the lock; rejection performs **no write** and preserves prior error responses. **PASS.**
**Concurrency-testing limitation (documented, unchanged):** `RefreshDatabase` single-transaction isolation prevents a true two-connection race test; `LastSuperAdminConcurrencyTest` Case F asserts the `FOR UPDATE` lock is emitted. Concurrency is therefore **not claimed fully verified end-to-end.**

---

## 18. Audit Logging & Observability

**Implemented:**
- `AuditLog::create()` on complaint creation, category/destination/assignment/status changes, notes, user create/update/toggle, master-data changes, profile update.
- Presentation-layer redaction of sensitive metadata: `AuditLogService::redactMetadata()` (`:172–203`), used by `SuperAdminAuditController::show()` (`:59`).
- Audit surfaces are **read-only** (no mutation routes; `routes/web.php:117–121`).

**Findings:**
- **F-18-30 (P3) — `AdminDashboardController::auditLog()` bypasses redaction.** `AdminDashboardController.php:85–92` renders `AuditLog` rows directly (`with('actor')->paginate(30)`) without passing metadata through `AuditLogService::redactMetadata()`. If any audit metadata contains a sensitive fragment, the **Admin** view (vs the Super Admin view) could render it unredacted. Severity is P3 because current metadata payloads are low-sensitivity (reference codes, ids, visibility), and passwords are never logged (`SuperAdminUserController.php:76`). Status: `CONFIRMED` / `PRE-EXISTING`.
- **F-18-31 (P3) — No structured monitoring/alerting.** No health metrics beyond `/up` (`bootstrap/app.php:12`); no alerting on failed logins or 429 spikes. `DECISION REQUIRED` for ops (§33, D-8).
- **No `complaint.viewed` audit event** — consistent with the frozen decision (`NOT APPLICABLE`).

---

## 19. Data Retention & Deletion Safety

- Retention: 5 years from `submitted_at`, then permanent delete incl. related audit logs (`config/business_rules.php:46–53`); command scheduled daily at 02:00 (`routes/console.php:25`).
- Master data (Dinas/Unit, Category) hard-delete is **blocked when ever used**; deactivation is the alternative (`SuperAdminDinasUnitController.php:112–117`, `SuperAdminCategoryController.php:155–160`).
- User accounts: **no hard-delete**; deactivated to preserve history (routes comment `routes/web.php:98`).

**Findings:**
- **F-18-32 (P2) — Retention purge is timezone-dependent** (see F-18-03): cutoff computed in UTC can delete ~8 hours early/late relative to WITA. Status: `CONFIRMED` (derived).
- **F-18-33 (P3) — Purge depends on external cron** not provisioned in-repo (§14). Status: `CONFIRMED`; ops `DECISION REQUIRED`.
- **No destructive DB ops performed by this audit.**

---

## 20. Error Handling & Information Disclosure

**Findings:**
- **F-18-34 (P2) — `APP_DEBUG=true` in `.env.example`** (`.env.example:4`). If copied to production, full stack traces (incl. env-derived values) leak. Status: `CONFIRMED`.
- **F-18-35 (P3) — No custom 500/403 views found** beyond `errors/429.blade.php` (Prompt 17). Default Laravel error rendering applies; with debug off this is acceptable. Status: `NOT APPLICABLE` (no disclosure with debug off).
- **F-18-36 (P3) — `shouldRenderJsonWhen` scoped to `api/*` or JSON-expecting requests** (`bootstrap/app.php:25–27`); no `api/*` routes exist → effectively inert. Status: `CONFIRMED` / `NOT APPLICABLE`.
- Audit redaction present for Super Admin (§18); Admin gap noted (F-18-30).

---

## 21. Frontend & Client-Side Security (SPA engine)

**F-18-01 (P2) — A live client-side SPA engine contradicts the frozen architecture.**
- `resources/js/spa.js` (490 lines) defines `class SuperBieSPA` (`spa.js:7`) that intercepts link clicks (`:82–87`), hover-prefetches (`:94–103`), intercepts form submits (`:125`), and `fetch`es pages with header `X-SPA-Request: true` (`:216, :289`).
- It **caches authenticated HTML** in a client `Map` (`spa.js:10, :201–202, :226–227, :318–319`) and **swaps DOM via `outerHTML`** (`:324, :342`).
- It is **started unconditionally** on every page: `resources/js/app.js:2,8` (`initSPA({ cacheTTL: 60000 })`).
- **Direct contradiction** with `architecture.md:13` ("…tanpa membangun SPA dan API server terpisah") and `rules.md:16`.
- **Origin:** introduced by earlier "cline checkpoint" commits, **not** by Prompts 15–17 → status `PRE-EXISTING`.
- **Functional dependency is low but non-zero:** the only UI it drives (`data-spa-refresh-icon`, cache pill) exists **only** in the orphaned `resources/views/layouts/dashboard.blade.php`; the active component layout `resources/views/components/layouts/dashboard.blade.php` has no SPA UI (only `id="main-content"`).
- **Security note:** the engine does **not** bypass server-side authorization (it merely re-renders server HTML), but it caches authenticated content in browser memory for up to 60 s and rewrites DOM — an un-audited surface with logout/back-button cache-leak potential. Status: `CONFIRMED`.

**F-18-37 (P3) — Dead frontend artifacts.** `resources/views/welcome.blade.php` (72 KB, 215 lines, default Laravel welcome) is unreferenced (the app serves `public.landing` at `/`, `routes/web.php:16–18`); `resources/views/layouts/{app,dashboard}.blade.php` are orphaned duplicates of the active `components/layouts/*` (only the latter are used via `x-layouts.*`). Status: `CONFIRMED` / `PRE-EXISTING`.

---

## 22. Documentation vs Implementation Drift

| # | Doc claim | Reality | Severity | Status |
|---|---|---|---|---|
| F-18-11a | `prd.md:56,106,138–139,210`; `architecture.md:159`; `design.md:77`; `README.md:41` describe **public tracking** + receipt/tracking pages with a tracking secret | **Not built** (frozen decision: tracking not required); no route/controller; `tracking_secret_hash` stored but never verified | P3 | `CONFIRMED` / `PRE-EXISTING` |
| F-18-11b | `rules.md:25` "Use Policies/Gates for authorization" | No Policy/Gate classes exist; `architecture.md:43,132` documents their intentional absence | P3 | `CONFIRMED` / `PRE-EXISTING` |
| F-18-11c | `architecture.md:229` "rate limit login, submit, **and tracking**" | Tracking absent (N/A); login limited (422); submit limited (429) | P3 | `CONFIRMED` |
| F-18-11d | `SETUP_AND_DOCS.md` documents Laragon/Windows setup | Consistent with Prompt 8 (Docker docs removed); no Docker present | P3 | `NOT APPLICABLE` |

**Note:** `architecture.md:13` (no-SPA) is **correct**; it is `spa.js` that violates it (§21).

---

## 23. Test Coverage & Quality Gaps

**Strengths:** 492 tests / 1638 assertions / 0 failed. Prompt 15/16/17 suites present: `OperatorDinasScopeTest` (31), `LastSuperAdminConcurrencyTest` (9), `ComplaintSubmissionRateLimitTest` (14).

**Findings:**
- **F-18-38 (P2) — No authentication/session test coverage.** No tests for login success/failure, logout, session regeneration, or the login rate limit. The most security-sensitive surface is the least tested. Status: `CONFIRMED`.
- **F-18-39 (P2) — CSRF is disabled for all tests.** `tests/TestCase.php:14` calls `withoutMiddleware(PreventRequestForgery::class)` globally, so no test exercises CSRF protection. Status: `CONFIRMED` / `PRE-EXISTING`.
- **F-18-40 (P3) — Pre-existing flaky factory.** `ComplaintCategoryFactory` can emit duplicate slugs (e.g. `velit-et`), intermittently failing `SuperAdminComplaintManagementTest::test_pagination_is_server_side`. Unrelated to Prompts 15–17. Status: `PRE-EXISTING`.
- **F-18-41 (P3) — No test for the timezone/daily-limit boundary** (ties to F-18-03). Status: `CONFIRMED`.

---

## 24. Deployment & Environment Readiness

**Findings:**
- **F-18-42 (P2) — No CI/CD, no Docker, no deployment pipeline.** `.github` absent; no `Dockerfile`/`docker-compose.yml` (consistent with the frozen "no Docker" rule, so Docker absence is `NOT APPLICABLE`, but the **absence of any CI** means the 492-test suite is not automatically enforced). Status: `CONFIRMED`.
- **F-18-43 (P2) — No backup/restore mechanism in-repo.** `architecture.md:199` lists automated backups as a requirement, but nothing in the repo provisions them. Status: `CONFIRMED` / `DECISION REQUIRED` for ops (§33, D-8).
- **F-18-44 (P3) — No production `.env` template for secure session/HTTPS.** See F-18-07/F-18-22.
- **F-18-45 (P3) — Scheduler requires external cron** (§14, `routes/console.php:20–22`). Status: `CONFIRMED`.

---

## 25. Prompt 15 Regression Verification

**PASS.** Migration `2025_01_01_100008_add_dinas_unit_id_to_users_table` present (Pending, expected); `User::dinasUnit()`, `DinasUnit::users()` present; `Complaint::scopeVisibleToOperator()` (`Complaint.php:131–149`) and `isVisibleToOperator()` (`:159`) implement the frozen visibility rule exactly (operator without unit → `whereRaw('1 = 0')`, no global fallback, `:135–138`); `authorizeComplaintAccess()` returns 404 out-of-scope (`OperatorComplaintController.php:566–567`); operator cache uses a version counter (`DashboardCacheService.php:111–135`, `UserObserver.php:20,26`). `OperatorDinasScopeTest` (31 tests) green.

**Residual (not a regression):** `assign`/`updateDestination`/`updateCategory` target validation was intentionally left unchanged (F-18-09).

---

## 26. Prompt 16 Regression Verification

**PASS.** `UserManagementService::wouldRemoveLastActiveSuperAdmin(..., lock: true)` with `lockForUpdate()` + `orderBy('id')` (`UserManagementService.php:77–81`); invoked **inside** the `DB::transaction` at `SuperAdminUserController.php:120–126` (update) and `toggleActive`; rejection performs no write and preserves prior responses. `LastSuperAdminConcurrencyTest` (9 tests) green. **Concurrency not claimed fully verified end-to-end** (§17).

---

## 27. Prompt 17 Regression Verification

**PASS.** `config/business_rules.php:28–29` (5 / 10 min); `AppServiceProvider.php:46–65` named limiter `complaint-submission`, key = user id, 429 response (JSON or `errors.429`); `routes/web.php:52–54` `throttle:complaint-submission` on POST store **only**, at the route boundary (before validation); `resources/views/errors/429.blade.php` present. `ComplaintSubmissionRateLimitTest` (14 tests) green. Daily limit remains a separate mechanism (§16).

---

## 28. Findings Summary Table (P0–P3)

| ID | Severity | Status | Finding | Evidence |
|---|---|---|---|---|
| F-18-01 | **P2** | PRE-EXISTING | Live client-side SPA engine contradicts frozen "no SPA" architecture | `resources/js/spa.js:7,216,342`; `resources/js/app.js:2,8`; `architecture.md:13` |
| F-18-02 | **P1** | PRE-EXISTING | Plaintext DB credentials committed in tracked `phpunit.xml` | `phpunit.xml:30–31`; `.gitignore:1–27` |
| F-18-03 | **P1/P2** | PRE-EXISTING | `APP_TIMEZONE` ignored; app runs in UTC, not WITA | `config/app.php:68`; `.env:5`; `StoreComplaintRequest.php:85–87` |
| F-18-04 | **P2** | CONFIRMED | No throttle on register / forgot-password | `routes/web.php:27,30` |
| F-18-05 | **P2** | PRE-EXISTING | No email verification | `User.php:5,12` |
| F-18-06 | **P2** | PRE-EXISTING | Password change does not invalidate other sessions | `ProfileController.php:55–71` |
| F-18-07 | **P2** | CONFIRMED | `SESSION_SECURE_COOKIE` unset; cookie hardening not enforced | `config/session.php:172`; `.env.example:30–34` |
| F-18-08 | **P3** | PRE-EXISTING | Unusual/unverified deps `laravel/pao`, `@laravel/multiplex` | `composer.json:16`; `package.json:16–18` |
| F-18-09 | **P2** | DECISION REQUIRED | Operator mutation targets under-constrained | `OperatorComplaintController.php:157–164,240–252,302–312` |
| F-18-10 | **P3** | PRE-EXISTING | `rules.md` mandates Policies/Gates; none exist | `rules.md:25`; `architecture.md:43,132` |
| F-18-11 | **P3** | PRE-EXISTING | Public tracking / receipt docs describe unbuilt features | `prd.md:56,106`; `design.md:77`; `README.md:41` |
| F-18-12 | **P3** | PRE-EXISTING | Unused `active` middleware alias | `bootstrap/app.php:17,20–22` |
| F-18-13 | **P3** | CONFIRMED | No trusted-proxy config (IP controls/audit) | `bootstrap/app.php:14–23`; `LoginRequest.php:66` |
| F-18-15 | **P2** | CONFIRMED | Register/reset unthrottled | `routes/web.php:27,30` |
| F-18-16 | **P3** | PRE-EXISTING | Login limit returns 422, not 429 | `LoginRequest.php:57–61` |
| F-18-17 | **P3** | PRE-EXISTING | Daily-limit TOCTOU | `StoreComplaintRequest.php:73–87`; `ComplaintController.php:59` |
| F-18-18 | **P3** | PRE-EXISTING | Reference-code `do/while` race | `ComplaintController.php:63–66` |
| F-18-19 | **P3** | PRE-EXISTING | Stored extension from client input | `ComplaintController.php:95` |
| F-18-22 | **P2** | CONFIRMED | Weak prod defaults in `.env.example` | `.env.example:1,4,7,21` |
| F-18-24 | **P2** | CONFIRMED | Dependency vuln scan not verified | (tooling) |
| F-18-26 | **P3** | PRE-EXISTING | Category slug race (UNIQUE backstop) | `SuperAdminCategoryController.php:187–196` |
| F-18-29 | **P3** | PRE-EXISTING | Operator cache version bump non-atomic | `DashboardCacheService.php:280–283` |
| F-18-30 | **P3** | PRE-EXISTING | Admin audit view bypasses redaction | `AdminDashboardController.php:85–92` |
| F-18-32 | **P2** | CONFIRMED | Retention cutoff timezone-dependent | `config/business_rules.php:50–52`; `config/app.php:68` |
| F-18-34 | **P2** | CONFIRMED | `APP_DEBUG=true` shipped | `.env.example:4` |
| F-18-37 | **P3** | PRE-EXISTING | Dead `welcome.blade.php` + orphan layouts | `resources/views/welcome.blade.php`; `layouts/{app,dashboard}.blade.php` |
| F-18-38 | **P2** | CONFIRMED | No auth/session tests | `tests/` |
| F-18-39 | **P2** | PRE-EXISTING | CSRF disabled in tests | `tests/TestCase.php:14` |
| F-18-40 | **P3** | PRE-EXISTING | Flaky `ComplaintCategoryFactory` slug | factory |
| F-18-42 | **P2** | CONFIRMED | No CI/CD pipeline | `.github` absent |
| F-18-43 | **P2** | CONFIRMED | No backup/restore provisioning | `architecture.md:199` |

**Counts:** P0 = **0** · P1 = **2** (F-18-02, F-18-03) · P2 = **15** · P3 = **17** · `DECISION REQUIRED` = **1** (F-18-09, plus D-1…D-8 in §33).

---

## 29. P0 Findings (Critical)

**None confirmed.** No authentication bypass, no remote code execution, no unprotected sensitive endpoint, and no confirmed live production-secret exposure were found. The committed DB credential (F-18-02) is P1 because it is a **local/test** credential, not a production secret.

---

## 30. P1 Findings (High)

**F-18-02 — Plaintext DB credentials committed in `phpunit.xml`.**
`phpunit.xml:30–31` hardcodes `DB_USERNAME=ruthless` / `DB_PASSWORD=Ruthless*77`. The file is tracked and `.gitignore` (lines 1–27) does not exclude it. **Impact:** credential leakage into VCS history; anyone with repo read access obtains a working DB account (currently a local test account). **Recommended action (NOT performed — requires a config change):** move these to environment variables / a git-ignored local config and rotate the credential. This is a follow-up change, not a Prompt-18 edit.

**F-18-03 — Timezone dead configuration (UTC vs Asia/Makassar).**
`config/app.php:68` hardcodes `'timezone' => 'UTC'` and ignores `APP_TIMEZONE` (`.env:5` = `Asia/Makassar`). **Impact:** the daily report limit (`StoreComplaintRequest.php:85–87`, `today()`) and the retention cutoff (`business_rules.php:50–52`) evaluate in UTC — up to 8 hours off WITA, mis-timing limit resets and data deletion for a Makassar service. **Recommended action:** bind `'timezone' => env('APP_TIMEZONE', 'UTC')` after the authoritative timezone is confirmed (**DECISION REQUIRED — D-1**).

---

## 31. P2 Findings (Medium)

- **F-18-04 / F-18-15** — Registration (`routes/web.php:27`) and password-reset request (`:30`) are unthrottled → scripted account creation / email-bombing.
- **F-18-05** — No email verification (`User.php:5,12`) → immediate full access after registration (`DECISION REQUIRED — D-4`).
- **F-18-06** — Password change does not invalidate other sessions (`ProfileController.php:55–71`) → stolen session survives credential rotation.
- **F-18-07** — `SESSION_SECURE_COOKIE` unset (`config/session.php:172`; `.env.example:30–34`) → cookie may traverse plaintext HTTP.
- **F-18-09** — Operator mutation targets under-constrained (`OperatorComplaintController.php:157–164,240–252,302–312`) → `DECISION REQUIRED — D-2`.
- **F-18-22 / F-18-34** — `.env.example` ships `APP_DEBUG=true` (`:4`), `APP_NAME=Laravel` (`:1`), `APP_LOCALE=en` (`:7`), `LOG_LEVEL=debug` (`:21`).
- **F-18-24** — Dependency vulnerability scan not verified (tooling).
- **F-18-32** — Retention purge is timezone-dependent (derived from F-18-03).
- **F-18-38** — No authentication/session test coverage.
- **F-18-39** — CSRF disabled across all tests (`tests/TestCase.php:14`).
- **F-18-42** — No CI/CD; the 492-test suite is not automatically enforced.
- **F-18-43** — No backup/restore provisioning despite `architecture.md:199`.

---

## 32. P3 Findings (Low)

- **F-18-08** — Unusual dev/optional deps (`composer.json:16`, `package.json:16–18`) → `DECISION REQUIRED — D-3`.
- **F-18-10** — `rules.md:25` (Policies/Gates) contradicts `architecture.md:43,132`.
- **F-18-11** — Docs describe unbuilt public tracking / receipt pages (`prd.md`, `design.md`, `README.md`).
- **F-18-12** — Unused `active` middleware alias (`bootstrap/app.php:17`).
- **F-18-13** — No trusted-proxy config (`bootstrap/app.php:14–23`) → `DECISION REQUIRED — D-6`.
- **F-18-16** — Login limit returns 422, not 429 (`LoginRequest.php:57–61`).
- **F-18-17 / F-18-18 / F-18-26** — TOCTOU/race on daily limit, reference code, category slug (DB constraints are backstops).
- **F-18-19** — Stored extension from client input (`ComplaintController.php:95`).
- **F-18-29** — Operator cache version bump non-atomic (`DashboardCacheService.php:280–283`).
- **F-18-30** — Admin audit view bypasses redaction (`AdminDashboardController.php:85–92`).
- **F-18-37** — Dead `welcome.blade.php` (72 KB) + orphan layouts.
- **F-18-40** — Flaky `ComplaintCategoryFactory` duplicate slug.
- **F-18-41** — No timezone/daily-limit boundary test.
- **F-18-44 / F-18-45** — No secure prod env template; scheduler requires external cron (`routes/console.php:20–22`).

---

## 33. Decision Required Register

No self-chosen values are applied; each item below is genuinely undefined by the frozen rules.

| ID | Question | Related findings |
|---|---|---|
| **D-1** | Is the authoritative timezone `Asia/Makassar` (WITA) or `UTC`? (Determines daily-limit reset and retention boundary.) | F-18-03, F-18-32 |
| **D-2** | Are Operator mutations (`updateCategory` any active category; `updateDestination` any mapped unit incl. non-own; `assign` any active operator cross-unit) intended, or must they be restricted (e.g., to the operator's own unit / mapped categories)? | F-18-09 |
| **D-3** | Are `laravel/pao`, `laravel/agent-detector`, and npm `@laravel/multiplex` required, or can they be removed? | F-18-08 |
| **D-4** | Is email verification required for new accounts? | F-18-05 |
| **D-5** | Should the login rate-limit key stay `email|ip`, or move to per-account (and return 429 for consistency)? | F-18-16, F-18-21 |
| **D-6** | Will the app run behind a reverse proxy/load balancer (requiring `trustProxies` and correct client IPs)? | F-18-13 |
| **D-7** | How is the Laravel scheduler (`schedule:run`) driven in production (cron/Windows Task Scheduler)? | F-18-33, F-18-45 |
| **D-8** | What are the production requirements for CI/CD, backups/restore, and monitoring/alerting? | F-18-42, F-18-43, F-18-31 |

---

## §40. Final Response

**Baseline (read-only, re-verified this prompt):**
- `php artisan test` → **492 tests / 1638 assertions / 0 failed / 0 skipped**.
- `php artisan route:list` → **61 routes**.
- `php artisan migrate:status` → `…100008_add_dinas_unit_id_to_users_table` **Pending** (expected; Prompt 15 documented it as unapplied).
- `npm run build` → **PASS** (one pre-existing non-blocking warning).
- No code/config/DB/package change was made. The only file written is `PROMPT_18_SECURITY_ARCHITECTURE_AUDIT.md`.

**Findings counts:** P0 = **0** · P1 = **2** · P2 = **15** · P3 = **17** · `DECISION REQUIRED` = **1 finding (F-18-09)** plus **8 decision items (D-1…D-8)**.

**Prompt 15 regression:** **PASS** (operator scope, 404 out-of-scope, version-counter cache).
**Prompt 16 regression:** **PASS** (`FOR UPDATE` last-super-admin guard inside transaction).
**Prompt 17 regression:** **PASS** (named `complaint-submission` limiter, 429, route boundary).

**Overall verdict:** **NOT PRODUCTION READY.**
The domain logic, authorization posture, and test suite are solid, and the frozen rules are enforced server-side. However, the following must be resolved before any production claim: committed DB credentials (F-18-02), the UTC-vs-WITA timezone defect affecting business-rule boundaries (F-18-03), unthrottled registration/reset (F-18-04/F-18-15), missing session hardening / weak `.env.example` defaults (F-18-07/F-18-22/F-18-34), the live SPA engine that violates the frozen architecture (F-18-01), and the absence of CI, backups, and auth/session test coverage (F-18-42/F-18-43/F-18-38). The one **DECISION REQUIRED** finding (operator mutation scope, F-18-09) must be answered before it can be classified as a defect or accepted as intended.

**Recommended next step:** obtain decisions on **D-1** (timezone) and **D-2** (operator mutation scope) first — they gate the two highest-impact items — then schedule a **fix prompt** (not part of Prompt 18) to remediate F-18-02, F-18-03, F-18-04/15, F-18-07/22/34, and to decide the disposition of `spa.js` (F-18-01). No remediation was performed under this discovery-only prompt.

**— END OF REPORT —**
