<?php

namespace App\Services;

use App\Models\PayrollLine;
use App\Models\StatutoryBracket;
use App\Models\StatutorySchedule;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

// system-architecture.md §6.1 / FR-2.3 / UC-05, UC-I5 / BR-14, BR-20.
//
// Resolves effectivity-dated statutory schedules by pay date and derives employer
// shares ONLY for remittance reporting when the imported register omits them (OI-13).
//
// Non-negotiable: takes no part in determining any employee's gross, deduction, or
// net pay (AC-2.3.5, BR-20). Employee values are always the imported ones.
class StatutoryScheduleService
{
    /**
     * Resolve the active schedule for an agency on a given pay date (BR-14).
     */
    public function resolveSchedule(string $agency, string $payDate): ?StatutorySchedule
    {
        return StatutorySchedule::query()
            ->where('agency', strtoupper($agency))
            ->where('is_active', true)
            ->where('effective_from', '<=', $payDate)
            ->where(function ($q) use ($payDate) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $payDate);
            })
            ->with('brackets')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Get all schedules for an agency with brackets.
     *
     * @return Collection<int, StatutorySchedule>
     */
    public function getSchedulesForAgency(string $agency): Collection
    {
        return StatutorySchedule::query()
            ->where('agency', strtoupper($agency))
            ->with('brackets')
            ->orderByDesc('effective_from')
            ->get();
    }

    /**
     * Derive employer share for a payroll line under a specific agency (FR-2.3, AC-2.3.4, OI-13).
     *
     * Returns:
     * - amount: string decimal (e.g. '950.00')
     * - source: 'IMPORTED' | 'DERIVED' | 'UNAVAILABLE'
     * - schedule_version: ?string (e.g. 'SSS-2024')
     * - statutory_schedule_id: ?int
     *
     * @return array{
     *     amount: string,
     *     source: string,
     *     schedule_version: ?string,
     *     statutory_schedule_id: ?int
     * }
     */
    public function deriveEmployerShare(string $agency, PayrollLine $line, string $payDate): array
    {
        $agency = strtoupper($agency);

        // 1. Check if deduction line already holds an imported employer share (OI-13 / AC-2.3.4)
        $deduction = $this->findAgencyDeductionLine($line, $agency);

        if ($deduction && $deduction->employer_share !== null) {
            return [
                'amount' => number_format((float) $deduction->employer_share, 2, '.', ''),
                'source' => 'IMPORTED',
                'schedule_version' => null,
                'statutory_schedule_id' => $deduction->statutory_schedule_id,
            ];
        }

        // 2. Resolve active statutory schedule for the pay date (BR-14)
        $schedule = $this->resolveSchedule($agency, $payDate);

        if ($schedule === null) {
            return [
                'amount' => '0.00',
                'source' => 'UNAVAILABLE',
                'schedule_version' => null,
                'statutory_schedule_id' => null,
            ];
        }

        // 3. Derive employer share from the active schedule (does NOT alter employee pay per AC-2.3.5)
        $employeeShare = $deduction ? (float) ($deduction->employee_share ?? $deduction->amount) : 0.0;
        $monthlyBasic = (float) ($line->compensationProfile?->monthly_rate ?? 0.0);

        if ($monthlyBasic <= 0.0 && $line->gross_pay > 0) {
            // Fallback to gross pay if compensation profile rate is unrecorded
            $monthlyBasic = (float) $line->gross_pay;
        }

        $derivedAmount = match ($agency) {
            'SSS' => $this->calculateSssEmployerShare($schedule, $monthlyBasic, $employeeShare),
            'PHILHEALTH' => $this->calculatePhilHealthEmployerShare($schedule, $monthlyBasic, $employeeShare),
            'PAGIBIG' => $this->calculatePagIbigEmployerShare($schedule, $monthlyBasic, $employeeShare),
            default => '0.00',
        };

        return [
            'amount' => $derivedAmount,
            'source' => 'DERIVED',
            'schedule_version' => $schedule->schedule_version,
            'statutory_schedule_id' => $schedule->statutory_schedule_id,
        ];
    }

    /**
     * SSS: Match salary bracket or corresponding employee share bracket.
     */
    protected function calculateSssEmployerShare(StatutorySchedule $schedule, float $salary, float $employeeShare): string
    {
        $brackets = $schedule->brackets;

        if ($brackets->isEmpty()) {
            return '0.00';
        }

        // Strategy A: Find bracket by employee share if exact match exists
        if ($employeeShare > 0) {
            $matchedByShare = $brackets->first(function (StatutoryBracket $b) use ($employeeShare) {
                return abs((float) $b->employee_share - $employeeShare) < 0.05;
            });
            if ($matchedByShare && $matchedByShare->employer_share !== null) {
                return number_format((float) $matchedByShare->employer_share, 2, '.', '');
            }
        }

        // Strategy B: Find bracket by salary range
        $matchedBySalary = $brackets->first(function (StatutoryBracket $b) use ($salary) {
            $from = (float) $b->range_from;
            $to = $b->range_to !== null ? (float) $b->range_to : PHP_FLOAT_MAX;

            return $salary >= $from && $salary <= $to;
        });

        if ($matchedBySalary && $matchedBySalary->employer_share !== null) {
            return number_format((float) $matchedBySalary->employer_share, 2, '.', '');
        }

        // Fallback to highest bracket if salary exceeds ceiling
        $highest = $brackets->last();

        return number_format((float) ($highest?->employer_share ?? 0.0), 2, '.', '');
    }

    /**
     * PhilHealth: 50-50 share between employee and employer.
     */
    protected function calculatePhilHealthEmployerShare(StatutorySchedule $schedule, float $salary, float $employeeShare): string
    {
        // If employee premium is present, employer share equals employee share (50-50 split)
        if ($employeeShare > 0) {
            return number_format($employeeShare, 2, '.', '');
        }

        $rate = (float) ($schedule->premium_rate ?? 0.05);
        $floor = (float) ($schedule->salary_floor ?? 10000.00);
        $ceiling = (float) ($schedule->salary_ceiling ?? 100000.00);

        $cappedSalary = max($floor, min($ceiling, $salary));
        $totalPremium = round($cappedSalary * $rate, 2);
        $employerShare = round($totalPremium / 2.0, 2);

        return number_format($employerShare, 2, '.', '');
    }

    /**
     * Pag-IBIG: Standard 2% employer share up to compensation cap.
     */
    protected function calculatePagIbigEmployerShare(StatutorySchedule $schedule, float $salary, float $employeeShare): string
    {
        // If brackets exist on schedule, match bracket
        if ($schedule->brackets->isNotEmpty()) {
            $matched = $schedule->brackets->first(function (StatutoryBracket $b) use ($salary) {
                $from = (float) $b->range_from;
                $to = $b->range_to !== null ? (float) $b->range_to : PHP_FLOAT_MAX;

                return $salary >= $from && $salary <= $to;
            });

            if ($matched && $matched->employer_share !== null) {
                return number_format((float) $matched->employer_share, 2, '.', '');
            }
        }

        // If employee share is present, typically ₱100 or ₱200, matching employer share
        if ($employeeShare > 0) {
            return number_format($employeeShare, 2, '.', '');
        }

        $cap = (float) ($schedule->compensation_cap ?? 5000.00);
        $cappedSalary = min($cap, $salary);
        $employerShare = round($cappedSalary * 0.02, 2);

        return number_format($employerShare, 2, '.', '');
    }

    /**
     * Find deduction line belonging to agency on the given payroll line.
     */
    protected function findAgencyDeductionLine(PayrollLine $line, string $agency): mixed
    {
        return $line->deductionLines->first(function ($dl) use ($agency) {
            $code = strtoupper($dl->deductionType?->deduction_code ?? '');
            $name = strtoupper($dl->deductionType?->deduction_name ?? '');

            return match ($agency) {
                'SSS' => str_contains($code, 'SSS') || str_contains($name, 'SSS'),
                'PHILHEALTH' => str_contains($code, 'PHILHEALTH') || str_contains($code, 'PHIC') || str_contains($name, 'PHILHEALTH'),
                'PAGIBIG' => str_contains($code, 'PAGIBIG') || str_contains($code, 'HDMF') || str_contains($name, 'PAG-IBIG') || str_contains($name, 'PAGIBIG'),
                'BIR' => str_contains($code, 'WTAX') || str_contains($code, 'BIR') || str_contains($name, 'WITHHOLDING'),
                default => false,
            };
        });
    }

    /**
     * Validate contiguous brackets with no gaps and no overlaps (UC-05 E2).
     *
     * @param  array<int, array{range_from: float|string, range_to: float|string|null}>  $brackets
     */
    public function validateContiguousBrackets(array $brackets): void
    {
        $count = count($brackets);
        if ($count === 0) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $from = (float) $brackets[$i]['range_from'];
            $to = $brackets[$i]['range_to'] !== null ? (float) $brackets[$i]['range_to'] : null;

            if ($to !== null && $from > $to) {
                throw new InvalidArgumentException('Bracket #'.($i + 1).": range_from ({$from}) cannot exceed range_to ({$to}).");
            }

            if ($i > 0) {
                $prevTo = (float) $brackets[$i - 1]['range_to'];
                // Expected next from is prevTo + 0.01 (or equal for strict ranges)
                $diff = round($from - $prevTo, 2);
                if ($diff < 0.0) {
                    throw new InvalidArgumentException('Bracket #'.($i + 1)." overlaps previous bracket (starts at {$from}, previous ends at {$prevTo}).");
                }
                if ($diff > 0.01) {
                    throw new InvalidArgumentException('Gap detected before bracket #'.($i + 1)." (starts at {$from}, previous ends at {$prevTo}).");
                }
            }
        }
    }
}
