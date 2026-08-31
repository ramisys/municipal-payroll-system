<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.1 / §5.1 — EMPLOYMENT_STATUS. FR-0.4 reference list.
class EmploymentStatus extends Model
{
    protected $primaryKey = 'employment_status_id';

    protected $fillable = [
        'status_name',
        'is_payroll_eligible',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_payroll_eligible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
