<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.1 / §5.1 — EMPLOYMENT_DETAIL. Dated version rows: a
// transfer (UC-09 A1) closes the current row (effective_to) and opens a
// new one rather than overwriting department/position/status in place —
// the same pattern COMPENSATION_PROFILE uses for BR-08, applied here so a
// past payroll run keeps reporting the department the employee actually
// belonged to at the time (§4.1 prose).
class EmploymentDetail extends Model
{
    protected $primaryKey = 'employment_detail_id';

    protected $fillable = [
        'employee_id',
        'department_id',
        'position_id',
        'employment_status_id',
        'date_hired',
        'effective_from',
        'effective_to',
        'separation_date',
        'separation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_hired' => 'date',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'separation_date' => 'date',
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
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'position_id');
    }

    /**
     * @return BelongsTo<EmploymentStatus, $this>
     */
    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class, 'employment_status_id', 'employment_status_id');
    }
}
