<?php

namespace App\Services;

use App\Exceptions\LedgerUnreachableException;
use App\Models\IntegrityAnchor;
use App\Models\IntegrityVerification;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\ReversalRecord;
use App\Models\User;
use InvalidArgumentException;

// system-architecture.md §6.7 / behavioral-diagrams.md Fig. 8 / UC-31 / FR-6.3.
// Recomputes cryptographic hashes against live records and verifies them against
// both local integrity anchors and Hyperledger Besu external ledger fingerprints.
//
// Guaranteed: Read-only check. Never alters payroll records. Records verification
// outcome permanently in INTEGRITY_VERIFICATION (append-only) (AC-6.3.7).
//
// Distinct outcomes (Figure 8):
// - MATCH: Record is byte-for-byte what was anchored.
// - MISMATCH: Tampering detected (E1, E4). Never auto-resolved and never re-anchored.
// - UNVERIFIABLE: Anchor is still pending (E2) or ledger is unreachable (E3).
class IntegrityVerificationService
{
    public function __construct(
        private readonly IntegrityService $integrityService,
        private readonly AuditService $auditService,
        private readonly LedgerGateway $ledgerGateway,
    ) {}

    /**
     * Verify integrity of a payroll run (UC-31).
     *
     * @return array{
     *     result: 'MATCH'|'MISMATCH'|'UNVERIFIABLE',
     *     recomputed_hash: string,
     *     anchored_hash: string,
     *     ledger_hash: ?string,
     *     anchor_status: string,
     *     ledger_tx_ref: ?string,
     *     chain_position: int,
     *     queued_at: string,
     *     verification_id: int,
     *     remarks: string,
     *     failure_position: ?string
     * }
     */
    public function verifyRun(PayrollRun $run, int $actorUserId): array
    {
        $anchor = IntegrityAnchor::query()
            ->where('scope_type', 'RUN')
            ->where('payroll_run_id', $run->payroll_run_id)
            ->first();

        if ($anchor === null) {
            throw new InvalidArgumentException("No integrity anchor exists for payroll run #{$run->payroll_run_id}. Only finalized runs are anchored.");
        }

        // Recompute hash using the canonical anchored rule (AC-4.5.5, FR-6.3)
        $recomputedHash = $this->integrityService->computeRunPayloadHash($run);

        return $this->evaluateAndPersistAnchor(
            anchor: $anchor,
            recomputedHash: $recomputedHash,
            actorUserId: $actorUserId,
            scope: 'RUN',
            scopeId: $run->payroll_run_id,
        );
    }

    /**
     * Verify integrity of a reversal record (UC-31).
     */
    public function verifyReversal(ReversalRecord $record, int $actorUserId): array
    {
        $anchor = IntegrityAnchor::query()
            ->where('scope_type', 'REVERSAL')
            ->where('reversal_record_id', $record->reversal_record_id)
            ->first();

        if ($anchor === null) {
            throw new InvalidArgumentException("No integrity anchor exists for reversal record #{$record->reversal_record_id}.");
        }

        $recomputedHash = $this->integrityService->computeReversalPayloadHash($record);

        return $this->evaluateAndPersistAnchor(
            anchor: $anchor,
            recomputedHash: $recomputedHash,
            actorUserId: $actorUserId,
            scope: 'REVERSAL',
            scopeId: $record->reversal_record_id,
        );
    }

