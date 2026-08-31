<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.2 / §5.1 — COMPENSATION_PROFILE. A dated version chain,
// never an overwrite (BR-08): a rate or coverage change closes the current
// open row (effective_to) and opens a new one, mirroring EMPLOYMENT_DETAIL.
class CompensationProfile extends Model
{
    protected $primaryKey = 'compensation_profile_id';

    protected $fillable = [
        'employee_id',
        'pay_basis',
        'basic_rate',
        'sss_covered',
        'philhealth_covered',
        'pagibig_covered',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sss_covered' => 'boolean',
            'philhealth_covered' => 'boolean',
            'pagibig_covered' => 'boolean',
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
}
