<?php

use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\SessionController;
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
    Route::get('/dashboard', function () {
        return view('dashboard', ['user' => Auth::user()]);
    })->name('dashboard');
});
