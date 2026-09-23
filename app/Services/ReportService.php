<?php

namespace App\Services;

use App\Models\Department;
use App\Models\OrganizationProfile;
use App\Models\PayrollRun;
use InvalidArgumentException;

// UC-30 · Generate report — FR-5.3, BR-14, BR-20.
// Provides the 11-report catalogue foundation, parameter validation,
// and generation for foundational operational reports (Payroll Register, Payroll Summary).
// Watermarks provisional if underlying run is not finalized (AC-5.3.5).
class ReportService
{
    /**
     * The complete 11-report catalogue as defined in FR-5.3.
     *
     * @return array<string, array{
     *     slug: string,
     *     name: string,
     *     priority: string,
     *     category: string,
     *     description: string,
     *     requires_period: bool,
     *     requires_year: bool,
     *     supports_department: bool,
     *     supports_employee: bool,
     *     implemented_in_w12: bool,
     * }>
     */
    public function catalogue(): array
    {
        return [
            'payroll_register' => [
                'slug' => 'payroll_register',
                'name' => 'Payroll Register',
                'priority' => 'Must',
                'category' => 'Payroll',
                'description' => 'All payroll lines for a period with earnings, deductions, and net pay, with exact reconciliation totals.',
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => true,
            ],
            'payroll_summary' => [
                'slug' => 'payroll_summary',
                'name' => 'Payroll Summary',
                'priority' => 'Must',
                'category' => 'Payroll',
                'description' => 'Totals grouped by department and by earning and deduction category across a pay period.',
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => true,
            ],
            'sss_remittance' => [
                'slug' => 'sss_remittance',
                'name' => 'SSS Remittance Report',
                'priority' => 'Must',
                'category' => 'Statutory',
                'description' => 'Employee and employer contributions per employee for the month, with SSS registration numbers.',
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => false,
            ],
            'philhealth_remittance' => [
                'slug' => 'philhealth_remittance',
                'name' => 'PhilHealth Remittance Report',
                'priority' => 'Must',
                'category' => 'Statutory',
                'description' => 'Employee and employer premiums per employee for the month, with PhilHealth identification numbers.',
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => false,
            ],
            'pagibig_remittance' => [
                'slug' => 'pagibig_remittance',
                'name' => 'Pag-IBIG Remittance Report',
                'priority' => 'Must',
                'category' => 'Statutory',
                'description' => 'Employee and employer contributions per employee for the month, with Pag-IBIG MID numbers.',
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => false,
            ],
            'withholding_tax' => [
                'slug' => 'withholding_tax',
                'name' => 'Withholding Tax Report (BIR)',
                'priority' => 'Must',
                'category' => 'Statutory',
                'description' => 'Taxable compensation and tax withheld per employee for the period with Tax Identification Numbers (TIN).',
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => false,
            ],
            'bank_transmittal' => [
                'slug' => 'bank_transmittal',
                'name' => 'Bank Transmittal Listing',
                'priority' => 'Must',
                'category' => 'Banking',
                'description' => "Employee name, bank account number, and net pay for the period in the client's bank format.",
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => false,
            ],
            'thirteenth_month' => [
                'slug' => 'thirteenth_month',
                'name' => '13th Month Pay Report',
                'priority' => 'Must',
                'category' => 'Payroll',
                'description' => 'Annual basic salary earned from imported lines flagged for 13th-month base beside 13th-month run figures.',
                'requires_period' => false,
                'requires_year' => true,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => false,
            ],
            'leave_ledger' => [
                'slug' => 'leave_ledger',
                'name' => 'Leave Ledger',
                'priority' => 'Should',
                'category' => 'Human Resources',
                'description' => 'Credits earned, used, and remaining per employee per leave type across the calendar year.',
                'requires_period' => false,
                'requires_year' => true,
                'supports_department' => true,
                'supports_employee' => true,
                'implemented_in_w12' => false,
            ],
            'loan_ledger' => [
                'slug' => 'loan_ledger',
                'name' => 'Loan Ledger',
                'priority' => 'Should',
                'category' => 'Loans & Deductions',
                'description' => 'Principal, amortization, total deducted to date, and remaining balance per employee.',
                'requires_period' => false,
                'requires_year' => true,
                'supports_department' => true,
                'supports_employee' => true,
                'implemented_in_w12' => false,
            ],
            'cost_comparison' => [
                'slug' => 'cost_comparison',
                'name' => 'Payroll Cost Comparison',
                'priority' => 'Could',
                'category' => 'Management Analytics',
                'description' => 'Period-over-period payroll expenditure movement and variance by department.',
                'requires_period' => true,
                'requires_year' => false,
                'supports_department' => true,
                'supports_employee' => false,
                'implemented_in_w12' => false,
            ],
        ];
    }

