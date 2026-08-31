<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\RunTransition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

// UC-17 · Create payroll run — FR-2.6, FR-4.4 (A2 cancel), BR-34.
//
// Week 7 Track B (pre-oral-demonstration-plan.md §6 Table 6): the run
// container wired to real repositories. A run holds no PAYROLL_LINE rows
// until a register is imported into it (UC-18) — PAYROLL_LINE.
// payroll_import_id and .compensation_profile_id are both NOT NULL
// (2025_08_31_000033_create_payroll_lines_table), so a line cannot exist
// before an import does. UC-17 step 4's "one payroll line per included
// employee" is therefore realized by PayrollImportService at first-import
// time, not here; this service defines and reports the population
// (step 5) and persists the run shell.
//
// `population_scope` (data-model.md §5.1: unique on period + scope + run
// type) carries no fixed enum in the schema. This slice supports 'ALL'
// (every active employee) and 'DEPARTMENT:<department_id>', both
// re-derivable at any later time from EMPLOYEE/EMPLOYMENT_DETAIL alone.
// UC-17 step 3's third option, a named/arbitrary selection, has nowhere to
// be persisted before an import creates lines (no RUN_POPULATION entity
// exists in data-model.md §5) and is cut for this slice per §6.1's "cut
// depth, never a use case" — ALL and DEPARTMENT still cover AC-2.6.3's
// refusal-of-collision test.
class PayrollRunService
{
    private const RUN_TYPES = ['REGULAR', 'THIRTEENTH_MONTH', 'FINAL_PAY', 'SPECIAL'];

    /**
     * @return array{run: PayrollRun, includedCount: int, excluded: array<int, string>}
     */
    public function createRun(PayrollPeriod $period, string $runType, string $populationScope, ?int $actorUserId): array
    {
        if (! in_array($runType, self::RUN_TYPES, true)) {
            throw new PayrollRunException("Unknown run type '{$runType}'.");
        }

        // UC-17 E1 — a cancelled run does not hold the period (data-model.md
        // §5.1's uniqueness excludes CANCELLED rows), so only a
        // not-cancelled sibling is a collision.
        $existing = PayrollRun::query()
            ->where('payroll_period_id', $period->payroll_period_id)
            ->where('population_scope', $populationScope)
            ->where('run_type', $runType)
            ->where('run_status', '<>', 'CANCELLED')
            ->first();

        if ($existing !== null) {
            throw new PayrollRunException(
                "UC-17 E1: an open run (#{$existing->payroll_run_id}, {$existing->run_status}) already exists for this period, population, and run type.",
                $existing->payroll_run_id,
            );
        }

        $included = $this->populationEmployees($period, $populationScope);
        $excluded = Employee::query()
            ->where('is_active', true)
            ->whereNotIn('employee_id', $included->pluck('employee_id'))
            ->orderBy('employee_no')
            ->pluck('employee_no')
            ->all();

        $run = DB::transaction(function () use ($period, $runType, $populationScope, $included, $actorUserId) {
            $run = PayrollRun::create([
                'payroll_period_id' => $period->payroll_period_id,
                'run_type' => $runType,
                'population_scope' => $populationScope,
                'run_status' => 'DRAFT',
                'employee_count' => $included->count(),
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => null,
                'to_status' => 'DRAFT',
                'performed_by' => $actorUserId,
                'performed_at' => now(),
                'reason' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            return $run;
        });

        return ['run' => $run, 'includedCount' => $included->count(), 'excluded' => $excluded];
    }

    // UC-17 A2 — Draft only (E4); a run past Draft is returned (UC-24) or
    // reversed (UC-26) instead, neither of which is in this slice.
    public function cancelRun(PayrollRun $run, string $reason, ?int $actorUserId): PayrollRun
    {
        if ($run->run_status !== 'DRAFT') {
            throw new PayrollRunException(
                "UC-17 E4: run #{$run->payroll_run_id} is '{$run->run_status}', not 'DRAFT', and cannot be cancelled this way."
            );
        }

        return DB::transaction(function () use ($run, $reason, $actorUserId) {
            $run->run_status = 'CANCELLED';
            $run->updated_by = $actorUserId;
            $run->save();

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => 'DRAFT',
                'to_status' => 'CANCELLED',
                'performed_by' => $actorUserId,
                'performed_at' => now(),
                'reason' => $reason,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            return $run;
        });
    }

    /**
     * The run's population as of today — every active employee the scope
     * string selects. UC-18/UC-32 re-resolve this on every call rather
     * than reading it from a stored list, since no such list exists to
     * read (see class docblock).
     *
     * @return Collection<int, Employee>
     */
    public function populationEmployees(PayrollPeriod $period, string $populationScope): Collection
    {
        $query = Employee::query()->where('is_active', true);

        if ($populationScope !== 'ALL') {
            if (! str_starts_with($populationScope, 'DEPARTMENT:')) {
                throw new PayrollRunException("Unknown population scope '{$populationScope}'.");
            }

            $departmentId = (int) substr($populationScope, strlen('DEPARTMENT:'));
            $cutoffEnd = $period->cutoff_end->toDateString();

            $query->whereHas('employmentDetails', function ($q) use ($departmentId, $cutoffEnd) {
                $q->where('department_id', $departmentId)
                    ->where('effective_from', '<=', $cutoffEnd)
                    ->where(function ($q) use ($cutoffEnd) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>=', $cutoffEnd);
                    });
            });
        }

        return $query->orderBy('employee_no')->get();
    }

    public static function populationScopeLabel(string $populationScope): string
    {
        return $populationScope === 'ALL' ? 'All active employees' : $populationScope;
    }
}
