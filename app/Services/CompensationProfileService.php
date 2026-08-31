<?php

namespace App\Services;

use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\RecurringDeduction;
use App\Models\RecurringEarning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// UC-11 · Maintain compensation profile — FR-1.2, BR-08.
//
// Every write here follows the dated-version-chain pattern EmployeeController
// already established for EMPLOYMENT_DETAIL (data-model.md §4.1/§4.2 prose):
// a change closes the current open row (effective_to) and opens a new one,
// never an UPDATE of pay_basis/basic_rate/amount in place — so a
// already-finalized run keeps reading the rate that was in force when it
// ran (AC-1.2.4, UC-11 A1).
//
// Unlike EMPLOYMENT_DETAIL, effective_to here is the last day a row is in
// force, inclusive — "the entry in force on the period's cut-off end date"
// (BR-08) — not the day the next one starts. Closing the current row
// therefore lands on the day *before* the new entry's effective_from, one
// day short of it; trg_compensation_profiles_no_overlap_ins/upd (Sprint 0's
// business-rule-triggers migration) enforces exactly this: it treats a
// closed row whose effective_to equals the new row's effective_from as
// still overlapping it.
class CompensationProfileService
{
    /**
     * UC-11 steps 2, 5, 6 — records a new dated compensation profile
     * version. First call for an employee simply opens the chain; a later
     * call closes the current open row at the new entry's effective_from
     * and opens the new row from there (BR-08).
     *
     * @param  array{pay_basis: string, basic_rate: string, sss_covered: bool, philhealth_covered: bool, pagibig_covered: bool, effective_from: string}  $data
     */
    public function recordProfile(Employee $employee, array $data, ?int $actorUserId): CompensationProfile
    {
        $current = $this->currentProfile($employee);

        // Closing the current row lands one day short of the new
        // effective_from (see the class docblock); chk_compensation_profiles_effective
        // then requires that closed effective_to to be strictly after the
        // current row's own effective_from, so the new date must be at
        // least two days after it, not merely after.
        if ($current !== null && ! Carbon::parse($data['effective_from'])->greaterThan($current->effective_from->copy()->addDay())) {
            throw new CompensationProfileException(
                "UC-11 E1: the effective date must be at least two days after the current entry's effective date ({$current->effective_from->toDateString()})."
            );
        }

        return DB::transaction(function () use ($employee, $data, $actorUserId, $current) {
            if ($current !== null) {
                $current->effective_to = Carbon::parse($data['effective_from'])->subDay()->toDateString();
                $current->updated_by = $actorUserId;
                $current->save();
            }

            return CompensationProfile::create([
                'employee_id' => $employee->employee_id,
                'pay_basis' => $data['pay_basis'],
                'basic_rate' => $data['basic_rate'],
                'sss_covered' => $data['sss_covered'],
                'philhealth_covered' => $data['philhealth_covered'],
                'pagibig_covered' => $data['pagibig_covered'],
                'effective_from' => $data['effective_from'],
                'effective_to' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);
        });
    }

    public function currentProfile(Employee $employee): ?CompensationProfile
    {
        return CompensationProfile::query()
            ->where('employee_id', $employee->employee_id)
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();
    }

    /**
     * UC-11 step 3 — a recurring earning is its own dated chain, scoped to
     * one earning type per employee (recurring_earnings_emp_type_from_unique).
     * No DB trigger enforces non-overlap here (unlike COMPENSATION_PROFILE),
     * so the check is application-level, same shape as ValidationService's
     * EMPLOYMENT_DETAIL check.
     */
    public function addRecurringEarning(Employee $employee, int $earningTypeId, string $amount, string $effectiveFrom, ?int $actorUserId): RecurringEarning
    {
        $this->assertNoOverlap(
            RecurringEarning::query()->where('employee_id', $employee->employee_id)->where('earning_type_id', $earningTypeId),
            $effectiveFrom,
        );

        return RecurringEarning::create([
            'employee_id' => $employee->employee_id,
            'earning_type_id' => $earningTypeId,
            'amount' => $amount,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }

    /**
     * UC-11 A2 — ends a recurring earning rather than deleting it; it stops
     * appearing in the input worksheet from the following period, but
     * persists as data (the system applies nothing itself).
     */
    public function endRecurringEarning(RecurringEarning $recurringEarning, string $effectiveTo, ?int $actorUserId): RecurringEarning
    {
        if (! Carbon::parse($effectiveTo)->greaterThan($recurringEarning->effective_from)) {
            throw new CompensationProfileException(
                "UC-11 E1: the end date must be after this entry's effective date ({$recurringEarning->effective_from->toDateString()})."
            );
        }

        $recurringEarning->effective_to = $effectiveTo;
        $recurringEarning->updated_by = $actorUserId;
        $recurringEarning->save();

        return $recurringEarning;
    }

    public function addRecurringDeduction(Employee $employee, int $deductionTypeId, string $amount, string $effectiveFrom, ?int $actorUserId): RecurringDeduction
    {
        $this->assertNoOverlap(
            RecurringDeduction::query()->where('employee_id', $employee->employee_id)->where('deduction_type_id', $deductionTypeId),
            $effectiveFrom,
        );

        return RecurringDeduction::create([
            'employee_id' => $employee->employee_id,
            'deduction_type_id' => $deductionTypeId,
            'amount' => $amount,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }

    public function endRecurringDeduction(RecurringDeduction $recurringDeduction, string $effectiveTo, ?int $actorUserId): RecurringDeduction
    {
        if (! Carbon::parse($effectiveTo)->greaterThan($recurringDeduction->effective_from)) {
            throw new CompensationProfileException(
                "UC-11 E1: the end date must be after this entry's effective date ({$recurringDeduction->effective_from->toDateString()})."
            );
        }

        $recurringDeduction->effective_to = $effectiveTo;
        $recurringDeduction->updated_by = $actorUserId;
        $recurringDeduction->save();

        return $recurringDeduction;
    }

    /**
     * @param  Builder<RecurringEarning>|Builder<RecurringDeduction>  $existingQuery  already scoped to employee + item type
     */
    private function assertNoOverlap($existingQuery, string $newEffectiveFrom): void
    {
        $overlapping = (clone $existingQuery)
            ->where(function ($q) use ($newEffectiveFrom) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $newEffectiveFrom);
            })
            ->where('effective_from', '<=', $newEffectiveFrom)
            ->exists();

        if ($overlapping) {
            throw new CompensationProfileException(
                "UC-11 E1: an entry already covers {$newEffectiveFrom} for this item; end it first or choose a later date."
            );
        }
    }
}
