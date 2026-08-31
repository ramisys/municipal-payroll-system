<?php

use App\Http\Controllers\AttendanceImportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CompensationProfileController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ImportColumnMapController;
use App\Http\Controllers\OrganizationProfileController;
use App\Http\Controllers\PayrollImportController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\ReferenceDataController;
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

    // Placeholder landing view (UC-01 main flow step 6). M2 (employees)
    // is wired up; M3 onward is still a later week.
    Route::get('/dashboard', function (AuthorizationService $authorizationService) {
        $user = Auth::user();

        return view('dashboard', [
            'user' => $user,
            'canManageEmployees' => $authorizationService->can($user, 'employees.manage'),
            'canManageUsers' => $authorizationService->can($user, 'users.manage'),
            'canViewAuditLog' => $authorizationService->can($user, 'audit_log.view'),
            'canManageOrganization' => $authorizationService->can($user, 'organization.manage'),
            'canImportAttendance' => $authorizationService->can($user, 'attendance.import'),
            'canManagePayrollRuns' => $authorizationService->can($user, 'payroll_run.create_import'),
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

    // UC-03 — Administrator only ('organization.manage').
    Route::get('/organization', [OrganizationProfileController::class, 'edit'])->name('organization.edit');
    Route::put('/organization', [OrganizationProfileController::class, 'update'])->name('organization.update');

    Route::get('/organization/periods', [OrganizationProfileController::class, 'periodsIndex'])->name('organization.periods.index');
    Route::post('/organization/periods', [OrganizationProfileController::class, 'periodsStore'])->name('organization.periods.store');
    Route::get('/organization/periods/{period}/edit', [OrganizationProfileController::class, 'periodsEdit'])->name('organization.periods.edit');
    Route::put('/organization/periods/{period}', [OrganizationProfileController::class, 'periodsUpdate'])->name('organization.periods.update');

    Route::get('/organization/holidays', [OrganizationProfileController::class, 'holidaysIndex'])->name('organization.holidays.index');
    Route::get('/organization/holidays/create', [OrganizationProfileController::class, 'holidaysCreate'])->name('organization.holidays.create');
    Route::post('/organization/holidays', [OrganizationProfileController::class, 'holidaysStore'])->name('organization.holidays.store');
    Route::get('/organization/holidays/{holiday}/edit', [OrganizationProfileController::class, 'holidaysEdit'])->name('organization.holidays.edit');
    Route::put('/organization/holidays/{holiday}', [OrganizationProfileController::class, 'holidaysUpdate'])->name('organization.holidays.update');

    // UC-04 — Administrator only ('reference_data.manage'). {type} is one
    // of the six slugs ReferenceDataController::config() knows.
    Route::get('/reference-data/{type}', [ReferenceDataController::class, 'index'])->name('reference-data.index');
    Route::get('/reference-data/{type}/create', [ReferenceDataController::class, 'create'])->name('reference-data.create');
    Route::post('/reference-data/{type}', [ReferenceDataController::class, 'store'])->name('reference-data.store');
    Route::get('/reference-data/{type}/{id}/edit', [ReferenceDataController::class, 'edit'])->whereNumber('id')->name('reference-data.edit');
    Route::put('/reference-data/{type}/{id}', [ReferenceDataController::class, 'update'])->whereNumber('id')->name('reference-data.update');
    Route::post('/reference-data/{type}/{id}/deactivate', [ReferenceDataController::class, 'deactivate'])->whereNumber('id')->name('reference-data.deactivate');
    Route::post('/reference-data/{type}/{id}/reactivate', [ReferenceDataController::class, 'reactivate'])->whereNumber('id')->name('reference-data.reactivate');

    // UC-08, UC-09, UC-10 — Payroll Officer, Administrator ('employees.manage').
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('/employees/{employee}/deactivate', [EmployeeController::class, 'deactivateForm'])->name('employees.deactivate-form');
    Route::post('/employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
    Route::get('/employees/{employee}/reactivate', [EmployeeController::class, 'reactivateForm'])->name('employees.reactivate-form');
    Route::post('/employees/{employee}/reactivate', [EmployeeController::class, 'reactivate'])->name('employees.reactivate');

    // UC-11 — Payroll Officer, Administrator ('employees.manage').
    Route::get('/employees/{employee}/compensation', [CompensationProfileController::class, 'index'])->name('employees.compensation.index');
    Route::post('/employees/{employee}/compensation', [CompensationProfileController::class, 'store'])->name('employees.compensation.store');
    Route::post('/employees/{employee}/compensation/recurring-earnings', [CompensationProfileController::class, 'storeEarning'])->name('employees.compensation.recurring-earnings.store');
    Route::post('/employees/{employee}/compensation/recurring-earnings/{recurringEarning}/end', [CompensationProfileController::class, 'endEarning'])->name('employees.compensation.recurring-earnings.end');
    Route::post('/employees/{employee}/compensation/recurring-deductions', [CompensationProfileController::class, 'storeDeduction'])->name('employees.compensation.recurring-deductions.store');
    Route::post('/employees/{employee}/compensation/recurring-deductions/{recurringDeduction}/end', [CompensationProfileController::class, 'endDeduction'])->name('employees.compensation.recurring-deductions.end');

    // UC-13 — Payroll Officer only ('attendance.import').
    Route::get('/attendance-import', [AttendanceImportController::class, 'create'])->name('attendance-import.create');
    Route::post('/attendance-import/preview', [AttendanceImportController::class, 'preview'])->name('attendance-import.preview');
    Route::post('/attendance-import/commit', [AttendanceImportController::class, 'commit'])->name('attendance-import.commit');
    Route::post('/attendance-import/cancel', [AttendanceImportController::class, 'cancel'])->name('attendance-import.cancel');

    // UC-04 mapping editor — AD-17, BR-41. Administrator only.
    Route::get('/import-column-maps', [ImportColumnMapController::class, 'index'])->name('import-column-maps.index');
    Route::get('/import-column-maps/create', [ImportColumnMapController::class, 'create'])->name('import-column-maps.create');
    Route::post('/import-column-maps', [ImportColumnMapController::class, 'store'])->name('import-column-maps.store');
    Route::post('/import-column-maps/{importColumnMap}/deactivate', [ImportColumnMapController::class, 'deactivate'])->name('import-column-maps.deactivate');
    Route::post('/import-column-maps/{importColumnMap}/reactivate', [ImportColumnMapController::class, 'reactivate'])->name('import-column-maps.reactivate');

    // UC-17, UC-32 — Payroll Officer only ('payroll_run.create_import').
    Route::get('/payroll-runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
    Route::get('/payroll-runs/create', [PayrollRunController::class, 'create'])->name('payroll-runs.create');
    Route::post('/payroll-runs', [PayrollRunController::class, 'store'])->name('payroll-runs.store');
    Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show'])->name('payroll-runs.show');
    Route::get('/payroll-runs/{payrollRun}/cancel', [PayrollRunController::class, 'cancelForm'])->name('payroll-runs.cancel-form');
    Route::post('/payroll-runs/{payrollRun}/cancel', [PayrollRunController::class, 'cancel'])->name('payroll-runs.cancel');
    Route::get('/payroll-runs/{payrollRun}/worksheet', [PayrollRunController::class, 'exportWorksheet'])->name('payroll-runs.worksheet');

    // UC-18 — Payroll Officer only ('payroll_run.create_import').
    Route::get('/payroll-runs/{payrollRun}/import', [PayrollImportController::class, 'create'])->name('payroll-imports.create');
    Route::post('/payroll-runs/{payrollRun}/import/preview', [PayrollImportController::class, 'preview'])->name('payroll-imports.preview');
    Route::post('/payroll-runs/{payrollRun}/import/commit', [PayrollImportController::class, 'commit'])->name('payroll-imports.commit');
    Route::post('/payroll-runs/{payrollRun}/import/cancel', [PayrollImportController::class, 'cancel'])->name('payroll-imports.cancel');

    // UC-33 — Payroll Officer, Approver, Administrator, Viewer ('payroll_records.search').
    Route::get('/payroll-runs/{payrollRun}/imports', [PayrollImportController::class, 'history'])->name('payroll-imports.history');
    Route::get('/payroll-runs/{payrollRun}/imports/{payrollImport}', [PayrollImportController::class, 'show'])->name('payroll-imports.show');
    Route::get('/payroll-runs/{payrollRun}/imports/{payrollImport}/download', [PayrollImportController::class, 'download'])->name('payroll-imports.download');
});
