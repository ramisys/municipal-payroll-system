<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.3 / §5.1 — ATTENDANCE_RECORD. Stores raw punches and
// derived figures together (§4.3 prose) so a later schedule change never
// silently restates history. Written only by AttendanceImportService
// (UC-13, all-or-nothing commit) this week; manual exception encoding
// (UC-14) is a later week.
class AttendanceRecord extends Model
{
    protected $primaryKey = 'attendance_record_id';

    protected $fillable = [
        'employee_id',
        'attendance_type_id',
        'work_date',
        'time_in',
        'time_out',
        'hours_worked',
        'late_minutes',
        'undertime_minutes',
        'overtime_hours',
        'night_diff_hours',
        'day_classification',
        'source',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours_worked' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'night_diff_hours' => 'decimal:2',
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
     * @return BelongsTo<AttendanceType, $this>
     */
    public function attendanceType(): BelongsTo
    {
        return $this->belongsTo(AttendanceType::class, 'attendance_type_id', 'attendance_type_id');
    }
}
