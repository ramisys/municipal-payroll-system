<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\Position;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\ValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// UC-08 · Register employee, UC-09 · Update employee record, UC-10 ·
// Deactivate or reactivate employee — FR-1.1, FR-1.5, BR-06, BR-07, BR-33.
// Payroll Officer and Administrator both hold 'employees.manage'
// (FR-6.2 matrix row "Maintain employee records"; RoleSeeder grants it to
// both — the Administrator's presence here is the rare support/migration
// capability use-case-model.md §828 describes, not a distinct screen).
//
// No delete: BR-07/BR-33 forbid it outright, and this controller offers no
// destroy() action, matching UserController and ReferenceDataController.
class EmployeeController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly ValidationService $validationService,
    ) {}

    // FR-1.1 behavior 6 — search/filter by name, employee number,
    // department, position, and status.
    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $query = Employee::query()->with(['currentEmploymentDetail.department', 'currentEmploymentDetail.position', 'currentEmploymentDetail.employmentStatus']);

        if ($request->filled('q')) {
            $term = trim($request->string('q'));
            $query->where(function ($q) use ($term) {
                $q->where('employee_no', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status') === 'active');
        }

        if ($request->filled('department_id')) {
            $departmentId = $request->integer('department_id');
            $query->whereHas('currentEmploymentDetail', fn ($q) => $q->where('department_id', $departmentId));
        }

        $employees = $query->orderBy('last_name')->orderBy('first_name')->get();

        return view('employees.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('department_name')->get(),
            'filters' => $request->only(['q', 'status', 'department_id']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        return view('employees.create', $this->referenceData());
    }

    public function store(Request $request): RedirectResponse|View
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate($this->personalAndEmploymentRules());

        $dateErrors = $this->validationService->validateEmployeeDateLogic($data['birth_date'], $data['date_hired'], null);
        if ($dateErrors !== []) {
            return back()->withErrors($dateErrors)->withInput();
        }

        // UC-08 E2 — a probable duplicate person is a warning the user
        // must explicitly acknowledge, not an outright refusal (unlike a
        // duplicate employee_no, which `unique:employees,employee_no`
        // already refuses via the rule above).
        $duplicate = $this->validationService->findProbableDuplicatePerson($data['last_name'], $data['first_name'], $data['birth_date']);
        if ($duplicate !== null && ! $request->boolean('acknowledge_duplicate')) {
            return view('employees.create', [
                ...$this->referenceData(),
                'duplicate' => $duplicate,
                'item' => $data,
            ]);
        }

        $employee = DB::transaction(function () use ($data, $request) {
            $employee = Employee::create([
                'employee_no' => $data['employee_no'],
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'birth_date' => $data['birth_date'],
                'sex' => $data['sex'],
                'civil_status' => $data['civil_status'],
                'contact_no' => $data['contact_no'] ?? null,
                'address' => $data['address'] ?? null,
                'sss_no' => $data['sss_no'] ?? null,
                'philhealth_no' => $data['philhealth_no'] ?? null,
                'pagibig_mid' => $data['pagibig_mid'] ?? null,
                'tin' => $data['tin'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()->user_id,
                'updated_by' => $request->user()->user_id,
            ]);

            EmploymentDetail::create([
                'employee_id' => $employee->employee_id,
                'department_id' => $data['department_id'],
                'position_id' => $data['position_id'],
                'employment_status_id' => $data['employment_status_id'],
                'date_hired' => $data['date_hired'],
                'effective_from' => $data['date_hired'],
                'effective_to' => null,
                'created_by' => $request->user()->user_id,
                'updated_by' => $request->user()->user_id,
            ]);

            $this->auditService->record(
                user: $request->user(),
                entityName: 'EMPLOYEE',
                entityId: $employee->employee_id,
                action: 'CREATE',
                newValues: $data,
            );

            return $employee;
        });

        return redirect()->route('employees.edit', $employee)->with('status', "Employee '{$employee->employee_no}' registered.");
    }

    public function edit(Request $request, Employee $employee): View
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $employee->load('currentEmploymentDetail');

        return view('employees.edit', [
            ...$this->referenceData(),
            'employee' => $employee,
            'detail' => $employee->currentEmploymentDetail,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate($this->personalAndEmploymentRules($employee->employee_id));

        $dateErrors = $this->validationService->validateEmployeeDateLogic($data['birth_date'], $data['date_hired'], null);
        if ($dateErrors !== []) {
            return back()->withErrors($dateErrors)->withInput();
        }

        DB::transaction(function () use ($data, $request, $employee) {
            $previousValues = $employee->only(array_keys($this->personalFields()));

            $employee->fill(array_intersect_key($data, $this->personalFields()));
            $employee->updated_by = $request->user()->user_id;
            $employee->save();

            $this->auditService->record(
                user: $request->user(),
                entityName: 'EMPLOYEE',
                entityId: $employee->employee_id,
                action: 'UPDATE',
                previousValues: $previousValues,
                newValues: array_intersect_key($data, $this->personalFields()),
            );

            $this->applyEmploymentTransfer($request, $employee, $data);
        });

        return redirect()->route('employees.edit', $employee)->with('status', "Employee '{$employee->employee_no}' updated.");
    }

    // UC-09 A1 · Transfer — a change of department, position, or
    // employment status does not overwrite the current EMPLOYMENT_DETAIL
    // row; it closes it (effective_to) and opens a new one, so a past
    // payroll run keeps reporting the department the employee actually
    // belonged to (data-model.md §4.1 prose, the BR-08 dated-row pattern).
    private function applyEmploymentTransfer(Request $request, Employee $employee, array $data): void
    {
        $current = EmploymentDetail::query()
            ->where('employee_id', $employee->employee_id)
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();

        $unchanged = $current !== null
            && (int) $current->department_id === (int) $data['department_id']
            && (int) $current->position_id === (int) $data['position_id']
            && (int) $current->employment_status_id === (int) $data['employment_status_id'];

        if ($unchanged) {
            return;
        }

        $effectiveFrom = $data['transfer_effective_from'] ?? now()->toDateString();

        $previousValues = $current?->only(['department_id', 'position_id', 'employment_status_id']);

        if ($current !== null) {
            $current->effective_to = $effectiveFrom;
            $current->updated_by = $request->user()->user_id;
            $current->save();
        }

        $new = EmploymentDetail::create([
            'employee_id' => $employee->employee_id,
            'department_id' => $data['department_id'],
            'position_id' => $data['position_id'],
            'employment_status_id' => $data['employment_status_id'],
            'date_hired' => $current->date_hired ?? $data['date_hired'],
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'created_by' => $request->user()->user_id,
            'updated_by' => $request->user()->user_id,
        ]);

        $this->auditService->record(
            user: $request->user(),
            entityName: 'EMPLOYMENT_DETAIL',
            entityId: $new->employment_detail_id,
            action: 'UPDATE',
            previousValues: $previousValues,
            newValues: ['department_id' => $new->department_id, 'position_id' => $new->position_id, 'employment_status_id' => $new->employment_status_id],
        );
    }

    public function deactivateForm(Request $request, Employee $employee): View
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        return view('employees.deactivate', ['employee' => $employee]);
    }

    // UC-10 main flow — separation date and reason are captured on the
    // employee's current (still-open) EMPLOYMENT_DETAIL row, closing it;
    // no new row is opened, since separation is terminal until a
    // reactivation explicitly starts a new one (A1).
    public function deactivate(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate([
            'separation_date' => ['required', 'date'],
            'separation_reason' => ['required', 'string', 'max:255'],
        ]);

        $current = EmploymentDetail::query()
            ->where('employee_id', $employee->employee_id)
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->firstOrFail();

        $dateErrors = $this->validationService->validateEmployeeDateLogic($employee->birth_date->toDateString(), $current->date_hired->toDateString(), $data['separation_date']);
        if (isset($dateErrors['separation_date'])) {
            return back()->withErrors($dateErrors)->withInput();
        }

        DB::transaction(function () use ($request, $employee, $current, $data) {
            $current->separation_date = $data['separation_date'];
            $current->separation_reason = $data['separation_reason'];
            $current->effective_to = $data['separation_date'];
            $current->updated_by = $request->user()->user_id;
            $current->save();

            $employee->is_active = false;
            $employee->updated_by = $request->user()->user_id;
            $employee->save();

            $this->auditService->record($request->user(), 'EMPLOYEE', $employee->employee_id, 'UPDATE', ['is_active' => true], ['is_active' => false, 'separation_date' => $data['separation_date'], 'separation_reason' => $data['separation_reason']]);
        });

        // NFR-6.3 — the deactivation is confirmed and the exclusion it
        // causes is stated back, not merely performed silently.
        return redirect()->route('employees.index')->with('status', "Employee '{$employee->employee_no}' deactivated; excluded from payroll runs for periods after {$data['separation_date']}.");
    }

    public function reactivateForm(Request $request, Employee $employee): View
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        return view('employees.reactivate', $this->referenceData() + ['employee' => $employee]);
    }

    // UC-10 A1 — preserves the original record and its full history; the
    // rehire opens a new EMPLOYMENT_DETAIL row rather than reopening the
    // old one, so the separation that closed it remains visible.
    public function reactivate(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'employees.manage');

        $data = $request->validate([
            'date_hired' => ['required', 'date'],
            'department_id' => ['required', 'integer', 'exists:departments,department_id'],
            'position_id' => ['required', 'integer', 'exists:positions,position_id'],
            'employment_status_id' => ['required', 'integer', 'exists:employment_statuses,employment_status_id'],
        ]);

        $dateErrors = $this->validationService->validateEmployeeDateLogic($employee->birth_date->toDateString(), $data['date_hired'], null);
        if ($dateErrors !== []) {
            return back()->withErrors($dateErrors)->withInput();
        }

        DB::transaction(function () use ($request, $employee, $data) {
            EmploymentDetail::create([
                'employee_id' => $employee->employee_id,
                'department_id' => $data['department_id'],
                'position_id' => $data['position_id'],
                'employment_status_id' => $data['employment_status_id'],
                'date_hired' => $data['date_hired'],
                'effective_from' => $data['date_hired'],
                'effective_to' => null,
                'created_by' => $request->user()->user_id,
                'updated_by' => $request->user()->user_id,
            ]);

            $employee->is_active = true;
            $employee->updated_by = $request->user()->user_id;
            $employee->save();

            $this->auditService->record($request->user(), 'EMPLOYEE', $employee->employee_id, 'UPDATE', ['is_active' => false], ['is_active' => true, 'date_hired' => $data['date_hired']]);
        });

        return redirect()->route('employees.index')->with('status', "Employee '{$employee->employee_no}' reactivated.");
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceData(): array
    {
        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('department_name')->get(),
            'positions' => Position::query()->where('is_active', true)->orderBy('position_title')->get(),
            'employmentStatuses' => EmploymentStatus::query()->where('is_active', true)->orderBy('status_name')->get(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function personalAndEmploymentRules(?int $ignoreEmployeeId = null): array
    {
        return [
            // FR-1.1 behavior 1, FR-1.5 item 1 — required personal fields.
            'employee_no' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_no')->ignore($ignoreEmployeeId, 'employee_id')],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'sex' => ['required', Rule::in(['M', 'F'])],
            'civil_status' => ['required', Rule::in(['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED'])],
            'contact_no' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            // FR-1.5 item 2, UC-I1 step 4 — government ID formats. FR-1.1
            // A3 — every one of these may be left blank (deferred).
            'sss_no' => ['nullable', 'string', 'regex:/^\d{2}-\d{7}-\d{1}$/'],
            'philhealth_no' => ['nullable', 'string', 'regex:/^\d{2}-\d{9}-\d{1}$/'],
            'pagibig_mid' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}-\d{4}$/'],
            'tin' => ['nullable', 'string', 'regex:/^\d{3}-\d{3}-\d{3}(-\d{3,4})?$/'],
            // FR-1.1 behavior 1, FR-1.5 item 1 — required employment fields.
            'date_hired' => ['required', 'date'],
            'department_id' => ['required', 'integer', 'exists:departments,department_id'],
            'position_id' => ['required', 'integer', 'exists:positions,position_id'],
            'employment_status_id' => ['required', 'integer', 'exists:employment_statuses,employment_status_id'],
            'transfer_effective_from' => ['nullable', 'date'],
            'acknowledge_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, true>
     */
    private function personalFields(): array
    {
        return array_fill_keys([
            'employee_no', 'last_name', 'first_name', 'middle_name', 'birth_date', 'sex', 'civil_status',
            'contact_no', 'address', 'sss_no', 'philhealth_no', 'pagibig_mid', 'tin',
        ], true);
    }
}
