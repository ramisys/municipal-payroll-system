<?php

namespace App\Http\Controllers;

use App\Models\ImportColumnMap;
use App\Models\PayrollImport;
use App\Models\PayrollRun;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\PayrollImportException;
use App\Services\PayrollImportService;
use App\Services\ReconciliationException;
use App\Services\RegisterParseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

// ✧ UC-18 · Import computed payroll register (this controller's create/
// preview/commit/cancel) and ✧ UC-33 · Review import history (history/
// show/download) — FR-2.5, FR-2.6, FR-2.8, FR-2.9, FR-2.10.
//
// preview()/commit() follow AttendanceImportController's two-step,
// session-stashed shape (UC-13's own AC-1.3.1 "commits nothing until the
// user confirms the preview", carried into UC-18 A2's identical wording):
// preview() stores the uploaded file and shows what would happen without
// writing; commit() re-parses that same stored file — never data posted
// back by the browser — inside PayrollImportService's one transaction.
//
// UC-18 write access is 'payroll_run.create_import' (Payroll Officer only,
// AC-6.2.4). UC-33 read access is 'payroll_records.search' — no FR-6.2
// matrix row names "review import history" specifically, and this is the
// existing broad-read key already granted to all four roles (Payroll
// Officer, Approver, Administrator, Viewer) that UC-33's own actor list
// requires.
class PayrollImportController extends Controller
{
    private const SESSION_KEY = 'payroll_import_pending';

    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly PayrollImportService $payrollImportService,
    ) {}

    public function create(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        return view('payroll-imports.create', [
            'run' => $payrollRun,
            'maps' => ImportColumnMap::query()->where('is_active', true)->orderBy('map_name')->get(),
        ]);
    }

    // UC-18 steps 1-5 / A2 preview only.
    public function preview(Request $request, PayrollRun $payrollRun): View|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        $data = $request->validate([
            'import_column_map_id' => ['required', 'integer', 'exists:import_column_maps,import_column_map_id'],
            'file' => ['required', 'file'],
        ]);

        $map = ImportColumnMap::query()->findOrFail($data['import_column_map_id']);
        $storedPath = $request->file('file')->store('register-imports', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $preview = $this->payrollImportService->preview($payrollRun, $absolutePath, $map);
        } catch (RegisterParseException $e) {
            Storage::disk('local')->delete($storedPath);

            return back()->withErrors(['file' => "E1: {$e->getMessage()}"])->withInput();
        }

        $request->session()->put(self::SESSION_KEY, [
            'path' => $storedPath,
            'payroll_run_id' => $payrollRun->payroll_run_id,
            'import_column_map_id' => $map->import_column_map_id,
        ]);

        return view('payroll-imports.preview', [
            'run' => $payrollRun,
            'map' => $map,
            'rows' => $preview['rows'],
            'result' => $preview['result'],
            'defects' => $preview['defects'],
        ]);
    }

    // UC-18 steps 6-10 / A1 supersession.
    public function commit(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        $pending = $request->session()->get(self::SESSION_KEY);
        if ($pending === null || (int) $pending['payroll_run_id'] !== $payrollRun->payroll_run_id) {
            return redirect()->route('payroll-imports.create', $payrollRun)->withErrors(['file' => 'UC-18: no previewed file is pending for this run; upload a file again.']);
        }

        $map = ImportColumnMap::query()->findOrFail($pending['import_column_map_id']);
        $absolutePath = Storage::disk('local')->path($pending['path']);

        try {
            $committed = $this->payrollImportService->commit($payrollRun, $absolutePath, $map, $request->user()->user_id);
        } catch (RegisterParseException $e) {
            return back()->withErrors(['file' => "E1: {$e->getMessage()}"]);
        } catch (ReconciliationException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->with('defects', $e->defectsAsArray());
        } catch (PayrollImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($pending['path']);
            $request->session()->forget(self::SESSION_KEY);
        }

        $import = $committed['import'];

        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_IMPORT',
            entityId: $import->payroll_import_id,
            action: 'CREATE',
            newValues: [
                'payroll_run_id' => $payrollRun->payroll_run_id,
                'version_no' => $import->version_no,
                'source_filename' => $import->source_filename,
                'source_sha256' => $import->source_sha256,
                'row_count' => $import->row_count,
            ],
        );

        $status = "Version {$import->version_no} accepted — {$import->row_count} employee(s) loaded.";
        if ($import->version_no > 1) {
            $status .= ' '.count($committed['changed']).' line(s) changed, '.count($committed['unchanged']).' unchanged from the prior version.';
        }

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', $status);
    }

    public function cancel(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');

        $pending = $request->session()->pull(self::SESSION_KEY);
        if ($pending !== null) {
            Storage::disk('local')->delete($pending['path']);
        }

        return redirect()->route('payroll-imports.create', $payrollRun)->with('status', 'Import cancelled; nothing was committed.');
    }

    // ✧ UC-33 · Review import history, steps 1-3.
    public function history(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        $imports = $payrollRun->imports()->with('importedBy')->orderByDesc('version_no')->get();

        return view('payroll-imports.history', ['run' => $payrollRun, 'imports' => $imports]);
    }

    // ✧ UC-33 step 4 — the stored reconciliation result, exactly as
    // recorded at import time.
    public function show(Request $request, PayrollRun $payrollRun, PayrollImport $payrollImport): View
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        return view('payroll-imports.show', ['run' => $payrollRun, 'import' => $payrollImport]);
    }

    // ✧ UC-33 step 6 / A2 — the retained source file, for a hash
    // recompute-and-compare against the stored value.
    public function download(Request $request, PayrollRun $payrollRun, PayrollImport $payrollImport): Response
    {
        $this->authorizationService->authorize($request->user(), 'payroll_records.search');

        // audit_logs.action has no 'READ' value (chk_audit_logs_action,
        // 2025_08_31_000xxx_create_audit_logs_table) — 'EXPORT' is the
        // closest sanctioned action for taking a stored file out of the
        // system, which is what a source-file download is.
        $this->auditService->record(
            user: $request->user(),
            entityName: 'PAYROLL_IMPORT',
            entityId: $payrollImport->payroll_import_id,
            action: 'EXPORT',
            newValues: ['export' => 'source_file'],
        );

        return response($payrollImport->source_file, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$payrollImport->source_filename}\"",
        ]);
    }
}
