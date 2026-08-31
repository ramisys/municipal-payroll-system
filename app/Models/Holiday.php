<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.3 / §5.1 — HOLIDAY. Maintained under UC-03 (FR-0.3
// behavior 3), not UC-04 — the holiday calendar belongs to the payroll
// calendar screen, not the FR-0.4 reference-list screen (FR-0.4 names
// only departments, positions, employment statuses, leave types, earning
// and deduction types).
class Holiday extends Model
{
    protected $primaryKey = 'holiday_id';

    protected $fillable = [
        'holiday_date',
        'holiday_name',
        'holiday_type',
        'is_local',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_local' => 'boolean',
        ];
    }
}
