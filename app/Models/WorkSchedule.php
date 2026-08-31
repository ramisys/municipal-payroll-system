<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.3 / §5.1 — WORK_SCHEDULE. A dated version chain like
// COMPENSATION_PROFILE and EMPLOYMENT_DETAIL. No use case in
// use-case-model.md maintains this directly; EmployeeController opens the
// first row at registration from the SYSTEM_CONFIG standard-hours default
// (AD-17-style documented default), and AttendanceImportService reads the
// row in force on the attendance date to derive BR-03/BR-04 figures.
class WorkSchedule extends Model
{
    protected $primaryKey = 'work_schedule_id';

    protected $fillable = [
        'employee_id',
        'standard_hours_per_day',
        'rest_days',
        'scheduled_time_in',
        'scheduled_time_out',
        'unpaid_break_hours',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'standard_hours_per_day' => 'decimal:2',
            'unpaid_break_hours' => 'decimal:2',
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
     * @return array<int, string> uppercase day names, e.g. ['SAT', 'SUN']
     */
    public function restDayList(): array
    {
        return array_filter(array_map('trim', explode(',', strtoupper($this->rest_days))));
    }
}
