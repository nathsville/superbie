<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Citizen\DashboardController as CitizenDashboard;
use App\Http\Controllers\Staff\OperatorDashboardController;
use App\Http\Controllers\Staff\OperatorComplaintController;
use App\Http\Controllers\Staff\AdminDashboardController;
use App\Http\Controllers\Staff\SuperAdminDashboardController;
use App\Http\Controllers\DashboardRedirectController;
use Illuminate\Support\Facades\Route;

// ─── Public / Guest routes ────────────────────────────────────────────────────

// Landing page. A controller (not a closure) because the page states the real
// category → Dinas/Unit mapping from the database instead of describing it.
Route::get('/', \App\Http\Controllers\LandingController::class)->name('home');

// ─── Public weather (WINDY migration) — READ-ONLY, no login (owner decision O-2)
//
// Public surface: shows the BMKG-derived forecast, the computed early-warning
// status, and the Windy.com embed. There is no write path and no per-user data.
// Multi-location: `/cuaca` shows the default location; `/cuaca/{key}` shows a
// named one. The `{location}` segment is validated against the configured
// location registry inside the controller (unknown keys → 404).
Route::get('/cuaca', [\App\Http\Controllers\Weather\PublicWeatherController::class, 'index'])
    ->name('weather.index');
Route::get('/cuaca/{location}', [\App\Http\Controllers\Weather\PublicWeatherController::class, 'show'])
    ->name('weather.location');

// ─── Auth routes ──────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Dashboard redirect — routes to role-specific dashboard
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    // ─── PHASE N4 — In-app notifications (all authenticated roles) ────────────
    //
    // Owner-scoped: every query starts from `auth()->user()->appNotifications()`,
    // so a user can only ever see/mark/delete their OWN notifications (IDOR
    // closed). `{notification}` is a scalar id, never an implicitly-bound model.
    // Admin has no notification centre and receives no notifications; the routes
    // remain reachable but return an empty list for roles that never receive any.
    Route::get('/notifikasi', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifikasi/{notification}/buka', [\App\Http\Controllers\NotificationController::class, 'open'])
        ->whereNumber('notification')->name('notifications.open');
    Route::patch('/notifikasi/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])
        ->whereNumber('notification')->name('notifications.read');
    Route::delete('/notifikasi/{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy'])
        ->whereNumber('notification')->name('notifications.destroy');
});

// ─── Citizen (Masyarakat) routes ─────────────────────────────────────────────

