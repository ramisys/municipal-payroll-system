<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.3 / §5.1 — ATTENDANCE_TYPE. A maintained reference list
// (data-model.md §7 "entities added beyond the FRS §8.1 inventory") even
// though FR-0.4's own behavior text names only the six lists
// ReferenceDataController predates this with; ReferenceDataController
// carries it as a seventh $type, same deactivate/reactivate convention.
class AttendanceType extends Model
{
    protected $primaryKey = 'attendance_type_id';

    protected $fillable = [
        'attendance_code',
        'attendance_name',
        'counts_as_worked',
        'requires_punches',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'counts_as_worked' => 'boolean',
            'requires_punches' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
