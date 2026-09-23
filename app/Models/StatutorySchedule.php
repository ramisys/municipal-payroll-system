<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// data-model.md §4.5 / §5.1 — STATUTORY_SCHEDULE.
// Effectivity-dated reference tables maintainable without code changes (FR-2.3, AC-2.3.1).
// Agencies: SSS, PHILHEALTH, PAGIBIG, BIR.
class StatutorySchedule extends Model
{
    protected $primaryKey = 'statutory_schedule_id';

    public const AGENCIES = ['SSS', 'PHILHEALTH', 'PAGIBIG', 'BIR'];

    protected $fillable = [
        'agency',
        'schedule_version',
        'effective_from',
        'effective_to',
        'pay_frequency',
        'premium_rate',
        'salary_floor',
        'salary_ceiling',
        'compensation_cap',
        'issuance_reference',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'premium_rate' => 'decimal:4',
            'salary_floor' => 'decimal:2',
            'salary_ceiling' => 'decimal:2',
            'compensation_cap' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StatutoryBracket, $this>
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(StatutoryBracket::class, 'statutory_schedule_id', 'statutory_schedule_id')
            ->orderBy('bracket_sequence');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Scope query to schedules in effect on a given date (BR-14).
     */
    public function scopeInEffectOn($query, string $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }
}
