<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.5 / §5.1 / §5.2 — STATUTORY_BRACKET.
// Salary or compensation brackets for SSS, BIR, and bracket-based schedules.
class StatutoryBracket extends Model
{
    protected $primaryKey = 'statutory_bracket_id';

    protected $fillable = [
        'statutory_schedule_id',
        'bracket_sequence',
        'range_from',
        'range_to',
        'employee_share',
        'employer_share',
        'base_tax',
        'marginal_rate',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'bracket_sequence' => 'integer',
            'range_from' => 'decimal:2',
            'range_to' => 'decimal:2',
            'employee_share' => 'decimal:2',
            'employer_share' => 'decimal:2',
            'base_tax' => 'decimal:2',
            'marginal_rate' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<StatutorySchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(StatutorySchedule::class, 'statutory_schedule_id', 'statutory_schedule_id');
    }
}
