<?php

namespace App\Http\Controllers;

use App\Models\IntegrityAnchor;
use App\Models\IntegrityVerification;
use App\Models\OrganizationProfile;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\ReversalRecord;
use App\Services\AuthorizationService;
use App\Services\IntegrityVerificationService;
use App\Services\LedgerAnchorService;
use App\Services\LedgerGateway;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

// UC-31 · Verify payroll record integrity — FR-6.3.
// Actors: Approver, Administrator, Viewer ('integrity.verify')
class IntegrityVerificationController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly IntegrityVerificationService $integrityVerificationService,
        private readonly LedgerAnchorService $ledgerAnchorService,
        private readonly LedgerGateway $ledgerGateway,
    ) {}

    /**
     * Display list of anchored payroll records, outbox queue metrics, and verification controls.
     */
    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        $query = IntegrityAnchor::query()
            ->with(['run.period', 'reversalRecord.run.period'])
            ->orderByDesc('chain_position');

        $statusFilter = $request->query('status', 'ALL');
        $scopeFilter = $request->query('scope', 'ALL');
        $retryLimit = $this->ledgerAnchorService->getRetryLimit();

        if ($statusFilter === 'CONFIRMED') {
            $query->where('anchor_status', 'CONFIRMED');
        } elseif ($statusFilter === 'PENDING') {
            $query->where('anchor_status', 'PENDING');
        } elseif ($statusFilter === 'STALLED') {
            $query->where('anchor_status', 'PENDING')->where('retry_count', '>=', $retryLimit);
        }

        if ($scopeFilter === 'RUN') {
            $query->where('scope_type', 'RUN');
        } elseif ($scopeFilter === 'REVERSAL') {
            $query->where('scope_type', 'REVERSAL');
        }

        $anchors = $query->paginate(20)->withQueryString();

        $metrics = [
            'total' => IntegrityAnchor::count(),
            'confirmed' => IntegrityAnchor::where('anchor_status', 'CONFIRMED')->count(),
            'pending' => IntegrityAnchor::where('anchor_status', 'PENDING')->count(),
            'stalled' => IntegrityAnchor::where('anchor_status', 'PENDING')->where('retry_count', '>=', $retryLimit)->count(),
            'ledger_online' => $this->ledgerGateway->isReachable(),
        ];

        $periods = PayrollPeriod::query()
            ->orderByDesc('payroll_year')
            ->orderByDesc('period_no')
            ->get();

        return view('integrity.index', [
            'anchors' => $anchors,
            'metrics' => $metrics,
            'statusFilter' => $statusFilter,
            'scopeFilter' => $scopeFilter,
            'retryLimit' => $retryLimit,
            'periods' => $periods,
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

            $msgType = match ($outcome['result']) {
                'MATCH' => 'success',
                'MISMATCH' => 'error',
                'UNVERIFIABLE' => 'warning',
            };
            $msg = "Integrity check for Run #{$payrollRun->payroll_run_id}: {$outcome['result']} — {$outcome['remarks']}";

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
            ->with('performer.role')
            ->orderByDesc('performed_at')
            ->get();

        $latestVerification = $verifications->first();

        // Read ledger hash directly for side-by-side inspection
        $ledgerHash = null;
        if ($anchor->anchor_status === 'CONFIRMED' && $this->ledgerGateway->isReachable()) {
            try {
                $ledgerHash = $this->ledgerGateway->readAnchoredHash($anchor->ledger_tx_ref ?? '', $anchor->payload_hash);
            } catch (\Throwable) {
                $ledgerHash = null;
            }
        }

        return view('integrity.show', [
            'run' => $payrollRun,
            'anchor' => $anchor,
            'verifications' => $verifications,
            'latestVerification' => $latestVerification,
            'ledgerHash' => $ledgerHash,
            'isStalled' => $this->ledgerAnchorService->isStalled($anchor),
            'retryLimit' => $this->ledgerAnchorService->getRetryLimit(),
        ]);
    }

    /**
     * Show detailed anchor information and verification history for a reversal record.
     */
    public function showReversal(Request $request, ReversalRecord $reversalRecord): View
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        $anchor = IntegrityAnchor::query()
            ->where('scope_type', 'REVERSAL')
            ->where('reversal_record_id', $reversalRecord->reversal_record_id)
            ->firstOrFail();

        $verifications = IntegrityVerification::query()
            ->where('integrity_anchor_id', $anchor->integrity_anchor_id)
            ->with('performer.role')
            ->orderByDesc('performed_at')
            ->get();

        $latestVerification = $verifications->first();

        $ledgerHash = null;
        if ($anchor->anchor_status === 'CONFIRMED' && $this->ledgerGateway->isReachable()) {
            try {
                $ledgerHash = $this->ledgerGateway->readAnchoredHash($anchor->ledger_tx_ref ?? '', $anchor->payload_hash);
            } catch (\Throwable) {
                $ledgerHash = null;
            }
        }

        return view('integrity.show-reversal', [
            'reversal' => $reversalRecord,
            'anchor' => $anchor,
            'verifications' => $verifications,
            'latestVerification' => $latestVerification,
            'ledgerHash' => $ledgerHash,
            'isStalled' => $this->ledgerAnchorService->isStalled($anchor),
            'retryLimit' => $this->ledgerAnchorService->getRetryLimit(),
        ]);
    }

    /**
     * Recompute hash and verify integrity of a reversal record (UC-31).
     */
    public function verifyReversal(Request $request, ReversalRecord $reversalRecord): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        try {
            $outcome = $this->integrityVerificationService->verifyReversal($reversalRecord, $request->user()->user_id);

            $msgType = match ($outcome['result']) {
                'MATCH' => 'success',
                'MISMATCH' => 'error',
                'UNVERIFIABLE' => 'warning',
            };
            $msg = "Integrity check for Reversal #{$reversalRecord->reversal_record_id}: {$outcome['result']} — {$outcome['remarks']}";

            return redirect()->route('integrity.reversals.show', $reversalRecord)->with($msgType, $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Verification failed: {$e->getMessage()}");
        }
    }

    /**
     * UC-31 A2: Verify all finalized runs in an entire payroll period.
     */
    public function verifyPeriod(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        try {
            $summary = $this->integrityVerificationService->verifyPeriod($payrollPeriod, $request->user()->user_id);

            $msg = "Period {$payrollPeriod->payroll_year}-{$payrollPeriod->period_no} verification complete: {$summary['total_runs']} run(s) checked — {$summary['matches']} MATCH, {$summary['mismatches']} MISMATCH, {$summary['unverifiable']} UNVERIFIABLE.";
            $msgType = $summary['mismatches'] > 0 ? 'error' : ($summary['unverifiable'] > 0 ? 'warning' : 'success');

            return redirect()->route('integrity.index')->with($msgType, $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Period verification failed: {$e->getMessage()}");
        }
    }

    /**
     * UC-31 A1 / BR-35: Verify audit log hash chain.
     */
    public function verifyAuditChain(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        try {
            $outcome = $this->integrityVerificationService->verifyAuditChain($request->user()->user_id);

            $msgType = $outcome['intact'] ? 'success' : 'error';

            return redirect()->route('integrity.index')->with($msgType, $outcome['message']);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Audit chain verification failed: {$e->getMessage()}");
        }
    }

    /**
     * Administrator manual trigger to flush the pending anchor transactional outbox.
     */
    public function processOutbox(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        try {
            $results = $this->ledgerAnchorService->processOutbox(50);

            $msg = "Outbox processed: {$results['processed']} anchor(s) evaluated — {$results['confirmed']} confirmed on Hyperledger Besu, {$results['failed']} failed/retrying.";
            $msgType = $results['failed'] > 0 ? 'warning' : 'success';

            return redirect()->route('integrity.index')->with($msgType, $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Outbox processing failed: {$e->getMessage()}");
        }
    }

    /**
     * UC-31 A3: Export formal DomPDF cryptographic verification certificate.
     */
    public function exportPdf(Request $request, IntegrityVerification $verification): Response
    {
        $this->authorizationService->authorize($request->user(), 'integrity.verify');

        $verification->load(['anchor.run.period', 'anchor.reversalRecord.run.period', 'performer.role']);
        $org = OrganizationProfile::current();

        $pdf = Pdf::loadView('integrity.pdf.certificate', [
            'v' => $verification,
            'anchor' => $verification->anchor,
            'org' => $org,
        ])->setPaper('a4', 'portrait');

        $filename = "integrity-certificate-{$verification->integrity_verification_id}.pdf";

        return $pdf->download($filename);
    }
}
