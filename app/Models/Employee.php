<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// data-model.md §4.1 / §5.1 — EMPLOYEE. Identity only (§4.1 prose); every
// field that changes over an employment — department, position, status,
// separation — lives on EMPLOYMENT_DETAIL as dated rows, not here (FR-1.1
// behavior 4/5, BR-08 pattern).
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $primaryKey = 'employee_id';

    protected $fillable = [
        'employee_no',
        'last_name',
        'first_name',
        'middle_name',
        'birth_date',
        'sex',
        'civil_status',
        'contact_no',
        'address',
        'sss_no',
        'philhealth_no',
        'pagibig_mid',
        'tin',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmploymentDetail, $this>
     */
    public function employmentDetails(): HasMany
    {
        return $this->hasMany(EmploymentDetail::class, 'employee_id', 'employee_id');
    }

    /**
     * The open-ended employment_detail row (effective_to IS NULL) — the
     * one in force as of today. A deactivated employee's current row also
     * carries the separation_date/separation_reason (UC-10).
     *
     * @return HasOne<EmploymentDetail, $this>
     */
    public function currentEmploymentDetail(): HasOne
    {
        return $this->hasOne(EmploymentDetail::class, 'employee_id', 'employee_id')
            ->whereNull('effective_to')
            ->latest('effective_from');
    }

    public function fullName(): string
    {
        $middle = $this->middle_name !== null && $this->middle_name !== '' ? " {$this->middle_name} " : ' ';

        return trim("{$this->first_name}{$middle}{$this->last_name}");
    }
}
