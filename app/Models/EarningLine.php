<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.4 / §5.1 — EARNING_LINE. `amount` is imported directly;
// the system does not verify quantity*rate*multiplier = amount (§4.4 prose).
class EarningLine extends Model
{
    protected $primaryKey = 'earning_line_id';

    protected $fillable = [
        'payroll_line_id',
        'earning_type_id',
        'quantity',
        'rate_applied',
        'multiplier_applied',
        'amount',
        'is_taxable',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate_applied' => 'decimal:2',
            'multiplier_applied' => 'decimal:4',
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PayrollLine, $this>
     */
    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class, 'payroll_line_id', 'payroll_line_id');
    }

    /**
     * @return BelongsTo<EarningType, $this>
     */
    public function earningType(): BelongsTo
    {
        return $this->belongsTo(EarningType::class, 'earning_type_id', 'earning_type_id');
    }
}
