<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

// UC-30 · Generate report — FR-5.3, BR-14, BR-20.
// All 11 catalogue reports exportable to PDF and Excel (AC-5.3.1, AC-5.3.3).
class ReportController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly ReportService $reportService,
    ) {}

    /**
     * Display the 11-report catalogue.
     */
    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'reports.generate');

        $catalogue = $this->reportService->catalogue();

        $grouped = [];
        foreach ($catalogue as $item) {
            $grouped[$item['category']][] = $item;
        }

        return view('reports.index', [
            'catalogue' => $catalogue,
            'groupedCatalogue' => $grouped,
        ]);
    }

    /**
     * Show parameter selection form for a specific report.
     */
    public function show(Request $request, string $reportType): View
    {
        $this->authorizationService->authorize($request->user(), 'reports.generate');

        $meta = $this->reportService->getReportMeta($reportType);
        $periods = PayrollPeriod::query()->orderByDesc('cutoff_end')->get();
        $departments = Department::query()->where('is_active', true)->orderBy('department_name')->get();
        $employees = Employee::query()->where('is_active', true)->orderBy('last_name')->get();

        return view('reports.show', [
            'meta' => $meta,
            'reportType' => $reportType,
            'periods' => $periods,
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }

    /**
     * Generate report preview from selected parameters.
     */
    public function generate(Request $request, string $reportType): View|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'reports.generate');

        $meta = $this->reportService->getReportMeta($reportType);
        $params = $request->only(['payroll_period_id', 'department_id', 'employee_id', 'payroll_year']);

        try {
            $data = $this->resolveReportData($reportType, $params);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('reports.show', $reportType)
                ->withErrors(['report' => $e->getMessage()])
                ->withInput();
        }

        return view('reports.view', [
            'reportType' => $reportType,
            'meta' => $meta,
            'data' => $data,
            'params' => $params,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
        ]);
    }

    /**
     * Export generated report to PDF.
     */
    public function exportPdf(Request $request, string $reportType): Response|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'reports.generate');

        $meta = $this->reportService->getReportMeta($reportType);
        $params = $request->only(['payroll_period_id', 'department_id', 'employee_id', 'payroll_year']);

        try {
            $data = $this->resolveReportData($reportType, $params);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('reports.show', $reportType)
                ->withErrors(['report' => $e->getMessage()]);
        }

        $this->auditService->record(
            $request->user(),
            'Report',
            null,
            'EXPORT',
            null,
            [
                'report' => $reportType,
                'format' => 'PDF',
                'params' => $params,
            ]
        );

        $pdf = Pdf::loadView('reports.pdf.report-pdf', [
            'reportType' => $reportType,
            'meta' => $meta,
            'data' => $data,
            'params' => $params,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
        ])->setPaper('a4', 'landscape');

        $filename = "report_{$reportType}_".now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export generated report to Excel.
     */
    public function exportExcel(Request $request, string $reportType): StreamedResponse|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'reports.generate');

        $meta = $this->reportService->getReportMeta($reportType);
        $params = $request->only(['payroll_period_id', 'department_id', 'employee_id', 'payroll_year']);

        try {
            $data = $this->resolveReportData($reportType, $params);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('reports.show', $reportType)
                ->withErrors(['report' => $e->getMessage()]);
        }

        $this->auditService->record(
            $request->user(),
            'Report',
            null,
            'EXPORT',
            null,
            [
                'report' => $reportType,
                'format' => 'XLSX',
                'params' => $params,
            ]
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($meta['name'], 0, 31));

        $orgName = $data['org']?->registered_name ?? config('app.name', 'Municipal Payroll System');
        $sheet->setCellValue('A1', $orgName);
        $sheet->setCellValue('A2', $meta['name'].(! empty($data['is_provisional']) ? ' [PROVISIONAL]' : ''));
        $sheet->setCellValue('A3', 'Generated: '.now()->format('Y-m-d H:i:s').' by '.$request->user()->username);
        $sheet->getStyle('A1:A2')->getFont()->setBold(true);

        $rowNum = 5;

        // Custom builders per report type
        if ($reportType === 'payroll_register') {
            $headers = ['A5' => 'Employee No', 'B5' => 'Name', 'C5' => 'Department', 'D5' => 'Days', 'E5' => 'Gross Pay', 'F5' => 'Deductions', 'G5' => 'Net Pay'];
            foreach ($headers as $c => $v) {
                $sheet->setCellValue($c, $v);
            }
            $sheet->getStyle('A5:G5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['lines'] as $l) {
                $dept = $l->employee?->currentEmploymentDetail?->department?->department_name ?? 'N/A';
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $l->employee?->employee_no, DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $l->employee?->fullName());
                $sheet->setCellValue('C'.$rowNum, $dept);
                $sheet->setCellValue('D'.$rowNum, (float) $l->days_worked);
                $sheet->setCellValue('E'.$rowNum, (float) $l->gross_pay);
                $sheet->setCellValue('F'.$rowNum, (float) $l->total_deductions);
                $sheet->setCellValue('G'.$rowNum, (float) $l->net_pay);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('D'.$rowNum, (float) $data['totals']['days_worked']);
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['gross_pay']);
            $sheet->setCellValue('F'.$rowNum, (float) $data['totals']['total_deductions']);
            $sheet->setCellValue('G'.$rowNum, (float) $data['totals']['net_pay']);
            $sheet->getStyle('A'.$rowNum.':G'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('E6:G'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

        } elseif ($reportType === 'payroll_summary') {
            $sheet->setCellValue('A5', 'Department');
            $sheet->setCellValue('B5', 'Headcount');
            $sheet->setCellValue('C5', 'Gross Pay');
            $sheet->setCellValue('D5', 'Total Deductions');
            $sheet->setCellValue('E5', 'Net Pay');
            $sheet->getStyle('A5:E5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['department_summaries'] as $ds) {
                $sheet->setCellValue('A'.$rowNum, $ds['department']);
                $sheet->setCellValue('B'.$rowNum, $ds['headcount']);
                $sheet->setCellValue('C'.$rowNum, (float) $ds['gross_pay']);
                $sheet->setCellValue('D'.$rowNum, (float) $ds['total_deductions']);
                $sheet->setCellValue('E'.$rowNum, (float) $ds['net_pay']);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('B'.$rowNum, $data['totals']['employee_count']);
            $sheet->setCellValue('C'.$rowNum, (float) $data['totals']['gross_pay']);
            $sheet->setCellValue('D'.$rowNum, (float) $data['totals']['total_deductions']);
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['net_pay']);
            $sheet->getStyle('A'.$rowNum.':E'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('C6:E'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

        } elseif (in_array($reportType, ['sss_remittance', 'philhealth_remittance', 'pagibig_remittance'], true)) {
            $idColName = match ($reportType) {
                'sss_remittance' => 'SSS No',
                'philhealth_remittance' => 'PhilHealth No',
                default => 'Pag-IBIG MID',
            };

            $sheet->setCellValue('A5', 'Employee No');
            $sheet->setCellValue('B5', 'Name');
            $sheet->setCellValue('C5', $idColName);
            $sheet->setCellValue('D5', 'Department');
            $sheet->setCellValue('E5', 'Employee Share');
            $sheet->setCellValue('F5', 'Employer Share');
            $sheet->setCellValue('G5', 'Total Contribution');
            $sheet->setCellValue('H5', 'Share Source');
            $sheet->setCellValue('I5', 'Schedule Version');
            $sheet->getStyle('A5:I5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['rows'] as $r) {
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $r['employee_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $r['employee_name']);
                $sheet->setCellValueExplicit('C'.$rowNum, (string) $r['id_number'], DataType::TYPE_STRING);
                $sheet->setCellValue('D'.$rowNum, $r['department']);
                $sheet->setCellValue('E'.$rowNum, (float) $r['employee_share']);
                $sheet->setCellValue('F'.$rowNum, (float) $r['employer_share']);
                $sheet->setCellValue('G'.$rowNum, (float) $r['total_contribution']);
                $sheet->setCellValue('H'.$rowNum, $r['source']);
                $sheet->setCellValue('I'.$rowNum, $r['schedule_version'] ?? 'N/A');
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['employee_share']);
            $sheet->setCellValue('F'.$rowNum, (float) $data['totals']['employer_share']);
            $sheet->setCellValue('G'.$rowNum, (float) $data['totals']['total_contribution']);
            $sheet->getStyle('A'.$rowNum.':I'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('E6:G'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

        } elseif ($reportType === 'withholding_tax') {
            $sheet->setCellValue('A5', 'Employee No');
            $sheet->setCellValue('B5', 'Name');
            $sheet->setCellValue('C5', 'TIN');
            $sheet->setCellValue('D5', 'Department');
            $sheet->setCellValue('E5', 'Taxable Compensation');
            $sheet->setCellValue('F5', 'Tax Withheld');
            $sheet->getStyle('A5:F5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['rows'] as $r) {
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $r['employee_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $r['employee_name']);
                $sheet->setCellValueExplicit('C'.$rowNum, (string) $r['tin_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('D'.$rowNum, $r['department']);
                $sheet->setCellValue('E'.$rowNum, (float) $r['taxable_compensation']);
                $sheet->setCellValue('F'.$rowNum, (float) $r['tax_withheld']);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['taxable_compensation']);
            $sheet->setCellValue('F'.$rowNum, (float) $data['totals']['tax_withheld']);
            $sheet->getStyle('A'.$rowNum.':F'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('E6:F'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

        } elseif ($reportType === 'bank_transmittal') {
            $sheet->setCellValue('A5', 'Employee No');
            $sheet->setCellValue('B5', 'Account Name');
            $sheet->setCellValue('C5', 'Bank Name');
            $sheet->setCellValue('D5', 'Account Number');
            $sheet->setCellValue('E5', 'Net Pay Amount');
            $sheet->getStyle('A5:E5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['rows'] as $r) {
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $r['employee_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $r['employee_name']);
                $sheet->setCellValue('C'.$rowNum, $r['bank_name']);
                $sheet->setCellValueExplicit('D'.$rowNum, (string) $r['bank_account_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('E'.$rowNum, (float) $r['net_pay']);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTAL TRANSMITTAL');
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['net_pay']);
            $sheet->getStyle('A'.$rowNum.':E'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('E6:E'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

        } elseif ($reportType === 'thirteenth_month') {
            $sheet->setCellValue('A5', 'Employee No');
            $sheet->setCellValue('B5', 'Employee Name');
            $sheet->setCellValue('C5', 'Department');
            $sheet->setCellValue('D5', 'Basic Salary Earned');
            $sheet->setCellValue('E5', 'Imported 13th Month');
            $sheet->getStyle('A5:E5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['rows'] as $r) {
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $r['employee_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $r['employee_name']);
                $sheet->setCellValue('C'.$rowNum, $r['department']);
                $sheet->setCellValue('D'.$rowNum, (float) $r['basic_salary_earned']);
                $sheet->setCellValue('E'.$rowNum, (float) $r['imported_13th_month']);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('D'.$rowNum, (float) $data['totals']['basic_salary_earned']);
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['imported_13th_month']);
            $sheet->getStyle('A'.$rowNum.':E'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('D6:E'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

        } elseif ($reportType === 'leave_ledger') {
            $sheet->setCellValue('A5', 'Employee No');
            $sheet->setCellValue('B5', 'Employee Name');
            $sheet->setCellValue('C5', 'Leave Type');
            $sheet->setCellValue('D5', 'Earned / Carried');
            $sheet->setCellValue('E5', 'Used');
            $sheet->setCellValue('F5', 'Remaining Balance');
            $sheet->getStyle('A5:F5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['rows'] as $r) {
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $r['employee_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $r['employee_name']);
                $sheet->setCellValue('C'.$rowNum, $r['leave_type']);
                $sheet->setCellValue('D'.$rowNum, (float) $r['credits_earned']);
                $sheet->setCellValue('E'.$rowNum, (float) $r['credits_used']);
                $sheet->setCellValue('F'.$rowNum, (float) $r['balance_remaining']);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('D'.$rowNum, (float) $data['totals']['credits_earned']);
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['credits_used']);
            $sheet->setCellValue('F'.$rowNum, (float) $data['totals']['balance_remaining']);
            $sheet->getStyle('A'.$rowNum.':F'.$rowNum)->getFont()->setBold(true);

        } elseif ($reportType === 'loan_ledger') {
            $sheet->setCellValue('A5', 'Employee No');
            $sheet->setCellValue('B5', 'Employee Name');
            $sheet->setCellValue('C5', 'Loan Type');
            $sheet->setCellValue('D5', 'Reference No');
            $sheet->setCellValue('E5', 'Principal');
            $sheet->setCellValue('F5', 'Amortization');
            $sheet->setCellValue('G5', 'Total Deducted');
            $sheet->setCellValue('H5', 'Remaining Balance');
            $sheet->setCellValue('I5', 'Status');
            $sheet->getStyle('A5:I5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['rows'] as $r) {
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $r['employee_no'], DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $r['employee_name']);
                $sheet->setCellValue('C'.$rowNum, $r['loan_type']);
                $sheet->setCellValue('D'.$rowNum, $r['loan_reference']);
                $sheet->setCellValue('E'.$rowNum, (float) $r['principal']);
                $sheet->setCellValue('F'.$rowNum, (float) $r['amortization']);
                $sheet->setCellValue('G'.$rowNum, (float) $r['total_deducted']);
                $sheet->setCellValue('H'.$rowNum, (float) $r['outstanding_balance']);
                $sheet->setCellValue('I'.$rowNum, $r['status']);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['principal']);
            $sheet->setCellValue('F'.$rowNum, (float) $data['totals']['amortization']);
            $sheet->setCellValue('H'.$rowNum, (float) $data['totals']['outstanding_balance']);
            $sheet->getStyle('A'.$rowNum.':I'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('E6:H'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');

        } elseif ($reportType === 'cost_comparison') {
            $sheet->setCellValue('A5', 'Department');
            $sheet->setCellValue('B5', 'Current Headcount');
            $sheet->setCellValue('C5', 'Prior Headcount');
            $sheet->setCellValue('D5', 'Current Gross');
            $sheet->setCellValue('E5', 'Prior Gross');
            $sheet->setCellValue('F5', 'Gross Variance');
            $sheet->setCellValue('G5', 'Variance %');
            $sheet->setCellValue('H5', 'Current Net');
            $sheet->setCellValue('I5', 'Prior Net');
            $sheet->setCellValue('J5', 'Net Variance');
            $sheet->getStyle('A5:J5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['rows'] as $r) {
                $sheet->setCellValue('A'.$rowNum, $r['department']);
                $sheet->setCellValue('B'.$rowNum, $r['current_headcount']);
                $sheet->setCellValue('C'.$rowNum, $r['prior_headcount']);
                $sheet->setCellValue('D'.$rowNum, (float) $r['current_gross']);
                $sheet->setCellValue('E'.$rowNum, (float) $r['prior_gross']);
                $sheet->setCellValue('F'.$rowNum, (float) $r['gross_variance']);
                $sheet->setCellValue('G'.$rowNum, $r['gross_variance_pct'].'%');
                $sheet->setCellValue('H'.$rowNum, (float) $r['current_net']);
                $sheet->setCellValue('I'.$rowNum, (float) $r['prior_net']);
                $sheet->setCellValue('J'.$rowNum, (float) $r['net_variance']);
                $rowNum++;
            }

            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('D'.$rowNum, (float) $data['totals']['current_gross']);
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['prior_gross']);
            $sheet->setCellValue('F'.$rowNum, (float) $data['totals']['gross_variance']);
            $sheet->setCellValue('G'.$rowNum, $data['totals']['gross_variance_pct'].'%');
            $sheet->setCellValue('H'.$rowNum, (float) $data['totals']['current_net']);
            $sheet->setCellValue('I'.$rowNum, (float) $data['totals']['prior_net']);
            $sheet->setCellValue('J'.$rowNum, (float) $data['totals']['net_variance']);
            $sheet->getStyle('A'.$rowNum.':J'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('D6:F'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('H6:J'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "report_{$reportType}_".now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Dispatch report data generation to ReportService.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function resolveReportData(string $reportType, array $params): array
    {
        return match ($reportType) {
            'payroll_register' => $this->reportService->generatePayrollRegister($params),
            'payroll_summary' => $this->reportService->generatePayrollSummary($params),
            'sss_remittance' => $this->reportService->generateSssRemittance($params),
            'philhealth_remittance' => $this->reportService->generatePhilhealthRemittance($params),
            'pagibig_remittance' => $this->reportService->generatePagibigRemittance($params),
            'withholding_tax' => $this->reportService->generateWithholdingTax($params),
            'bank_transmittal' => $this->reportService->generateBankTransmittal($params),
            'thirteenth_month' => $this->reportService->generateThirteenthMonth($params),
            'leave_ledger' => $this->reportService->generateLeaveLedger($params),
            'loan_ledger' => $this->reportService->generateLoanLedger($params),
            'cost_comparison' => $this->reportService->generateCostComparison($params),
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
        };
    }
}
