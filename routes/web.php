<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\UserController;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// UC-01 — unauthenticated.
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

// Everything past this point requires an authenticated session (AC-0.1.1),
// an idle check every request (BR-32), and — until the initial password
// is changed — is redirected to the change-password screen (UC-01 A1).
Route::middleware(['auth', 'session.idle', 'password.changed'])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.update');

    // Placeholder landing view (UC-01 main flow step 6). Per-role module
    // screens don't exist yet — M2 onward is a later week.
    Route::get('/dashboard', function (AuthorizationService $authorizationService) {
        $user = Auth::user();

        return view('dashboard', [
            'user' => $user,
            'canManageUsers' => $authorizationService->can($user, 'users.manage'),
            'canViewAuditLog' => $authorizationService->can($user, 'audit_log.view'),
        ]);
    })->name('dashboard');

    // UC-02 — Administrator only (AuthorizationService::authorize enforces
    // this inside every UserController action; the route list itself
    // grants nothing).
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    // UC-06 — Approver, Administrator, Viewer (AuthorizationService
    // enforces; not the Payroll Officer, per the FR-6.2 matrix).
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
});
