<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.4 / §5.1 — PAYSLIP_ISSUANCE. Records every payslip
// events: an ORIGINAL row per employee when a finalized run's payslips are
// first generated (UC-27), and one REPRINT row per explicit reissue
// (UC-28, AC-3.4.3). The table is the BR-24 evidence: PayrollRunService's
// reversal guard checks that any row exists before allowing reverse past
// the pay date.
class PayslipIssuance extends Model
{
    protected $primaryKey = 'payslip_issuance_id';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'issuance_type',
        'issued_by',
        'issued_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by', 'user_id');
    }
}
