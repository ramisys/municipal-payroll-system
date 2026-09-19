<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.4 / §5.1 — REVERSAL_RECORD. FR-4.5 / UC-26 / BR-24.
// One per run; survives the run returning to Draft after reverse.
class ReversalRecord extends Model
{
    protected $primaryKey = 'reversal_record_id';

    protected $fillable = [
        'payroll_run_id',
        'original_total_gross',
        'original_total_net',
        'original_employee_count',
        'reason',
        'reversed_by',
        'reversed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'original_total_gross' => 'decimal:2',
            'original_total_net' => 'decimal:2',
            'original_employee_count' => 'integer',
            'reversed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by', 'user_id');
    }
}
