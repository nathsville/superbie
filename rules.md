# Vibe Coding Rules — SuperBie / Lapor Pak Wali

These rules are mandatory instructions for developers and AI coding assistants. When rules conflict, follow this priority: (1) security/privacy, (2) `prd.md` scope and business rules, (3) `schema.md` data contract, (4) `architecture.md`, (5) `design.md`, then implementation convenience.

## 1. Scope and source of truth
1. Implement **Lapor Pak Wali** in this phase.
2. **Approved scope expansion (Phase 3, O-1):** the **WINDY public weather dashboard** (read-only public weather information on the landing page) is now an approved feature of SuperBie. Implement it **only** per the approved planning documents and their release gates (`_superbie_audit/WINDY_MIGRATION_PLAN.md`). **JDIH remains not built** (navigation label only); **SIPHP is now an approved feature (PHASE S — see rule 2b)**, and **no GIS / wilayah boundary map / GeoJSON** may be built as part of WINDY. WINDY keeps **exactly the four existing roles** (public read-only access without login; only **Super Admin** manages WINDY configuration and manual internal sync; **Admin stays monitoring-only**), and must **not** replicate `XFrameOptionsMode.ALLOWALL` (the Windy.com embed requires an approved CSP/allowlist + privacy decision).
2a. **Owner decisions for WINDY (Phase 4E, D-11…D-17).** These are binding and must be reflected in any WINDY work:
   - **Forecast horizon = 3 days**, prioritising the **free BMKG API** for land weather. **Do not** select paid OpenWeatherMap or any other paid service; **do not** assume BMKG's general weather API provides sea-wave data.
   - **Operational and display timezone = `Asia/Makassar` (WITA)**; observation time, forecast issue time, and sync time are **distinct**; convert source times consistently to WITA for display. The **timestamp storage convention** (UTC vs local) is **not yet decided** and remains an **open technical clarification** — do not invent one, and do not mix timezones.
   - **Legacy WINDY thresholds are recorded as initial spec values, not operationally validated** (rain 20/50, wind 25/40 km/h, wave 1.5/2.0 m); status priority is **SIAGA > WASPADA > NORMAL**; the **rain unit & measurement period must be clarified** before implementation (never compare mm/3h with mm/day); **wave status may only be computed from verified, compatible numeric wave data**. **Never** output synthetic values, invented wave heights, or default values presented as real measurements; when data is unavailable, require a **"Data tidak tersedia"** / safe-equivalent state.
   - **Prioritise free and official data sources.** The **BMKG marine forecast API** must be **investigated as a candidate wave data source** but must **not** be claimed verified (endpoint/schema/params/license/availability). Keep the **Windy.com embed** as an interactive map visualisation **separate** from backend data sources; `embed2.html` is **not** automatically a structured data API for Laravel; **no paid Windy Point Forecast API**. Comply with provider **attribution/license** obligations (Phase 4A); do not remove BMKG/OpenWeatherMap attribution.
   - **No historical Google Sheets import.** Do not read, copy, import, modify, or delete Google Sheets data. New data collection starts only after implementation and release-gate approval.
   - **Scheduled automatic sync and manual sync are both planned**; only **Super Admin** triggers manual sync and manages WINDY configuration; **Admin monitors only**; public users are view-only. Sync must handle API failure, log safely, and prevent concurrent execution. **Scheduler hosting is not verified** — do not claim automatic sync is production-ready until hosting and the scheduler trigger are confirmed; do not set a sync interval without a basis from provider policy and a documented decision.
   - **Preserve the existing SuperBie stack and architecture patterns** (Routes → Form Request → Controller → Service → Model → MySQL; Blade/Alpine/Vite; existing audit/cache/config mechanisms). Add **no** new framework, package, library, service, queue worker, job infrastructure, database, or other technology; **no Docker**; **no** GIS/boundary map/GeoJSON/`boundary.html`. The internal system stays auth/authorization-protected. **Four roles remain.**
