# Vibe Coding Rules — SuperBie / Lapor Pak Wali

These rules are mandatory instructions for developers and AI coding assistants. When rules conflict, follow this priority: (1) security/privacy, (2) `prd.md` scope and business rules, (3) `schema.md` data contract, (4) `architecture.md`, (5) `design.md`, then implementation convenience.

## 1. Scope and source of truth
1. Implement **Lapor Pak Wali only** in this phase.
2. Do not build SIPHP, weather dashboard, or JDIH features yet.
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
- Use Policies/Gates for authorization. Hiding a button is not authorization.
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

## 5. Security and privacy
- Validate on the server regardless of frontend validation.
- Escape output by default; do not render user-provided HTML.
- Protect state-changing browser requests with Laravel CSRF protection.
- Apply rate limiting to login, complaint submission, and public tracking.
  - **Login (FINAL, Prompt 20):** policy is **UNCHANGED** (D-5) — key `email|ip` (per-account, never IP-only), threshold 5 attempts, window = framework default decay (60 s), hit-on-failure / clear-on-success, `Lockout` event. Prompt 20 changed **only** the HTTP semantics of the exceeded state: it is now **HTTP 429 (Too Many Requests)** instead of a 422 validation error, with a native `Retry-After` header from `RateLimiter::availableIn()`. Invalid credentials remain a normal validation failure (not 429). The limiter lives in `LoginRequest` (not a route `throttle:` middleware) and is applied to the login POST only — it never spills onto logout, profile, password change, complaint, or dashboard.
  - **Registration (DECISION REQUIRED):** no registration abuse policy (threshold, window, scope key, captcha/lockout) is defined anywhere in the repository → **not implemented**, recorded as `DECISION REQUIRED` (Prompt 20). Do not invent a value.
  - **Forgot-password / reset (DECISION REQUIRED):** no HTTP abuse policy is defined → **not implemented**, recorded as `DECISION REQUIRED` (Prompt 20). Only the framework password broker's per-email `throttle` (60 s, `config/auth.php`) applies. Do not invent a value. The request endpoint already returns a generic response (enumeration-safe); the reset endpoint's user/token status messages are a known gap (see Prompt 20 report, F-20-01).
  - **Complaint submission (FINAL, Prompt 17):** 5 submissions / 10 minutes / authenticated user → HTTP 429 when exceeded. Enforced server-side by the named Laravel rate limiter `complaint-submission` (scope key = authenticated user id, never the client IP) via `throttle:` middleware on the submission route only. This is **security/abuse protection**, kept **separate** from the daily report business limit (5 / calendar day); it never replaces it. A submission with any number of attachments counts as **one** submission. Values live in `config/business_rules.php`.
- Use session regeneration on login and invalidation on logout.
- **Password change (FINAL, Prompt 19):** changing a password must invalidate the user's OTHER authenticated sessions (framework `auth.session` middleware + `Auth::logoutOtherDevices()`), while keeping the current session valid. Login rate-limit policy (key `email|ip`, threshold, window) is unchanged (also reconfirmed in Prompt 20).
- **Production cookies (Prompt 19):** serve over HTTPS with `SESSION_SECURE_COOKIE=true`; keep `false` for local HTTP development. Never commit `.env`; never commit real DB credentials in any tracked config file (e.g. `phpunit.xml`).
- Every authenticated route/action must verify authentication and authorization according to role and ownership.
- Public tracking responses must use explicit allowlisted fields; never serialize the full complaint model.
- Internal notes must never be visible publicly.
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
- Test public tracking never returns internal notes or personal contact fields.
- Test private attachment download authorization.
- Test rate limits on public endpoints.
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
