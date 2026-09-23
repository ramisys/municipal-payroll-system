<?php

namespace App\Services;

use App\Models\IntegrityAnchor;
use App\Models\IntegrityVerification;
use App\Models\PayrollRun;
use App\Models\User;
use InvalidArgumentException;

// system-architecture.md §6.7 / behavioral-diagrams.md Fig. 8 / UC-31 / FR-6.3.
// Recomputes cryptographic hashes against live records and verifies them against
// anchored payload hashes in INTEGRITY_ANCHOR.
//
// Guaranteed: Read-only check. Never alters payroll records. Records verification
// outcome permanently in INTEGRITY_VERIFICATION (append-only).
class IntegrityVerificationService
{
    public function __construct(
        private readonly IntegrityService $integrityService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Verify integrity of a payroll run (UC-31).
     *
     * @return array{
     *     result: 'MATCH'|'MISMATCH'|'UNVERIFIABLE',
     *     recomputed_hash: string,
     *     anchored_hash: string,
     *     anchor_status: string,
     *     chain_position: int,
     *     queued_at: string,
     *     verification_id: int
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
        $anchoredHash = $anchor->payload_hash;

        // Determine outcome
        if ($recomputedHash === $anchoredHash) {
            $result = 'MATCH';
            $remarks = 'Record unaltered since anchoring. Live hash matches anchored fingerprint byte-for-byte.';
            $failurePosition = null;
        } else {
            $result = 'MISMATCH';
            $remarks = "Hash divergence detected! Recomputed hash {$recomputedHash} differs from anchored hash {$anchoredHash}.";
            $failurePosition = 'PAYLOAD_DIVERGENCE';
        }

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
                    'scope' => 'RUN',
                    'payroll_run_id' => $run->payroll_run_id,
                    'result' => $result,
                    'recomputed_hash' => $recomputedHash,
                    'anchored_hash' => $anchoredHash,
                ]
            );
        }

        return [
            'result' => $result,
            'recomputed_hash' => $recomputedHash,
            'anchored_hash' => $anchoredHash,
            'anchor_status' => $anchor->anchor_status,
            'chain_position' => $anchor->chain_position,
            'queued_at' => $anchor->queued_at->toIso8601String(),
            'verification_id' => $verification->integrity_verification_id,
            'remarks' => $remarks,
        ];
    }
}
