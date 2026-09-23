<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\PayrollRecordSearchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

// UC-29 · Search payroll records — FR-5.2, NFR-5.5.
// Preconditions: User is signed in.
// Actors: All roles per FR-6.2 (PO, Approver, Admin, Viewer — 'payroll_records.search').
class PayrollRecordSearchController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly PayrollRecordSearchService $searchService,
    ) {}

    /**
     * Search payroll records across runs and lines with multi-criteria filtering.
     */
    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $filters = $request->only(['q', 'payroll_period_id', 'date_from', 'date_to', 'department_id', 'run_status']);

        $lines = $this->searchService->searchLines($filters, 15);
        $runs = $this->searchService->searchRuns($filters, 10);
        $appliedCriteria = $this->searchService->describeAppliedCriteria($filters);

        $periods = PayrollPeriod::query()->orderByDesc('cutoff_end')->get();
        $departments = Department::query()->where('is_active', true)->orderBy('department_name')->get();
        $runStatuses = ['DRAFT', 'FOR_REVIEW', 'APPROVED', 'FINALIZED', 'REVERSED', 'CANCELLED'];

        return view('payroll-records.index', [
            'lines' => $lines,
            'runs' => $runs,
            'filters' => $filters,
            'appliedCriteria' => $appliedCriteria,
            'periods' => $periods,
            'departments' => $departments,
            'runStatuses' => $runStatuses,
            'hasSearched' => ! empty(array_filter($filters)),
        ]);
    }

    /**
     * Retained record view for a single PayrollLine (FR-5.1, AC-5.1.1, AC-5.1.3).
     */
    public function show(Request $request, PayrollLine $payrollLine): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $payrollLine->load([
            'employee.employmentDetails.department',
            'employee.employmentDetails.position',
            'employee.employmentDetails.employmentStatus',
            'run.period',
            'import',
            'compensationProfile',
            'earningLines.earningType',
            'deductionLines.deductionType',
        ]);

        return view('payroll-records.show', [
            'line' => $payrollLine,
            'run' => $payrollLine->run,
            'employee' => $payrollLine->employee,
        ]);
    }

    /**
     * View an employee's full chronological payroll history (UC-29 A2).
     */
    public function employeeHistory(Request $request, Employee $employee): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $employee->load(['currentEmploymentDetail.department', 'currentEmploymentDetail.position']);
        $lines = $this->searchService->employeeHistory($employee->employee_id);

        return view('payroll-records.employee-history', [
            'employee' => $employee,
            'lines' => $lines,
        ]);
    }

    /**
     * Export matching search results to PDF (UC-29 A1).
     */
    public function exportPdf(Request $request): Response
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $filters = $request->only(['q', 'payroll_period_id', 'date_from', 'date_to', 'department_id', 'run_status']);
        $lines = $this->searchService->exportLines($filters);
        $appliedCriteria = $this->searchService->describeAppliedCriteria($filters);

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PayrollLine',
            entityId: null,
            action: 'EXPORT',
            previousValues: null,
            newValues: [
                'type' => 'search_results_pdf',
                'count' => $lines->count(),
                'filters' => $filters,
            ]
        );

        $pdf = Pdf::loadView('payroll-records.pdf.search-results', [
            'lines' => $lines,
            'filters' => $filters,
            'appliedCriteria' => $appliedCriteria,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
        ])->setPaper('a4', 'landscape');

        $filename = 'payroll_records_search_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export matching search results to Excel (UC-29 A1).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $filters = $request->only(['q', 'payroll_period_id', 'date_from', 'date_to', 'department_id', 'run_status']);
        $lines = $this->searchService->exportLines($filters);

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PayrollLine',
            entityId: null,
            action: 'EXPORT',
            previousValues: null,
            newValues: [
                'type' => 'search_results_xlsx',
                'count' => $lines->count(),
                'filters' => $filters,
            ]
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Search Results');

        // Header metadata
        $sheet->setCellValue('A1', config('app.name', 'Municipal Payroll System').' — Payroll Records Search Export');
        $sheet->setCellValue('A2', 'Exported: '.now()->format('Y-m-d H:i:s').' by '.$request->user()->username);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        // Column headers
        $headers = [
            'A4' => 'Employee No',
            'B4' => 'Employee Name',
            'C4' => 'Department',
            'D4' => 'Period',
            'E4' => 'Days Worked',
            'F4' => 'Gross Pay',
            'G4' => 'Total Deductions',
            'H4' => 'Net Pay',
            'I4' => 'Run ID',
            'J4' => 'Run Status',
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }
        $sheet->getStyle('A4:J4')->getFont()->setBold(true);

        $rowNum = 5;
        foreach ($lines as $line) {
            $period = $line->run?->period;
            $periodLabel = $period ? "{$period->payroll_year}-{$period->period_no}" : 'N/A';
            $dept = $line->employee?->currentEmploymentDetail?->department?->department_name ?? 'N/A';

            $sheet->setCellValueExplicit('A'.$rowNum, (string) $line->employee?->employee_no, DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$rowNum, $line->employee?->fullName());
            $sheet->setCellValue('C'.$rowNum, $dept);
            $sheet->setCellValue('D'.$rowNum, $periodLabel);
            $sheet->setCellValue('E'.$rowNum, (float) $line->days_worked);
            $sheet->setCellValue('F'.$rowNum, (float) $line->gross_pay);
            $sheet->setCellValue('G'.$rowNum, (float) $line->total_deductions);
            $sheet->setCellValue('H'.$rowNum, (float) $line->net_pay);
            $sheet->setCellValue('I'.$rowNum, (int) $line->payroll_run_id);
            $sheet->setCellValue('J'.$rowNum, (string) $line->run?->run_status);

            $rowNum++;
        }

        // Format currency columns
        $sheet->getStyle('F5:H'.($rowNum - 1))->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'payroll_records_search_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
