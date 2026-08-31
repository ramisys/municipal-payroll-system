<?php

namespace App\Http\Controllers;

use App\Models\AttendanceType;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\EmploymentStatus;
use App\Models\LeaveType;
use App\Models\Position;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// UC-04 · Maintain reference data — FR-0.4, BR-12, BR-25, BR-33.
// Administrator only ('reference_data.manage').
//
// One controller for all seven lists (departments, positions, employment
// statuses, earning types, deduction types, leave types, attendance types)
// because UC-04 is itself one use case whose first step is "select a
// reference list" — near-identical controllers would just be copies of the
// same deactivate/in-use-guard logic (AC-0.4.1, AC-0.4.2). $type is the
// route segment naming which list; config() is the only place that knows
// how each list differs.
//
// Attendance types are carried here even though FR-0.4's own behavior text
// names only the first six — data-model.md §7 lists ATTENDANCE_TYPE among
// "entities added beyond the FRS §8.1 inventory... for the maintained
// reference lists," so a seventh $type is the documented home for it,
// not scope creep.
class ReferenceDataController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request, string $type): View
    {
        $this->authorizationService->authorize($request->user(), 'reference_data.manage');
        $config = $this->config($type);

        $items = $config['model']::query()->orderBy($config['name_column'])->get();

        return view('reference-data.index', ['type' => $type, 'config' => $config, 'items' => $items]);
    }

    public function create(Request $request, string $type): View
    {
        $this->authorizationService->authorize($request->user(), 'reference_data.manage');
        $config = $this->config($type);

        return view('reference-data.form', ['type' => $type, 'config' => $config, 'item' => new $config['model']]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'reference_data.manage');
        $config = $this->config($type);

        $data = $request->validate($this->rules($type));
        $data = $this->normalizeBooleans($type, $data);

        $item = new $config['model']($data);
        $item->is_active = true;
        $item->created_by = $request->user()->user_id;
        $item->updated_by = $request->user()->user_id;
        $item->save();

        $this->auditService->record($request->user(), $config['entity_name'], $item->getKey(), 'CREATE', null, $data);

        return redirect()->route('reference-data.index', $type)->with('status', "{$config['label']} entry created.");
    }

    public function edit(Request $request, string $type, int $id): View
    {
        $this->authorizationService->authorize($request->user(), 'reference_data.manage');
        $config = $this->config($type);

        $item = $this->find($config, $id);

        return view('reference-data.form', ['type' => $type, 'config' => $config, 'item' => $item]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'reference_data.manage');
        $config = $this->config($type);

        $item = $this->find($config, $id);
        $data = $request->validate($this->rules($type, $id));
        $data = $this->normalizeBooleans($type, $data);

        $previousValues = $item->only(array_keys($data));
        $item->fill($data);
        $item->updated_by = $request->user()->user_id;
        $item->save();

        $this->auditService->record($request->user(), $config['entity_name'], $item->getKey(), 'UPDATE', $previousValues, $data);

        return redirect()->route('reference-data.index', $type)->with('status', "{$config['label']} entry updated.");
    }

    // AC-0.4.2 — a deactivated entry is not selectable on new records but
    // continues to display on existing ones; it is never deleted
    // (deletion is not offered at all — matches UserController's
    // deactivate/reactivate pattern for the same reason, BR-33).
    public function deactivate(Request $request, string $type, int $id): RedirectResponse
    {
        return $this->setActive($request, $type, $id, false);
    }

    public function reactivate(Request $request, string $type, int $id): RedirectResponse
    {
        return $this->setActive($request, $type, $id, true);
    }

    private function setActive(Request $request, string $type, int $id, bool $active): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'reference_data.manage');
        $config = $this->config($type);

        $item = $this->find($config, $id);
        $item->is_active = $active;
        $item->updated_by = $request->user()->user_id;
        $item->save();

        $this->auditService->record($request->user(), $config['entity_name'], $item->getKey(), 'UPDATE', ['is_active' => ! $active], ['is_active' => $active]);

        return redirect()->route('reference-data.index', $type)->with('status', $active ? 'Entry reactivated.' : 'Entry deactivated.');
    }

    private function find(array $config, int $id): Model
    {
        $item = $config['model']::query()->find($id);

        if (! $item) {
            throw new NotFoundHttpException;
        }

        return $item;
    }

    /**
     * @return array{model: class-string<Model>, label: string, entity_name: string, name_column: string, table: string, key: string, columns: array<int, array{field: string, label: string, type: string}>}
     */
    private function config(string $type): array
    {
        $configs = [
            'departments' => [
                'model' => Department::class,
                'label' => 'Department',
                'entity_name' => 'DEPARTMENT',
                'name_column' => 'department_name',
                'table' => 'departments',
                'key' => 'department_id',
                'columns' => [
                    ['field' => 'department_code', 'label' => 'Code', 'type' => 'text'],
                    ['field' => 'department_name', 'label' => 'Name', 'type' => 'text'],
                ],
            ],
            'positions' => [
                'model' => Position::class,
                'label' => 'Position',
                'entity_name' => 'POSITION',
                'name_column' => 'position_title',
                'table' => 'positions',
                'key' => 'position_id',
                'columns' => [
                    ['field' => 'position_code', 'label' => 'Code', 'type' => 'text'],
                    ['field' => 'position_title', 'label' => 'Title', 'type' => 'text'],
                ],
            ],
            'employment-statuses' => [
                'model' => EmploymentStatus::class,
                'label' => 'Employment status',
                'entity_name' => 'EMPLOYMENT_STATUS',
                'name_column' => 'status_name',
                'table' => 'employment_statuses',
                'key' => 'employment_status_id',
                'columns' => [
                    ['field' => 'status_name', 'label' => 'Name', 'type' => 'text'],
                    // 'boolean_required' renders as a Yes/No radio pair in
                    // the form so the value is always submitted explicitly
                    // (AC-0.4.3-equivalent) — a checkbox left unchecked
                    // submits nothing at all, which is exactly the silent
                    // default this type of flag must not have.
                    ['field' => 'is_payroll_eligible', 'label' => 'Payroll-eligible', 'type' => 'boolean_required'],
                ],
            ],
            'earning-types' => [
                'model' => EarningType::class,
                'label' => 'Earning type',
                'entity_name' => 'EARNING_TYPE',
                'name_column' => 'earning_name',
                'table' => 'earning_types',
                'key' => 'earning_type_id',
                'columns' => [
                    ['field' => 'earning_code', 'label' => 'Code', 'type' => 'text'],
                    ['field' => 'earning_name', 'label' => 'Name', 'type' => 'text'],
                    // AC-0.4.3 — "every earning type carries an explicit
                    // taxability flag; no earning type defaults silently."
                    ['field' => 'is_taxable', 'label' => 'Taxable', 'type' => 'boolean_required'],
                    ['field' => 'is_thirteenth_month_base', 'label' => '13th-month base', 'type' => 'boolean_required'],
                    ['field' => 'is_recurring_allowed', 'label' => 'Recurring allowed', 'type' => 'boolean'],
                ],
            ],
            'deduction-types' => [
                'model' => DeductionType::class,
                'label' => 'Deduction type',
                'entity_name' => 'DEDUCTION_TYPE',
                'name_column' => 'deduction_name',
                'table' => 'deduction_types',
                'key' => 'deduction_type_id',
                'columns' => [
                    ['field' => 'deduction_code', 'label' => 'Code', 'type' => 'text'],
                    ['field' => 'deduction_name', 'label' => 'Name', 'type' => 'text'],
                    ['field' => 'is_statutory', 'label' => 'Statutory', 'type' => 'boolean_required'],
                    ['field' => 'statutory_agency', 'label' => 'Agency', 'type' => 'text'],
                    ['field' => 'participates_in_floor_check', 'label' => 'Net-pay floor check', 'type' => 'boolean'],
                ],
            ],
            'leave-types' => [
                'model' => LeaveType::class,
                'label' => 'Leave type',
                'entity_name' => 'LEAVE_TYPE',
                'name_column' => 'leave_name',
                'table' => 'leave_types',
                'key' => 'leave_type_id',
                'columns' => [
                    ['field' => 'leave_code', 'label' => 'Code', 'type' => 'text'],
                    ['field' => 'leave_name', 'label' => 'Name', 'type' => 'text'],
                    ['field' => 'is_paid', 'label' => 'Paid', 'type' => 'boolean_required'],
                    ['field' => 'annual_credits', 'label' => 'Annual credits', 'type' => 'text'],
                    ['field' => 'allows_negative_balance', 'label' => 'Allows negative balance', 'type' => 'boolean'],
                    ['field' => 'excludes_rest_days', 'label' => 'Excludes rest days', 'type' => 'boolean'],
                    ['field' => 'carryover_rule', 'label' => 'Carry-over rule', 'type' => 'text'],
                ],
            ],
            'attendance-types' => [
                'model' => AttendanceType::class,
                'label' => 'Attendance type',
                'entity_name' => 'ATTENDANCE_TYPE',
                'name_column' => 'attendance_name',
                'table' => 'attendance_types',
                'key' => 'attendance_type_id',
                'columns' => [
                    ['field' => 'attendance_code', 'label' => 'Code', 'type' => 'text'],
                    ['field' => 'attendance_name', 'label' => 'Name', 'type' => 'text'],
                    ['field' => 'counts_as_worked', 'label' => 'Counts as worked', 'type' => 'boolean_required'],
                    ['field' => 'requires_punches', 'label' => 'Requires punches', 'type' => 'boolean_required'],
                ],
            ],
        ];

        if (! isset($configs[$type])) {
            throw new NotFoundHttpException("Unknown reference list '{$type}'.");
        }

        return $configs[$type];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(string $type, ?int $ignoreId = null): array
    {
        return match ($type) {
            'departments' => [
                'department_code' => ['required', 'string', 'max:50', Rule::unique('departments', 'department_code')->ignore($ignoreId, 'department_id')],
                'department_name' => ['required', 'string', 'max:255'],
            ],
            'positions' => [
                'position_code' => ['required', 'string', 'max:50', Rule::unique('positions', 'position_code')->ignore($ignoreId, 'position_id')],
                'position_title' => ['required', 'string', 'max:255'],
            ],
            'employment-statuses' => [
                'status_name' => ['required', 'string', 'max:255', Rule::unique('employment_statuses', 'status_name')->ignore($ignoreId, 'employment_status_id')],
                // AC-0.4.3-equivalent for this list: the flag is required, never left at a column default.
                'is_payroll_eligible' => ['required', 'boolean'],
            ],
            'earning-types' => [
                'earning_code' => ['required', 'string', 'max:50', Rule::unique('earning_types', 'earning_code')->ignore($ignoreId, 'earning_type_id')],
                'earning_name' => ['required', 'string', 'max:255'],
                // AC-0.4.3 — required, no silent default.
                'is_taxable' => ['required', 'boolean'],
                'is_thirteenth_month_base' => ['required', 'boolean'],
                'is_recurring_allowed' => ['sometimes', 'boolean'],
            ],
            'deduction-types' => [
                'deduction_code' => ['required', 'string', 'max:50', Rule::unique('deduction_types', 'deduction_code')->ignore($ignoreId, 'deduction_type_id')],
                'deduction_name' => ['required', 'string', 'max:255'],
                'is_statutory' => ['required', 'boolean'],
                'statutory_agency' => ['nullable', 'string', 'max:255'],
                'participates_in_floor_check' => ['sometimes', 'boolean'],
            ],
            'leave-types' => [
                'leave_code' => ['required', 'string', 'max:50', Rule::unique('leave_types', 'leave_code')->ignore($ignoreId, 'leave_type_id')],
                'leave_name' => ['required', 'string', 'max:255'],
                'is_paid' => ['required', 'boolean'],
                'annual_credits' => ['required', 'numeric', 'min:0'],
                'allows_negative_balance' => ['sometimes', 'boolean'],
                'excludes_rest_days' => ['sometimes', 'boolean'],
                'carryover_rule' => ['nullable', 'string', 'max:255'],
            ],
            'attendance-types' => [
                'attendance_code' => ['required', 'string', 'max:50', Rule::unique('attendance_types', 'attendance_code')->ignore($ignoreId, 'attendance_type_id')],
                'attendance_name' => ['required', 'string', 'max:255'],
                'counts_as_worked' => ['required', 'boolean'],
                'requires_punches' => ['required', 'boolean'],
            ],
            default => throw new NotFoundHttpException("Unknown reference list '{$type}'."),
        };
    }

    /**
     * Checkbox fields absent from the request mean "false", not "leave
     * unset" — HTML forms don't submit an unchecked checkbox at all.
     * Fields marked 'required'/'boolean' in rules() must still resolve to
     * an explicit true/false here (AC-0.4.3); 'sometimes' fields simply
     * default to false when omitted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeBooleans(string $type, array $data): array
    {
        $booleanFields = match ($type) {
            'employment-statuses' => ['is_payroll_eligible'],
            'earning-types' => ['is_taxable', 'is_thirteenth_month_base', 'is_recurring_allowed'],
            'deduction-types' => ['is_statutory', 'participates_in_floor_check'],
            'leave-types' => ['is_paid', 'allows_negative_balance', 'excludes_rest_days'],
            'attendance-types' => ['counts_as_worked', 'requires_punches'],
            default => [],
        };

        foreach ($booleanFields as $field) {
            $data[$field] = (bool) ($data[$field] ?? false);
        }

        return $data;
    }
}
