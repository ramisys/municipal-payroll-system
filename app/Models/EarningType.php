<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.2 / §5.1 — EARNING_TYPE. FR-0.4 reference list, BR-12.
// AC-0.4.3 — no earning type may default silently: is_taxable and
// is_thirteenth_month_base are always required as an explicit choice by
// ReferenceDataController, never left at the column's own default.
class EarningType extends Model
{
    protected $primaryKey = 'earning_type_id';

    protected $fillable = [
        'earning_code',
        'earning_name',
        'is_taxable',
        'is_thirteenth_month_base',
        'is_recurring_allowed',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_thirteenth_month_base' => 'boolean',
            'is_recurring_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