2b. **Approved scope expansion (PHASE S — SIPHP market module, 2026-10-17, D-S1…D-S7).** The **SIPHP market module** (market price information + retail directory) is an approved feature of SuperBie, to be built **natively inside this Laravel app** per `SIPHP_INTEGRATION_PLAN.md` and its release gates (G1–G5). Binding decisions:
   - **Rebuild native (D-S1):** **no iframe/embed** and **no runtime data sync** with `siphp-parepare.com`; the legacy system is not touched.
   - **Manual data entry (D-S2):** all market data is entered by staff in SuperBie; no scraping, no external API, no import from the legacy site.
   - **Public read-only surface (D-S3):** a public `/siphp` page (no login) plus landing-page entry points. **Super Admin only** may write (D-S5); **Admin stays monitoring-only**; no new role (four roles remain).
   - **Retail directory included (D-S4a);** store photos use the **public disk** with generated filenames and server-side MIME/size validation (D-S7). Store photos are **not** complaint attachments — the private-attachment rules are unchanged.
   - **No second complaint channel (D-S4c):** market-related complaints stay in Lapor Pak Wali; the legacy market complaint form is **not** rebuilt.
   - **Price history (D-S6):** one price row per (market × item × date); the public page shows the latest price per item plus its date. **Never show synthetic/default values** — use a "Data harga belum tersedia" state when empty.
   - **Constraints:** no new technology/Docker/queue/service; schema changes go through `schema.md` **first**; no retention policy for market data yet (`TODO: Define requirement`). **JDIH remains not built.**
3. Read all project documents before coding or editing.
4. `prd.md` defines product behavior; `schema.md` defines data structures; `architecture.md` defines system structure; `design.md` defines UI and animation; this file defines engineering rules.
5. Do not silently invent business rules, official categories, SLA, public data visibility, notification provider, file limits, or retention periods. Use `TODO: Define requirement`.
6. If documents conflict, stop and identify the conflict before making a consequential change.

## 2. Technology constraints
- Backend: Laravel monolith, PHP version compatible with the selected Laravel release.
- Database: MySQL/InnoDB.
- Frontend: Blade + Alpine.js + Tailwind CSS + Laravel Vite. (Livewire **tidak** digunakan pada implementasi ini. Custom client-side SPA engine juga **tidak** digunakan - F-18-01 dihapus pada Prompt 19.) **Prompt 26:** lapisan *progressive internal navigation* (`resources/js/navigation.js`) bersifat enhancement GET-only dengan cache in-memory identity-scoped; ini **bukan** SPA penuh (tidak ada router/state framework/Service Worker/localStorage/API baru) dan tidak mengubah batas keamanan server.
- Do not add React, a separate Node.js API, microservices, Redis, paid services, or large libraries unless the owner explicitly approves and a concrete need is documented.
- Node/npm may be used to compile frontend assets; business logic must remain in Laravel.
- Pin dependency versions via `composer.lock` and the relevant package lockfile.

## 3. Implementation quality
- Prefer the smallest clear implementation that meets requirements.
- Keep controllers thin. Put multi-step use cases in focused Action/Service classes.
- Use Form Requests for HTTP validation and authorization.
- Enforce authorization server-side (middleware `role:*`/`active` + Form Request `authorize()` + inline controller checks; this implementation deliberately uses **no** Policy/Gate classes). Hiding a button is not authorization.
- Use Eloquent relationships and parameterized queries. Never concatenate user input into SQL.
- Avoid duplicated business logic across controllers, services, and jobs.
- Use enums/constants for controlled status values; never trust status values sent by the browser.
- Do not introduce abstractions, repositories, interfaces, or design patterns without a concrete benefit.
- No dead code, commented-out implementations, placeholder buttons that look functional, or fake dashboard metrics.
- Do not replace or delete existing project files wholesale without inspecting their contents and explaining why the change is needed.
- Keep changes focused and reviewable.

