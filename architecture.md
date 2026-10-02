# Architecture Specification
## SuperBie — Lapor Pak Wali (Laravel + MySQL)

- **Architecture style:** modular monolith.
- **Application framework:** Laravel (gunakan versi stabil yang kompatibel dengan PHP yang tersedia; pin versi aktual di `composer.json`).
- **Rendering:** Blade + Livewire untuk interaksi server-driven; Alpine.js untuk interaksi ringan; Tailwind CSS untuk styling.
- **Database:** MySQL, InnoDB, `utf8mb4`.
- **Scope:** Lapor Pak Wali MVP saja.

## 1. Architecture decisions

1. **Laravel monolith** dipilih karena autentikasi, validasi, routing, ORM, migrations, CSRF protection, storage, queues, dan testing tersedia dalam satu ekosistem. Ini mengurangi biaya operasional dan kompleksitas integrasi.
2. **Blade + Livewire + Alpine.js** dipilih agar formulir dan panel data dapat interaktif tanpa membangun SPA dan API server terpisah.
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
    Staff[Petugas / Operator / Admin / Super Admin] --> Browser
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
- **Browser:** menampilkan halaman publik dan panel internal, menjalankan CSS/JS animasi serta Livewire/Alpine.
- **HTTPS/Web server:** terminasi TLS dan meneruskan request ke Laravel; produksi menggunakan konfigurasi web server yang menunjuk ke direktori `public/`.
- **Laravel:** routing, middleware, controllers/Livewire components, Form Requests, policies, application services, Eloquent, views.
- **Authentication/authorization:** session untuk masyarakat/pelapor dan seluruh akun internal; middleware `auth`; policies/gates untuk aksi; rate limiting untuk login, submit, dan tracking.
- **Role model:** `masyarakat`, `petugas`, `operator`, `admin`, `super_admin`. Masyarakat/pelapor wajib login sebelum membuat laporan. Admin hanya monitoring; Super Admin memiliki akses penuh.
- **MySQL:** data utama pengaduan, akun, kategori, riwayat status, catatan, lampiran metadata, dan audit log.
- **Private storage:** file lampiran di luar akses publik langsung; download melalui route terotorisasi.
- **Logs:** Laravel logging dengan redaksi data sensitif; log bukan pengganti audit log.
- **Email/queue/CDN:** opsional; jangan menjadi dependency MVP kecuali disepakati dan dikonfigurasi.

## 3. Application layering

```mermaid
flowchart TB
    Request[HTTP Request / Livewire Action] --> Middleware[Middleware: HTTPS, CSRF, Auth, Throttle]
    Middleware --> Route[Route]
    Route --> FormRequest[Form Request / Validation]
    FormRequest --> Controller[Controller atau Livewire Component]
    Controller --> Policy[Policy / Authorization]
    Policy --> Action[Application Action / Service]
    Action --> Model[Eloquent Model / Query]
    Model --> DB[(MySQL)]
    Action --> Storage[Storage Adapter]
    Action --> Audit[Audit Logger]
    Controller --> View[Blade View / Component]
    View --> Response[HTML / Livewire Response]
```

- **Route:** deklarasi endpoint dan middleware; tidak memuat business logic.
- **Controller:** mengorkestrasi request-response HTTP. Controller harus tipis.
- **Livewire component:** mengelola state interaktif server-driven; tidak boleh menjadi tempat menumpuk semua business logic.
- **Form Request:** validasi dan authorization awal request.
- **Policy/Gate:** aturan akses berbasis user, role, dan kepemilikan.
- **Action/Service:** satu use case atau aturan domain yang bermakna, misalnya `SubmitComplaint`, `TransitionComplaintStatus`, `CreatePublicResponse`.
- **Eloquent/Query layer:** persistence, relasi, scopes, query teroptimasi.
- **Storage adapter:** mengelola file melalui Laravel Storage; tidak menyimpan path dari input user.
- **Audit logger:** menulis event administratif penting tanpa menyimpan rahasia.
- **Blade components:** komponen presentasi reusable dengan semantic HTML dan standar animasi.

## 4. Suggested project structure

