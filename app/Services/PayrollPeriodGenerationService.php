<?php

namespace App\Services;

use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// UC-03 behavior 4-5 / FR-0.3 / BR-34.
//
// Pay-period boundary policy (cutoff dates, the pay-date lag after a
// cutoff) is not fixed by any baseline document — OI-02 leaves the
// frequency itself open, and this class holds no day-factor-style rate
// assumption (A-05, OI-03 closed). It builds a calendar-month-aligned
// schedule so periods are contiguous by construction, then re-validates
// that construction independently with assertNoOverlapOrGap() — the same
// method a single-period edit (UC-03 A1) is checked against — so BR-34
// is proven by a check, not merely assumed from the generation algorithm.
class PayrollPeriodGenerationService
{
    /**
     * @return array<int, PayrollPeriod>
     */
    public function generateForYear(int $payrollYear, string $payFrequency, int $payDateOffsetDays, ?int $actorUserId): array
    {
        if (PayrollPeriod::query()->where('payroll_year', $payrollYear)->exists()) {
            throw new PayrollPeriodException("Payroll year {$payrollYear} already has periods defined.");
        }

        $schedule = $this->buildYearSchedule($payrollYear, $payFrequency, $payDateOffsetDays);

        $this->assertNoOverlapOrGap($schedule);

        return DB::transaction(function () use ($schedule, $actorUserId) {
            $created = [];

            foreach ($schedule as $period) {
                $created[] = PayrollPeriod::create([
                    ...$period,
                    'created_by' => $actorUserId,
                    'updated_by' => $actorUserId,
                ]);
            }

            return $created;
        });
    }

    /**
     * UC-03 A1 — edits one period's dates and re-validates the whole
     * year against BR-34 before saving. UC-03 E2 — refused if a payroll
     * run already exists for the period.
     */
    public function adjustPeriod(PayrollPeriod $period, string $cutoffStart, string $cutoffEnd, string $payDate, ?int $actorUserId): PayrollPeriod
    {
        if ($this->periodIsInUse($period)) {
            throw new PayrollPeriodException(
                "Period {$period->payroll_year}-{$period->period_no} already has a payroll run and cannot be rescheduled."
            );
        }

        $siblings = PayrollPeriod::query()
            ->where('payroll_year', $period->payroll_year)
            ->where('payroll_period_id', '<>', $period->payroll_period_id)
            ->get()
            ->map(fn (PayrollPeriod $sibling) => [
                'period_no' => $sibling->period_no,
                'cutoff_start' => $sibling->cutoff_start->toDateString(),
                'cutoff_end' => $sibling->cutoff_end->toDateString(),
            ])
            ->all();

        $candidate = [
            'period_no' => $period->period_no,
            'cutoff_start' => $cutoffStart,
            'cutoff_end' => $cutoffEnd,
        ];

        $this->assertNoOverlapOrGap([...$siblings, $candidate]);

        $period->cutoff_start = $cutoffStart;
        $period->cutoff_end = $cutoffEnd;
        $period->pay_date = $payDate;
        $period->updated_by = $actorUserId;
        $period->save();

        return $period;
    }

    private function periodIsInUse(PayrollPeriod $period): bool
    {
        return DB::table('payroll_runs')->where('payroll_period_id', $period->payroll_period_id)->exists();
    }

    /**
     * @return array<int, array{payroll_year: int, period_no: int, pay_frequency: string, cutoff_start: string, cutoff_end: string, pay_date: string}>
     */
    private function buildYearSchedule(int $payrollYear, string $payFrequency, int $payDateOffsetDays): array
    {
        return match ($payFrequency) {
            'MONTHLY' => $this->buildMonthly($payrollYear, $payDateOffsetDays),
            'SEMI_MONTHLY' => $this->buildSemiMonthly($payrollYear, $payDateOffsetDays),
            default => throw new PayrollPeriodException("Unknown pay frequency '{$payFrequency}'."),
        };
    }

