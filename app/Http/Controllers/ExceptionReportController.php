<?php

namespace App\Http\Controllers;

use App\Models\ExceptionInstance;
use App\Models\PayrollRun;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\ExceptionEvaluationException;
use App\Services\ExceptionEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC-20 · Review exception report — FR-4.1. Read access is
// exception_report.view (PO, Approver, Admin, Viewer). Acknowledgment of
// warnings is limited to Payroll Officer and Approver (UC-20 A3 refuses
// Admin/Viewer); gated here on payroll_run.create_import or
// payroll_run.approve_return rather than inventing a new permission key.
class ExceptionReportController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
        private readonly ExceptionEvaluator $exceptionEvaluator,
    ) {}

    public function show(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'exception_report.view');

        $exceptions = $payrollRun->exceptions()
            ->with(['payrollLine.employee', 'acknowledgedBy'])
            ->orderByRaw("CASE severity WHEN 'BLOCKING' THEN 0 ELSE 1 END")
            ->orderBy('rule_code')
            ->orderBy('exception_instance_id')
            ->get();

        $blockingOpen = $exceptions->where('severity', 'BLOCKING')->where('is_resolved', false)->count();
        $warningOpen = $exceptions->where('severity', 'WARNING')->where('is_resolved', false)->count();

        return view('exception-report.show', [
            'run' => $payrollRun->load('period'),
            'exceptions' => $exceptions,
            'blockingOpen' => $blockingOpen,
            'warningOpen' => $warningOpen,
            'canAcknowledge' => $this->canAcknowledge($request),
            'canManageImport' => $this->authorizationService->can($request->user(), 'payroll_run.create_import'),
            'resolutionPath' => fn (string $code) => $this->exceptionEvaluator->resolutionPath($code),
        ]);
    }

    public function acknowledge(Request $request, PayrollRun $payrollRun, ExceptionInstance $exception): RedirectResponse
    {
        if (! $this->canAcknowledge($request)) {
            $this->authorizationService->authorize($request->user(), 'payroll_run.create_import');
        }

        if ((int) $exception->payroll_run_id !== (int) $payrollRun->payroll_run_id) {
            abort(404);
        }

        $data = $request->validate([
            'acknowledgment_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->exceptionEvaluator->acknowledge($exception, $request->user(), $data['acknowledgment_reason']);
        } catch (ExceptionEvaluationException $e) {
            return back()->withErrors(['acknowledgment_reason' => $e->getMessage()]);
        }

        $this->auditService->record(
            user: $request->user(),
            entityName: 'EXCEPTION_INSTANCE',
            entityId: $exception->exception_instance_id,
            action: 'UPDATE',
            previousValues: ['is_resolved' => false],
            newValues: [
                'is_resolved' => true,
                'rule_code' => $exception->rule_code,
                'acknowledgment_reason' => $data['acknowledgment_reason'],
            ],
        );

        return back()->with('status', "Warning {$exception->rule_code} acknowledged.");
    }

    private function canAcknowledge(Request $request): bool
    {
        $user = $request->user();

        return $this->authorizationService->can($user, 'payroll_run.create_import')
            || $this->authorizationService->can($user, 'payroll_run.approve_return');
    }
}
