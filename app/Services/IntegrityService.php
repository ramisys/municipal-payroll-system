<?php

namespace App\Services;

use App\Models\IntegrityAnchor;
use App\Models\PayrollRun;
use App\Models\ReversalRecord;

// system-architecture.md §6.7, §7.3 / FR-6.3 / UC-I6 / BR-36.
//
// Computes canonical cryptographic fingerprints (payload_hash) for finalized
// payroll runs and reversal records, and writes transactional outbox rows
// in INTEGRITY_ANCHOR with status = 'PENDING'.
//
// Transmission to the external ledger is asynchronous (W14); this service
// ensures the promise to anchor commits in the same transaction as finalization
// or reversal (AC-4.5.5, AC-6.3.1, AC-6.3.5), so a ledger outage never blocks
// payroll operations.
class IntegrityService
{
    /**
     * Compute deterministic payload hash for a finalized run and write
     * a pending integrity anchor row in the current transaction.
     */
    public function queueRunAnchor(PayrollRun $run, int $actorUserId): IntegrityAnchor
    {
        $payloadHash = $this->computeRunPayloadHash($run);

        $chainPosition = ((int) IntegrityAnchor::query()->max('chain_position')) + 1;

        return IntegrityAnchor::create([
            'scope_type' => 'RUN',
            'payroll_run_id' => $run->payroll_run_id,
            'reversal_record_id' => null,
            'audit_log_from' => null,
            'audit_log_to' => null,
            'payload_hash' => $payloadHash,
            'hash_algorithm' => 'SHA-256',
            'chain_position' => $chainPosition,
            'ledger_tx_ref' => null,
            'ledger_block_ref' => null,
            'anchor_status' => 'PENDING',
            'queued_at' => now(),
            'confirmed_at' => null,
            'retry_count' => 0,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }

    /**
     * Compute deterministic payload hash for a reversal record and write
     * a pending integrity anchor row in the current transaction.
     */
    public function queueReversalAnchor(ReversalRecord $record, int $actorUserId): IntegrityAnchor
    {
        $payloadHash = $this->computeReversalPayloadHash($record);

        $chainPosition = ((int) IntegrityAnchor::query()->max('chain_position')) + 1;

        return IntegrityAnchor::create([
            'scope_type' => 'REVERSAL',
            'payroll_run_id' => null,
            'reversal_record_id' => $record->reversal_record_id,
            'audit_log_from' => null,
            'audit_log_to' => null,
            'payload_hash' => $payloadHash,
            'hash_algorithm' => 'SHA-256',
            'chain_position' => $chainPosition,
            'ledger_tx_ref' => null,
            'ledger_block_ref' => null,
            'anchor_status' => 'PENDING',
            'queued_at' => now(),
            'confirmed_at' => null,
            'retry_count' => 0,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }

    /**
     * Canonical hash over run totals, payroll lines, and bound versions (AC-4.5.5, FR-6.3).
     */
    public function computeRunPayloadHash(PayrollRun $run): string
    {
        $currentImport = $run->currentImport();

        $lines = $run->lines()
            ->where('payroll_import_id', $currentImport?->payroll_import_id)
            ->orderBy('payroll_line_id')
            ->get();

        $linesData = $lines->map(function ($line) {
            return [
                'payroll_line_id' => $line->payroll_line_id,
                'employee_id' => $line->employee_id,
                'payroll_import_id' => $line->payroll_import_id,
                'compensation_profile_id' => $line->compensation_profile_id,
                'gross_pay' => (string) $line->gross_pay,
                'total_deductions' => (string) $line->total_deductions,
                'net_pay' => (string) $line->net_pay,
            ];
        })->all();

        $payload = [
            'payroll_run_id' => $run->payroll_run_id,
            'payroll_period_id' => $run->payroll_period_id,
            'run_type' => $run->run_type,
            'population_scope' => $run->population_scope,
            'employee_count' => (int) $run->employee_count,
            'total_gross' => (string) $run->total_gross,
            'total_deductions' => (string) $run->total_deductions,
            'total_net' => (string) $run->total_net,
            'current_import_id' => $currentImport?->payroll_import_id,
            'source_sha256' => $currentImport?->source_sha256,
            'lines' => $linesData,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Canonical hash over reversal record's original figures and reason (AC-4.5.2, FR-6.3).
     */
    public function computeReversalPayloadHash(ReversalRecord $record): string
    {
        $payload = [
            'reversal_record_id' => $record->reversal_record_id,
            'payroll_run_id' => $record->payroll_run_id,
            'original_total_gross' => (string) $record->original_total_gross,
            'original_total_net' => (string) $record->original_total_net,
            'original_employee_count' => (int) $record->original_employee_count,
            'reason' => $record->reason,
            'reversed_by' => (int) $record->reversed_by,
            'reversed_at' => $record->reversed_at?->format('Y-m-d H:i:s'),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
