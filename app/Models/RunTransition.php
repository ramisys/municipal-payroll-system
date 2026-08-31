<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.4 / §5.1 — RUN_TRANSITION. Append-only history of every
// state change a payroll run goes through (§4.4 prose); this slice only
// ever writes the null->DRAFT (UC-17 step 4) and DRAFT->CANCELLED (UC-17
// A2) transitions, since M5's submit/approve/return/finalize states are
// out of the slice (pre-oral-demonstration-plan.md §4.1).
class RunTransition extends Model
{
    protected $primaryKey = 'run_transition_id';

    protected $fillable = [
        'payroll_run_id',
        'from_status',
        'to_status',
        'performed_by',
        'performed_at',
        'reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }
}
