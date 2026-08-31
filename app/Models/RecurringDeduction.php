<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.2 / §5.1 — RECURRING_DEDUCTION. FR-1.2 behavior 4, UC-11
// step 4: an amount per deduction type with an effectivity date, ending
// (rather than deleting) when it stops applying (UC-11 A2). Read directly
// by WorksheetExportService as a reference column (AC-1.2.2) — this model
// is the maintenance path that fills that table.
class RecurringDeduction extends Model
{
    protected $primaryKey = 'recurring_deduction_id';

    protected $fillable = [
        'employee_id',
        'deduction_type_id',
        'amount',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * @return BelongsTo<DeductionType, $this>
     */
    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }
}