Route::middleware(['auth', 'role:masyarakat'])->prefix('laporan')->name('citizen.')->group(function () {
    Route::get('/dashboard', [CitizenDashboard::class, 'index'])->name('dashboard');
    Route::get('/riwayat', [CitizenDashboard::class, 'history'])->name('history');
    Route::get('/buat', [\App\Http\Controllers\Citizen\ComplaintController::class, 'create'])->name('complaint.create');
    // Complaint submission rate limit (Prompt 17): 5 submissions / 10 minutes /
    // authenticated user (named limiter `complaint-submission`). Applied ONLY to
    // the creation route so page views, detail, tracking, and attachment download
    // are never throttled. Guests are redirected by `auth` before the limiter runs.
    Route::post('/buat', [\App\Http\Controllers\Citizen\ComplaintController::class, 'store'])
        ->middleware('throttle:complaint-submission')
        ->name('complaint.store');
    Route::get('/profil', [\App\Http\Controllers\Citizen\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [\App\Http\Controllers\Citizen\ProfileController::class, 'update'])->name('profile.update');
    Route::get('/{complaint}', [\App\Http\Controllers\Citizen\ComplaintController::class, 'show'])
        ->whereNumber('complaint')
        ->name('complaint.show');
    // PHASE 2 — Return-to-citizen correction. The reporter may correct ONLY
    // their own complaint and ONLY while it is returned to them (server-side).
    // A resubmission updates the SAME complaint (never a new one) and sends it
    // back to the Super Admin validation queue.
    Route::get('/{complaint}/perbaiki', [\App\Http\Controllers\Citizen\ComplaintController::class, 'edit'])
        ->whereNumber('complaint')
        ->name('complaint.edit');
    Route::put('/{complaint}/perbaiki', [\App\Http\Controllers\Citizen\ComplaintController::class, 'update'])
        ->whereNumber('complaint')
        ->name('complaint.update');
    Route::get('/{complaint}/lampiran/{attachment}', [\App\Http\Controllers\Citizen\ComplaintController::class, 'downloadAttachment'])
        ->whereNumber('complaint')
        ->whereNumber('attachment')
        ->name('complaint.attachment');
    // PHASE 3 — Bagian 2 — Citizen satisfaction rating. OWNER-ONLY and ONLY
    // while the complaint is `resolved` (enforced server-side). Create-once and
    // immutable: there is deliberately NO update/delete route.
    Route::post('/{complaint}/rating', [\App\Http\Controllers\Citizen\ComplaintController::class, 'storeRating'])
        ->whereNumber('complaint')
        ->name('rating.store');
});

// ─── Citizen Help Desk / Pusat Bantuan (PROMPT TAHAP 2) ──────────────────────
//
// A support-ticket system SEPARATE from complaints. Owner-scoped: every
// per-ticket route resolves the ticket through a `user_id`-scoped query, so a
// foreign id yields 404 (IDOR closed). The write endpoints carry the
// `help-desk-write` rate limiter (abuse protection, keyed on the user id).
Route::middleware(['auth', 'role:masyarakat'])->prefix('bantuan')->name('citizen.help.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Citizen\HelpTicketController::class, 'index'])->name('index');
    Route::get('/buat', [\App\Http\Controllers\Citizen\HelpTicketController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Citizen\HelpTicketController::class, 'store'])
        ->middleware('throttle:help-desk-write')
        ->name('store');
    Route::get('/{ticket}', [\App\Http\Controllers\Citizen\HelpTicketController::class, 'show'])
        ->whereNumber('ticket')
        ->name('show');
    Route::post('/{ticket}/pesan', [\App\Http\Controllers\Citizen\HelpTicketController::class, 'reply'])
        ->whereNumber('ticket')
        ->middleware('throttle:help-desk-write')
        ->name('reply');
    // Only `resolved → in_progress`, and a message is REQUIRED (never silent).
    Route::post('/{ticket}/buka-kembali', [\App\Http\Controllers\Citizen\HelpTicketController::class, 'reopen'])
        ->whereNumber('ticket')
        ->middleware('throttle:help-desk-write')
        ->name('reopen');
});

// ─── Operator routes (accessible by operator and super_admin) ────────────────

