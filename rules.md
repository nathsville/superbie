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
- Frontend: Blade + Livewire + Alpine.js + Tailwind CSS + Laravel Vite.
- Do not add React, a separate Node.js API, microservices, Redis, paid services, or large libraries unless the owner explicitly approves and a concrete need is documented.
- Node/npm may be used to compile frontend assets; business logic must remain in Laravel.
- Pin dependency versions via `composer.lock` and the relevant package lockfile.

## 3. Implementation quality
- Prefer the smallest clear implementation that meets requirements.
- Keep controllers thin. Put multi-step use cases in focused Action/Service classes.
- Use Form Requests for conventional HTTP validation and Livewire validation rules for Livewire actions.
- Use Policies/Gates for authorization. Hiding a button is not authorization.
- Use Eloquent relationships and parameterized queries. Never concatenate user input into SQL.
- Avoid duplicated business logic across controllers, Livewire components, and jobs.
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
- Use session regeneration on login and invalidation on logout.
- Every authenticated route/action must verify authentication and authorization according to role and ownership.
- Public tracking responses must use explicit allowlisted fields; never serialize the full complaint model.
- Internal notes must never be visible publicly.
- Keep attachments private and serve them through authorized routes.
- Masyarakat/pelapor may only access their own report data; Petugas only assigned reports; Admin monitoring only; Super Admin full access.
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
- Test Petugas/Operator/Admin/Super Admin permissions.
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
