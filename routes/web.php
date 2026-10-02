<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Citizen\DashboardController as CitizenDashboard;
use App\Http\Controllers\Staff\PetugasDashboardController;
use App\Http\Controllers\Staff\OperatorDashboardController;
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
    Route::post('/buat', [\App\Http\Controllers\Citizen\ComplaintController::class, 'store'])->name('complaint.store');
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

// ─── Petugas routes ───────────────────────────────────────────────────────────

Route::middleware(['auth', 'role:petugas'])->prefix('petugas')->name('petugas.')->group(function () {
    Route::get('/dashboard', [PetugasDashboardController::class, 'index'])->name('dashboard');
    Route::get('/laporan/{complaint}', [\App\Http\Controllers\Staff\PetugasComplaintController::class, 'show'])->name('complaint.show');
    Route::patch('/laporan/{complaint}/status', [\App\Http\Controllers\Staff\PetugasComplaintController::class, 'updateStatus'])->name('complaint.update-status');
    Route::post('/laporan/{complaint}/catatan', [\App\Http\Controllers\Staff\PetugasComplaintController::class, 'addNote'])->name('complaint.add-note');
});

// ─── Operator routes ──────────────────────────────────────────────────────────

Route::middleware(['auth', 'role:operator'])->prefix('operator')->name('operator.')->group(function () {
    Route::get('/dashboard', [OperatorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/laporan', [\App\Http\Controllers\Staff\OperatorComplaintController::class, 'index'])->name('complaint.index');
    Route::get('/laporan/{complaint}', [\App\Http\Controllers\Staff\OperatorComplaintController::class, 'show'])->name('complaint.show');
    Route::patch('/laporan/{complaint}/kategori', [\App\Http\Controllers\Staff\OperatorComplaintController::class, 'updateCategory'])->name('complaint.update-category');
    Route::patch('/laporan/{complaint}/tugaskan', [\App\Http\Controllers\Staff\OperatorComplaintController::class, 'assign'])->name('complaint.assign');
    Route::patch('/laporan/{complaint}/status', [\App\Http\Controllers\Staff\OperatorComplaintController::class, 'updateStatus'])->name('complaint.update-status');
    Route::post('/laporan/{complaint}/catatan', [\App\Http\Controllers\Staff\OperatorComplaintController::class, 'addNote'])->name('complaint.add-note');
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

    // Complaint management
    Route::get('/laporan', [\App\Http\Controllers\SuperAdmin\ComplaintManagementController::class, 'index'])->name('complaint.index');
    Route::get('/laporan/{complaint}', [\App\Http\Controllers\SuperAdmin\ComplaintManagementController::class, 'show'])->name('complaint.show');

    // User management
    Route::get('/pengguna', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'index'])->name('user.index');
    Route::get('/pengguna/buat', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'create'])->name('user.create');
    Route::post('/pengguna', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'store'])->name('user.store');
    Route::get('/pengguna/{user}/edit', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'edit'])->name('user.edit');
    Route::patch('/pengguna/{user}', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'update'])->name('user.update');
    Route::patch('/pengguna/{user}/reset-password', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'resetPassword'])->name('user.reset-password');
    Route::patch('/pengguna/{user}/toggle-aktif', [\App\Http\Controllers\SuperAdmin\UserManagementController::class, 'toggleActive'])->name('user.toggle-active');

    // Category management
    Route::resource('/kategori', \App\Http\Controllers\SuperAdmin\CategoryController::class)->except(['show']);

    // Configuration management
    Route::get('/konfigurasi', [\App\Http\Controllers\SuperAdmin\ConfigurationController::class, 'index'])->name('config.index');
    Route::patch('/konfigurasi', [\App\Http\Controllers\SuperAdmin\ConfigurationController::class, 'update'])->name('config.update');

    // Audit log
    Route::get('/audit-log', [\App\Http\Controllers\SuperAdmin\AuditSecurityController::class, 'index'])->name('audit.index');
});
