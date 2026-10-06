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

Route::get('/', function () {
    return view('public.landing');
})->name('home');

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
    Route::get('/{complaint}/lampiran/{attachment}', [\App\Http\Controllers\Citizen\ComplaintController::class, 'downloadAttachment'])
        ->whereNumber('complaint')
        ->whereNumber('attachment')
        ->name('complaint.attachment');
});

// ─── Operator routes (accessible by operator and super_admin) ────────────────

Route::middleware(['auth', 'role:operator,super_admin'])->prefix('operator')->name('operator.')->group(function () {
    Route::get('/dashboard', [OperatorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/laporan', [OperatorComplaintController::class, 'index'])->name('complaint.index');
    Route::get('/laporan/{complaint}', [OperatorComplaintController::class, 'show'])->name('complaint.show');
    Route::patch('/laporan/{complaint}/kategori', [OperatorComplaintController::class, 'updateCategory'])->name('complaint.update-category');
    Route::patch('/laporan/{complaint}/tujuan', [OperatorComplaintController::class, 'updateDestination'])->name('complaint.update-destination');
    Route::patch('/laporan/{complaint}/tugaskan', [OperatorComplaintController::class, 'assign'])->name('complaint.assign');
    Route::patch('/laporan/{complaint}/status', [OperatorComplaintController::class, 'updateStatus'])->name('complaint.update-status');
    Route::post('/laporan/{complaint}/catatan', [OperatorComplaintController::class, 'addNote'])->name('complaint.add-note');
    Route::get('/laporan/{complaint}/lampiran/{attachment}', [OperatorComplaintController::class, 'downloadAttachment'])
        ->whereNumber('complaint')
        ->whereNumber('attachment')
        ->name('complaint.attachment');
});

// ─── Admin (monitoring only) routes ──────────────────────────────────────────

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/laporan', [AdminDashboardController::class, 'complaints'])->name('complaints');
    Route::get('/audit-log', [AdminDashboardController::class, 'auditLog'])->name('audit-log');
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

    // Category ↔ Dinas/Unit mapping (Prompt 5C) — Super Admin only
    Route::get('/kategori/{category}/mapping', [\App\Http\Controllers\Staff\SuperAdminCategoryMappingController::class, 'edit'])
        ->whereNumber('category')->name('categories.mapping.edit');
    Route::put('/kategori/{category}/mapping', [\App\Http\Controllers\Staff\SuperAdminCategoryMappingController::class, 'update'])
        ->whereNumber('category')->name('categories.mapping.update');

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
