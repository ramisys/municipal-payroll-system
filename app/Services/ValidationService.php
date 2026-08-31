<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Carbon;

// UC-I1 · Validate data entry — FR-1.5, included by UC-08/UC-09.
//
// Required fields, type/range checks, and government-ID format (UC-I1
// steps 1, 2, 4) are already expressed as Laravel `$request->validate()`
// rule arrays in EmployeeController, the same convention every other
// controller in this repo uses (ReferenceDataController, UserController) —
// duplicating that here would just be a second copy of the same rules.
//
// What the declarative rule DSL cannot express is cross-field date logic
// (step 3) and the probable-duplicate warning (step 5, UC-08 E2), so
// those two live here instead.
class ValidationService
{
    /**
     * UC-I1 step 3 / AC-1.5.1. `birth_date < CURRENT_DATE` cannot be a
     * MySQL CHECK constraint (non-deterministic function — see the
     * employees_table migration comment), so it is enforced here at
     * point of entry instead.
     *
     * @return array<string, string> field => the specific correction required; empty when all checks pass
     */
    public function validateEmployeeDateLogic(string $birthDate, string $dateHired, ?string $separationDate): array
    {
        $errors = [];
        $today = Carbon::today();

        if (! Carbon::parse($birthDate)->lessThan($today)) {
            $errors['birth_date'] = 'Date of birth must be before today.';
        }

        if (Carbon::parse($dateHired)->greaterThan($today)) {
            $errors['date_hired'] = 'Date hired cannot be in the future.';
        }

        if ($separationDate !== null && Carbon::parse($separationDate)->lessThan(Carbon::parse($dateHired))) {
            $errors['separation_date'] = 'Separation date cannot be earlier than the date hired.';
        }

        return $errors;
    }

    /**
     * BR-08 dated-row pattern — a transfer or separation closes the
     * employee's current EMPLOYMENT_DETAIL row by setting its effective_to,
     * which chk_employment_details_effective requires to be strictly after
     * that row's own effective_from. A row can be future-dated (a scheduled
     * transfer), so this can't be assumed and must be checked at entry
     * rather than left to surface as a DB constraint violation.
     *
     * @return array<string, string> field => the specific correction required; empty when the check passes
     */
    public function validateEmploymentEffectiveDate(string $currentEffectiveFrom, string $newEffectiveTo, string $field): array
    {
        if (! Carbon::parse($newEffectiveTo)->greaterThan(Carbon::parse($currentEffectiveFrom))) {
            return [$field => "This date must be after the employee's current effective date ({$currentEffectiveFrom})."];
        }

        return [];
    }

    /**
     * UC-I1 step 5, UC-08 E2 — a probable duplicate by name and date of
     * birth is a warning, not a refusal: the caller routes it back to the
     * user, who must explicitly acknowledge it before the save proceeds
     * (AC-1.5.2's sibling case for a *person* rather than an employee
     * number, which `unique:employees,employee_no` already refuses
     * outright).
     */
    public function findProbableDuplicatePerson(string $lastName, string $firstName, string $birthDate, ?int $ignoreEmployeeId = null): ?Employee
    {
        return Employee::query()
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($lastName))])
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($firstName))])
            ->whereDate('birth_date', $birthDate)
            ->when($ignoreEmployeeId !== null, fn ($q) => $q->where('employee_id', '!=', $ignoreEmployeeId))
            ->first();
    }
}
