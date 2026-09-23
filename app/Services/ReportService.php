<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OrganizationProfile;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// UC-30 · Generate report — FR-5.3, BR-14, BR-20.
// Complete 11-report catalogue outputs from stored payroll and HR data.
// Calls StatutoryScheduleService to derive employer shares where absent (AC-2.3.4, OI-13).
// Watermarks provisional if underlying run is not finalized (AC-5.3.5).
class ReportService
{
    public function __construct(
        private readonly StatutoryScheduleService $statutoryScheduleService,
    ) {}

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
     *     implemented: bool,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
                'implemented' => true,
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
     * 1. Payroll Register Report.
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
                'earningLines.earningType',
                'deductionLines.deductionType',
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

        foreach ($lines as $line) {
            $totGross = bcadd($totGross, (string) $line->gross_pay, 2);
            $totDeductions = bcadd($totDeductions, (string) $line->total_deductions, 2);
            $totNet = bcadd($totNet, (string) $line->net_pay, 2);
            $totDays = bcadd($totDays, (string) $line->days_worked, 2);
        }

        return [
            'meta' => $this->getReportMeta('payroll_register'),
            'run' => $run,
            'period' => $run->period,
            'lines' => $lines,
            'is_provisional' => $run->run_status !== 'FINALIZED',
            'totals' => [
                'employee_count' => $lines->count(),
                'gross_pay' => $totGross,
                'total_deductions' => $totDeductions,
                'net_pay' => $totNet,
                'days_worked' => $totDays,
            ],
            'parameters' => $this->describeParameters($params, $run),
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * 2. Payroll Summary Report.
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
                $name = $el->earningType?->earning_name ?? 'Basic Pay';
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

        return [
            'meta' => $this->getReportMeta('payroll_summary'),
            'run' => $run,
            'period' => $run->period,
            'department_summaries' => array_values($departmentSummaries),
            'earning_totals' => $earningCategoryTotals,
            'deduction_totals' => $deductionCategoryTotals,
            'is_provisional' => $run->run_status !== 'FINALIZED',
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
     * 3. SSS Remittance Report (FR-5.3, AC-2.3.4, AC-5.3.2).
     */
    public function generateSssRemittance(array $params): array
    {
        return $this->generateStatutoryRemittanceReport('SSS', 'sss_remittance', $params);
    }

    /**
     * 4. PhilHealth Remittance Report (FR-5.3, AC-2.3.4, AC-5.3.2).
     */
    public function generatePhilhealthRemittance(array $params): array
    {
        return $this->generateStatutoryRemittanceReport('PHILHEALTH', 'philhealth_remittance', $params);
    }

    /**
     * 5. Pag-IBIG Remittance Report (FR-5.3, AC-2.3.4, AC-5.3.2).
     */
    public function generatePagibigRemittance(array $params): array
    {
        return $this->generateStatutoryRemittanceReport('PAGIBIG', 'pagibig_remittance', $params);
    }

    /**
     * Helper for Statutory Remittance Reports (SSS, PhilHealth, Pag-IBIG).
     */
    protected function generateStatutoryRemittanceReport(string $agency, string $slug, array $params): array
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

        $allLines = $linesQuery->get();
        $payDate = $run->period->pay_date->format('Y-m-d');

        $rows = [];
        $totEe = '0.00';
        $totEr = '0.00';
        $totTotal = '0.00';

        foreach ($allLines as $line) {
            $emp = $line->employee;

            // Find agency deduction line
            $agencyDl = $line->deductionLines->first(function ($dl) use ($agency) {
                $code = strtoupper($dl->deductionType?->deduction_code ?? '');
                $name = strtoupper($dl->deductionType?->deduction_name ?? '');

                return match ($agency) {
                    'SSS' => str_contains($code, 'SSS') || str_contains($name, 'SSS'),
                    'PHILHEALTH' => str_contains($code, 'PHILHEALTH') || str_contains($code, 'PHIC') || str_contains($name, 'PHILHEALTH'),
                    'PAGIBIG' => str_contains($code, 'PAGIBIG') || str_contains($code, 'HDMF') || str_contains($name, 'PAG-IBIG') || str_contains($name, 'PAGIBIG'),
                    default => false,
                };
            });

            // Employee covered if has deduction or has government agency ID number
            $idNumber = match ($agency) {
                'SSS' => $emp?->sss_no,
                'PHILHEALTH' => $emp?->philhealth_no,
                'PAGIBIG' => $emp?->pagibig_no,
                default => null,
            };

            if (! $agencyDl && empty($idNumber)) {
                continue; // skip un-covered employees per AC-5.3.4
            }

            $eeShare = $agencyDl ? (string) ($agencyDl->employee_share ?? $agencyDl->amount) : '0.00';

            // Derive employer share
            $erResult = $this->statutoryScheduleService->deriveEmployerShare($agency, $line, $payDate);
            $erShare = $erResult['amount'];
            $totalContribution = bcadd($eeShare, $erShare, 2);

            $totEe = bcadd($totEe, $eeShare, 2);
            $totEr = bcadd($totEr, $erShare, 2);
            $totTotal = bcadd($totTotal, $totalContribution, 2);

            $rows[] = [
                'employee_no' => $emp?->employee_no ?? '—',
                'employee_name' => $emp?->fullName() ?? '—',
                'id_number' => $idNumber ?? 'MISSING',
                'department' => $emp?->currentEmploymentDetail?->department?->department_name ?? '—',
                'employee_share' => $eeShare,
                'employer_share' => $erShare,
                'total_contribution' => $totalContribution,
                'source' => $erResult['source'],
                'schedule_version' => $erResult['schedule_version'],
            ];
        }

        $org = OrganizationProfile::first();
        $employerAgencyId = match ($agency) {
            'SSS' => $org?->sss_employer_no,
            'PHILHEALTH' => $org?->philhealth_employer_no,
            'PAGIBIG' => $org?->pagibig_employer_no,
            default => null,
        };

        return [
            'meta' => $this->getReportMeta($slug),
            'run' => $run,
            'period' => $run->period,
            'agency' => $agency,
            'employer_agency_id' => $employerAgencyId,
            'rows' => $rows,
            'is_provisional' => $run->run_status !== 'FINALIZED',
            'totals' => [
                'employee_count' => count($rows),
                'employee_share' => $totEe,
                'employer_share' => $totEr,
                'total_contribution' => $totTotal,
            ],
            'parameters' => $this->describeParameters($params, $run),
            'org' => $org,
        ];
    }

    /**
     * 6. Withholding Tax Report (BIR).
     */
    public function generateWithholdingTax(array $params): array
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
                'earningLines',
                'deductionLines.deductionType',
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

        $rows = [];
        $totTaxable = '0.00';
        $totWithheld = '0.00';

        foreach ($lines as $line) {
            $emp = $line->employee;

            // Compute taxable compensation: sum of earning lines flagged is_taxable
            $taxableSum = '0.00';
            foreach ($line->earningLines as $el) {
                if ($el->is_taxable) {
                    $taxableSum = bcadd($taxableSum, (string) $el->amount, 2);
                }
            }

            // Find WTAX deduction
            $wtaxDl = $line->deductionLines->first(function ($dl) {
                $code = strtoupper($dl->deductionType?->deduction_code ?? '');
                $name = strtoupper($dl->deductionType?->deduction_name ?? '');

                return str_contains($code, 'WTAX') || str_contains($code, 'BIR') || str_contains($name, 'WITHHOLDING');
            });

            $taxWithheld = $wtaxDl ? (string) $wtaxDl->amount : '0.00';

            $totTaxable = bcadd($totTaxable, $taxableSum, 2);
            $totWithheld = bcadd($totWithheld, $taxWithheld, 2);

            $rows[] = [
                'employee_no' => $emp?->employee_no ?? '—',
                'employee_name' => $emp?->fullName() ?? '—',
                'tin_no' => $emp?->tin_no ?? 'MISSING',
                'department' => $emp?->currentEmploymentDetail?->department?->department_name ?? '—',
                'taxable_compensation' => $taxableSum,
                'tax_withheld' => $taxWithheld,
            ];
        }

        $org = OrganizationProfile::first();

        return [
            'meta' => $this->getReportMeta('withholding_tax'),
            'run' => $run,
            'period' => $run->period,
            'employer_tin' => $org?->tin_no,
            'rows' => $rows,
            'is_provisional' => $run->run_status !== 'FINALIZED',
            'totals' => [
                'employee_count' => count($rows),
                'taxable_compensation' => $totTaxable,
                'tax_withheld' => $totWithheld,
            ],
            'parameters' => $this->describeParameters($params, $run),
            'org' => $org,
        ];
    }

