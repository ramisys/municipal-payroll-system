<?php

namespace Tests\Unit;

use App\Models\PayrollPeriod;
use App\Services\PayrollPeriodException;
use App\Services\PayrollPeriodGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UC-03 behaviors 4-5 / FR-0.3 / BR-34 — pre-oral-demonstration-plan.md
 * §6 Table 6, W4's stated "ends with": periods for a year generate with
 * no overlap and no gap.
 */
class PayrollPeriodGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_monthly_year_generates_twelve_contiguous_periods_with_no_overlap_or_gap(): void
    {
        $periods = (new PayrollPeriodGenerationService)->generateForYear(2027, 'MONTHLY', 5, null);

        self::assertCount(12, $periods);
        $this->assertYearIsContiguous(2027);
    }

    public function test_a_semi_monthly_year_generates_twenty_four_contiguous_periods_with_no_overlap_or_gap(): void
    {
        $periods = (new PayrollPeriodGenerationService)->generateForYear(2027, 'SEMI_MONTHLY', 5, null);

        self::assertCount(24, $periods);
        $this->assertYearIsContiguous(2027);

        $first = PayrollPeriod::query()->where('payroll_year', 2027)->where('period_no', 1)->firstOrFail();
        self::assertSame('2027-01-01', $first->cutoff_start->toDateString());
        self::assertSame('2027-01-15', $first->cutoff_end->toDateString());

        $second = PayrollPeriod::query()->where('payroll_year', 2027)->where('period_no', 2)->firstOrFail();
        self::assertSame('2027-01-16', $second->cutoff_start->toDateString());
        self::assertSame('2027-01-31', $second->cutoff_end->toDateString());
    }

    public function test_a_second_generation_for_the_same_year_is_refused(): void
    {
        (new PayrollPeriodGenerationService)->generateForYear(2027, 'MONTHLY', 5, null);

        $this->expectException(PayrollPeriodException::class);

        (new PayrollPeriodGenerationService)->generateForYear(2027, 'MONTHLY', 5, null);
    }

    public function test_adjusting_a_period_to_overlap_its_neighbor_is_refused(): void
    {
        $service = new PayrollPeriodGenerationService;
        $service->generateForYear(2027, 'MONTHLY', 5, null);

        $january = PayrollPeriod::query()->where('payroll_year', 2027)->where('period_no', 1)->firstOrFail();

        $this->expectException(PayrollPeriodException::class);

        // Extends January into February's cutoff_start (2027-02-01) — an overlap.
        $service->adjustPeriod($january, '2027-01-01', '2027-02-05', '2027-02-10', null);
    }

    public function test_adjusting_a_period_to_leave_a_gap_is_refused(): void
    {
        $service = new PayrollPeriodGenerationService;
        $service->generateForYear(2027, 'MONTHLY', 5, null);

        $january = PayrollPeriod::query()->where('payroll_year', 2027)->where('period_no', 1)->firstOrFail();

        $this->expectException(PayrollPeriodException::class);

        // Ends January early, leaving 2027-01-29 - 2027-01-31 uncovered.
        $service->adjustPeriod($january, '2027-01-01', '2027-01-28', '2027-02-02', null);
    }

    public function test_adjusting_a_period_already_backing_a_payroll_run_is_refused(): void
    {
        $service = new PayrollPeriodGenerationService;
        $service->generateForYear(2027, 'MONTHLY', 5, null);

        $january = PayrollPeriod::query()->where('payroll_year', 2027)->where('period_no', 1)->firstOrFail();

        DB::table('payroll_runs')->insert([
            'payroll_period_id' => $january->payroll_period_id,
            'run_type' => 'REGULAR',
            'population_scope' => 'ALL',
            'run_status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(PayrollPeriodException::class);

        $service->adjustPeriod($january, '2027-01-02', '2027-01-31', '2027-02-05', null);
    }

    private function assertYearIsContiguous(int $year): void
    {
        $periods = PayrollPeriod::query()
            ->where('payroll_year', $year)
            ->orderBy('cutoff_start')
            ->get();

        self::assertSame("{$year}-01-01", $periods->first()->cutoff_start->toDateString());
        self::assertSame("{$year}-12-31", $periods->last()->cutoff_end->toDateString());

        $previousEnd = null;
        foreach ($periods as $period) {
            if ($previousEnd !== null) {
                self::assertSame(
                    $previousEnd->copy()->addDay()->toDateString(),
                    $period->cutoff_start->toDateString(),
                    'No gap and no overlap between consecutive periods.'
                );
            }
            $previousEnd = $period->cutoff_end;
        }
    }
}
