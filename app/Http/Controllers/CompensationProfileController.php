<?php

namespace App\Http\Controllers;

use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\RecurringDeduction;
use App\Models\RecurringEarning;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\CompensationProfileException;
use App\Services\CompensationProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// UC-11 · Maintain compensation profile — FR-1.2, BR-08. Payroll Officer
// and Administrator, same 'employees.manage' grant as EmployeeController
// (RoleSeeder's comment: "employees.manage FR-1.1, FR-1.2 — PO, Admin").
//
// All writes go through CompensationProfileService, which owns the
// dated-version-chain logic (BR-08) and the overlap refusal (UC-11 E1);
// this controller only translates that between HTTP and the service.
class CompensationProfileController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly CompensationProfileService $compensationProfileService,
    ) {}

    public function index(Request $request, Employee $employee): View
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $profiles = $employee->compensationProfiles()->orderByDesc('effective_from')->get();
        $earnings = $employee->recurringEarnings()->with('earningType')->orderByDesc('effective_from')->get();
        $deductions = $employee->recurringDeductions()->with('deductionType')->orderByDesc('effective_from')->get();

        return view('employees.compensation', [
            'employee' => $employee,
            'profiles' => $profiles,
            'currentProfile' => $this->compensationProfileService->currentProfile($employee),
            'earnings' => $earnings,
            'deductions' => $deductions,
            'earningTypes' => EarningType::query()->where('is_active', true)->orderBy('earning_name')->get(),
            'deductionTypes' => DeductionType::query()->where('is_active', true)->orderBy('deduction_name')->get(),
        ]);
    }

    // UC-11 steps 2, 5, 6 — a new dated compensation profile version.
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate([
            'pay_basis' => ['required', Rule::in(['MONTHLY', 'DAILY', 'HOURLY'])],
            'basic_rate' => ['required', 'decimal:0,2', 'gt:0'],
            'sss_covered' => ['sometimes', 'boolean'],
            'philhealth_covered' => ['sometimes', 'boolean'],
            'pagibig_covered' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
        ]);
        $data['sss_covered'] = $request->boolean('sss_covered');
        $data['philhealth_covered'] = $request->boolean('philhealth_covered');
        $data['pagibig_covered'] = $request->boolean('pagibig_covered');

        try {
            $profile = $this->compensationProfileService->recordProfile($employee, $data, $request->user()->user_id);
        } catch (CompensationProfileException $e) {
            return back()->withErrors(['effective_from' => $e->getMessage()])->withInput();
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'COMPENSATION_PROFILE',
            entityId: $profile->compensation_profile_id,
            action: 'CREATE',
            newValues: $data,
        );

        return redirect()->route('employees.compensation.index', $employee)->with('status', 'Compensation profile recorded.');
    }

    // UC-11 step 3.
    public function storeEarning(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate([
            'earning_type_id' => ['required', 'integer', 'exists:earning_types,earning_type_id'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'effective_from' => ['required', 'date'],
        ]);

        try {
            $earning = $this->compensationProfileService->addRecurringEarning(
                $employee,
                (int) $data['earning_type_id'],
                $data['amount'],
                $data['effective_from'],
                $request->user()->user_id,
            );
        } catch (CompensationProfileException $e) {
            return back()->withErrors(['earning_type_id' => $e->getMessage()])->withInput();
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'RECURRING_EARNING',
            entityId: $earning->recurring_earning_id,
            action: 'CREATE',
            newValues: $data,
        );

        return redirect()->route('employees.compensation.index', $employee)->with('status', 'Recurring earning added.');
    }

    // UC-11 A2 — ends rather than deletes.
    public function endEarning(Request $request, Employee $employee, RecurringEarning $recurringEarning): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate(['effective_to' => ['required', 'date']]);

        try {
            $this->compensationProfileService->endRecurringEarning($recurringEarning, $data['effective_to'], $request->user()->user_id);
        } catch (CompensationProfileException $e) {
            return back()->withErrors(['effective_to' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'RECURRING_EARNING',
            entityId: $recurringEarning->recurring_earning_id,
            action: 'UPDATE',
            newValues: ['effective_to' => $data['effective_to']],
        );

        return redirect()->route('employees.compensation.index', $employee)->with('status', 'Recurring earning ended.');
    }

    // UC-11 step 4.
    public function storeDeduction(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate([
            'deduction_type_id' => ['required', 'integer', 'exists:deduction_types,deduction_type_id'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'effective_from' => ['required', 'date'],
        ]);

        try {
            $deduction = $this->compensationProfileService->addRecurringDeduction(
                $employee,
                (int) $data['deduction_type_id'],
                $data['amount'],
                $data['effective_from'],
                $request->user()->user_id,
            );
        } catch (CompensationProfileException $e) {
            return back()->withErrors(['deduction_type_id' => $e->getMessage()])->withInput();
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'RECURRING_DEDUCTION',
            entityId: $deduction->recurring_deduction_id,
            action: 'CREATE',
            newValues: $data,
        );

        return redirect()->route('employees.compensation.index', $employee)->with('status', 'Recurring deduction added.');
    }

    public function endDeduction(Request $request, Employee $employee, RecurringDeduction $recurringDeduction): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate(['effective_to' => ['required', 'date']]);

        try {
            $this->compensationProfileService->endRecurringDeduction($recurringDeduction, $data['effective_to'], $request->user()->user_id);
        } catch (CompensationProfileException $e) {
            return back()->withErrors(['effective_to' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'RECURRING_DEDUCTION',
            entityId: $recurringDeduction->recurring_deduction_id,
            action: 'UPDATE',
            newValues: ['effective_to' => $data['effective_to']],
        );

        return redirect()->route('employees.compensation.index', $employee)->with('status', 'Recurring deduction ended.');
    }
}