    /**
     * Get catalogue metadata for a single report.
     *
     * @return array<string, mixed>
     */
    public function getReportMeta(string $slug): array
    {
        $catalogue = $this->catalogue();
        if (! isset($catalogue[$slug])) {
            throw new InvalidArgumentException("Report '{$slug}' is not in the report catalogue.");
        }

        return $catalogue[$slug];
    }

    /**
     * Generate data for the Payroll Register report.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function generatePayrollRegister(array $params): array
    {
        $run = $this->resolveRun($params);
        $currentImport = $run->currentImport();

        if ($currentImport === null) {
            throw new InvalidArgumentException('No accepted register import exists for this payroll run.');
        }

        $linesQuery = $run->lines()
            ->where('payroll_import_id', $currentImport->payroll_import_id)
            ->with([
                'employee.employmentDetails.department',
                'employee.employmentDetails.position',
                'employee.employmentDetails.employmentStatus',
                'earningLines.earningType',
                'deductionLines.deductionType',
                'compensationProfile',
            ])
            ->join('employees', 'employees.employee_id', '=', 'payroll_lines.employee_id')
            ->select('payroll_lines.*')
            ->orderBy('employees.employee_no');

        if (! empty($params['department_id'])) {
            $deptId = (int) $params['department_id'];
            $linesQuery->whereHas('employee.employmentDetails', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        $lines = $linesQuery->get();

        $totGross = '0.00';
        $totDeductions = '0.00';
        $totNet = '0.00';
        $totDays = '0.00';
        $totHours = '0.00';

        foreach ($lines as $line) {
            $totGross = bcadd($totGross, (string) $line->gross_pay, 2);
            $totDeductions = bcadd($totDeductions, (string) $line->total_deductions, 2);
            $totNet = bcadd($totNet, (string) $line->net_pay, 2);
            $totDays = bcadd($totDays, (string) $line->days_worked, 2);
            $totHours = bcadd($totHours, (string) $line->hours_worked, 2);
        }

        $isProvisional = $run->run_status !== 'FINALIZED';

        return [
            'meta' => $this->getReportMeta('payroll_register'),
            'run' => $run,
            'period' => $run->period,
            'lines' => $lines,
            'is_provisional' => $isProvisional,
            'totals' => [
                'employee_count' => $lines->count(),
                'gross_pay' => $totGross,
                'total_deductions' => $totDeductions,
                'net_pay' => $totNet,
                'days_worked' => $totDays,
                'hours_worked' => $totHours,
            ],
            'parameters' => $this->describeParameters($params, $run),
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * Generate data for the Payroll Summary report.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function generatePayrollSummary(array $params): array
    {
        $run = $this->resolveRun($params);
        $currentImport = $run->currentImport();

        if ($currentImport === null) {
            throw new InvalidArgumentException('No accepted register import exists for this payroll run.');
        }

        $linesQuery = $run->lines()
            ->where('payroll_import_id', $currentImport->payroll_import_id)
            ->with([
                'employee.employmentDetails.department',
                'earningLines.earningType',
                'deductionLines.deductionType',
            ]);

        if (! empty($params['department_id'])) {
            $deptId = (int) $params['department_id'];
            $linesQuery->whereHas('employee.employmentDetails', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        $lines = $linesQuery->get();

        // Department breakdown
        $departmentSummaries = [];
        $earningCategoryTotals = [];
        $deductionCategoryTotals = [];

        $grandGross = '0.00';
        $grandDeductions = '0.00';
        $grandNet = '0.00';

        foreach ($lines as $line) {
            $dept = $line->employee->currentEmploymentDetail?->department?->department_name ?? 'Unassigned';

            if (! isset($departmentSummaries[$dept])) {
                $departmentSummaries[$dept] = [
                    'department' => $dept,
                    'headcount' => 0,
                    'gross_pay' => '0.00',
                    'total_deductions' => '0.00',
                    'net_pay' => '0.00',
                ];
            }

            $departmentSummaries[$dept]['headcount']++;
            $departmentSummaries[$dept]['gross_pay'] = bcadd($departmentSummaries[$dept]['gross_pay'], (string) $line->gross_pay, 2);
            $departmentSummaries[$dept]['total_deductions'] = bcadd($departmentSummaries[$dept]['total_deductions'], (string) $line->total_deductions, 2);
            $departmentSummaries[$dept]['net_pay'] = bcadd($departmentSummaries[$dept]['net_pay'], (string) $line->net_pay, 2);

            $grandGross = bcadd($grandGross, (string) $line->gross_pay, 2);
            $grandDeductions = bcadd($grandDeductions, (string) $line->total_deductions, 2);
            $grandNet = bcadd($grandNet, (string) $line->net_pay, 2);

            foreach ($line->earningLines as $el) {
                $name = $el->earningType?->earning_name ?? 'Basic / Other Earning';
                $earningCategoryTotals[$name] = bcadd($earningCategoryTotals[$name] ?? '0.00', (string) $el->amount, 2);
            }

            foreach ($line->deductionLines as $dl) {
                $name = $dl->deductionType?->deduction_name ?? 'Other Deduction';
                $deductionCategoryTotals[$name] = bcadd($deductionCategoryTotals[$name] ?? '0.00', (string) $dl->amount, 2);
            }
        }

        ksort($departmentSummaries);
        ksort($earningCategoryTotals);
        ksort($deductionCategoryTotals);

        $isProvisional = $run->run_status !== 'FINALIZED';

        return [
            'meta' => $this->getReportMeta('payroll_summary'),
            'run' => $run,
            'period' => $run->period,
            'department_summaries' => array_values($departmentSummaries),
            'earning_totals' => $earningCategoryTotals,
            'deduction_totals' => $deductionCategoryTotals,
            'is_provisional' => $isProvisional,
            'totals' => [
                'employee_count' => $lines->count(),
                'gross_pay' => $grandGross,
                'total_deductions' => $grandDeductions,
                'net_pay' => $grandNet,
            ],
            'parameters' => $this->describeParameters($params, $run),
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * Resolve the targeted PayrollRun from parameters.
     */
    private function resolveRun(array $params): PayrollRun
    {
        if (! empty($params['payroll_run_id'])) {
            $run = PayrollRun::with(['period'])->find($params['payroll_run_id']);
            if ($run) {
                return $run;
            }
        }

        if (! empty($params['payroll_period_id'])) {
            $run = PayrollRun::with(['period'])
                ->where('payroll_period_id', (int) $params['payroll_period_id'])
                ->orderByDesc('payroll_run_id')
                ->first();

            if ($run) {
                return $run;
            }
        }

        throw new InvalidArgumentException('Please select a valid pay period or payroll run.');
    }

    /**
     * Describe parameters for display on report headers.
     *
     * @return array<string, string>
     */
    private function describeParameters(array $params, PayrollRun $run): array
    {
        $desc = [
            'Period' => "{$run->period->payroll_year}-{$run->period->period_no} ({$run->period->cutoff_start->toDateString()} to {$run->period->cutoff_end->toDateString()})",
            'Run ID' => "#{$run->payroll_run_id} ({$run->run_status})",
        ];

        if (! empty($params['department_id'])) {
            $dept = Department::find($params['department_id']);
            $desc['Department'] = $dept ? $dept->department_name : "#{$params['department_id']}";
        } else {
            $desc['Department'] = 'All Departments';
        }

        return $desc;
    }
}
