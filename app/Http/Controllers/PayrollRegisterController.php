<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmploymentStatus;
use App\Models\PayrollRun;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// UC-21 · Review payroll register — FR-4.2.
// Preconditions: The run has an accepted current payroll-register import.
// Actors: Payroll Officer, Approver, Viewer ('payroll_records.search').
class PayrollRegisterController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
    ) {}

    public function show(Request $request, PayrollRun $payrollRun): View|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $currentImport = $payrollRun->currentImport();
        if ($currentImport === null) {
            return redirect()->route('payroll-runs.show', $payrollRun)
                ->withErrors(['register' => 'UC-21 E1: No accepted register import exists for this run. Please import a computed register first (UC-18).']);
        }

        $payrollRun->load(['period', 'reversalRecord']);

        // Load lines with relations
        $linesQuery = $payrollRun->lines()
            ->where('payroll_import_id', $currentImport->payroll_import_id)
            ->with([
                'employee.employmentDetails.department',
                'employee.employmentDetails.employmentStatus',
                'earningLines.earningType',
                'deductionLines.deductionType',
                'compensationProfile',
            ]);

        // Filters: department, status, net range
        if ($request->filled('department_id')) {
            $deptId = (int) $request->input('department_id');
            $linesQuery->whereHas('employee.employmentDetails', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        if ($request->filled('employment_status_id')) {
            $statusId = (int) $request->input('employment_status_id');
            $linesQuery->whereHas('employee.employmentDetails', function ($q) use ($statusId) {
                $q->where('employment_status_id', $statusId);
            });
        }

        if ($request->filled('min_net')) {
            $linesQuery->where('net_pay', '>=', (float) $request->input('min_net'));
        }

        if ($request->filled('max_net')) {
            $linesQuery->where('net_pay', '<=', (float) $request->input('max_net'));
        }

        // Sorting
        $sortCol = $request->input('sort', 'employee_no');
        $sortDir = strtolower($request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortCol === 'employee_no') {
            $linesQuery->join('employees', 'employees.employee_id', '=', 'payroll_lines.employee_id')
                ->select('payroll_lines.*')
                ->orderBy('employees.employee_no', $sortDir);
        } elseif (in_array($sortCol, ['gross_pay', 'total_deductions', 'net_pay', 'days_worked', 'hours_worked'], true)) {
            $linesQuery->orderBy($sortCol, $sortDir);
        } else {
            $linesQuery->orderBy('payroll_line_id', $sortDir);
        }

        $lines = $linesQuery->get();

        // Calculate column totals
        $totGross = '0.00';
        $totDeductions = '0.00';
        $totNet = '0.00';
        $totDays = 0.0;
        $totHours = 0.0;

        foreach ($lines as $line) {
            $totGross = bcadd($totGross, (string) $line->gross_pay, 2);
            $totDeductions = bcadd($totDeductions, (string) $line->total_deductions, 2);
            $totNet = bcadd($totNet, (string) $line->net_pay, 2);
            $totDays += (float) $line->days_worked;
            $totHours += (float) $line->hours_worked;
        }

        // Prior period comparison (AC-4.2.4)
        $previousPeriod = $payrollRun->period->previousPeriod();
        $previousRun = null;
        $previousLinesByEmployee = [];

        if ($previousPeriod !== null) {
            $previousRun = PayrollRun::query()
                ->where('payroll_period_id', $previousPeriod->payroll_period_id)
                ->where('run_type', $payrollRun->run_type)
                ->where('population_scope', $payrollRun->population_scope)
                ->where('run_status', '<>', 'CANCELLED')
                ->first();

            if ($previousRun !== null) {
                $prevImport = $previousRun->currentImport();
                if ($prevImport !== null) {
                    $prevLines = $previousRun->lines()
                        ->where('payroll_import_id', $prevImport->payroll_import_id)
                        ->get();
                    foreach ($prevLines as $pl) {
                        $previousLinesByEmployee[$pl->employee_id] = $pl;
                    }
                }
            }
        }

        return view('payroll-register.show', [
            'run' => $payrollRun,
            'currentImport' => $currentImport,
            'lines' => $lines,
            'totals' => [
                'gross' => $totGross,
                'deductions' => $totDeductions,
                'net' => $totNet,
                'days' => $totDays,
                'hours' => $totHours,
            ],
            'departments' => Department::query()->where('is_active', true)->orderBy('department_name')->get(),
            'employmentStatuses' => EmploymentStatus::query()->where('is_active', true)->orderBy('status_name')->get(),
            'previousPeriod' => $previousPeriod,
            'previousRun' => $previousRun,
            'previousLinesByEmployee' => $previousLinesByEmployee,
            'isProvisional' => $payrollRun->run_status !== 'FINALIZED',
        ]);
    }

    // A1 · Export register to Excel.
    public function export(Request $request, PayrollRun $payrollRun)
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $currentImport = $payrollRun->currentImport();
        if ($currentImport === null) {
            return back()->withErrors(['register' => 'No accepted register import exists to export.']);
        }

        $lines = $payrollRun->lines()
            ->where('payroll_import_id', $currentImport->payroll_import_id)
            ->with(['employee.employmentDetails.department'])
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Register');

        $isProvisional = $payrollRun->run_status !== 'FINALIZED';
        $title = "Payroll Register - Period {$payrollRun->period->payroll_year}-{$payrollRun->period->period_no}".($isProvisional ? ' (PROVISIONAL)' : '');

        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', "Run #{$payrollRun->payroll_run_id} · Status: {$payrollRun->run_status} · Import Version: {$currentImport->version_no}");

        $headers = [
            'Employee No.',
            'Employee Name',
            'Department',
            'Days Worked',
            'Hours Worked',
            'Gross Pay',
            'Total Deductions',
            'Net Pay',
        ];

        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValue([$col++, 4], $header);
        }

        $row = 5;
        $totGross = '0.00';
        $totDeductions = '0.00';
        $totNet = '0.00';

        foreach ($lines as $line) {
            $emp = $line->employee;
            $dept = $emp->employmentDetails->first()?->department?->department_name ?? '—';

            $sheet->setCellValue([1, $row], $emp->employee_no);
            $sheet->setCellValue([2, $row], $emp->fullName());
            $sheet->setCellValue([3, $row], $dept);
            $sheet->setCellValue([4, $row], $line->days_worked);
            $sheet->setCellValue([5, $row], $line->hours_worked);
            $sheet->setCellValue([6, $row], $line->gross_pay);
            $sheet->setCellValue([7, $row], $line->total_deductions);
            $sheet->setCellValue([8, $row], $line->net_pay);

            $totGross = bcadd($totGross, (string) $line->gross_pay, 2);
            $totDeductions = bcadd($totDeductions, (string) $line->total_deductions, 2);
            $totNet = bcadd($totNet, (string) $line->net_pay, 2);
            $row++;
        }

        // Totals row
        $sheet->setCellValue([1, $row], 'TOTALS');
        $sheet->setCellValue([6, $row], $totGross);
        $sheet->setCellValue([7, $row], $totDeductions);
        $sheet->setCellValue([8, $row], $totNet);

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'EXPORT',
            newValues: ['export' => 'payroll_register', 'format' => 'xlsx', 'is_provisional' => $isProvisional],
        );

        $filename = "payroll-register-run-{$payrollRun->payroll_run_id}.xlsx";
        $tempPath = tempnam(sys_get_temp_dir(), 'register');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
