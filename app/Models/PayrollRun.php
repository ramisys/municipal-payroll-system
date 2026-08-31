<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// data-model.md §4.4 / §5.1 — PAYROLL_RUN. Created by PayrollRunService
// (UC-17, BR-34); holds no figures of its own (§7 beat 7) — total_gross,
// total_deductions, and total_net stay at their zero default through
// Draft/intake and are derived for display, never written here (§7 beat 11:
// "run totals displayed and derived — never stored"). They are populated
// only at finalization (FR-4.5, M5), which is out of this slice.
class PayrollRun extends Model
{
    protected $primaryKey = 'payroll_run_id';

    protected $fillable = [
        'payroll_period_id',
        'run_type',
        'population_scope',
        'run_status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'finalized_at',
        'employee_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'employee_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id', 'payroll_period_id');
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class, 'payroll_run_id', 'payroll_run_id');
    }

    /**
     * @return HasMany<PayrollImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(PayrollImport::class, 'payroll_run_id', 'payroll_run_id');
    }

    /**
     * @return HasMany<RunTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(RunTransition::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function currentImport(): ?PayrollImport
    {
        return $this->imports()->where('is_current', true)->first();
    }
}