    /**
     * @return array<int, array{payroll_year: int, period_no: int, pay_frequency: string, cutoff_start: string, cutoff_end: string, pay_date: string}>
     */
    private function buildMonthly(int $year, int $offsetDays): array
    {
        $periods = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1);
            $end = $start->copy()->endOfMonth();

            $periods[] = [
                'payroll_year' => $year,
                'period_no' => $month,
                'pay_frequency' => 'MONTHLY',
                'cutoff_start' => $start->toDateString(),
                'cutoff_end' => $end->toDateString(),
                'pay_date' => $end->copy()->addDays($offsetDays)->toDateString(),
            ];
        }

        return $periods;
    }

    /**
     * @return array<int, array{payroll_year: int, period_no: int, pay_frequency: string, cutoff_start: string, cutoff_end: string, pay_date: string}>
     */
    private function buildSemiMonthly(int $year, int $offsetDays): array
    {
        $periods = [];
        $periodNo = 1;

        for ($month = 1; $month <= 12; $month++) {
            $firstStart = Carbon::create($year, $month, 1);
            $firstEnd = Carbon::create($year, $month, 15);

            $periods[] = [
                'payroll_year' => $year,
                'period_no' => $periodNo++,
                'pay_frequency' => 'SEMI_MONTHLY',
                'cutoff_start' => $firstStart->toDateString(),
                'cutoff_end' => $firstEnd->toDateString(),
                'pay_date' => $firstEnd->copy()->addDays($offsetDays)->toDateString(),
            ];

            $secondStart = Carbon::create($year, $month, 16);
            $secondEnd = $secondStart->copy()->endOfMonth();

            $periods[] = [
                'payroll_year' => $year,
                'period_no' => $periodNo++,
                'pay_frequency' => 'SEMI_MONTHLY',
                'cutoff_start' => $secondStart->toDateString(),
                'cutoff_end' => $secondEnd->toDateString(),
                'pay_date' => $secondEnd->copy()->addDays($offsetDays)->toDateString(),
            ];
        }

        return $periods;
    }

    /**
     * BR-34 — sorted by cutoff_start: the first period starts January 1,
     * the last ends December 31, and each period's cutoff_start is
     * exactly one day after the previous period's cutoff_end. Identical
     * for a freshly generated year and for an existing year with one
     * period's dates replaced (A1), since both are just an array of
     * {cutoff_start, cutoff_end} by the time this runs.
     *
     * @param  array<int, array{cutoff_start: string, cutoff_end: string}>  $periods
     */
    private function assertNoOverlapOrGap(array $periods): void
    {
        usort($periods, fn (array $a, array $b) => $a['cutoff_start'] <=> $b['cutoff_start']);

        $year = (int) substr($periods[0]['cutoff_start'], 0, 4);

        if ($periods[0]['cutoff_start'] !== sprintf('%04d-01-01', $year)) {
            throw new PayrollPeriodException(
                "BR-34: payroll year {$year} must begin on January 1; the first period starts {$periods[0]['cutoff_start']}."
            );
        }

        $lastEnd = $periods[count($periods) - 1]['cutoff_end'];
        if ($lastEnd !== sprintf('%04d-12-31', $year)) {
            throw new PayrollPeriodException(
                "BR-34: payroll year {$year} must end on December 31; the last period ends {$lastEnd}."
            );
        }

        for ($i = 1; $i < count($periods); $i++) {
            $previousEnd = Carbon::parse($periods[$i - 1]['cutoff_end']);
            $thisStart = Carbon::parse($periods[$i]['cutoff_start']);
            $expectedStart = $previousEnd->copy()->addDay();

            if (! $thisStart->equalTo($expectedStart)) {
                $relation = $thisStart->lessThan($expectedStart) ? 'overlaps' : 'leaves a gap before';

                throw new PayrollPeriodException(
                    "BR-34: the period starting {$periods[$i]['cutoff_start']} {$relation} the period ending {$periods[$i - 1]['cutoff_end']}."
                );
            }
        }
    }
}