## 4. Database rules
- Migrations are the source of truth; no undocumented manual schema changes.
- Match `schema.md` exactly or update the document first when a change is approved.
- Use foreign keys, appropriate indexes, transactions, and deliberate delete behavior.
- Do not hard-delete complaint records or histories through the normal UI unless an approved retention policy defines the behavior.
- Status updates and history/audit records must be written atomically.
- Never store passwords or tracking secrets in plaintext.
- Do not log sensitive complaint contents or personal data unnecessarily.
- Seeders must not commit real personal data or production credentials.
- **Legacy/deprecated columns (PHASE 6C, FINAL):** `complaints.assigned_to`, `tracking_secret_hash`, `reporter_phone`, and `public_updated_at` are **DEPRECATED** — they must **never** be written by any active workflow and must **not** be used for routing, assignment, metrics, filters, or authorization. They are retained for historical preservation only (**no column DROP**), and historical values must remain untouched (no bulk migration, no nulling, no reassignment). `complaints.reporter_email` and `complaint_categories.dinas_name` are **RETAINED** as historical snapshots. Historical `petugas` rows are **retained as historical** (no reassignment, no deletion). The legacy `assignee()` relation and `scopeAssignedTo()` / `User::assignedComplaints()` exist only for historical inspection — do not build new features on them.
- **Complaint lifecycle (LC-2, FINAL — single source of truth `app/Enums/ComplaintStatus.php`):** seven enum cases are retained, but the ACTIVE lifecycle has exactly **four** statuses (`submitted`, `in_progress` = active; `resolved`, `rejected` = final business outcomes) and **three** transitions: `submitted → in_progress`, `in_progress → resolved`, `in_progress → rejected`. `under_review`, `waiting_for_information`, and `closed` are **legacy** (history only) — no transition may enter or leave them, and the manual close edge `resolved → closed` is **retired**. Legacy rows are status-locked but **not** globally mutation-locked (non-status actions keep their normal permissions). Do not add statuses, transitions, or a reopen workflow; do not migrate or mass-update legacy data.
- **Operator status workflow vs completion gate (PHASE N6A / F-1, FINAL):** while a complaint sits at `review_state = pending_completion_validation` (the Operator has submitted the completion and it awaits Super Admin validation), the **Operator** must **NOT** be able to use `PATCH /operator/laporan/{complaint}/status` (`operator.complaint.update-status`) at all — no status change, no `resolved_at`, no status history, no success audit, no public response, no notification. The guard is enforced **server-side** (the UI merely hides the options), runs **before any mutation**, is re-asserted against the locked row inside the transaction, and **never** rewrites `review_state`. `review_state = completion_returned_to_operator` (Super Admin returned the submission) **restores** the normal "Perbarui Status" options. **Super Admin is NOT affected** (G7 keeps its global authority over the same endpoint). No transition was added to the LC-2 3-transition matrix. An **Operator** can never set a complaint to `resolved` directly — that transition is reserved for Super Admin completion validation.
- **Completion Validation Queue eligibility (PHASE N6C / N6B-1, FINAL — Option A):** the Super Admin completion-validation queue (`Complaint::scopePendingCompletionValidation()`, route `super-admin.completion-validation.index`) lists a complaint **iff BOTH** `review_state = pending_completion_validation` **AND** `status = in_progress`. Any other status (`resolved`, `rejected`, `closed`, …) excludes the row even when `review_state` is still `pending_completion_validation`. This is a **read/query rule only** — it never mutates `review_state`. **G7 interaction (frozen, intentional):** a Super Admin direct resolve (`in_progress → resolved` via `operator.complaint.update-status`) deliberately does **NOT** rewrite `review_state`; the resulting `resolved + pending_completion_validation` row is no longer shown in the queue because its status is not `in_progress`. `Approve`/`Return` keep their own independent server-side guards (`status = in_progress` + `review_state = pending_completion_validation`, locked row, stale-state re-check) — queue filtering is **not** the authorization boundary.
- **Completion validation actions require BOTH conditions (PHASE N6E / N6D-1, FINAL):** `Approve` **and** `Return` (`super-admin.completion-validation.approve` / `.../kembalikan`) are only actionable from `status = in_progress` **AND** `review_state = pending_completion_validation`. The guard runs **before any mutation** and is re-asserted against the **locked** row inside the transaction, so a stale `pending_completion_validation` row whose status is not `in_progress` (e.g. a frozen G7 direct resolve, or a `rejected`/`closed` row) is **rejected** — no mutation, no audit, no `completion_return_reason`, no `completion_submitted_at` clearing, no `review_state` rewrite, no M6/M7. The completion-validation **detail** page (`.../validasi-penyelesaian/{complaint}`) stays reachable for such a stale row (so the M5 notification deep-link never 404s) but is rendered **read-only**: it shows a "Tidak Lagi Dapat Divalidasi" notice and offers **no** Approve/Return action. `review_state` is never rewritten by any of this. No status and no transition were added.
- **In-app notifications (PHASE N4, FINAL):** the `notifications` table + `NotificationService` are **in-app only** — no email/Mailable/Mail facade, no queue/Job, no broadcasting/websocket/realtime, and no Laravel `Notifiable` database channel. Notifications are created by the **explicit** `NotificationService` from the business action (never observers/events/listeners), **INSIDE the caller's existing `DB::transaction`**, anchored on the canonical `audit_logs.id` as `source_event_id`. The UNIQUE `(recipient_user_id, complaint_id, type, source_event_id)` index is the idempotency guarantee. Recipients: **active only**, **never the actor**, **never Admin/Petugas**; the Operator is resolved **only** from `complaints.dinas_unit_id → DinasUnit::operator()` (never `assigned_to`); a Dinas/Unit with no Operator is skipped + logged (no fallback). Every read/mark/delete query is **owner-scoped** (`auth()->user()->appNotifications()`) — never `Notification::find($id)` alone. `data` carries no PII/secrets/URLs; action URLs are resolved at render time. The `ComplaintRetentionService::purge()` deletes notifications **before** the complaint.

