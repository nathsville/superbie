# Architecture Specification
## SuperBie — Lapor Pak Wali (Laravel + MySQL)

- **Architecture style:** modular monolith.
- **Application framework:** Laravel (gunakan versi stabil yang kompatibel dengan PHP yang tersedia; pin versi aktual di `composer.json`).
- **Rendering:** Blade untuk server-rendered HTML; Alpine.js untuk interaksi klien ringan; Tailwind CSS untuk styling. (Livewire **tidak** digunakan pada implementasi ini.)
- **Database:** MySQL, InnoDB, `utf8mb4`.
- **Scope:** Lapor Pak Wali MVP **plus the approved WINDY public weather dashboard** (read-only public weather information on the landing page; Phase 3, O-1; **owner decisions Phase 4E D-11…D-17**: 3-day forecast from free BMKG, WITA display, legacy thresholds as initial values only, free/official sources + BMKG marine API investigation, no historical Sheets import, scheduled + manual sync). JDIH remains not built (navigation label only); **SIPHP is approved (PHASE S, 2026-10-17 — `SIPHP_INTEGRATION_PLAN.md`) as a native market module**; **no GIS / wilayah boundary map / GeoJSON**. WINDY implementation is gated by `_superbie_audit/WINDY_MIGRATION_PLAN.md`.

## 1. Architecture decisions

1. **Laravel monolith** dipilih karena autentikasi, validasi, routing, ORM, migrations, CSRF protection, storage, queues, dan testing tersedia dalam satu ekosistem. Ini mengurangi biaya operasional dan kompleksitas integrasi.
2. **Blade + Alpine.js** dipilih agar formulir dan panel data tetap interaktif tanpa membangun SPA dan API server terpisah. Livewire tidak dipakai; seluruh interaktivitas ditangani Blade + Alpine.js + CSS transitions. **F-18-01 (Prompt 19):** custom client-side SPA engine (`resources/js/spa.js`) yang sebelumnya bertentangan dengan keputusan ini telah **dihapus**. **Prompt 26:** ditambahkan lapisan *progressive internal navigation* (`resources/js/navigation.js`) — pengganti yang disetujui, ringan dan non-SPA: hanya intercept GET internal, swap DOM region shell (`#main-content`, `#primary-navigation`, `#page-heading`, `#page-actions`), dan cache in-memory per-tab yang identity-scoped. Laravel tetap source of truth; tidak ada router/state-framework/Service Worker/penyimpanan persisten. Lihat §9.1.
3. **MySQL/InnoDB** menjadi sumber data transaksional utama. Relasi dan foreign key dipakai untuk menjaga integritas.
4. **Tailwind CSS + CSS keyframes/transitions** menjadi dasar visual. Gunakan satu strategi animasi utama; hindari memasang banyak library animasi yang tumpang tindih.
5. **Laravel session authentication** digunakan untuk panel internal berbasis browser. Tidak perlu JWT untuk alur server-rendered MVP.
6. **Laravel Storage abstraction** untuk lampiran; gunakan private disk untuk file pengaduan.
7. **Monolith dulu, scaling kemudian.** Redis, worker queue khusus, object storage, CDN, dan load balancer baru ditambahkan jika metrik/lingkungan produksi membutuhkannya.

## 2. System context

```mermaid
flowchart TD
    Citizen[Pengunjung / Pelapor] --> Browser[Browser]
    Citizen[Masyarakat / Pelapor] --> Browser
    Staff[Operator / Admin / Super Admin] --> Browser
    Browser --> HTTPS[HTTPS / Web Server]
    HTTPS --> Laravel[Laravel Application]
    Laravel --> Auth[Session Auth + Authorization]
    Laravel --> Domain[Application / Domain Services]
    Domain --> MySQL[(MySQL InnoDB)]
    Domain --> Storage[Private File Storage]
    Laravel --> Logs[Application Logs]
    Laravel --> Mail[Email Provider - optional]
    Laravel --> Queue[Queue - optional, later]
    CDN[CDN - optional later] -. static assets only .-> Browser
```

