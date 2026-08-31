<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.3 / §5.1 — LEAVE_TYPE. FR-0.4 reference list.
class LeaveType extends Model
{
    protected $primaryKey = 'leave_type_id';

    protected $fillable = [
        'leave_code',
        'leave_name',
        'is_paid',
        'annual_credits',
        'allows_negative_balance',
        'excludes_rest_days',
        'carryover_rule',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'annual_credits' => 'decimal:2',
            'allows_negative_balance' => 'boolean',
            'excludes_rest_days' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
