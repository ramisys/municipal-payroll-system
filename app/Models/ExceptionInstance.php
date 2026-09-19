<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.4 / §5.1 — EXCEPTION_INSTANCE. FR-4.1 / UC-I4 / UC-20.
// Raised by ExceptionEvaluator after an accepted import (and later on
// demand). Blocking rows block submission; warnings require an audited
// acknowledgment before a run can advance (FR-4.1 behavior 4–5).
class ExceptionInstance extends Model
{
    /** @var list<string> Register-content rules resolvable only by corrected import (AC-4.1.5). */
    public const REIMPORT_ONLY = ['EX-03', 'EX-04', 'EX-11', 'EX-12', 'EX-13', 'EX-14'];

    protected $primaryKey = 'exception_instance_id';

    protected $fillable = [
        'payroll_run_id',
        'payroll_line_id',
        'rule_code',
        'severity',
        'triggering_values',
        'is_resolved',
        'acknowledged_by',
        'acknowledged_at',
        'acknowledgment_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_resolved' => 'boolean',
            'acknowledged_at' => 'datetime',
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
     * @return BelongsTo<PayrollLine, $this>
     */
    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class, 'payroll_line_id', 'payroll_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by', 'user_id');
    }

    public function isBlocking(): bool
    {
        return $this->severity === 'BLOCKING';
    }

    public function requiresCorrectedImport(): bool
    {
        return in_array($this->rule_code, self::REIMPORT_ONLY, true);
    }
}
