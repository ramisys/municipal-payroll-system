<?php

namespace App\Services;

use App\Models\Department;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// UC-29 · Search payroll records — FR-5.2, NFR-5.5.
// Locates historical payroll records (runs and payroll lines) by multi-criteria
// query rather than manual file search (AC-5.2.1).
// Provides applied criteria description for empty states (AC-5.2.3) and pagination (UC-29 E2).
class PayrollRecordSearchService
{
    /**
     * Search payroll lines matching criteria with pagination.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PayrollLine>
     */
    public function searchLines(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildLinesQuery($filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Search all matching lines for file export (PDF / Excel).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, PayrollLine>
     */
    public function exportLines(array $filters): Collection
    {
        return $this->buildLinesQuery($filters)->get();
    }

    /**
     * Search matching payroll runs (when period, status, or date filters are provided).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, PayrollRun>
     */
    public function searchRuns(array $filters, int $limit = 10): Collection
    {
        $query = PayrollRun::query()->with(['period', 'submitter', 'approver']);

        if (! empty($filters['payroll_period_id'])) {
            $query->where('payroll_period_id', (int) $filters['payroll_period_id']);
        }

        if (! empty($filters['run_status'])) {
            $query->where('run_status', strtoupper((string) $filters['run_status']));
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $query->whereHas('period', function (Builder $q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->where('cutoff_end', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->where('cutoff_start', '<=', $filters['date_to']);
                }
            });
        }

        return $query->orderByDesc('payroll_run_id')->limit($limit)->get();
    }

    /**
     * Build the query for payroll lines.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<PayrollLine>
     */
    public function buildLinesQuery(array $filters): Builder
    {
        $query = PayrollLine::query()
            ->with([
                'employee.employmentDetails.department',
                'employee.employmentDetails.position',
                'employee.employmentDetails.employmentStatus',
                'run.period',
                'compensationProfile',
                'earningLines.earningType',
                'deductionLines.deductionType',
            ])
            ->join('employees', 'employees.employee_id', '=', 'payroll_lines.employee_id')
            ->join('payroll_runs', 'payroll_runs.payroll_run_id', '=', 'payroll_lines.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.payroll_period_id', '=', 'payroll_runs.payroll_period_id')
            ->select('payroll_lines.*');

        // Exact employee filter (e.g. from employee payroll history)
        if (! empty($filters['employee_id'])) {
            $query->where('payroll_lines.employee_id', (int) $filters['employee_id']);
        }

        // Partial match on employee name or employee number (AC-5.2.1)
        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('employees.employee_no', 'like', "%{$term}%")
                    ->orWhere('employees.last_name', 'like', "%{$term}%")
                    ->orWhere('employees.first_name', 'like', "%{$term}%");
            });
        }

        // Specific pay period
        if (! empty($filters['payroll_period_id'])) {
            $query->where('payroll_runs.payroll_period_id', (int) $filters['payroll_period_id']);
        }

        // Date range against period cutoff dates
        if (! empty($filters['date_from'])) {
            $query->where('payroll_periods.cutoff_end', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('payroll_periods.cutoff_start', '<=', $filters['date_to']);
        }

        // Department filter
        if (! empty($filters['department_id'])) {
            $deptId = (int) $filters['department_id'];
            $query->whereHas('employee.employmentDetails', function (Builder $q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        // Run status filter
        if (! empty($filters['run_status'])) {
            $query->where('payroll_runs.run_status', strtoupper((string) $filters['run_status']));
        }

        // Default ordering: newest cutoff_end descending, then employee_no
        return $query->orderByDesc('payroll_periods.cutoff_end')
            ->orderBy('employees.employee_no');
    }

    /**
     * Get an employee's full chronological payroll history across all runs (UC-29 A2).
     *
     * @return Collection<int, PayrollLine>
     */
    public function employeeHistory(int $employeeId): Collection
    {
        return PayrollLine::query()
            ->where('payroll_lines.employee_id', $employeeId)
            ->with([
                'run.period',
                'compensationProfile',
                'earningLines.earningType',
                'deductionLines.deductionType',
            ])
            ->join('payroll_runs', 'payroll_runs.payroll_run_id', '=', 'payroll_lines.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.payroll_period_id', '=', 'payroll_runs.payroll_period_id')
            ->select('payroll_lines.*')
            ->orderByDesc('payroll_periods.cutoff_end')
            ->get();
    }

    /**
     * Format a human-readable list of applied criteria for AC-5.2.3 / UC-29 E1.
     * When no result matches, this is displayed to the user.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    public function describeAppliedCriteria(array $filters): array
    {
        $criteria = [];

        if (! empty($filters['q'])) {
            $criteria[] = 'Employee: '.trim((string) $filters['q']);
        }

        if (! empty($filters['employee_id'])) {
            $criteria[] = 'Employee ID #'.(int) $filters['employee_id'];
        }

        if (! empty($filters['payroll_period_id'])) {
            $period = PayrollPeriod::find($filters['payroll_period_id']);
            if ($period) {
                $criteria[] = "Pay Period {$period->payroll_year}-{$period->period_no} ({$period->cutoff_start->toDateString()} to {$period->cutoff_end->toDateString()})";
            } else {
                $criteria[] = "Period #{$filters['payroll_period_id']}";
            }
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $criteria[] = "Date range from {$filters['date_from']} to {$filters['date_to']}";
        } elseif (! empty($filters['date_from'])) {
            $criteria[] = "Cutoff on or after {$filters['date_from']}";
        } elseif (! empty($filters['date_to'])) {
            $criteria[] = "Cutoff on or before {$filters['date_to']}";
        }

        if (! empty($filters['department_id'])) {
            $department = Department::find($filters['department_id']);
            $criteria[] = 'Department: '.($department ? $department->department_name : "#{$filters['department_id']}");
        }

        if (! empty($filters['run_status'])) {
            $criteria[] = 'Run state: '.strtoupper((string) $filters['run_status']);
        }

        return $criteria;
    }
}
