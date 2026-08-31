<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.2 / §5.1 — DEDUCTION_TYPE. FR-0.4 reference list, BR-25.
class DeductionType extends Model
{
    protected $primaryKey = 'deduction_type_id';

    protected $fillable = [
        'deduction_code',
        'deduction_name',
        'is_statutory',
        'statutory_agency',
        'participates_in_floor_check',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_statutory' => 'boolean',
            'participates_in_floor_check' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
