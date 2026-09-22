<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PayrollImport;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayslipIssuance;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\PayrollRunException;
use App\Services\PayrollRunService;
use App\Services\WorksheetExportException;
use App\Services\WorksheetExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// UC-17 · Create payroll run — FR-2.6, FR-4.4 (A2 cancel).
// UC-23 · Submit payroll run — FR-4.4.
// UC-24 · Approve or return payroll run — FR-4.4, BR-28.
// UC-25 · Finalize payroll run — FR-4.4, FR-4.5.
// UC-26 · Reverse finalized payroll run — FR-4.5, BR-24.
class PayrollRunController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly PayrollRunService $payrollRunService,
        private readonly WorksheetExportService $worksheetExportService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $runs = PayrollRun::query()
            ->with(['period', 'reversalRecord', 'submitter', 'approver'])
            ->orderByDesc('payroll_run_id')
            ->get();

        return view('payroll-runs.index', ['runs' => $runs]);
    }

    public function create(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        return view('payroll-runs.create', [
            'periods' => PayrollPeriod::query()->orderByDesc('payroll_year')->orderByDesc('period_no')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('department_name')->get(),
        ]);
    }

    // UC-17 steps 1-6.
    public function store(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        $data = $request->validate([
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,payroll_period_id'],
            'run_type' => ['required', Rule::in(['REGULAR', 'THIRTEENTH_MONTH', 'FINAL_PAY', 'SPECIAL'])],
            'scope' => ['required', Rule::in(['ALL', 'DEPARTMENT'])],
            'department_id' => ['required_if:scope,DEPARTMENT', 'nullable', 'integer', 'exists:departments,department_id'],
        ]);

        $period = PayrollPeriod::query()->findOrFail($data['payroll_period_id']);
        $populationScope = $data['scope'] === 'ALL' ? 'ALL' : "DEPARTMENT:{$data['department_id']}";

        try {
            $created = $this->payrollRunService->createRun($period, $data['run_type'], $populationScope, $request->user()->user_id);
        } catch (PayrollRunException $e) {
            $redirect = back()->withErrors(['payroll_period_id' => $e->getMessage()])->withInput();

            return $e->existingRunId !== null
                ? redirect()->route('payroll-runs.show', $e->existingRunId)->withErrors(['payroll_period_id' => $e->getMessage()])
                : $redirect;
        }

        $run = $created['run'];

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $run->payroll_run_id,
            action: 'CREATE',
            newValues: ['payroll_period_id' => $period->payroll_period_id, 'run_type' => $data['run_type'], 'population_scope' => $populationScope],
        );

        $status = "Run #{$run->payroll_run_id} created in Draft — {$created['includedCount']} employee(s) included.";
        if ($created['excluded'] !== []) {
            $status .= ' Excluded by the selection: '.implode(', ', $created['excluded']).'.';
        }

        return redirect()->route('payroll-runs.show', $run)->with('status', $status);
    }

    public function show(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $payrollRun->load([
            'period',
            'lines.employee',
            'reversalRecord.reversedBy',
            'integrityAnchor',
            'submitter',
            'approver',
            'transitions.performer',
        ]);

        $currentImport = $payrollRun->currentImport();
        $user = $request->user();

        $canManage = $this->authorizationService->can($user, 'payroll_run.create_import');
        $canSubmit = $this->authorizationService->can($user, 'payroll_run.submit');
        $canApproveReturn = $this->authorizationService->can($user, 'payroll_run.approve_return');
        $canFinalize = $this->authorizationService->can($user, 'payroll_run.finalize');
        $canGeneratePayslips = $this->authorizationService->can($user, 'payslips.generate');
        $canReprintPayslips = $this->authorizationService->can($user, 'payslips.reprint');

        $isSubmitter = (int) $payrollRun->submitted_by === (int) $user->user_id;
        $hasBlockingExceptions = $payrollRun->exceptions()->where('severity', 'BLOCKING')->exists();
        $hasUnacknowledgedWarnings = $payrollRun->exceptions()->where('severity', 'WARNING')->whereNull('acknowledged_at')->exists();

        // Check BR-24 reversible condition
        $hasIssuedPayslips = DB::table('payslip_issuances')->where('payroll_run_id', $payrollRun->payroll_run_id)->exists();
        $payDatePassed = $payrollRun->period->pay_date->isPast();
        $canReverse = ($payrollRun->run_status === 'FINALIZED') && ! ($hasIssuedPayslips && $payDatePassed);

        // W11 · payslip state for the run (UC-27/UC-28 evidence + buttons).
        $issuanceCounts = PayslipIssuance::query()
            ->where('payroll_run_id', $payrollRun->payroll_run_id)
            ->selectRaw('issuance_type, COUNT(*) AS cnt')
            ->groupBy('issuance_type')
            ->pluck('cnt', 'issuance_type');

        return view('payroll-runs.show', [
            'run' => $payrollRun,
            'currentImport' => $currentImport,
            'totals' => $this->derivedTotals($payrollRun, $currentImport),
            'canManage' => $canManage,
            'canSubmit' => $canSubmit,
            'canApproveReturn' => $canApproveReturn,
            'canFinalize' => $canFinalize,
            'canGeneratePayslips' => $canGeneratePayslips,
            'canReprintPayslips' => $canReprintPayslips,
            'originalIssuances' => (int) ($issuanceCounts['ORIGINAL'] ?? 0),
            'reprintIssuances' => (int) ($issuanceCounts['REPRINT'] ?? 0),
            'departments' => Department::query()->where('is_active', true)->orderBy('department_name')->get(),
            'isSubmitter' => $isSubmitter,
            'hasBlockingExceptions' => $hasBlockingExceptions,
            'hasUnacknowledgedWarnings' => $hasUnacknowledgedWarnings,
            'canReverse' => $canReverse,
            'transitions' => $payrollRun->transitions()->with('performer')->orderByDesc('run_transition_id')->get(),
        ]);
    }

    // UC-23 · Submit payroll run for review.
    public function submit(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.submit');

        $fromStatus = $payrollRun->run_status;

        try {
            $this->payrollRunService->submitRun($payrollRun, $request->user()->user_id);
        } catch (PayrollRunException $e) {
            return redirect()->route('payroll-runs.show', $payrollRun)->withErrors(['submit' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'UPDATE',
            previousValues: ['run_status' => $fromStatus],
            newValues: ['run_status' => 'FOR_REVIEW', 'submitted_by' => $request->user()->user_id],
        );

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', "Run #{$payrollRun->payroll_run_id} submitted for review.");
    }

    // UC-24 · Approve payroll run.
    public function approve(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.approve_return');

        try {
            $this->payrollRunService->approveRun($payrollRun, $request->user()->user_id);
        } catch (PayrollRunException $e) {
            return redirect()->route('payroll-runs.show', $payrollRun)->withErrors(['approve' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'APPROVE',
            previousValues: ['run_status' => 'FOR_REVIEW'],
            newValues: ['run_status' => 'APPROVED', 'approved_by' => $request->user()->user_id],
        );

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', "Run #{$payrollRun->payroll_run_id} approved.");
    }

    // UC-24 A1/A2 · Return confirmation form.
    public function returnForm(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.approve_return');

        return view('payroll-runs.return', ['run' => $payrollRun]);
    }

    // UC-24 A1/A2 · Return payroll run.
    public function returnRun(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.approve_return');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $fromStatus = $payrollRun->run_status;

        try {
            $this->payrollRunService->returnRun($payrollRun, $data['reason'], $request->user()->user_id);
        } catch (PayrollRunException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'UPDATE',
            previousValues: ['run_status' => $fromStatus],
            newValues: ['run_status' => 'RETURNED', 'reason' => $data['reason']],
        );

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', "Run #{$payrollRun->payroll_run_id} returned for correction.");
    }

    // UC-25 · Finalize confirmation form (NFR-6.3).
    public function finalizeForm(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.finalize');

        $currentImport = $payrollRun->currentImport();
        $totals = $this->derivedTotals($payrollRun, $currentImport);

        return view('payroll-runs.finalize', [
            'run' => $payrollRun,
            'currentImport' => $currentImport,
            'totals' => $totals,
        ]);
    }

    // UC-25 · Finalize payroll run.
    public function finalize(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.finalize');

        try {
            $this->payrollRunService->finalizeRun($payrollRun, $request->user()->user_id);
        } catch (PayrollRunException $e) {
            return redirect()->route('payroll-runs.show', $payrollRun)->withErrors(['finalize' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'FINALIZE',
            previousValues: ['run_status' => 'APPROVED'],
            newValues: [
                'run_status' => 'FINALIZED',
                'finalized_at' => $payrollRun->finalized_at?->toDateTimeString(),
                'total_gross' => (string) $payrollRun->total_gross,
                'total_deductions' => (string) $payrollRun->total_deductions,
                'total_net' => (string) $payrollRun->total_net,
            ],
        );

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', "Run #{$payrollRun->payroll_run_id} finalized and locked.");
    }

    // UC-26 · Reverse confirmation form (NFR-6.3).
    public function reverseForm(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.finalize');

        return view('payroll-runs.reverse', ['run' => $payrollRun]);
    }

    // UC-26 · Reverse finalized payroll run.
    public function reverse(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.finalize');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->payrollRunService->reverseRun($payrollRun, $data['reason'], $request->user()->user_id);
        } catch (PayrollRunException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'REVERSE',
            previousValues: ['run_status' => 'FINALIZED'],
            newValues: ['run_status' => 'DRAFT', 'reason' => $data['reason']],
        );

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', "Run #{$payrollRun->payroll_run_id} reversed back to Draft.");
    }

    public function cancelForm(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        return view('payroll-runs.cancel', ['run' => $payrollRun]);
    }

    // UC-17 A2.
    public function cancel(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->payrollRunService->cancelRun($payrollRun, $data['reason'], $request->user()->user_id);
        } catch (PayrollRunException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'UPDATE',
            previousValues: ['run_status' => 'DRAFT'],
            newValues: ['run_status' => 'CANCELLED', 'reason' => $data['reason']],
        );

        return redirect()->route('payroll-runs.index')->with('status', "Run #{$payrollRun->payroll_run_id} cancelled.");
    }

    // UC-32 · Export payroll input worksheet.
    public function exportWorksheet(Request $request, PayrollRun $payrollRun)
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        try {
            $spreadsheet = $this->worksheetExportService->export($payrollRun->payroll_run_id);
        } catch (WorksheetExportException $e) {
            return back()->withErrors(['worksheet' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_RUN',
            entityId: $payrollRun->payroll_run_id,
            action: 'EXPORT',
            newValues: ['export' => 'input_worksheet'],
        );

        $filename = "payroll-run-{$payrollRun->payroll_run_id}-worksheet.xlsx";
        $tempPath = tempnam(sys_get_temp_dir(), 'worksheet');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * §7 beat 11 — run totals are displayed and derived, never stored
     * through Draft and intake. Stored at finalization (FR-4.5).
     *
     * @return array{gross: string, deductions: string, net: string}
     */
    private function derivedTotals(PayrollRun $run, ?PayrollImport $currentImport): array
    {
        if ($run->run_status === 'FINALIZED') {
            return [
                'gross' => (string) $run->total_gross,
                'deductions' => (string) $run->total_deductions,
                'net' => (string) $run->total_net,
            ];
        }

        $lines = $run->lines()->where('payroll_import_id', $currentImport?->payroll_import_id)->get();

        $gross = '0.00';
        $deductions = '0.00';
        $net = '0.00';
        foreach ($lines as $line) {
            $gross = bcadd($gross, (string) $line->gross_pay, 2);
            $deductions = bcadd($deductions, (string) $line->total_deductions, 2);
            $net = bcadd($net, (string) $line->net_pay, 2);
        }

        return ['gross' => $gross, 'deductions' => $deductions, 'net' => $net];
    }
}