### Components
- **Browser:** menampilkan halaman publik dan panel internal, menjalankan CSS/JS animasi serta Alpine.js.
- **HTTPS/Web server:** terminasi TLS dan meneruskan request ke Laravel; produksi menggunakan konfigurasi web server yang menunjuk ke direktori `public/`.
- **Laravel:** routing, middleware, controllers, Form Requests, application services, Eloquent, views.
- **Authentication/authorization:** session untuk masyarakat/pelapor dan seluruh akun internal; middleware `auth`; otorisasi berbasis peran via middleware `role:*`/`active` dan `authorize()` di Form Request; rate limiting untuk login dan complaint submission. **Tidak ada rate limiting untuk public tracking** karena public tracking tidak diimplementasikan. (Tidak ada kelas Policy/Gate terpisah pada implementasi ini.)
- **Role model (FINAL):** 4 role **aktif** — `masyarakat`, `operator`, `admin`, `super_admin`. Masyarakat/pelapor wajib login sebelum membuat laporan. Admin hanya monitoring; Super Admin memiliki akses penuh.
  - Catatan: peran historis `petugas` **bukan** active role — tidak dapat dipilih, tidak memiliki route, dan tidak memiliki workflow aktif. Baris data historis yang masih merujuk `petugas` dipertahankan apa adanya (tidak dihapus/di-remap) — **FINAL (PHASE 6B Decision 1 / PHASE 6C)**: retain as historical, no reassignment, no deletion (lihat bagian Historical data).
  - **Operator ↔ Dinas/Unit (FINAL, D-2 / Prompt 21):** satu Operator mewakili **tepat satu** Dinas/Unit (`users.dinas_unit_id`); satu Dinas/Unit dimiliki **maksimal satu** Operator aktif. **Super Admin** adalah actor authoritative yang menetapkan/mengubah Dinas/Unit Operator melalui User Management (`super-admin/users`). Operator tidak boleh self-assign atau mengubah assignment Operator lain; ditegakkan server-side (middleware `role:super_admin` + Form Request `authorize()` + guard di model layer `User::booted() saving`). Operator hanya bekerja pada Dinas miliknya. Operator tanpa unit adalah state valid namun **denied** (tanpa fallback global). Deaktivasi **tidak** menghapus assignment. D-2 tidak menyiratkan batasan tambahan apa pun.
- **MySQL:** data utama pengaduan, akun, kategori, riwayat status, catatan, lampiran metadata, dan audit log.
- **Private storage:** file lampiran di luar akses publik langsung; download melalui route terotorisasi.
- **Logs:** Laravel logging dengan redaksi data sensitif; log bukan pengganti audit log.
- **Email/queue/CDN:** opsional; jangan menjadi dependency MVP kecuali disepakati dan dikonfigurasi.

## 3. Application layering

```mermaid
flowchart TB
    Request[HTTP Request] --> Middleware[Middleware: HTTPS, CSRF, Auth, Throttle]
    Middleware --> Route[Route]
    Route --> FormRequest[Form Request / Validation]
    FormRequest --> Controller[Controller]
    Controller --> Policy[Authorization: Middleware role:* + Form Request authorize]
    Policy --> Action[Application Service]
    Action --> Model[Eloquent Model / Query]
    Model --> DB[(MySQL)]
    Action --> Storage[Storage Adapter]
    Action --> Audit[Audit Logger]
    Controller --> View[Blade View / Component]
    View --> Response[HTML Response]
```

- **Route:** deklarasi endpoint dan middleware; tidak memuat business logic.
- **Controller:** mengorkestrasi request-response HTTP. Controller harus tipis.
- **Form Request:** validasi dan authorization awal request.
- **Authorization:** middleware `role:*` / `active` pada route + `authorize()` di Form Request (tidak ada kelas Policy terpisah pada implementasi ini).
- **Action/Service:** satu use case atau aturan domain yang bermakna, misalnya `SubmitComplaint`, `TransitionComplaintStatus`, `CreatePublicResponse`.
- **Eloquent/Query layer:** persistence, relasi, scopes, query teroptimasi.
- **Storage adapter:** mengelola file melalui Laravel Storage; tidak menyimpan path dari input user.
- **Audit logger:** menulis event administratif penting tanpa menyimpan rahasia.
- **Blade components:** komponen presentasi reusable dengan semantic HTML dan standar animasi.

## 4. Suggested project structure

```text
app/
  Enums/
    ComplaintStatus.php
    ComplaintNoteVisibility.php
    UserRole.php
  Http/
    Controllers/
      Auth/
      Citizen/
      Staff/
      SuperAdmin/
      DashboardRedirectController.php
    Requests/
    Middleware/
      EnsureUserHasRole.php
      EnsureUserIsActive.php
  Models/
    User.php
    Complaint.php
    ComplaintCategory.php
    ComplaintStatusHistory.php
    ComplaintNote.php
    ComplaintAttachment.php
    AuditLog.php
  Services/
    ...
resources/
  views/
    layouts/
    components/layouts/
    auth/
    public/
    citizen/
    operator/
    admin/
    super-admin/
  css/
    app.css
  js/
    app.js
database/
  migrations/
  seeders/
tests/
  Feature/
  Unit/
```

