<?php

namespace App\Services;

use App\Exceptions\LedgerUnreachableException;
use App\Models\IntegrityAnchor;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\Log;

// system-architecture.md §6.7, §7.3 / UC-I6 / FR-6.3 / BR-36.
//
// Manages the transactional outbox transmission for integrity anchors.
// An anchor is created as PENDING in the payroll finalization or reversal transaction.
// After the transaction commits, this service transmits the hash asynchronously
// to the external permissioned Hyperledger Besu ledger via LedgerGateway.
//
// Guarantees:
// 1. Asynchronous transmission: An unreachable ledger NEVER throws into the caller
//    or disrupts payroll operations (AC-6.3.5).
// 2. Safe retries: A confirmed anchor is immutable; a failed transmission increments
//    retry_count and stays PENDING until retried.
// 3. Stalled detection: Anchors exceeding ANCHOR_RETRY_LIMIT are flagged on the UI.
class LedgerAnchorService
{
    public function __construct(
        private readonly LedgerGateway $ledgerGateway,
    ) {}

    /**
     * Attempt to transmit a pending anchor to the external ledger.
     *
     * @return bool True if confirmed; false if ledger unreachable or failed
     */
    public function transmitAnchor(IntegrityAnchor $anchor): bool
    {
        if ($anchor->anchor_status === 'CONFIRMED') {
            return true;
        }

        $ref = match ($anchor->scope_type) {
            'RUN' => "RUN:{$anchor->payroll_run_id}",
            'REVERSAL' => "REVERSAL:{$anchor->reversal_record_id}",
            'AUDIT_SEGMENT' => "AUDIT:{$anchor->audit_log_from}-{$anchor->audit_log_to}",
            default => "RECORD:{$anchor->integrity_anchor_id}",
        };

        try {
            $receipt = $this->ledgerGateway->anchor(
                payloadHash: $anchor->payload_hash,
                ref: $ref,
                chainPosition: $anchor->chain_position,
            );

            // Update only fields permitted by trg_integrity_anchors_restricted_update
            $anchor->update([
                'anchor_status' => 'CONFIRMED',
                'ledger_tx_ref' => $receipt['tx_hash'],
                'ledger_block_ref' => $receipt['block_number'],
                'confirmed_at' => now(),
            ]);

            Log::info("Integrity anchor #{$anchor->integrity_anchor_id} ({$ref}) confirmed on ledger: tx={$receipt['tx_hash']}");

            return true;
        } catch (LedgerUnreachableException $e) {
            $anchor->increment('retry_count');
            Log::warning("Ledger unreachable for anchor #{$anchor->integrity_anchor_id} ({$ref}), retry #{$anchor->retry_count}: {$e->getMessage()}");

            return false;
        } catch (\Throwable $e) {
            $anchor->increment('retry_count');
            Log::error("Transmission error for anchor #{$anchor->integrity_anchor_id} ({$ref}), retry #{$anchor->retry_count}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Process pending anchors in the transactional outbox in order of chain position.
     *
     * @return array{processed: int, confirmed: int, failed: int}
     */
    public function processOutbox(int $limit = 50): array
    {
        $pendingAnchors = IntegrityAnchor::query()
            ->where('anchor_status', 'PENDING')
            ->orderBy('chain_position')
            ->limit($limit)
            ->get();

        $processed = 0;
        $confirmed = 0;
        $failed = 0;

        foreach ($pendingAnchors as $anchor) {
            $processed++;
            $success = $this->transmitAnchor($anchor);
            if ($success) {
                $confirmed++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'confirmed' => $confirmed,
            'failed' => $failed,
        ];
    }

    /**
     * Check if an anchor has exceeded the retry threshold and should be reported as stalled.
     */
    public function isStalled(IntegrityAnchor $anchor): bool
    {
        if ($anchor->anchor_status !== 'PENDING') {
            return false;
        }

        $retryLimit = (int) SystemConfig::value('ANCHOR_RETRY_LIMIT', 5);

        return $anchor->retry_count >= $retryLimit;
    }

    /**
     * Get system retry limit.
     */
    public function getRetryLimit(): int
    {
        return (int) SystemConfig::value('ANCHOR_RETRY_LIMIT', 5);
    }
}