    /**
     * 7. Bank Transmittal Listing.
     */
    public function generateBankTransmittal(array $params): array
    {
        $run = $this->resolveRun($params);
        $currentImport = $run->currentImport();

        if ($currentImport === null) {
            throw new InvalidArgumentException('No accepted register import exists for this payroll run.');
        }

        $linesQuery = $run->lines()
            ->where('payroll_import_id', $currentImport->payroll_import_id)
            ->with(['employee.employmentDetails.department'])
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

        $rows = [];
        $totNet = '0.00';

        foreach ($lines as $line) {
            $emp = $line->employee;
            $net = (string) $line->net_pay;
            $totNet = bcadd($totNet, $net, 2);

            $rows[] = [
                'employee_no' => $emp?->employee_no ?? '—',
                'employee_name' => $emp?->fullName() ?? '—',
                'bank_name' => $emp?->bank_name ?? 'Default Disbursing Bank',
                'bank_account_no' => $emp?->bank_account_no ?? 'CASH / UNRECORDED',
                'net_pay' => $net,
            ];
        }

        return [
            'meta' => $this->getReportMeta('bank_transmittal'),
            'run' => $run,
            'period' => $run->period,
            'rows' => $rows,
            'is_provisional' => $run->run_status !== 'FINALIZED',
            'totals' => [
                'employee_count' => count($rows),
                'net_pay' => $totNet,
            ],
            'parameters' => $this->describeParameters($params, $run),
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * 8. 13th Month Pay Report (FR-5.3, BR-12, OI-14).
     */
    public function generateThirteenthMonth(array $params): array
    {
        $year = (int) ($params['payroll_year'] ?? date('Y'));

        // All active employees
        $empQuery = Employee::query()->where('is_active', true)->with(['currentEmploymentDetail.department']);

        if (! empty($params['department_id'])) {
            $deptId = (int) $params['department_id'];
            $empQuery->whereHas('employmentDetails', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        $employees = $empQuery->orderBy('employee_no')->get();

        // 13th month run for this year (if imported)
        $thirteenthRun = PayrollRun::query()
            ->where('run_type', 'THIRTEENTH_MONTH')
            ->whereHas('period', function ($q) use ($year) {
                $q->where('payroll_year', $year);
            })
            ->first();

        $rows = [];
        $totBasicEarned = '0.00';
        $totImported13th = '0.00';

        foreach ($employees as $emp) {
            // Sum basic salary from earning lines across periods of this year flagged for 13th-month base
            $basicEarned = DB::table('payroll_lines')
                ->join('payroll_runs', 'payroll_runs.payroll_run_id', '=', 'payroll_lines.payroll_run_id')
                ->join('payroll_periods', 'payroll_periods.payroll_period_id', '=', 'payroll_runs.payroll_period_id')
                ->join('earning_lines', 'earning_lines.payroll_line_id', '=', 'payroll_lines.payroll_line_id')
                ->where('payroll_lines.employee_id', $emp->employee_id)
                ->where('payroll_periods.payroll_year', $year)
                ->where('earning_lines.is_taxable', true) // BR-12 base
                ->where('payroll_runs.run_status', 'FINALIZED')
                ->sum('earning_lines.amount');

            $basicEarnedStr = number_format((float) ($basicEarned ?? 0.0), 2, '.', '');

            // Figure from imported 13th month run (if exists)
            $imported13th = '0.00';
            if ($thirteenthRun) {
                $thirteenthLine = $thirteenthRun->lines()
                    ->where('employee_id', $emp->employee_id)
                    ->first();
                if ($thirteenthLine) {
                    $imported13th = (string) $thirteenthLine->gross_pay;
                }
            }

            $totBasicEarned = bcadd($totBasicEarned, $basicEarnedStr, 2);
            $totImported13th = bcadd($totImported13th, $imported13th, 2);

            $rows[] = [
                'employee_no' => $emp->employee_no,
                'employee_name' => $emp->fullName(),
                'department' => $emp->currentEmploymentDetail?->department?->department_name ?? '—',
                'basic_salary_earned' => $basicEarnedStr,
                'imported_13th_month' => $imported13th,
            ];
        }

        return [
            'meta' => $this->getReportMeta('thirteenth_month'),
            'payroll_year' => $year,
            'rows' => $rows,
            'is_provisional' => false,
            'totals' => [
                'employee_count' => count($rows),
                'basic_salary_earned' => $totBasicEarned,
                'imported_13th_month' => $totImported13th,
            ],
            'parameters' => [
                'Calendar Year' => (string) $year,
                'Department' => ! empty($params['department_id']) ? Department::find($params['department_id'])?->department_name : 'All Departments',
            ],
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * 9. Leave Ledger Report (FR-5.3, Should).
     */
    public function generateLeaveLedger(array $params): array
    {
        $year = (int) ($params['payroll_year'] ?? date('Y'));

        $query = DB::table('leave_balances')
            ->join('employees', 'employees.employee_id', '=', 'leave_balances.employee_id')
            ->join('leave_types', 'leave_types.leave_type_id', '=', 'leave_balances.leave_type_id')
            ->where('leave_balances.payroll_year', $year)
            ->select([
                'employees.employee_no',
                'employees.last_name',
                'employees.first_name',
                'leave_types.leave_name',
                'leave_balances.credits_carried_over',
                'leave_balances.credits_earned',
                'leave_balances.credits_used',
                'leave_balances.balance_remaining',
            ])
            ->orderBy('employees.employee_no')
            ->orderBy('leave_types.leave_name');

        if (! empty($params['employee_id'])) {
            $query->where('leave_balances.employee_id', (int) $params['employee_id']);
        }

        $records = $query->get();

        $rows = [];
        $totEarned = 0.0;
        $totUsed = 0.0;
        $totRemaining = 0.0;

        foreach ($records as $r) {
            $earned = (float) $r->credits_earned + (float) $r->credits_carried_over;
            $used = (float) $r->credits_used;
            $remaining = (float) $r->balance_remaining;

            $totEarned += $earned;
            $totUsed += $used;
            $totRemaining += $remaining;

            $rows[] = [
                'employee_no' => $r->employee_no,
                'employee_name' => "{$r->last_name}, {$r->first_name}",
                'leave_type' => $r->leave_name,
                'credits_earned' => number_format($earned, 2),
                'credits_used' => number_format($used, 2),
                'balance_remaining' => number_format($remaining, 2),
            ];
        }

        return [
            'meta' => $this->getReportMeta('leave_ledger'),
            'payroll_year' => $year,
            'rows' => $rows,
            'is_provisional' => false,
            'totals' => [
                'employee_count' => count($rows),
                'credits_earned' => number_format($totEarned, 2),
                'credits_used' => number_format($totUsed, 2),
                'balance_remaining' => number_format($totRemaining, 2),
            ],
            'parameters' => [
                'Year' => (string) $year,
                'Employee' => ! empty($params['employee_id']) ? Employee::find($params['employee_id'])?->fullName() : 'All Employees',
            ],
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * 10. Loan Ledger Report (FR-5.3, Should).
     */
    public function generateLoanLedger(array $params): array
    {
        $query = DB::table('loan_accounts')
            ->join('employees', 'employees.employee_id', '=', 'loan_accounts.employee_id')
            ->join('deduction_types', 'deduction_types.deduction_type_id', '=', 'loan_accounts.deduction_type_id')
            ->select([
                'employees.employee_no',
                'employees.last_name',
                'employees.first_name',
                'deduction_types.deduction_name as loan_type',
                'loan_accounts.loan_reference',
                'loan_accounts.principal_amount',
                'loan_accounts.amortization_amount',
                'loan_accounts.outstanding_balance',
                'loan_accounts.loan_status',
            ])
            ->orderBy('employees.employee_no');

        if (! empty($params['employee_id'])) {
            $query->where('loan_accounts.employee_id', (int) $params['employee_id']);
        }

        $records = $query->get();

        $rows = [];
        $totPrincipal = '0.00';
        $totAmortization = '0.00';
        $totOutstanding = '0.00';

        foreach ($records as $r) {
            $principal = (string) $r->principal_amount;
            $amortization = (string) $r->amortization_amount;
            $outstanding = (string) $r->outstanding_balance;
            $deducted = bcsub($principal, $outstanding, 2);

            $totPrincipal = bcadd($totPrincipal, $principal, 2);
            $totAmortization = bcadd($totAmortization, $amortization, 2);
            $totOutstanding = bcadd($totOutstanding, $outstanding, 2);

            $rows[] = [
                'employee_no' => $r->employee_no,
                'employee_name' => "{$r->last_name}, {$r->first_name}",
                'loan_type' => $r->loan_type,
                'loan_reference' => $r->loan_reference,
                'principal' => $principal,
                'amortization' => $amortization,
                'total_deducted' => $deducted,
                'outstanding_balance' => $outstanding,
                'status' => $r->loan_status,
            ];
        }

        return [
            'meta' => $this->getReportMeta('loan_ledger'),
            'rows' => $rows,
            'is_provisional' => false,
            'totals' => [
                'count' => count($rows),
                'principal' => $totPrincipal,
                'amortization' => $totAmortization,
                'outstanding_balance' => $totOutstanding,
            ],
            'parameters' => [
                'Employee' => ! empty($params['employee_id']) ? Employee::find($params['employee_id'])?->fullName() : 'All Employees',
            ],
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * 11. Payroll Cost Comparison Report (FR-5.3, Could).
     */
    public function generateCostComparison(array $params): array
    {
        $run = $this->resolveRun($params);
        $currentPeriod = $run->period;

        // Find prior period chronologically
        $priorPeriod = PayrollPeriod::query()
            ->where('cutoff_end', '<', $currentPeriod->cutoff_start)
            ->orderByDesc('cutoff_end')
            ->first();

        $priorRun = null;
        if ($priorPeriod) {
            $priorRun = PayrollRun::query()
                ->where('payroll_period_id', $priorPeriod->payroll_period_id)
                ->orderByDesc('payroll_run_id')
                ->first();
        }

        // Aggregate current run by department
        $currentDeptData = $this->aggregateRunByDepartment($run);
        $priorDeptData = $priorRun ? $this->aggregateRunByDepartment($priorRun) : [];

        $allDepts = array_unique(array_merge(array_keys($currentDeptData), array_keys($priorDeptData)));
        sort($allDepts);

        $rows = [];
        $currTotGross = '0.00';
        $priorTotGross = '0.00';
        $currTotNet = '0.00';
        $priorTotNet = '0.00';

        foreach ($allDepts as $dept) {
            $c = $currentDeptData[$dept] ?? ['headcount' => 0, 'gross' => '0.00', 'net' => '0.00'];
            $p = $priorDeptData[$dept] ?? ['headcount' => 0, 'gross' => '0.00', 'net' => '0.00'];

            $grossDiff = bcsub($c['gross'], $p['gross'], 2);
            $netDiff = bcsub($c['net'], $p['net'], 2);

            $grossPct = (float) $p['gross'] > 0 ? (((float) $grossDiff / (float) $p['gross']) * 100) : 0.0;

            $currTotGross = bcadd($currTotGross, $c['gross'], 2);
            $priorTotGross = bcadd($priorTotGross, $p['gross'], 2);
            $currTotNet = bcadd($currTotNet, $c['net'], 2);
            $priorTotNet = bcadd($priorTotNet, $p['net'], 2);

            $rows[] = [
                'department' => $dept,
                'current_headcount' => $c['headcount'],
                'prior_headcount' => $p['headcount'],
                'current_gross' => $c['gross'],
                'prior_gross' => $p['gross'],
                'gross_variance' => $grossDiff,
                'gross_variance_pct' => number_format($grossPct, 1),
                'current_net' => $c['net'],
                'prior_net' => $p['net'],
                'net_variance' => $netDiff,
            ];
        }

        $totGrossVariance = bcsub($currTotGross, $priorTotGross, 2);
        $totGrossPct = (float) $priorTotGross > 0 ? (((float) $totGrossVariance / (float) $priorTotGross) * 100) : 0.0;

        return [
            'meta' => $this->getReportMeta('cost_comparison'),
            'run' => $run,
            'current_period' => $currentPeriod,
            'prior_period' => $priorPeriod,
            'rows' => $rows,
            'is_provisional' => $run->run_status !== 'FINALIZED',
            'totals' => [
                'current_gross' => $currTotGross,
                'prior_gross' => $priorTotGross,
                'gross_variance' => $totGrossVariance,
                'gross_variance_pct' => number_format($totGrossPct, 1),
                'current_net' => $currTotNet,
                'prior_net' => $priorTotNet,
                'net_variance' => bcsub($currTotNet, $priorTotNet, 2),
            ],
            'parameters' => [
                'Current Period' => "{$currentPeriod->payroll_year}-{$currentPeriod->period_no}",
                'Comparison Period' => $priorPeriod ? "{$priorPeriod->payroll_year}-{$priorPeriod->period_no}" : 'None available',
            ],
            'org' => OrganizationProfile::first(),
        ];
    }

    /**
     * Aggregate payroll run metrics by department.
     *
     * @return array<string, array{headcount: int, gross: string, net: string}>
     */
    protected function aggregateRunByDepartment(PayrollRun $run): array
    {
        $currentImport = $run->currentImport();
        if (! $currentImport) {
            return [];
        }

        $lines = $run->lines()
            ->where('payroll_import_id', $currentImport->payroll_import_id)
            ->with(['employee.employmentDetails.department'])
            ->get();

        $data = [];
        foreach ($lines as $l) {
            $dept = $l->employee->currentEmploymentDetail?->department?->department_name ?? 'Unassigned';
            if (! isset($data[$dept])) {
                $data[$dept] = ['headcount' => 0, 'gross' => '0.00', 'net' => '0.00'];
            }
            $data[$dept]['headcount']++;
            $data[$dept]['gross'] = bcadd($data[$dept]['gross'], (string) $l->gross_pay, 2);
            $data[$dept]['net'] = bcadd($data[$dept]['net'], (string) $l->net_pay, 2);
        }

        return $data;
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