Adjust the exact structure to the installed Laravel version. Do not create empty abstractions or files only to imitate a pattern.

> **Catatan (Prompt 8):** struktur di atas mencerminkan implementasi aktual. Tidak ada direktori `Actions/`, `Policies/`, atau `Livewire/` pada implementasi ini — business logic multi-langkah berada di `app/Services/`, dan otorisasi memakai middleware `role:*`/`active` + `authorize()` di Form Request. Dokumentasi sebelumnya menyarankan struktur tersebut sebagai aspirasi; ini telah diselaraskan dengan kode nyata.

## 5. Main request flows

### 5.1 Authenticated citizen complaint submission
1. Browser requests the complaint form.
2. Laravel requires an authenticated masyarakat/pelapor session.
3. The UI displays active complaint categories; the related dinas/unit (from the current category mapping) is shown as information. The citizen never chooses the Dinas/Unit — the ACTUAL destination is decided later by the Super Admin during initial validation.
4. The user submits the complaint; rate limit, daily report limit, and server-side validation run.
   - **Rate limit (Prompt 17):** the submission route is protected by the named limiter `complaint-submission` (5 / 10 min / authenticated user, key = user id). It runs at the route boundary **before** validation, so the 6th attempt within the window returns **HTTP 429** and no complaint is created. Guests are redirected by `auth` first, so they never consume quota.
   - **Daily report limit (business rule):** checked in `StoreComplaintRequest` (5 / calendar day, overridable via `app_settings.daily_report_limit`) and kept independent of the rate limiter.
5. File uploads, if present, are validated and stored on a private disk with generated names.
6. A non-guessable public `reference_code` is generated. The legacy `tracking_secret_hash` column is **no longer written** (DEPRECATED — PHASE 6C Decision 4); there is **no** public tracking endpoint that ever verifies it (see §5.2).
7. A database transaction creates the complaint with `status = submitted` and `review_state = pending_initial_validation`, plus attachment metadata. There is **no** citizen → operator path: the complaint enters the Super Admin initial-validation queue first.
8. The reporter is redirected to their own complaint detail (authenticated). The complaint is never shown to guests.
9. **PHASE 2 initial validation (Super Admin):** approve (`submitted → in_progress`, the single first active transition) writes the final category, routes to an active Dinas/Unit mapped to that category that has an Operator, and resolves the Operator automatically from `complaint.dinas_unit_id`; return sets `returned_to_citizen` so the reporter may correct and resubmit the SAME complaint. Approval emits notification **M2** only (no duplicate M8). Under the LC-2 lifecycle the initial-validation approval sets `in_progress` directly (the former intermediate `under_review` stage is retired as a legacy status).

### 5.1A Citizen dashboard and history
1. Authenticated masyarakat/pelapor requests the dashboard.
2. Laravel returns only the user's own reports.
3. The user can open a report detail and see allowed status/public response information.

### 5.1B Role-specific internal flows
- Operator: operational report handling **within the Operator's own Dinas/Unit only** — review, status change, internal notes, public responses, geographic location update, completion submission, and attachments, subject to permission. **LC-2 lifecycle:** the only Operator status actions in the active lifecycle are `submitted → in_progress` (performed by the Super Admin initial validation, not the Operator) and `in_progress → rejected`; the `waiting_for_information` and `closed` targets are legacy and are no longer offered. An Operator **cannot** change a complaint's category, Dinas/Unit destination, or Operator assignment (those are decided by the Super Admin during initial validation; the operator endpoints were removed in PHASE 2R). An Operator **cannot** set a complaint to `resolved` directly — the completion gate requires Super Admin approval (PHASE 3). **PHASE N6A / F-1:** while a complaint is at `review_state = pending_completion_validation` the Operator **cannot** change its status at all (enforced server-side, before any mutation); the options return once the Super Admin returns the submission (`completion_returned_to_operator`). Super Admin is unaffected (G7).
- Admin: monitoring reports and audit log only.
- Super Admin: full website management, including reports, accounts (4 fixed roles), roles (assignment only), categories, Dinas/Unit + mapping, location master (Kecamatan/Kelurahan), operational configuration, initial validation, completion validation, audit and security. **PHASE N6C / N6B-1:** the completion-validation queue lists a complaint only when `status = in_progress` **AND** `review_state = pending_completion_validation`; a frozen G7 direct resolve does not rewrite `review_state`, so such a row leaves the queue because its status is no longer `in_progress` (the queue filter is a read rule, never a mutation). **PHASE N6E / N6D-1:** Approve **and** Return are likewise only actionable from that same pair (`status = in_progress` + `review_state = pending_completion_validation`), enforced server-side before any mutation and re-asserted on the locked row; a stale non-`in_progress` row is rejected (no mutation, no audit, no notification), and its completion-validation detail page is rendered read-only.

