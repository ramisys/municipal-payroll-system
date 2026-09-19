<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.6 — INTEGRITY_ANCHOR. Queued PENDING on finalize/reverse
// (AC-4.5.5); ledger transmit is W14.
class IntegrityAnchor extends Model
{
    protected $primaryKey = 'integrity_anchor_id';

    protected $fillable = [
        'scope_type',
        'payroll_run_id',
        'reversal_record_id',
        'audit_log_from',
        'audit_log_to',
        'payload_hash',
        'hash_algorithm',
        'chain_position',
        'ledger_tx_ref',
        'ledger_block_ref',
        'anchor_status',
        'queued_at',
        'confirmed_at',
        'retry_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'retry_count' => 'integer',
            'chain_position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    /**
     * @return BelongsTo<ReversalRecord, $this>
     */
    public function reversalRecord(): BelongsTo
    {
        return $this->belongsTo(ReversalRecord::class, 'reversal_record_id', 'reversal_record_id');
    }
}