```text
app/
  Actions/
    Complaints/
      SubmitComplaint.php
      TrackComplaint.php
      TransitionComplaintStatus.php
      AddComplaintNote.php
  Enums/
    ComplaintStatus.php
    ComplaintNoteVisibility.php
  Http/
    Controllers/
      Public/
        ComplaintSubmissionController.php
        ComplaintTrackingController.php
      Staff/
        DashboardController.php
        ComplaintController.php
      Admin/
        MonitoringController.php
      SuperAdmin/
        ComplaintManagementController.php
        UserManagementController.php
        CategoryController.php
        ConfigurationController.php
        AuditSecurityController.php
    Requests/
      StoreComplaintRequest.php
      TrackComplaintRequest.php
      UpdateComplaintStatusRequest.php
    Middleware/
  Livewire/
    Public/
    Staff/
    Admin/
  Models/
    User.php
    Complaint.php
    ComplaintCategory.php
    ComplaintStatusHistory.php
    ComplaintNote.php
    ComplaintAttachment.php
    AuditLog.php
  Policies/
    ComplaintPolicy.php
    ComplaintCategoryPolicy.php
resources/
  views/
    layouts/
    components/
    pages/
      public/
      citizen/
      staff/
      operator/
      petugas/
      admin/
      super-admin/
  css/
    app.css
  js/
    app.js
    animations.js
database/
  migrations/
  seeders/
tests/
  Feature/
  Unit/
```

Adjust the exact structure to the installed Laravel version. Do not create empty abstractions or files only to imitate a pattern.

## 5. Main request flows

### 5.1 Authenticated citizen complaint submission
1. Browser requests the complaint form.
2. Laravel requires an authenticated masyarakat/pelapor session.
3. The UI displays active complaint categories and the related dinas/unit for the selected category.
4. The user submits the complaint; rate limit, daily report limit, and server-side validation run.
5. File uploads, if present, are validated and stored on a private disk with generated names.
6. `SubmitComplaint` generates a non-guessable reference and tracking secret.
7. A database transaction creates the complaint and attachment metadata, with the category-linked dinas/unit recorded or resolvable according to the schema.
8. The raw tracking secret is shown once to the user.
9. The response shows a receipt and the related dinas/unit.

### 5.1A Citizen dashboard and history
1. Authenticated masyarakat/pelapor requests the dashboard.
2. Laravel returns only the user's own reports.
3. The user can open a report detail and see allowed status/public response information.

### 5.1B Role-specific internal flows
- Petugas: only assigned reports; may view details, status, internal notes, public responses, and attachments.
- Operator: review and operational management, including category and assignment to Petugas, subject to permission.
- Admin: monitoring reports and audit log only.
- Super Admin: full website management, including reports, accounts, roles/permissions, categories, operational configuration, audit and security.

### 5.2 Public tracking
1. User enters reference and required verification.
2. Apply rate limit before lookup.
3. Normalize reference and verify secret/second factor.
4. Return only an explicit public projection of the complaint, not the whole Eloquent model.
5. Use generic errors so an attacker cannot enumerate valid references.

### 5.3 Staff update
1. Request passes session authentication and CSRF.
2. Policy verifies permission for the requested complaint/action.
3. Request validates the requested status transition and note visibility.
4. In a transaction, update complaint and insert status history/audit record.
5. Return the updated view/state only after commit.

## 6. Authentication and authorization


- Use Laravel's supported session-based web authentication.
- Regenerate session ID after login; invalidate session and regenerate CSRF token at logout.
- Hash passwords using Laravel Hash; never store plaintext.
- Enforce `auth` middleware for staff routes.
- Use roles/permissions only to the level needed. MVP roles: `masyarakat`, `petugas`, `operator`, `admin`, `super_admin`.
- Masyarakat/pelapor wajib memiliki akun dan session login untuk membuat laporan, melihat dashboard, dan melihat riwayat laporan miliknya.
- Petugas hanya mengakses laporan yang ditugaskan.
- Operator mengelola proses operasional laporan sesuai permission.
- Admin hanya memiliki akses monitoring laporan dan audit log.
- Super Admin memiliki akses penuh ke fungsi website.

- Use Policies for complaint view/update/download actions, not only hiding buttons.
- Super Admin account management must prevent privilege escalation and should preserve at least one active administrator.
- Reset password requires a properly configured email provider; otherwise document as unavailable in local MVP.

## 7. Database architecture

- MySQL InnoDB; UTF-8 `utf8mb4`.
- Migrations are the schema source of truth; no manual production-only schema edits.
- Use BIGINT auto-increment primary keys unless a documented public identifier requires a random reference.
- Foreign keys must use deliberate delete behavior. Do not cascade-delete complaint histories/audit evidence when a complaint is deleted.
- Use transactions for complaint creation and status transitions.
- Index only query paths supported by actual screens: reference lookup, status/date list, category filtering, actor/time audit lookup.
- Store times in UTC where feasible; render in the configured application timezone (`Asia/Makassar` for Parepare deployment, after confirmation).
- Backups: automated database backup, restricted access, encryption at rest where supported, retention policy, and periodic restore drills.

## 8. File storage