### 5.2 Public tracking — NOT IMPLEMENTED (frozen decision)
Public tracking is **not required** for the MVP and is **not built**. There is **no** public tracking route, controller, view, or API:
- A guest **cannot** enter a complaint number or a tracking secret, and **cannot** view any complaint status.
- Guests have **no** access to complaint data. A citizen must be **logged in** and can view **only their own** complaints (scoped to `reporter_id`).
- The legacy `complaints.tracking_secret_hash` column is **no longer written** (DEPRECATED — PHASE 6C Decision 4) and is **never verified** (there is no endpoint). Historical values are retained untouched; no column drop.
- The former design of a reference + secret lookup (§5.2 in earlier revisions) is intentionally **not** implemented and must not be described as available.

### 5.3 Staff update
1. Request passes session authentication and CSRF.
2. Server-side authorization verifies permission for the requested complaint/action.
3. Request validates the requested status transition and note visibility.
4. In a transaction, update complaint and insert status history/audit record.
5. Return the updated view/state only after commit.

### 5.4 Login (Prompt 20)
1. `POST /login` is a guest route; validation runs first (`email`, `password` required/format).
2. `LoginRequest::authenticate()` calls `ensureIsNotRateLimited()` → on failure (`Auth::attempt` false) it `RateLimiter::hit()`s key `email|ip` and throws a validation error (wrong-credential case stays a **422**-style field error).
3. Threshold = 5 attempts, window = framework default decay (60 s); a success calls `RateLimiter::clear()`.
4. When the threshold is exceeded, the response is **HTTP 429** (`ThrottleRequestsException`, the framework's own 429 exception) with a native `Retry-After` header from `RateLimiter::availableIn()`. The `Lockout` event is dispatched. Invalid input still yields a validation failure, never a 429.
5. Registration and forgot-password abuse limits are **not implemented** — no policy is defined → `DECISION REQUIRED`.

### 5.5 In-app notifications (PHASE N4)
1. Every **business milestone** (complaint submitted, initial validation approved/returned, resubmission, completion submitted/returned/validated, status transitions, rating) resolves its recipients through the explicit `App\Services\NotificationService`.
2. `NotificationService::emit(Complaint, milestone, sourceEventId, actor)` is called **INSIDE the caller's existing `DB::transaction`**, immediately after the canonical `AuditLog::create(...)`; the audit row id is passed as `sourceEventId` (the idempotency anchor). A notification insert failure rolls back the whole business action.
3. Recipients are filtered to: **active** accounts only, **never** the actor, **never** Admin/Petugas; the Operator is resolved **only** from `complaints.dinas_unit_id → DinasUnit::operator()` (never `assigned_to`); every active Super Admin is fetched in a **single query**. A Dinas/Unit with no Operator is skipped + logged (no fallback).
4. One notification row is written per recipient, guarded by the application pre-check **and** the DB UNIQUE index `notifications_idempotency_unique` (one business action × one recipient = one logical notification).
5. The user reads notifications in an **owner-scoped** centre (`/notifikasi`): the bell (unread badge, ≤10 latest dropdown) and the paginated page (20/page, newest first). Opening marks read; single mark-read, mark-all, and owner-scoped delete are supported. The action URL is resolved **at render time** from `(type, recipient role, complaint_id)` with a safe fallback — never stored.
6. **In-app only:** no email/Mailable, no queue/Job, no broadcasting/websocket/realtime, and no Laravel `Notifiable` database channel. No observer/event/listener is used (an explicit service keeps emission single and idempotent).
7. **Retention:** notifications are complaint children; `ComplaintRetentionService::purge()` deletes them **before** the parent complaint. The 5-year-from-`submitted_at` policy is unchanged.
8. **LC-2 — dormant milestones (M9, M11):** under the active lifecycle (`submitted → in_progress → {resolved | rejected}`) the transitions to `waiting_for_information` and `closed` no longer exist, so **M9** (`status_waiting_for_information`) and **M11** (`status_closed`) are **dormant** for the new workflow. Their constants, recipient mapping, and emit branches are deliberately **retained** for historical compatibility; no active complaint can trigger them. Milestones M1–M8, M10, M12 remain active.

## 6. Authentication and authorization


- Use Laravel's supported session-based web authentication.
- Regenerate session ID after login; invalidate session and regenerate CSRF token at logout.
- Hash passwords using Laravel Hash; never store plaintext.
- Enforce `auth` middleware for staff routes.
- Use roles/permissions only to the level needed. MVP roles (4 active): `masyarakat`, `operator`, `admin`, `super_admin`. Granular permission management is **not** implemented (fixed roles only).
- Masyarakat/pelapor wajib memiliki akun dan session login untuk membuat laporan, melihat dashboard, dan melihat riwayat laporan miliknya.
- Operator mengelola proses operasional laporan pada Dinas/Unit miliknya saja, sesuai permission.
- Admin hanya memiliki akses monitoring laporan dan audit log.
- Super Admin memiliki akses penuh ke fungsi website.

- Enforce authorization on complaint view/update/download actions server-side, not only by hiding buttons (implementasi: middleware `role:*` + `authorize()` di Form Request; bukan kelas Policy terpisah).
- Super Admin account management must prevent privilege escalation and should preserve at least one active administrator.
- Reset password requires a properly configured email provider; otherwise document as unavailable in local MVP.

## 7. Database architecture

- MySQL InnoDB; UTF-8 `utf8mb4`.
- Migrations are the schema source of truth; no manual production-only schema edits.
- Use BIGINT auto-increment primary keys unless a documented public identifier requires a random reference.
- Foreign keys must use deliberate delete behavior. Do not cascade-delete complaint histories/audit evidence when a complaint is deleted.
- Use transactions for complaint creation and status transitions.
- Index only query paths supported by actual screens: reference lookup, status/date list, category filtering, actor/time audit lookup.
- Store times in UTC where feasible; render in the configured application timezone. **FINAL (D-1, Prompt 19):** the authoritative application timezone is `Asia/Makassar` (WITA / UTC+8), bound via `APP_TIMEZONE` and `config('app.timezone')`. Business-rule calendar boundaries (daily report limit) and the retention cutoff follow WITA. **WINDY time policy (Phase 4E, D-12):** the **operational and display timezone is FINAL `Asia/Makassar` (WITA)**; observation time, forecast issue time, and sync time are **distinct** and source times are converted consistently to WITA for display. **However, the timestamp STORAGE convention (UTC vs local) is NOT decided by the owner** and remains an **open technical clarification (O-15)** — the existing project pattern stores local application time while the WINDY spec proposed UTC storage. This divergence is **not** silently resolved; do not mix timezones and do not invent a convention (see `_superbie_audit/WINDY_DATA_MAPPING.md`).
- Backups: automated database backup, restricted access, encryption at rest where supported, retention policy, and periodic restore drills.

## 8. File storage

- Store complaint attachments on a Laravel private disk (local private path for development; private object storage may be introduced later).
- Never expose the private storage path as a public URL.
- Use generated UUID/ULID filenames, validate MIME and extension, enforce size/count limits, and serve through an authorized controller.
- Do not assume a file is safe solely because its extension is allowed.
- Define a malware-scanning step before production if attachment risk/infrastructure requires it.
- **Attachment rules are FINAL:** optional; max **10 files** per complaint; max **20 MB** per file; formats **JPG/JPEG/PNG/PDF/MP4**; **no malware scanning** required for the MVP. Source of truth: `config/business_rules.php`. Files are private with no public storage access.

## 9. Animation and frontend architecture

Every visible UI component must be covered by the animation system, including header, navigation, buttons, links, cards, badges, inputs, labels, validation messages, alerts, modals, tables, pagination, empty states, loading indicators, dropdowns, tooltips, file previews, and page transitions. “Animation” can be a short transition of color, opacity, border, transform, or focus state; it does not mean every element must continuously move.

- Use CSS custom properties for durations/easing.
- Use Alpine.js for stateful micro-interactions and CSS classes for transitions.
- Use IntersectionObserver only for meaningful entrance/reveal effects; avoid scroll-triggered motion on every text line.
- Avoid layout-shifting animations and animations that delay form submission.
- `prefers-reduced-motion: reduce` must disable non-essential movement and replace it with immediate/low-motion state changes.
- Focus indicators must remain visible and cannot be removed for visual polish.
- Loading feedback must not fake progress; success feedback only appears after server confirmation.

### 9.1 Progressive internal navigation (Prompt 26)

A lightweight client-side navigation layer (`resources/js/navigation.js`, bootstrapped once by `resources/js/app.js`) makes internal page changes feel SPA-like **without** turning the app into an SPA. It is a **progressive enhancement**:

- **Server stays the source of truth.** Intercepted links issue a normal `fetch()` GET; Laravel runs the full middleware → authorization → controller → Blade pipeline and returns complete HTML. No JSON API, no client-side router, no client-side authorization.
- **GET only.** Only same-origin `<a>` GET clicks are intercepted. Mutations (POST/PUT/PATCH/DELETE), logout, downloads, external links, `mailto:`/`tel:`, hash-only links, `target="_blank"`, `download`, and any link marked `data-no-navigation` are left to the browser. Laravel PRG, CSRF, validation redirects and flash messages are untouched.
- **Shell swap targets.** The dashboard shell persists; only these server-rendered regions are replaced: `#main-content` (page body), `#primary-navigation` (sidebar menu → server-derived active state), `#page-heading` (title + breadcrumb) and `#page-actions` (header actions). `<title>` is updated from the response.
- **In-memory page cache.** A per-tab `Map` (TTL 60 s, max 10 entries, LRU eviction). **No** `localStorage`, `sessionStorage`, `CacheStorage`, IndexedDB or Service Worker. It is an optimization only — never an authorization layer.
- **Identity-scoped.** Entries are namespaced by a server-derived, non-reversible identity hash rendered in `<meta name="navigation-identity">` (HMAC of user id + role + `dinas_unit_id`, `@auth` only). Guests get no identity → caching is disabled. An identity change (role/unit change, re-login) purges the cache.
- **Conservative caching.** Only safe read-only pages are cached; any page whose `#main-content` contains a non-GET form (a mutation surface) is never cached, and neither are auth, form, or attachment-download paths.
- **No stale-while-revalidate.** A page is served either fresh from the network or from a non-expired cache entry.
- **Fallbacks.** Auth redirects (session expiry), non-HTML responses, non-2xx responses, network/timeout/parse failures, and shell/layout mismatches fall back to a real browser navigation. Native `document.startViewTransition()` is used when available (falling back to a plain swap) and is never required. In-flight requests are aborted on rapid navigation (`AbortController`); prefetch is conservative (primary nav hover/focus, max 2 concurrent).

## 10. Security architecture

| Threat | Mitigation |
|---|---|
| SQL injection | Eloquent/query parameter binding; never concatenate user input into SQL |
| XSS | Blade escaping; sanitize rich text only if rich text is explicitly enabled |
| CSRF | Laravel CSRF middleware for browser state changes |
| Brute force / spam | Rate limit login and submit; generic errors; monitoring. **Login (Prompt 20):** key `email|ip`, threshold 5, default 60 s decay, hit-on-failure/clear-on-success, `Lockout` event — exceeded → **HTTP 429** with native `Retry-After` (F-18-16). Registration + forgot-password abuse limits are `DECISION REQUIRED` (no policy defined → not implemented). Complaint submission = 5 / 10 min / authenticated user (named limiter `complaint-submission`, key = user id, HTTP 429) — separate from the daily business limit. **No public tracking rate limit** (no public tracking exists) |
| Broken access control | Server-side authorization on every protected operation and private file download (middleware `role:*`/`active` + Form Request `authorize()`) |
| File upload abuse | Allowlist, size/count limits, MIME inspection, generated filenames, private storage |
| Session theft | HTTPS, secure/HttpOnly/SameSite cookies, session regeneration; **F-18-06 (Prompt 19):** password change invalidates other sessions via the `auth.session` middleware + `Auth::logoutOtherDevices()`. `SESSION_SECURE_COOKIE` must be `true` in HTTPS production (env-driven; default off for local HTTP) |
| Data leakage | Explicit public DTO/projection, no internal notes in public views, log redaction. **PHASE N4:** notifications are owner-scoped (`auth()->user()->appNotifications()`, scalar route id → `findOrFail`) so a user can never read/mark/delete another user's notification (IDOR closed); `data` carries no PII/secrets/URLs; internal reasons/narratives are never placed in a notification |
| Mass assignment | `$fillable`/`$guarded` carefully; validate and map accepted fields |
| Secret leakage | `.env` excluded from version control; never log tokens or tracking secrets |
| Dependency vulnerabilities | Keep Composer/npm lockfiles, audit dependencies, patch regularly |
| Admin misuse | Least privilege, audit events, strong password policy and optional MFA later |

## 11. API and external integration

The MVP primarily uses Laravel web routes, not a separate public REST API. If an API is later required, version it under `/api/v1`, use explicit Resources/DTOs, Form Requests, authorization, pagination, rate limiting, and consistent error responses. Do not expose all database columns by returning Eloquent models directly.

Optional integrations (email/SMS/WhatsApp) must be behind service classes and environment configuration. If no provider is configured, the core complaint flow must still work. **PHASE N4:** in-app notifications are **not** an external integration — they are stored in the app's own `notifications` table and rendered in-app; no email/SMS/WhatsApp provider is required or used.

**WINDY weather integration (Phase 3 O-1 / Phase 4E D-11…D-17 — approved scope, not yet built):** WINDY reads **public weather data from validated real APIs**, prioritising the **free BMKG API** (land forecast, **3-day horizon**, D-11) through service-class adapters behind environment configuration. **Paid OpenWeatherMap and other paid services are excluded (D-11)**; free OWM only if decided (O-3) and its **attribution is kept (D-14)**. **Do not assume BMKG's general weather API provides sea-wave data** — the **BMKG marine forecast API is an unverified candidate** wave source (D-14, must not be claimed verified). Public requests must **not** trigger per-request API calls; data is fetched by an internal sync (**scheduled + manual — D-16**) and served read-only from storage/cache. **Manual sync is triggered only by Super Admin; the scheduled sync depends on a host scheduler that is not yet verified (O-7).** Sync must handle API failure, log safely, and prevent concurrent execution. **No historical Google Sheets import (D-15).** **Never present synthetic/invented/default values as real measurements**; when data is unavailable show a **"Data tidak tersedia"** state (D-13). Provider **attribution/license (BMKG, OpenWeatherMap)** must be honoured (D-14). **Not yet verified as an available implementation pattern in this repository:** Laravel HTTP client usage, `Cache::lock`, background jobs/queue workers actually used, and CSP/security headers — these are **NEEDS HOSTING/ENVIRONMENT VERIFICATION**. Secrets stay server-side only.

**SIPHP market module (PHASE S — approved 2026-10-17, D-S1…D-S7):** **no external integration.** The module is a **native rebuild** inside this Laravel app: market price information + retail directory, with **manual staff entry** (**Super Admin only**; Admin stays monitoring-only; public read-only at `/siphp`, no login). There is **no scraping, no iframe/embed, and no runtime data sync** with `siphp-parepare.com`; the legacy system is not touched. Data rules: one price row per (market × item × date) — the public page shows the latest price per item plus its date and an honest **"Data harga belum tersedia"** empty state; **never synthetic/default values**. Store photos live on the **public disk** with generated filenames and server-side MIME/size validation. Config via `config/siphp.php` (`SIPHP_ENABLED`). No new technology, no queue, no role changes (four roles remain). Schema changes go through `schema.md` first (`SIPHP_INTEGRATION_PLAN.md` gates G1–G5).

## 12. Performance and caching

- Build/minify CSS/JS using Laravel Vite.
- Optimize images, use responsive sizes, and lazy-load below-the-fold media.
- Paginate staff tables on the server; eager-load relationships to avoid N+1.
- Add indexes after inspecting common query paths and `EXPLAIN`.
- Start without Redis or application cache. Cache public static content only if needed; never cache private complaint data across users.
- CDN is optional and should serve static assets only. Do not cache authenticated HTML or private attachments at a shared edge.
- **Client-side navigation cache (Prompt 26):** the progressive navigation layer keeps a short-lived (60 s), size-bounded (10), identity-scoped **in-memory** `Map` per browser tab. This is a UX optimization only and is never shared across users/roles/units and never becomes an authorization boundary. Authenticated HTML is not made publicly cacheable to enable it. `Cache-Control` policy for authenticated responses (e.g. `private, no-store`) remains `DECISION REQUIRED` (Prompt 25 ADR-25-8).
- **In-app notifications (PHASE N4):** notification queries are **never cached** (always live, owner-scoped). The unread count and the ≤10 latest dropdown use the `(recipient_user_id, read_at)` and `(recipient_user_id, created_at, id)` indexes; the Super Admin fan-out is a **single** query (no N+1). The dashboard shell adds at most two indexed queries per render.
- **Dashboard KPI cache (LC-2C):** the dashboard aggregates are cached by `DashboardCacheService` with a **60-second TTL** on the `database` cache store (no tags). Keys keep role/scope separation: `dashboard:citizen:{id}` (per citizen), `dashboard:operator:{global|unit:{id|none}}:{version}` (per scope + a version counter), `dashboard:admin`, and `dashboard:superadmin`. Invalidation is by **observers** (complaint, user, complaint-category, dinas-unit, complaint-rating) calling `clearGlobalDashboardCaches()` / the version bumpers — **no** new event/listener/queue/observer is introduced for this. KPI semantics follow the LC-2 lifecycle (active = `submitted` + `in_progress`; success = `resolved`; rejection = `rejected`; legacy statuses excluded from active and labelled "historis" where shown). See `prd.md` F-014.

## 13. Deployment and operations

- Local: PHP, Composer, Node/npm for Vite build, MySQL; gunakan lingkungan Windows/Laragon developer (Laragon menjalankan PHP/MySQL/Node lokal). **Docker bukan jalur utama/aktif pada implementasi ini**; jika file Docker historis masih ada, itu bukan cara menjalankan aplikasi versi ini.
- Production: supported PHP runtime, Composer install with production flags, MySQL, HTTPS, correct web root, writable `storage` and `bootstrap/cache`, configured scheduler/queue only if used.
- Set `APP_DEBUG=false` in production.
- Use separate `.env` per environment; secrets must not be committed.
- Deployment checklist: migrations reviewed, backup created, assets built, health check passed, storage permissions verified, HTTPS enforced, admin seeded securely, logs monitored.
- Monitoring MVP: Laravel logs and web server/database health. External APM is optional.
- Recovery: documented backup and restore procedure tested before launch.
- **Operations runbook (Prompt 23):** `PRODUCTION_OPERATIONS.md` records the operational state using **IMPLEMENTED / REQUIRED / DECISION REQUIRED**. Implemented: `GET /up` health probe, the daily `complaints:purge-expired` schedule, the `database` queue config (no jobs dispatched yet), and a secret-free GitHub Actions CI workflow. **REQUIRED** (host/infrastructure, not this repository): the `schedule:run` trigger, database + attachment backups, a rehearsed restore procedure, and production `.env`. **DECISION REQUIRED** (no value invented): deployment target, scheduler mechanism, backup policy + **RPO/RTO**, monitoring/alerting, trusted proxies (D-6), and the CI platform. CI (`.github/workflows/ci.yml`) runs **build + test only** and never deploys or uses production secrets; no Docker/Kubernetes/Redis/APM/cloud tooling is introduced.

## 14. Scalability path

Start with one Laravel application and one MySQL instance. If usage increases, scale vertically first, optimize queries/indexes, move long-running notifications/file processing to a queue, then consider shared cache, object storage, load balancing, and multiple app instances. These are future options, not MVP dependencies.

```mermaid
flowchart LR
    Client --> Web[Web Server]
    Web --> App1[Laravel App]
    App1 --> DB[(MySQL)]
    App1 -. later .-> Queue[Queue Worker]
    App1 -. later .-> Cache[Shared Cache]
    App1 -. later .-> ObjectStorage[Private Object Storage]
```

## 15. Architecture acceptance checklist

- [ ] Lapor Pak Wali is implemented in the MVP, plus the approved **WINDY** public weather dashboard (read-only, Phase 3 / O-1) once its release gates are met. JDIH is **not** implemented; **SIPHP is approved for PHASE S** (`SIPHP_INTEGRATION_PLAN.md`); **no GIS / wilayah boundary map**. WINDY honours the **Phase 4E owner decisions (D-11…D-17)**: 3-day forecast from free BMKG, WITA display, legacy thresholds as initial values only, free/official sources + BMKG marine API investigation (unverified), no historical Sheets import, scheduled + manual sync (manual = Super Admin only; scheduled depends on an unverified host scheduler O-7; Admin monitoring), provider attribution, and no new technology/Docker.
- [ ] Routes, authorization, validation, business actions, persistence, and views have separated responsibilities.
- [ ] Complaint access never exposes private fields or internal notes (there is no public tracking; only the owner may view their own complaint).
- [ ] Attachments are private and authorized at download.
- [ ] Status update and history are transactional.
- [ ] UI components follow design tokens and animation coverage.
- [ ] Reduced-motion, keyboard access, responsive states, and errors are handled.
- [ ] Automated tests cover happy path, validation, authorization, complaint privacy, and file access.
