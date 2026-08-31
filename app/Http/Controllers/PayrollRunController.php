<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\PayrollRunException;
use App\Services\PayrollRunService;
use App\Services\WorksheetExportException;
use App\Services\WorksheetExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// UC-17 · Create payroll run — FR-2.6, FR-4.4 (A2 cancel). Payroll Officer
// only ('payroll_run.create_import', RoleSeeder's comment: "FR-2.6, FR-2.8
// — PO"; AC-6.2.4 forbids the Administrator this function).
//
// exportWorksheet() carries UC-32, included by UC-17 (a run is created and
// its input worksheet exported "in the same operation" — use-case-model.md
// §4.4 prose) and reachable again from the run's own screen for a
// re-export after correction (UC-32 A1).
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
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        $runs = PayrollRun::query()
            ->with('period')
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

    // UC-17 steps 1-6 — creates the run, then proceeds to payroll intake
    // (step 6), landing on the run's own screen where UC-32's worksheet
    // and UC-18's import both live.
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
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        $payrollRun->load(['period', 'lines.employee']);
        $currentImport = $payrollRun->currentImport();

        return view('payroll-runs.show', [
            'run' => $payrollRun,
            'currentImport' => $currentImport,
            'totals' => $this->derivedTotals($payrollRun),
        ]);
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

    // ✧ UC-32 · Export payroll input worksheet.
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
     * (PAYROLL_RUN.total_* stay at their zero default through Draft and
     * intake; see PayrollRun's own docblock). Summed from the run's
     * current payroll lines only.
     *
     * @return array{gross: string, deductions: string, net: string}
     */
    private function derivedTotals(PayrollRun $run): array
    {
        $lines = $run->lines()->where('payroll_import_id', $run->currentImport()?->payroll_import_id)->get();

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