    /**
     * Handle UC-31 E4: Record not found in MySQL despite existing anchor.
     * Reported as MISMATCH, not as a missing record.
     */
    public function verifyMissingRecord(IntegrityAnchor $anchor, int $actorUserId): array
    {
        $result = 'MISMATCH';
        $failurePosition = 'RECORD_DELETED';
        $remarks = "Record not found in database! An anchor (#{$anchor->chain_position}) exists for {$anchor->scope_type} #{$anchor->payroll_run_id}, but the database row is missing. Alteration detected (UC-31 E4).";
        $recomputedHash = str_repeat('0', 64);

        $verification = IntegrityVerification::create([
            'integrity_anchor_id' => $anchor->integrity_anchor_id,
            'performed_by' => $actorUserId,
            'performed_at' => now(),
            'recomputed_hash' => $recomputedHash,
            'result' => $result,
            'failure_position' => $failurePosition,
            'remarks' => $remarks,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);

        if ($user = User::find($actorUserId)) {
            $this->auditService->record(
                $user,
                'INTEGRITY_VERIFICATION',
                $verification->integrity_verification_id,
                'CREATE',
                null,
                [
                    'scope' => $anchor->scope_type,
                    'result' => $result,
                    'failure_position' => $failurePosition,
                    'remarks' => $remarks,
                ]
            );
        }

        return [
            'result' => $result,
            'recomputed_hash' => $recomputedHash,
            'anchored_hash' => $anchor->payload_hash,
            'ledger_hash' => null,
            'anchor_status' => $anchor->anchor_status,
            'ledger_tx_ref' => $anchor->ledger_tx_ref,
            'chain_position' => $anchor->chain_position,
            'queued_at' => $anchor->queued_at->toIso8601String(),
            'verification_id' => $verification->integrity_verification_id,
            'remarks' => $remarks,
            'failure_position' => $failurePosition,
        ];
    }

    /**
     * Core evaluation logic implementing the Figure 8 decision tree.
     */
    private function evaluateAndPersistAnchor(
        IntegrityAnchor $anchor,
        string $recomputedHash,
        int $actorUserId,
        string $scope,
        int $scopeId,
    ): array {
        $anchoredHash = strtolower($anchor->payload_hash);
        $recomputedHash = strtolower($recomputedHash);
        $ledgerHash = null;
        $failurePosition = null;

        // Branch 1: Anchor status is PENDING (UC-31 E2)
        if ($anchor->anchor_status === 'PENDING') {
            $result = 'UNVERIFIABLE';
            $failurePosition = 'PENDING_ANCHOR';
            $queuedAge = $anchor->queued_at->diffForHumans();
            $remarks = "Not yet anchored. Fingerprint queued {$queuedAge} (retries: {$anchor->retry_count}). This is an absence of evidence, not a failure of integrity (UC-31 E2).";
        } else {
            // Branch 2: Anchor is CONFIRMED — query external Hyperledger Besu node (Figure 8)
            $isReachable = false;
            try {
                $isReachable = $this->ledgerGateway->isReachable();
                if ($isReachable) {
                    $ledgerHash = $this->ledgerGateway->readAnchoredHash(
                        txRef: $anchor->ledger_tx_ref ?? '',
                        payloadHash: $anchoredHash
                    );
                }
            } catch (LedgerUnreachableException) {
                $isReachable = false;
            } catch (\Throwable) {
                $isReachable = false;
            }

            if (! $isReachable) {
                // Branch 2a: Ledger unreachable (UC-31 E3)
                $result = 'UNVERIFIABLE';
                $failurePosition = 'LEDGER_UNREACHABLE';
                $remarks = 'Verification unavailable. External permissioned ledger is unreachable. This is an absence of connectivity, not a mismatch (UC-31 E3). Payroll operations are unaffected.';
            } else {
                // Branch 2b: Ledger responded with hash
                $normalizedLedgerHash = $ledgerHash ? strtolower(trim($ledgerHash)) : null;

                // Compare recomputed hash against anchored and ledger hashes
                $matchesLocal = ($recomputedHash === $anchoredHash);
                $matchesLedger = ($normalizedLedgerHash !== null && $recomputedHash === $normalizedLedgerHash);

                if ($matchesLocal && $matchesLedger) {
                    $result = 'MATCH';
                    $failurePosition = null;
                    $remarks = 'Record unaltered since anchoring. Live hash matches local anchor and external ledger byte-for-byte.';
                } else {
                    $result = 'MISMATCH';
                    $failurePosition = 'PAYLOAD_DIVERGENCE';
                    $remarks = 'Hash divergence detected! Finalized record altered outside application rules (UC-31 E1). System will never auto-resolve or re-anchor.';
                }
            }
        }

        $remarks = mb_substr($remarks, 0, 255);

        // Persist verification result (append-only table)
        $verification = IntegrityVerification::create([
            'integrity_anchor_id' => $anchor->integrity_anchor_id,
            'performed_by' => $actorUserId,
            'performed_at' => now(),
            'recomputed_hash' => $recomputedHash,
            'result' => $result,
            'failure_position' => $failurePosition,
            'remarks' => $remarks,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);

        if ($user = User::find($actorUserId)) {
            $this->auditService->record(
                $user,
                'INTEGRITY_VERIFICATION',
                $verification->integrity_verification_id,
                'CREATE',
                null,
                [
                    'scope' => $scope,
                    'scope_id' => $scopeId,
                    'result' => $result,
                    'recomputed_hash' => $recomputedHash,
                    'anchored_hash' => $anchoredHash,
                    'ledger_hash' => $ledgerHash,
                    'failure_position' => $failurePosition,
                ]
            );
        }

        return [
            'result' => $result,
            'recomputed_hash' => $recomputedHash,
            'anchored_hash' => $anchoredHash,
            'ledger_hash' => $ledgerHash,
            'anchor_status' => $anchor->anchor_status,
            'ledger_tx_ref' => $anchor->ledger_tx_ref,
            'chain_position' => $anchor->chain_position,
            'queued_at' => $anchor->queued_at->toIso8601String(),
            'verification_id' => $verification->integrity_verification_id,
            'remarks' => $remarks,
            'failure_position' => $failurePosition,
        ];
    }

    /**
     * UC-31 A2: Verify an entire payroll period.
     *
     * @return array{
     *     period_id: int,
     *     total_runs: int,
     *     matches: int,
     *     mismatches: int,
     *     unverifiable: int,
     *     runs: array<int, array<string, mixed>>
     * }
     */
    public function verifyPeriod(PayrollPeriod $period, int $actorUserId): array
    {
        $finalizedRuns = PayrollRun::query()
            ->where('payroll_period_id', $period->payroll_period_id)
            ->where('run_status', 'FINALIZED')
            ->get();

        $matches = 0;
        $mismatches = 0;
        $unverifiable = 0;
        $runResults = [];

        foreach ($finalizedRuns as $run) {
            $outcome = $this->verifyRun($run, $actorUserId);
            $runResults[$run->payroll_run_id] = $outcome;

            match ($outcome['result']) {
                'MATCH' => $matches++,
                'MISMATCH' => $mismatches++,
                'UNVERIFIABLE' => $unverifiable++,
            };
        }

        return [
            'period_id' => $period->payroll_period_id,
            'total_runs' => count($finalizedRuns),
            'matches' => $matches,
            'mismatches' => $mismatches,
            'unverifiable' => $unverifiable,
            'runs' => $runResults,
        ];
    }

    /**
     * UC-31 A1 / BR-35 / AC-6.1.5: Walk and verify the entire audit log hash chain.
     *
     * @return array{
     *     intact: bool,
     *     broken_at: ?int,
     *     checked: int,
     *     message: string
     * }
     */
    public function verifyAuditChain(int $actorUserId): array
    {
        $result = $this->auditService->verifyChain();

        $message = $result['intact']
            ? "Audit log hash chain is completely INTACT across all {$result['checked']} recorded entries (BR-35)."
            : "Audit log chain failure detected at log ID #{$result['broken_at']}. Tampering or deletion detected (AC-6.3.4).";

        if ($user = User::find($actorUserId)) {
            $this->auditService->record(
                $user,
                'AUDIT_CHAIN_VERIFICATION',
                $result['broken_at'],
                'CREATE',
                null,
                [
                    'intact' => $result['intact'],
                    'broken_at' => $result['broken_at'],
                    'checked_entries' => $result['checked'],
                ]
            );
        }

        return [
            'intact' => $result['intact'],
            'broken_at' => $result['broken_at'],
            'checked' => $result['checked'],
            'message' => $message,
        ];
    }
}