## 5. Security and privacy
- Validate on the server regardless of frontend validation.
- Escape output by default; do not render user-provided HTML.
- Protect state-changing browser requests with Laravel CSRF protection.
- Apply rate limiting to login and complaint submission. (There is **no public tracking**, so there is no tracking rate limit.)
  - **Login (FINAL, Prompt 20):** policy is **UNCHANGED** (D-5) — key `email|ip` (per-account, never IP-only), threshold 5 attempts, window = framework default decay (60 s), hit-on-failure / clear-on-success, `Lockout` event. Prompt 20 changed **only** the HTTP semantics of the exceeded state: it is now **HTTP 429 (Too Many Requests)** instead of a 422 validation error, with a native `Retry-After` header from `RateLimiter::availableIn()`. Invalid credentials remain a normal validation failure (not 429). The limiter lives in `LoginRequest` (not a route `throttle:` middleware) and is applied to the login POST only — it never spills onto logout, profile, password change, complaint, or dashboard.
  - **Registration (DECISION REQUIRED):** no registration abuse policy (threshold, window, scope key, captcha/lockout) is defined anywhere in the repository → **not implemented**, recorded as `DECISION REQUIRED` (Prompt 20). Do not invent a value.
  - **Forgot-password / reset (DECISION REQUIRED):** no HTTP abuse policy is defined → **not implemented**, recorded as `DECISION REQUIRED` (Prompt 20). Only the framework password broker's per-email `throttle` (60 s, `config/auth.php`) applies. Do not invent a value. The request endpoint already returns a generic response (enumeration-safe); the reset endpoint's user/token status messages are a known gap (see Prompt 20 report, F-20-01).
  - **Complaint submission (FINAL, Prompt 17):** 5 submissions / 10 minutes / authenticated user → HTTP 429 when exceeded. Enforced server-side by the named Laravel rate limiter `complaint-submission` (scope key = authenticated user id, never the client IP) via `throttle:` middleware on the submission route only. This is **security/abuse protection**, kept **separate** from the daily report business limit (5 / calendar day); it never replaces it. A submission with any number of attachments counts as **one** submission. Values live in `config/business_rules.php`.
- Use session regeneration on login and invalidation on logout.
- **Password change (FINAL, Prompt 19):** changing a password must invalidate the user's OTHER authenticated sessions (framework `auth.session` middleware + `Auth::logoutOtherDevices()`), while keeping the current session valid. Login rate-limit policy (key `email|ip`, threshold, window) is unchanged (also reconfirmed in Prompt 20).
- **Production cookies (Prompt 19):** serve over HTTPS with `SESSION_SECURE_COOKIE=true`; keep `false` for local HTTP development. Never commit `.env`; never commit real DB credentials in any tracked config file (e.g. `phpunit.xml`).
- Every authenticated route/action must verify authentication and authorization according to role and ownership.
- Public complaint access must use explicit allowlisted fields and never serialize the full complaint model. **There is no public tracking** — guests cannot access complaint data; a citizen must be logged in and may only view their own complaint.
- Internal notes must never be visible to the citizen.
- Keep attachments private and serve them through authorized routes.
- Masyarakat/pelapor may only access their own report data; Operator handles operational report management; Admin monitoring only; Super Admin full access.
- **Operator ↔ Dinas/Unit assignment (FINAL, D-2 / Prompt 21):** one Operator represents **exactly one** Dinas/Unit (`users.dinas_unit_id`). The **Super Admin** is the authoritative actor that assigns or changes an Operator's Dinas/Unit, exclusively through the existing Super Admin User Management surface (`super-admin/users`). An Operator **must not** self-assign and **must not** modify another Operator's assignment. Enforced server-side (route middleware `role:super_admin` + Form Request `authorize()` + a model-layer guard), never UI-only. An Operator **without** a unit is a valid but **denied-scope** state (sees no complaints; no global fallback). Deactivation does **not** clear the assignment. D-2 does **not** imply any additional restriction (no per-category restriction, no one-Operator-per-unit rule, no forced unit on activation) — those are `DECISION REQUIRED` if ever needed.
- Use generated storage names, server-detected MIME checks, file size/count limits, and safe display of original filenames.
- Never commit `.env`, credentials, private keys, real tracking secrets, or production data.
- Production must use HTTPS and `APP_DEBUG=false`.
- Redact passwords, tokens, cookies, tracking secrets, and sensitive form content from logs.