Route::middleware(['auth', 'role:operator,super_admin'])->prefix('operator')->name('operator.')->group(function () {
    Route::get('/dashboard', [OperatorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/laporan', [OperatorComplaintController::class, 'index'])->name('complaint.index');
    Route::get('/laporan/{complaint}', [OperatorComplaintController::class, 'show'])->name('complaint.show');

    // PHASE 2R — Operator IMMUTABILITY of routing metadata.
    //
    // The following endpoints were REMOVED entirely (not merely hidden) because
    // an Operator must NEVER be able to change a complaint's category, Dinas/Unit
    // destination, or Operator assignment. Those three fields are decided ONLY by
    // the Super Admin initial-validation surface (`super-admin/validasi/...`) and
    // are resolved automatically from `complaints.dinas_unit_id`:
    //
    //   - PATCH /operator/laporan/{complaint}/kategori   (category_id)   REMOVED
    //   - PATCH /operator/laporan/{complaint}/tujuan     (dinas_unit_id) REMOVED
    //   - PATCH /operator/laporan/{complaint}/tugaskan   (assigned_to)   REMOVED
    //
    // Removing the route (rather than returning 403) is the strongest possible
    // server-side enforcement: there is no longer any Operator code path that can
    // mutate these columns. The Operator may still EDIT the geographic location
    // (Kecamatan/Kelurahan/coordinates) — a concern that is deliberately separate
    // from the Dinas/Unit destination — and may still run the operational actions
    // (status / internal note / public response).
    Route::patch('/laporan/{complaint}/lokasi', [OperatorComplaintController::class, 'updateLocation'])->name('complaint.update-location');
    Route::patch('/laporan/{complaint}/status', [OperatorComplaintController::class, 'updateStatus'])->name('complaint.update-status');
    // PHASE 3 — Completion Validation Gate. Operator SUBMITS the finished work
    // for Super Admin validation. This is NOT a status change (the status stays
    // `in_progress`); it moves the review state to `pending_completion_validation`.
    // An Operator can no longer reach `resolved` directly (enforced in
    // `updateStatus()`); completion approval belongs to a later Super Admin part.
    Route::patch('/laporan/{complaint}/ajukan-penyelesaian', [OperatorComplaintController::class, 'submitCompletion'])->name('complaint.submit-completion');
    Route::post('/laporan/{complaint}/catatan', [OperatorComplaintController::class, 'addNote'])->name('complaint.add-note');
    Route::get('/laporan/{complaint}/lampiran/{attachment}', [OperatorComplaintController::class, 'downloadAttachment'])
        ->whereNumber('complaint')
        ->whereNumber('attachment')
        ->name('complaint.attachment');
});

// ─── Staff self-service profile (shared: operator / admin / super_admin) ─────
//
// Self-service account page. A staff member may edit ONLY their own
// non-privileged contact fields (name, phone number), their profile photo, and
// their own password. Privileged fields — role and Dinas/Unit assignment —
// remain Super-Admin-only via User Management (Prompt 9/21) and are NOT read
// from this request (also enforced at the model layer), so this route cannot
// bypass the frozen authorization model.

Route::middleware(['auth', 'role:operator,admin,super_admin'])->prefix('profil')->name('staff.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Staff\StaffProfileController::class, 'show'])->name('profile');
    Route::patch('/', [\App\Http\Controllers\Staff\StaffProfileController::class, 'update'])->name('profile.update');
});

// ─── Admin (monitoring only) routes ──────────────────────────────────────────

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/laporan', [AdminDashboardController::class, 'complaints'])->name('complaints');
    // Admin has NO management routes — monitoring only
});

