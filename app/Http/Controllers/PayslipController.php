<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\PayslipException;
use App\Services\PayslipService;
use Illuminate\Http\Request;

// UC-27 · Generate payslips — FR-3.1, FR-3.2, FR-3.3, NFR-3.5.
// UC-28 · Reprint payslip — FR-3.4, NFR-5.5.
//
// Generation is a Payroll Officer action ('payslips.generate'); reprint and
// the single-payslip view cover the FR-6.2 matrix row "Reprint a past
// payslip" (PO, Approver, Admin, Viewer read) via 'payslips.reprint'.
// Every refusal — a run that is not Finalized, or a payslip for an employee
// outside the run — is enforced server-side, not by hiding a button.
class PayslipController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly PayslipService $payslipService,
    ) {}

    /**
     * UC-27 · Generate the whole payslip set for a finalized run in one
     * action (FR-3.3 behavior 1), optionally filtered by department, and
     * export it as a single multi-page PDF (AC-3.3.1, AC-3.3.2).
     */
    public function generate(Request $request, PayrollRun $payrollRun)
    {
        $this->authorizationService->authorize($request->user(), 'payslips.generate');

        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,department_id'],
        ]);
        $departmentId = $request->integer('department_id') ?: null;

        try {
            $this->payslipService->assertFinalized($payrollRun);
        } catch (PayslipException $e) {
            return redirect()->route('payroll-runs.show', $payrollRun)
                ->withErrors(['payslips' => $e->getMessage()]);
        }

        $payrollRun->load('period');
        $lines = $this->payslipService->runLines($payrollRun, $departmentId);
        $newIssued = $this->payslipService->recordOriginalIssuances($payrollRun, $lines, $request->user()->user_id);

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'EXPORT',
            newValues: [
                'export' => 'payslip_batch',
                'payslips' => $lines->count(),
                'new_issuances' => $newIssued,
                'department_id' => $departmentId,
            ],
        );

        $pdf = $this->payslipService->renderBatchPdf($payrollRun, $lines);

        return $pdf->stream(
            "payslips-run-{$payrollRun->payroll_run_id}-{$payrollRun->period->payroll_year}-period-{$payrollRun->period->period_no}.pdf"
        );
    }

    /**
     * One payslip for one employee of a finalized run, rendered live from
     * the stored payroll line. Read-only: nothing is recorded. The file
     * name is employee number plus period, so individual files are unique
     * and identifiable (AC-3.3.3).
     */
    public function pdf(Request $request, PayrollRun $payrollRun, Employee $employee)
    {
        $this->authorizationService->authorize($request->user(), 'payslips.reprint');

        $line = $this->lineInRun($payrollRun, $employee);

        try {
            $this->payslipService->assertFinalized($payrollRun, 'A payslip');
        } catch (PayslipException $e) {
            return redirect()->route('payroll-runs.show', $payrollRun)
                ->withErrors(['payslips' => $e->getMessage()]);
        }

        $payrollRun->load('period');

        return $this->payslipService->renderSinglePdf($payrollRun, $line)->stream(
            "{$line->employee->employee_no}-{$payrollRun->period->payroll_year}-p{$payrollRun->period->period_no}.pdf"
        );
    }

    /**
     * UC-28 · Reissue one employee's payslip from the stored line of a
     * finalized run. The document is regenerated — never a saved file that
     * could diverge from the record (FR-3.4 behavior 2) — marked as a
     * reprint, and the reprint is recorded with user and timestamp
     * (AC-3.4.3). Every value matches the original (AC-3.4.2).
     */
    public function reprint(Request $request, PayrollRun $payrollRun, Employee $employee)
    {
        $this->authorizationService->authorize($request->user(), 'payslips.reprint');

        $line = $this->lineInRun($payrollRun, $employee);

        try {
            $issuance = $this->payslipService->recordReprint($payrollRun, $line, $request->user()->user_id);
        } catch (PayslipException $e) {
            return redirect()->route('payroll-runs.show', $payrollRun)
                ->withErrors(['payslips' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYSLIP_ISSUANCE',
            entityId: $issuance->payslip_issuance_id,
            action: 'EXPORT',
            newValues: [
                'export' => 'payslip_reprint',
                'issuance_type' => 'REPRINT',
                'payroll_run_id' => $payrollRun->payroll_run_id,
                'employee_id' => $line->employee_id,
                'employee_no' => $line->employee?->employee_no,
                'issued_at' => $issuance->issued_at->toDateTimeString(),
            ],
        );

        $payrollRun->load('period');

        return $this->payslipService->renderSinglePdf($payrollRun, $line, $issuance)->stream(
            "{$line->employee->employee_no}-{$payrollRun->period->payroll_year}-p{$payrollRun->period->period_no}.pdf"
        );
    }

    /**
     * The payroll line that pairs the run with the requested employee,
     * loaded with everything a payslip renders. The inverse pair — a run
     * that does not contain that employee — is a 404, not a payslip.
     */
    private function lineInRun(PayrollRun $payrollRun, Employee $employee): PayrollLine
    {
        $line = $payrollRun->lines()
            ->where('employee_id', $employee->employee_id)
            ->with([
                'employee.employmentDetails.department',
                'employee.employmentDetails.position',
                'employee.employmentDetails.employmentStatus',
                'earningLines.earningType',
                'deductionLines.deductionType',
            ])
            ->first();

        abort_if($line === null, 404, 'This employee has no payroll line in the selected run.');

        return $line;
    }
}
