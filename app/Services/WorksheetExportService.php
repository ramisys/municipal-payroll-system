<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// FR-2.11 — Payroll input worksheet export. The system's half of the
// single-entry boundary CR-01 drew: everything the accounting office needs
// to compute leaves through this file; nothing it computes comes back
// through anything but FR-2.8's import (system-architecture.md §2).
//
// pre-oral-demonstration-plan.md §6 Table 6, W5 scope: the export
// mechanics against fixtures, queried straight off the schema with
// DB::table() (the same evidence-code convention IntakeFidelityHarness
// uses) rather than wired to a real screen — that controller and route
// are W7. Behavior 3 (the period's attendance summary and approved leave
// days) is a declared partial this week, following §4.2's own framing:
// AttendanceImportService doesn't exist until W6, and leave
// administration is out of this increment entirely. Both are added once
// their data exists to query.
//
// Behavior 6 requires monetary and rate columns typed as numbers, not
// text — that is a property of *this* direction only. It has no relation
// to BR-40/AD-18 (RegisterImportService, the opposite direction): that
// rule forbids reading an incoming monetary cell through PHP float, so a
// register value is never silently rounded on the way in. Writing a
// stored decimal string out to a numeric Excel cell is what the
// accounting office's own worksheet needs to be usable, and is what every
// spreadsheet writer does — PhpSpreadsheet's own numeric cell type.
class WorksheetExportService
{
    public function export(int $payrollRunId): Spreadsheet
    {
        $run = DB::table('payroll_runs')->where('payroll_run_id', $payrollRunId)->first();
        if ($run === null) {
            throw new WorksheetExportException("Payroll run {$payrollRunId} does not exist.");
        }

        $period = DB::table('payroll_periods')->where('payroll_period_id', $run->payroll_period_id)->first();
        $organization = DB::table('organization_profiles')->first();
        $rows = $this->resolveRows($period);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Input Worksheet');

        $nextRow = $this->writeHeaderBlock($sheet, $run, $period, $organization);
        $deductionTypes = DB::table('deduction_types')->where('is_statutory', false)->orderBy('deduction_code')->get();
        $nextRow = $this->writeColumnHeaders($sheet, $nextRow, $deductionTypes);
        $this->writeRows($sheet, $nextRow, $rows, $deductionTypes, $period);

        return $spreadsheet;
    }

    /**
     * AC-2.11.1 — every active employee whose employment covers the
     * period's cut-off, one row each. Behavior 2/BR-08 — the compensation
     * entry read is the one in force for the cut-off, not simply the
     * latest one.
     *
     * @return array<int, object>
     */
    private function resolveRows(object $period): array
    {
        return DB::table('employees')
            ->join('employment_details', 'employment_details.employee_id', '=', 'employees.employee_id')
            ->join('departments', 'departments.department_id', '=', 'employment_details.department_id')
            ->join('positions', 'positions.position_id', '=', 'employment_details.position_id')
            ->leftJoin('compensation_profiles', function ($join) use ($period) {
                $join->on('compensation_profiles.employee_id', '=', 'employees.employee_id')
                    ->where('compensation_profiles.effective_from', '<=', $period->cutoff_end)
                    ->where(function ($q) use ($period) {
                        $q->whereNull('compensation_profiles.effective_to')
                            ->orWhere('compensation_profiles.effective_to', '>=', $period->cutoff_end);
                    });
            })
            ->where('employees.is_active', true)
            ->where('employment_details.effective_from', '<=', $period->cutoff_end)
            ->where(function ($q) use ($period) {
                $q->whereNull('employment_details.effective_to')
                    ->orWhere('employment_details.effective_to', '>=', $period->cutoff_end);
            })
            ->select([
                'employees.employee_id',
                'employees.employee_no',
                'employees.last_name',
                'employees.first_name',
                'employees.middle_name',
                'departments.department_name',
                'positions.position_title',
                'compensation_profiles.pay_basis',
                'compensation_profiles.basic_rate',
            ])
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->get()
            ->all();
    }

    /**
     * Behavior 5 — names the run, period, cut-off dates, and export
     * timestamp, so a returned register can be matched to the worksheet
     * it came from (AC-2.11.4). Returns the next free row.
     */
    private function writeHeaderBlock(Worksheet $sheet, object $run, object $period, ?object $organization): int
    {
        $lines = [
            ['Organization', $organization->registered_name ?? 'Municipal Payroll System'],
            ['Payroll run', "#{$run->payroll_run_id} ({$run->run_type}, {$run->population_scope})"],
            ['Payroll period', "{$period->payroll_year}-{$period->period_no}"],
            ['Cut-off', "{$period->cutoff_start} to {$period->cutoff_end}"],
            ['Exported', now()->toDateTimeString()],
        ];

        $row = 1;
        foreach ($lines as [$label, $value]) {
            $sheet->setCellValueExplicit([1, $row], $label, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $row], $value, DataType::TYPE_STRING);
            $row++;
        }

        return $row + 1; // one blank row before the column headers
    }

    /**
     * @param  Collection<int, object>  $deductionTypes
     */
    private function writeColumnHeaders(Worksheet $sheet, int $row, $deductionTypes): int
    {
        $headers = ['Employee No.', 'Name', 'Department', 'Position', 'Pay Basis', 'Basic Rate'];
        foreach ($deductionTypes as $deductionType) {
            $headers[] = "Standing: {$deductionType->deduction_name}";
        }
        $headers[] = 'Open Loan Balance';

        foreach ($headers as $i => $header) {
            $sheet->setCellValueExplicit([$i + 1, $row], $header, DataType::TYPE_STRING);
        }

        return $row + 1;
    }

    /**
     * Behavior 4 — standing deductions and open loan balances as
     * reference columns, read live from the compensation profile (AC-1.2.2),
     * not carried forward from a prior period's copy.
     *
     * @param  array<int, object>  $rows
     * @param  Collection<int, object>  $deductionTypes
     */
    private function writeRows(Worksheet $sheet, int $startRow, array $rows, $deductionTypes, object $period): void
    {
        $row = $startRow;

        foreach ($rows as $employee) {
            $name = trim($employee->first_name.' '.($employee->middle_name ? $employee->middle_name.' ' : '').$employee->last_name);

            $col = 1;
            $sheet->setCellValueExplicit([$col++, $row], $employee->employee_no, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$col++, $row], $name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$col++, $row], $employee->department_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$col++, $row], $employee->position_title, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$col++, $row], $employee->pay_basis ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$col++, $row], (float) ($employee->basic_rate ?? 0), DataType::TYPE_NUMERIC);

            $byType = DB::table('recurring_deductions')
                ->where('employee_id', $employee->employee_id)
                ->where('effective_from', '<=', $period->cutoff_end)
                ->where(function ($q) use ($period) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $period->cutoff_end);
                })
                ->get()
                ->keyBy('deduction_type_id');

            foreach ($deductionTypes as $deductionType) {
                $amount = $byType->get($deductionType->deduction_type_id)?->amount;
                $sheet->setCellValueExplicit([$col++, $row], (float) ($amount ?? 0), DataType::TYPE_NUMERIC);
            }

            $openLoanBalance = (float) DB::table('loan_accounts')
                ->where('employee_id', $employee->employee_id)
                ->where('outstanding_balance', '>', 0)
                ->sum('outstanding_balance');
            $sheet->setCellValueExplicit([$col++, $row], $openLoanBalance, DataType::TYPE_NUMERIC);

            $row++;
        }
    }
}
