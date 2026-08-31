<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// data-model.md §4.4 / §5.1 — PAYROLL_LINE. Every figure here is imported,
// not computed (CR-01). trg_payroll_lines_reconcile_ins/upd (Sprint 0's
// business-rule-triggers migration) require a row to be inserted with zero
// gross_pay/total_deductions and only set to a real total by an UPDATE
// issued after every EARNING_LINE/DEDUCTION_LINE child exists — see
// PayrollImportService, which is the only writer of this model outside the
// IntakeFidelityHarness evidence path.
class PayrollLine extends Model
{
    protected $primaryKey = 'payroll_line_id';

    protected $fillable = [
        'payroll_run_id',
        'payroll_import_id',
        'employee_id',
        'compensation_profile_id',
        'days_worked',
        'hours_worked',
        'basic_pay',
        'gross_pay',
        'taxable_compensation',
        'total_deductions',
        'net_pay',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'days_worked' => 'decimal:2',
            'hours_worked' => 'decimal:2',
            'basic_pay' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'taxable_compensation' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
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
     * @return BelongsTo<PayrollImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(PayrollImport::class, 'payroll_import_id', 'payroll_import_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * @return BelongsTo<CompensationProfile, $this>
     */
    public function compensationProfile(): BelongsTo
    {
        return $this->belongsTo(CompensationProfile::class, 'compensation_profile_id', 'compensation_profile_id');
    }

    /**
     * @return HasMany<EarningLine, $this>
     */
    public function earningLines(): HasMany
    {
        return $this->hasMany(EarningLine::class, 'payroll_line_id', 'payroll_line_id');
    }

    /**
     * @return HasMany<DeductionLine, $this>
     */
    public function deductionLines(): HasMany
    {
        return $this->hasMany(DeductionLine::class, 'payroll_line_id', 'payroll_line_id');
    }
}
