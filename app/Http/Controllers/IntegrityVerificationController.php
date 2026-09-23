<?php

namespace App\Http\Controllers;

use App\Models\IntegrityAnchor;
use App\Models\IntegrityVerification;
use App\Models\PayrollRun;
use App\Services\AuthorizationService;
use App\Services\IntegrityVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC-31 · Verify payroll record integrity — FR-6.3.
// Actors: Approver, Admin, Viewer ('integrity.verify')
class IntegrityVerificationController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly IntegrityVerificationService $integrityVerificationService,
    ) {}

    /**
     * Display list of anchored payroll runs and their verification status.
     */
    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        $anchors = IntegrityAnchor::query()
            ->with([
                'run.period',
                'reversalRecord',
            ])
            ->orderByDesc('chain_position')
            ->get();

        return view('integrity.index', [
            'anchors' => $anchors,
        ]);
    }

    /**
     * Recompute hash and verify integrity of a finalized run (UC-31).
     */
    public function verify(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        try {
            $outcome = $this->integrityVerificationService->verifyRun($payrollRun, $request->user()->user_id);

            $msgType = $outcome['result'] === 'MATCH' ? 'success' : 'error';
            $msg = "Integrity check for run #{$payrollRun->payroll_run_id}: {$outcome['result']} — {$outcome['remarks']}";

            return redirect()->route('integrity.show', $payrollRun)->with($msgType, $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Verification failed: {$e->getMessage()}");
        }
    }

    /**
     * Show detailed anchor information and verification history for a run.
     */
    public function show(Request $request, PayrollRun $payrollRun): View
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        $anchor = IntegrityAnchor::query()
            ->where('scope_type', 'RUN')
            ->where('payroll_run_id', $payrollRun->payroll_run_id)
            ->firstOrFail();

        $verifications = IntegrityVerification::query()
            ->where('integrity_anchor_id', $anchor->integrity_anchor_id)
            ->with('performer')
            ->orderByDesc('performed_at')
            ->get();

        return view('integrity.show', [
            'run' => $payrollRun,
            'anchor' => $anchor,
            'verifications' => $verifications,
        ]);
    }
}
