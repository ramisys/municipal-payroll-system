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
// Preconditions: Payroll data exists for the requested period.
// Actors: All roles per FR-6.2 (PO, Approver, Admin, Viewer — 'reports.generate').
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

        if (! $meta['implemented_in_w12']) {
            return redirect()->route('reports.show', $reportType)
                ->with('warning', "The '{$meta['name']}' schedule and employer-share derivation engine is scheduled for Week 13 milestone P-D. The foundation catalogue is established.");
        }

        try {
            $data = match ($reportType) {
                'payroll_register' => $this->reportService->generatePayrollRegister($params),
                'payroll_summary' => $this->reportService->generatePayrollSummary($params),
                default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
            };
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('reports.show', $reportType)
                ->withErrors(['report' => $e->getMessage()])
                ->withInput();
        }

        // Record audit entry
        $this->auditService->record(
            user: $request->user(),
            entityName: 'Report',
            entityId: null,
            action: 'EXPORT',
            previousValues: null,
            newValues: [
                'report' => $reportType,
                'params' => $params,
                'provisional' => $data['is_provisional'],
            ]
        );

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

        if (! $meta['implemented_in_w12']) {
            return redirect()->route('reports.show', $reportType);
        }

        $data = match ($reportType) {
            'payroll_register' => $this->reportService->generatePayrollRegister($params),
            'payroll_summary' => $this->reportService->generatePayrollSummary($params),
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
        };

        $this->auditService->record(
            user: $request->user(),
            entityName: 'Report',
            entityId: null,
            action: 'EXPORT',
            previousValues: null,
            newValues: [
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

        if (! $meta['implemented_in_w12']) {
            return redirect()->route('reports.show', $reportType);
        }

        $data = match ($reportType) {
            'payroll_register' => $this->reportService->generatePayrollRegister($params),
            'payroll_summary' => $this->reportService->generatePayrollSummary($params),
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}"),
        };

        $this->auditService->record(
            user: $request->user(),
            entityName: 'Report',
            entityId: null,
            action: 'EXPORT',
            previousValues: null,
            newValues: [
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
        $sheet->setCellValue('A2', $meta['name'].($data['is_provisional'] ? ' [PROVISIONAL]' : ''));
        $sheet->setCellValue('A3', 'Generated: '.now()->format('Y-m-d H:i:s').' by '.$request->user()->username);
        $sheet->getStyle('A1:A2')->getFont()->setBold(true);

        $rowNum = 5;
        if ($reportType === 'payroll_register') {
            $headers = ['A5' => 'Employee No', 'B5' => 'Name', 'C5' => 'Department', 'D5' => 'Days', 'E5' => 'Hours', 'F5' => 'Gross Pay', 'G5' => 'Deductions', 'H5' => 'Net Pay'];
            foreach ($headers as $c => $v) {
                $sheet->setCellValue($c, $v);
            }
            $sheet->getStyle('A5:H5')->getFont()->setBold(true);

            $rowNum = 6;
            foreach ($data['lines'] as $l) {
                $dept = $l->employee?->currentEmploymentDetail?->department?->department_name ?? 'N/A';
                $sheet->setCellValueExplicit('A'.$rowNum, (string) $l->employee?->employee_no, DataType::TYPE_STRING);
                $sheet->setCellValue('B'.$rowNum, $l->employee?->fullName());
                $sheet->setCellValue('C'.$rowNum, $dept);
                $sheet->setCellValue('D'.$rowNum, (float) $l->days_worked);
                $sheet->setCellValue('E'.$rowNum, (float) $l->hours_worked);
                $sheet->setCellValue('F'.$rowNum, (float) $l->gross_pay);
                $sheet->setCellValue('G'.$rowNum, (float) $l->total_deductions);
                $sheet->setCellValue('H'.$rowNum, (float) $l->net_pay);
                $rowNum++;
            }

            // Totals row
            $sheet->setCellValue('A'.$rowNum, 'TOTALS');
            $sheet->setCellValue('D'.$rowNum, (float) $data['totals']['days_worked']);
            $sheet->setCellValue('E'.$rowNum, (float) $data['totals']['hours_worked']);
            $sheet->setCellValue('F'.$rowNum, (float) $data['totals']['gross_pay']);
            $sheet->setCellValue('G'.$rowNum, (float) $data['totals']['total_deductions']);
            $sheet->setCellValue('H'.$rowNum, (float) $data['totals']['net_pay']);
            $sheet->getStyle('A'.$rowNum.':H'.$rowNum)->getFont()->setBold(true);
            $sheet->getStyle('F6:H'.$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
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
        }

        foreach (range('A', 'H') as $col) {
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
}