// ─── Super Admin routes ───────────────────────────────────────────────────────

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    // User Management (Prompt 9) — Super Admin only.
    // No hard-delete: accounts are deactivated to preserve history/audit.
    Route::get('/users', [\App\Http\Controllers\Staff\SuperAdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [\App\Http\Controllers\Staff\SuperAdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [\App\Http\Controllers\Staff\SuperAdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\Staff\SuperAdminUserController::class, 'edit'])
        ->whereNumber('user')->name('users.edit');
    Route::put('/users/{user}', [\App\Http\Controllers\Staff\SuperAdminUserController::class, 'update'])
        ->whereNumber('user')->name('users.update');
    Route::patch('/users/{user}/active', [\App\Http\Controllers\Staff\SuperAdminUserController::class, 'toggleActive'])
        ->whereNumber('user')->name('users.toggle');

    // Semua Laporan — Complaint monitoring (Prompt 12) — Super Admin only, READ-ONLY.
    // No create/update/delete route: Super Admin may inspect but never mutate
    // complaints (no status/category/routing/assignment/note/attachment changes).
    Route::get('/laporan', [\App\Http\Controllers\Staff\SuperAdminComplaintController::class, 'index'])
        ->name('complaints.index');
    Route::get('/laporan/{complaint}', [\App\Http\Controllers\Staff\SuperAdminComplaintController::class, 'show'])
        ->whereNumber('complaint')->name('complaints.show');

    // STAGE 3 — Official Super Admin reply to a citizen's written review.
    //
    // Deliberately NOT under `/super-admin/laporan` — that surface is a frozen,
    // strictly READ-ONLY monitoring contract (guarded by a dedicated test that
    // forbids ANY mutating method on `super-admin/laporan*`). The reply lives on
    // its own path so that invariant stays intact. It is isolated in its own
    // controller and writes ONLY `complaint_ratings.admin_response`
    // (create-once, immutable) — never the complaint status, rating, review text,
    // KPI, or analytics. Super Admin only (server-side `role:super_admin`).
    Route::post('/balasan-ulasan/{complaint}', [\App\Http\Controllers\Staff\SuperAdminReviewResponseController::class, 'store'])
        ->whereNumber('complaint')->name('review-response.store');

    // PHASE 2 — Initial validation queue + decisions (Super Admin ONLY).
    // These routes exist so every new citizen complaint passes Super Admin
    // validation before reaching an Operator. They never add a complaint status
    // value; the review state is a separate, isolated concept.
    Route::get('/validasi', [\App\Http\Controllers\Staff\SuperAdminValidationController::class, 'index'])
        ->name('validation.index');
    Route::get('/validasi/{complaint}', [\App\Http\Controllers\Staff\SuperAdminValidationController::class, 'show'])
        ->whereNumber('complaint')->name('validation.show');
    Route::post('/validasi/{complaint}/setujui', [\App\Http\Controllers\Staff\SuperAdminValidationController::class, 'approve'])
        ->whereNumber('complaint')->name('validation.approve');
    Route::post('/validasi/{complaint}/kembalikan', [\App\Http\Controllers\Staff\SuperAdminValidationController::class, 'returnToCitizen'])
        ->whereNumber('complaint')->name('validation.return');

    // PHASE 3 (Bagian 1C) — Completion validation queue + decisions (Super Admin ONLY).
    // After an Operator SUBMITS the finished work (Bagian 1B-2), the completion
    // must be validated here before the complaint becomes final `resolved`.
    // DELIBERATELY SEPARATE from the initial-validation routes above: the two
    // gates have different queues and side effects. No complaint status value is
    // added; approval uses the existing `in_progress → resolved` transition.
    Route::get('/validasi-penyelesaian', [\App\Http\Controllers\Staff\SuperAdminCompletionValidationController::class, 'index'])
        ->name('completion-validation.index');
    Route::get('/validasi-penyelesaian/{complaint}', [\App\Http\Controllers\Staff\SuperAdminCompletionValidationController::class, 'show'])
        ->whereNumber('complaint')->name('completion-validation.show');
    Route::post('/validasi-penyelesaian/{complaint}/setujui', [\App\Http\Controllers\Staff\SuperAdminCompletionValidationController::class, 'approve'])
        ->whereNumber('complaint')->name('completion-validation.approve');
    Route::post('/validasi-penyelesaian/{complaint}/kembalikan', [\App\Http\Controllers\Staff\SuperAdminCompletionValidationController::class, 'returnToOperator'])
        ->whereNumber('complaint')->name('completion-validation.return');

    // Audit & Security surface (Prompt 10) — Super Admin only, READ-ONLY.
    // No create/update/delete route: the audit trail is append-only.
    Route::get('/audit', [\App\Http\Controllers\Staff\SuperAdminAuditController::class, 'index'])->name('audit.index');
    Route::get('/audit/{auditLog}', [\App\Http\Controllers\Staff\SuperAdminAuditController::class, 'show'])
        ->whereNumber('auditLog')->name('audit.show');

    // Configuration Management (Prompt 11) — Super Admin only.
    // Only keys with a proven runtime consumer are exposed; no create/delete,
    // no arbitrary key, no .env/infrastructure editing.
    Route::get('/config', [\App\Http\Controllers\Staff\SuperAdminConfigController::class, 'index'])->name('config.index');
    Route::get('/config/{setting}/edit', [\App\Http\Controllers\Staff\SuperAdminConfigController::class, 'edit'])
        ->name('config.edit');
    Route::put('/config/{setting}', [\App\Http\Controllers\Staff\SuperAdminConfigController::class, 'update'])
        ->name('config.update');

    // Dinas/Unit master data CRUD (Prompt 5C) — Super Admin only
    Route::get('/dinas', [\App\Http\Controllers\Staff\SuperAdminDinasUnitController::class, 'index'])->name('dinas.index');
    Route::get('/dinas/create', [\App\Http\Controllers\Staff\SuperAdminDinasUnitController::class, 'create'])->name('dinas.create');
    Route::post('/dinas', [\App\Http\Controllers\Staff\SuperAdminDinasUnitController::class, 'store'])->name('dinas.store');
    Route::get('/dinas/{dinas_unit}/edit', [\App\Http\Controllers\Staff\SuperAdminDinasUnitController::class, 'edit'])
        ->whereNumber('dinas_unit')->name('dinas.edit');
    Route::put('/dinas/{dinas_unit}', [\App\Http\Controllers\Staff\SuperAdminDinasUnitController::class, 'update'])
        ->whereNumber('dinas_unit')->name('dinas.update');
    Route::delete('/dinas/{dinas_unit}', [\App\Http\Controllers\Staff\SuperAdminDinasUnitController::class, 'destroy'])
        ->whereNumber('dinas_unit')->name('dinas.destroy');

    // Kecamatan master data CRUD — Super Admin only (FINAL location rule)
    Route::get('/kecamatan', [\App\Http\Controllers\Staff\SuperAdminKecamatanController::class, 'index'])->name('kecamatan.index');
    Route::get('/kecamatan/create', [\App\Http\Controllers\Staff\SuperAdminKecamatanController::class, 'create'])->name('kecamatan.create');
    Route::post('/kecamatan', [\App\Http\Controllers\Staff\SuperAdminKecamatanController::class, 'store'])->name('kecamatan.store');
    Route::get('/kecamatan/{kecamatan}/edit', [\App\Http\Controllers\Staff\SuperAdminKecamatanController::class, 'edit'])
        ->whereNumber('kecamatan')->name('kecamatan.edit');
    Route::put('/kecamatan/{kecamatan}', [\App\Http\Controllers\Staff\SuperAdminKecamatanController::class, 'update'])
        ->whereNumber('kecamatan')->name('kecamatan.update');
    Route::delete('/kecamatan/{kecamatan}', [\App\Http\Controllers\Staff\SuperAdminKecamatanController::class, 'destroy'])
        ->whereNumber('kecamatan')->name('kecamatan.destroy');

    // Kelurahan master data CRUD — Super Admin only (FINAL location rule)
    Route::get('/kelurahan', [\App\Http\Controllers\Staff\SuperAdminKelurahanController::class, 'index'])->name('kelurahan.index');
    Route::get('/kelurahan/create', [\App\Http\Controllers\Staff\SuperAdminKelurahanController::class, 'create'])->name('kelurahan.create');
    Route::post('/kelurahan', [\App\Http\Controllers\Staff\SuperAdminKelurahanController::class, 'store'])->name('kelurahan.store');
    Route::get('/kelurahan/{kelurahan}/edit', [\App\Http\Controllers\Staff\SuperAdminKelurahanController::class, 'edit'])
        ->whereNumber('kelurahan')->name('kelurahan.edit');
    Route::put('/kelurahan/{kelurahan}', [\App\Http\Controllers\Staff\SuperAdminKelurahanController::class, 'update'])
        ->whereNumber('kelurahan')->name('kelurahan.update');
    Route::delete('/kelurahan/{kelurahan}', [\App\Http\Controllers\Staff\SuperAdminKelurahanController::class, 'destroy'])
        ->whereNumber('kelurahan')->name('kelurahan.destroy');

    // Category ↔ Dinas/Unit mapping (Prompt 5C) — Super Admin only
    Route::get('/kategori/{category}/mapping', [\App\Http\Controllers\Staff\SuperAdminCategoryMappingController::class, 'edit'])
        ->whereNumber('category')->name('categories.mapping.edit');
    Route::put('/kategori/{category}/mapping', [\App\Http\Controllers\Staff\SuperAdminCategoryMappingController::class, 'update'])
        ->whereNumber('category')->name('categories.mapping.update');

    // ─── Help Desk / Pusat Bantuan (PROMPT TAHAP 2) — Super Admin only ───────
    //
    // Ticket handling surface. Authorization is server-side (`role:super_admin`).
    // Admin (monitoring only) and Operator have NO access here. Status changes
    // obey the explicit `HelpTicketStatus` transition matrix. No complaint is
    // ever mutated by these endpoints.
    Route::get('/bantuan', [\App\Http\Controllers\Staff\SuperAdminHelpDeskController::class, 'index'])
        ->name('help.index');
    Route::get('/bantuan/{ticket}', [\App\Http\Controllers\Staff\SuperAdminHelpDeskController::class, 'show'])
        ->whereNumber('ticket')->name('help.show');
    Route::post('/bantuan/{ticket}/balas', [\App\Http\Controllers\Staff\SuperAdminHelpDeskController::class, 'reply'])
        ->whereNumber('ticket')->name('help.reply');
    Route::patch('/bantuan/{ticket}/status', [\App\Http\Controllers\Staff\SuperAdminHelpDeskController::class, 'updateStatus'])
        ->whereNumber('ticket')->name('help.status');

    // ─── WINDY weather (migrated legacy feature) — Super Admin only ──────────
    //
    // Configuration of risk thresholds (stored in `app_settings`, O-16) plus a
    // MANUAL BMKG sync trigger (O-7 default: no scheduler / job / queue). The
    // public read-only view lives at `/cuaca` outside this group.
    Route::get('/cuaca', [\App\Http\Controllers\Staff\SuperAdminWeatherController::class, 'index'])
        ->name('weather.index');
    Route::post('/cuaca/sinkronisasi', [\App\Http\Controllers\Staff\SuperAdminWeatherController::class, 'sync'])
        ->middleware('throttle:weather-sync')
        ->name('weather.sync');
    Route::put('/cuaca/ambang/{setting}', [\App\Http\Controllers\Staff\SuperAdminWeatherController::class, 'updateSetting'])
        ->name('weather.setting.update');

    // Category master data management (Prompt 6, Bagian 1) — Super Admin only
    Route::get('/kategori', [\App\Http\Controllers\Staff\SuperAdminCategoryController::class, 'index'])->name('categories.index');
    Route::get('/kategori/create', [\App\Http\Controllers\Staff\SuperAdminCategoryController::class, 'create'])->name('categories.create');
    Route::post('/kategori', [\App\Http\Controllers\Staff\SuperAdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('/kategori/{category}/edit', [\App\Http\Controllers\Staff\SuperAdminCategoryController::class, 'edit'])
        ->whereNumber('category')->name('categories.edit');
    Route::put('/kategori/{category}', [\App\Http\Controllers\Staff\SuperAdminCategoryController::class, 'update'])
        ->whereNumber('category')->name('categories.update');
    Route::patch('/kategori/{category}/status', [\App\Http\Controllers\Staff\SuperAdminCategoryController::class, 'toggleActive'])
        ->whereNumber('category')->name('categories.toggle');
    Route::delete('/kategori/{category}', [\App\Http\Controllers\Staff\SuperAdminCategoryController::class, 'destroy'])
        ->whereNumber('category')->name('categories.destroy');
});
