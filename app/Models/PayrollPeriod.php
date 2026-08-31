<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.4 / §5.1 — PAYROLL_PERIOD. Rows are produced by
// PayrollPeriodGenerationService (UC-03, BR-34), not created ad hoc.
class PayrollPeriod extends Model
{
    protected $primaryKey = 'payroll_period_id';

    protected $fillable = [
        'payroll_year',
        'period_no',
        'pay_frequency',
        'cutoff_start',
        'cutoff_end',
        'pay_date',
        'is_closed',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payroll_year' => 'integer',
            'period_no' => 'integer',
            'cutoff_start' => 'date',
            'cutoff_end' => 'date',
            'pay_date' => 'date',
            'is_closed' => 'boolean',
        ];
    }
}
