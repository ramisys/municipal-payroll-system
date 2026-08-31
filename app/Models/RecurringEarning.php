<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.2 / §5.1 — RECURRING_EARNING. FR-1.2 behavior 3, UC-11
// step 3: an amount per earning type with an effectivity date, ending
// (rather than deleting) when it stops applying (UC-11 A2).
class RecurringEarning extends Model
{
    protected $primaryKey = 'recurring_earning_id';

    protected $fillable = [
        'employee_id',
        'earning_type_id',
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
     * @return BelongsTo<EarningType, $this>
     */
    public function earningType(): BelongsTo
    {
        return $this->belongsTo(EarningType::class, 'earning_type_id', 'earning_type_id');
    }
}
