<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.4 / §5.1 — DEDUCTION_LINE. `statutory_schedule_id` is
// populated only where an employer share was derived rather than imported
// (§4.4 prose); this slice never derives one (FR-2.3/UC-I5 out per
// pre-oral-demonstration-plan.md §4.1), so it stays null on every row this
// application writes.
class DeductionLine extends Model
{
    protected $primaryKey = 'deduction_line_id';

    protected $fillable = [
        'payroll_line_id',
        'deduction_type_id',
        'statutory_schedule_id',
        'employee_share',
        'employer_share',
        'amount',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_share' => 'decimal:2',
            'employer_share' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PayrollLine, $this>
     */
    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class, 'payroll_line_id', 'payroll_line_id');
    }

    /**
     * @return BelongsTo<DeductionType, $this>
     */
    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }
}