## 6. Frontend and animation rules
- Every visible component must use the shared design tokens and have an intentional animated state/entrance/exit where appropriate.
- Use a consistent system based on CSS transitions/keyframes, Alpine.js, and Tailwind. Do not add multiple overlapping animation libraries.
- Animations must be short, purposeful, non-blocking, and must not shift layout unnecessarily.
- Never delay a form submission or server action to wait for an animation.
- Respect `prefers-reduced-motion: reduce`; reduced-motion support is mandatory for accessibility.
- All controls need visible focus states and keyboard support.
- Use semantic HTML and real button/link elements.
- Every input needs a visible label; placeholders are not labels.
- Every async interaction needs loading, success, and error feedback where relevant.
- Do not use fake loading percentages or show success before server confirmation.
- Avoid decorative movement that makes complaint forms difficult to read or use.
- Do not invent official logos, seals, photos, numbers, statistics, or promises.

## 7. Error handling
- Show human-readable Indonesian messages to users.
- Keep stack traces and internal exceptions out of the UI.
- Log exceptions with correlation/context where practical, but redact sensitive data.
- Handle empty, invalid, unauthorized, not-found, rate-limited, and server-error states.
- Use generic tracking failure messages to avoid report enumeration.

## 8. Testing rules
Before considering a feature complete:
- Add Feature tests for successful and invalid requests.
- Test guest access to staff routes is denied.
- Test masyarakat/pelapor ownership and daily report limit.
- Test Operator/Admin/Super Admin permissions.
- Test status transition allowlist and transactional history.
- Test that guests cannot access complaint data and that a citizen can only view their own complaint (no public tracking exists).
- Test private attachment download authorization.
- Test rate limits on public endpoints.
- **Test in-app notifications (PHASE N4):** milestone→recipient matrix (M1–M12; M9 `status_waiting_for_information` and M11 `status_closed` are **dormant** under LC-2 — retained for history but unreachable from the active lifecycle), never the actor/Admin/Petugas, inactive recipients, Dinas-without-Operator (no fallback), idempotency (same `source_event_id` = one row; UNIQUE constraint is the final guard), read/unread + owner-scoped IDOR on read/mark/delete, render-time target fallback, privacy (no PII/internal reason), and retention purge deleting notifications before the complaint.
- Test mobile-friendly behavior and `prefers-reduced-motion` at UI level where test tooling permits.
- Run formatter/linter and relevant automated tests; report the actual result, never claim tests passed if not run.

## 9. Git and change management
- Make small changes by feature slice.
- Explain changed files and behavior.
- Do not mix unrelated refactors with feature implementation.
- Never force-push or discard user changes without explicit instruction.
- Before migrations that could destroy data, explain impact and require confirmation.
- Keep `.env.example` updated without secrets.
- Keep README setup commands consistent with actual dependencies.

## 9A. Operations (Prompt 23)
- The operational source of truth is `PRODUCTION_OPERATIONS.md`; it distinguishes **IMPLEMENTED** / **REQUIRED** / **DECISION REQUIRED**. Do not describe a REQUIRED or DECISION REQUIRED item as implemented.
- **Never invent** infrastructure or policy: no Docker/Kubernetes/Redis/Elasticsearch/Prometheus/Grafana/Sentry/New Relic/cloud provider/Terraform/Ansible/Supervisor/systemd/external cron without an explicit decision. Backup frequency, retention, destination, encryption, **RPO/RTO**, alert thresholds, and the deployment target are `DECISION REQUIRED` until the owner defines them.
- The scheduler trigger (`* * * * * php artisan schedule:run`) and any queue worker are **host responsibilities**, not code in this repository. The retention schedule frequency (`complaints:purge-expired`, daily 02:00) is a frozen business rule and must not change.
- CI (`.github/workflows/ci.yml`) runs **build + test only**, uses no production secrets, and must never add a deploy step while the deployment target is unknown.
- Production must run with `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and `APP_TIMEZONE=Asia/Makassar`.
- Keep application logging free of secrets/personal data; `LOG_LEVEL` must not be `debug` in production.
- Backups and restores must never target production from this repository's tooling; a restore is rehearsed on a non-production environment first.

## 10. Definition of done
A task is complete only when:
1. It meets the relevant acceptance criteria in `prd.md`.
2. Data changes match `schema.md`.
3. Architecture remains consistent with `architecture.md`.
4. UI matches `design.md`, including animation coverage and reduced-motion.
5. Validation, authorization, errors, and privacy have been considered.
6. Relevant automated tests were run and their result reported.
7. No unrelated features or speculative business rules were added.
8. Setup/configuration documentation is updated if needed.