- Store complaint attachments on a Laravel private disk (local private path for development; private object storage may be introduced later).
- Never expose the private storage path as a public URL.
- Use generated UUID/ULID filenames, validate MIME and extension, enforce size/count limits, and serve through an authorized controller.
- Do not assume a file is safe solely because its extension is allowed.
- Define a malware-scanning step before production if attachment risk/infrastructure requires it.
- `TODO: Define requirement` for approved file types, maximum size/count, retention, and malware scanning.

## 9. Animation and frontend architecture

Every visible UI component must be covered by the animation system, including header, navigation, buttons, links, cards, badges, inputs, labels, validation messages, alerts, modals, tables, pagination, empty states, loading indicators, dropdowns, tooltips, file previews, and page transitions. “Animation” can be a short transition of color, opacity, border, transform, or focus state; it does not mean every element must continuously move.

- Use CSS custom properties for durations/easing.
- Use Alpine.js for stateful micro-interactions and CSS classes for transitions.
- Use IntersectionObserver only for meaningful entrance/reveal effects; avoid scroll-triggered motion on every text line.
- Avoid layout-shifting animations and animations that delay form submission.
- `prefers-reduced-motion: reduce` must disable non-essential movement and replace it with immediate/low-motion state changes.
- Focus indicators must remain visible and cannot be removed for visual polish.
- Loading feedback must not fake progress; success feedback only appears after server confirmation.

## 10. Security architecture

| Threat | Mitigation |
|---|---|
| SQL injection | Eloquent/query parameter binding; never concatenate user input into SQL |
| XSS | Blade escaping; sanitize rich text only if rich text is explicitly enabled |
| CSRF | Laravel CSRF middleware for browser state changes |
| Brute force / spam | Rate limit login, submit, tracking; generic errors; monitoring |
| Broken access control | Policies on every protected operation and private file download |
| File upload abuse | Allowlist, size/count limits, MIME inspection, generated filenames, private storage |
| Session theft | HTTPS, secure/HttpOnly/SameSite cookies, session regeneration |
| Data leakage | Explicit public DTO/projection, no internal notes in public views, log redaction |
| Mass assignment | `$fillable`/`$guarded` carefully; validate and map accepted fields |
| Secret leakage | `.env` excluded from version control; never log tokens or tracking secrets |
| Dependency vulnerabilities | Keep Composer/npm lockfiles, audit dependencies, patch regularly |
| Admin misuse | Least privilege, audit events, strong password policy and optional MFA later |

## 11. API and external integration

The MVP primarily uses Laravel web routes and Livewire requests, not a separate public REST API. If an API is later required, version it under `/api/v1`, use explicit Resources/DTOs, Form Requests, Policies, pagination, rate limiting, and consistent error responses. Do not expose all database columns by returning Eloquent models directly.

Optional integrations (email/SMS/WhatsApp) must be behind service classes and environment configuration. If no provider is configured, the core complaint flow must still work.

## 12. Performance and caching

- Build/minify CSS/JS using Laravel Vite.
- Optimize images, use responsive sizes, and lazy-load below-the-fold media.
- Paginate staff tables on the server; eager-load relationships to avoid N+1.
- Add indexes after inspecting common query paths and `EXPLAIN`.
- Start without Redis or application cache. Cache public static content only if needed; never cache private complaint data across users.
- CDN is optional and should serve static assets only. Do not cache authenticated HTML or private attachments at a shared edge.

## 13. Deployment and operations

- Local: PHP, Composer, Node/npm for Vite build, MySQL; use the developer's existing Windows/Laragon environment if applicable.
- Production: supported PHP runtime, Composer install with production flags, MySQL, HTTPS, correct web root, writable `storage` and `bootstrap/cache`, configured scheduler/queue only if used.
- Set `APP_DEBUG=false` in production.
- Use separate `.env` per environment; secrets must not be committed.
- Deployment checklist: migrations reviewed, backup created, assets built, health check passed, storage permissions verified, HTTPS enforced, admin seeded securely, logs monitored.
- Monitoring MVP: Laravel logs and web server/database health. External APM is optional.
- Recovery: documented backup and restore procedure tested before launch.

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

- [ ] Only Lapor Pak Wali is implemented in the MVP.
- [ ] Routes, authorization, validation, business actions, persistence, and views have separated responsibilities.
- [ ] Complaint tracking never exposes private fields or internal notes.
- [ ] Attachments are private and authorized at download.
- [ ] Status update and history are transactional.
- [ ] UI components follow design tokens and animation coverage.
- [ ] Reduced-motion, keyboard access, responsive states, and errors are handled.
- [ ] Automated tests cover happy path, validation, authorization, tracking privacy, and file access.
