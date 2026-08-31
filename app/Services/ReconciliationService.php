<?php

namespace App\Services;

// system-architecture.md §6.4, §6.6 / FR-2.9, BR-06, BR-37, BR-38.
//
// Sprint 1b/W3 scope: a pure domain check over the rows
// RegisterImportService already parsed to decimal strings, plus the run's
// employee population. No database, no browser (AD-04, AD-05) — this is
// the second half of the pair that must be drivable from a test harness
// against fixtures, the same argument §6.6 makes for RegisterImportService.
// Wiring this against real repositories inside PayrollRunController's
// transaction (architecture Figure 4) is Sprint 5/W7.
//
// Every sum and comparison uses BCMath at scale 2 (§6.4, AD-18's argument
// carried into the reconciliation step) — never a native PHP operator on
// a monetary value.
class ReconciliationService
{
    /**
     * @param  array<int, array{row_number: int, employee_no: string, earnings: array<string, string>, deductions: array<string, string>, employer_shares: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}>  $rows
     *                                                                                                                                                                                                                                                   Rows as returned by RegisterImportService::parseRows().
     * @param  array<int, array{employee_no: string, is_active: bool}>  $employees
     *                                                                              Every employee in the run's scope. BR-38's "active employee in the
     *                                                                              run's population" is the subset where is_active is true; the
     *                                                                              inactive rows are carried so a row matching one can be told apart
     *                                                                              from a row matching no employee at all.
     * @param  array{gross_pay?: string, total_deductions?: string, net_pay?: string}|null  $fileControlTotals
     *                                                                                                          Control totals the register itself carries, if any (FR-2.9 behavior
     *                                                                                                          2). The canonical template has no reserved control-total cells
     *                                                                                                          until OI-12 fixes the real layout (AD-17), so this is supplied by
     *                                                                                                          the caller rather than parsed by RegisterImportService — proceeding
     *                                                                                                          on the documented default per implementation-plan.md §5. Omitted
     *                                                                                                          entirely, this check is skipped, matching "where the register
     *                                                                                                          carries control totals."
     *
     * @throws ReconciliationException if any row, control total, matching, or completeness check fails (E2-E5).
     */
    public function reconcile(array $rows, array $employees, ?array $fileControlTotals = null): ReconciliationResult
    {
        $defects = [];

        $employeeIsActive = [];
        foreach ($employees as $employee) {
            $employeeIsActive[$employee['employee_no']] = $employee['is_active'];
        }

        $seenRowsByEmployeeNo = [];
        $matchedActiveEmployeeNumbers = [];

        $sumGross = '0.00';
        $sumDeductions = '0.00';
        $sumNet = '0.00';

        foreach ($rows as $row) {
            $defects = [
                ...$defects,
                ...$this->checkRowArithmetic($row),
                ...$this->checkEmployeeMatch($row, $employeeIsActive, $seenRowsByEmployeeNo, $matchedActiveEmployeeNumbers),
            ];

            $seenRowsByEmployeeNo[$row['employee_no']] ??= [];
            $seenRowsByEmployeeNo[$row['employee_no']][] = $row['row_number'];

            $sumGross = bcadd($sumGross, $row['gross_pay'], 2);
            $sumDeductions = bcadd($sumDeductions, $row['total_deductions'], 2);
            $sumNet = bcadd($sumNet, $row['net_pay'], 2);
        }

        foreach ($employeeIsActive as $employeeNo => $isActive) {
            if ($isActive && ! isset($matchedActiveEmployeeNumbers[$employeeNo])) {
                $defects[] = new ReconciliationDefect(
                    type: 'OMITTED_ACTIVE_EMPLOYEE',
                    message: "Active employee '{$employeeNo}' is in the run's population but does not appear in the register.",
                    employeeNo: $employeeNo,
                );
            }
        }

        $defects = [...$defects, ...$this->checkFileControlTotals($fileControlTotals, $sumGross, $sumDeductions, $sumNet)];

        if ($defects !== []) {
            throw new ReconciliationException($defects);
        }

        return new ReconciliationResult(
            rowCount: count($rows),
            controlTotalGross: $sumGross,
            controlTotalDeductions: $sumDeductions,
            controlTotalNet: $sumNet,
            matchedEmployeeCount: count($matchedActiveEmployeeNumbers),
        );
    }

    /**
     * BR-37 — for this row alone: gross = sum(earnings), total deductions =
     * sum(deductions), net = gross - total deductions. Exact to the
     * centavo; no tolerance (AC-2.9.1, AC-2.9.2).
     *
     * @param  array{row_number: int, employee_no: string, earnings: array<string, string>, deductions: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}  $row
     * @return array<int, ReconciliationDefect>
     */
    private function checkRowArithmetic(array $row): array
    {
        $defects = [];

        $expectedGross = $this->sumMoney($row['earnings']);
        if (bccomp($expectedGross, $row['gross_pay'], 2) !== 0) {
            $defects[] = new ReconciliationDefect(
                type: 'ROW_GROSS_MISMATCH',
                message: "Row {$row['row_number']} ({$row['employee_no']}): gross pay {$row['gross_pay']} does not equal the sum of earnings {$expectedGross}.",
                row: $row['row_number'],
                employeeNo: $row['employee_no'],
            );
        }

        $expectedDeductions = $this->sumMoney($row['deductions']);
        if (bccomp($expectedDeductions, $row['total_deductions'], 2) !== 0) {
            $defects[] = new ReconciliationDefect(
                type: 'ROW_DEDUCTIONS_MISMATCH',
                message: "Row {$row['row_number']} ({$row['employee_no']}): total deductions {$row['total_deductions']} does not equal the sum of deductions {$expectedDeductions}.",
                row: $row['row_number'],
                employeeNo: $row['employee_no'],
            );
        }

        $expectedNet = bcsub($row['gross_pay'], $row['total_deductions'], 2);
        if (bccomp($expectedNet, $row['net_pay'], 2) !== 0) {
            $defects[] = new ReconciliationDefect(
                type: 'ROW_NET_MISMATCH',
                message: "Row {$row['row_number']} ({$row['employee_no']}): net pay {$row['net_pay']} does not equal gross less deductions {$expectedNet}.",
                row: $row['row_number'],
                employeeNo: $row['employee_no'],
            );
        }

        return $defects;
    }

    /**
     * BR-06, BR-38 — a row must match exactly one active employee in the
     * population, and every employee number appears at most once
     * (AC-2.9.4, and the duplicate-row half of BR-38).
     *
     * @param  array{row_number: int, employee_no: string}  $row
     * @param  array<string, bool>  $employeeIsActive
     * @param  array<string, array<int, int>>  $seenRowsByEmployeeNo
     * @param  array<string, bool>  $matchedActiveEmployeeNumbers
     * @return array<int, ReconciliationDefect>
     */
    private function checkEmployeeMatch(array $row, array $employeeIsActive, array $seenRowsByEmployeeNo, array &$matchedActiveEmployeeNumbers): array
    {
        $defects = [];
        $employeeNo = $row['employee_no'];

        if (isset($seenRowsByEmployeeNo[$employeeNo])) {
            $defects[] = new ReconciliationDefect(
                type: 'DUPLICATE_EMPLOYEE_ROW',
                message: "Row {$row['row_number']}: employee number '{$employeeNo}' already appears in row {$seenRowsByEmployeeNo[$employeeNo][0]}.",
                row: $row['row_number'],
                employeeNo: $employeeNo,
            );
        }

        if (! array_key_exists($employeeNo, $employeeIsActive)) {
            $defects[] = new ReconciliationDefect(
                type: 'UNMATCHED_EMPLOYEE',
                message: "Row {$row['row_number']}: employee number '{$employeeNo}' matches no active employee in the run's population.",
                row: $row['row_number'],
                employeeNo: $employeeNo,
            );
        } elseif (! $employeeIsActive[$employeeNo]) {
            $defects[] = new ReconciliationDefect(
                type: 'INACTIVE_EMPLOYEE_MATCHED',
                message: "Row {$row['row_number']}: employee number '{$employeeNo}' matches an inactive employee.",
                row: $row['row_number'],
                employeeNo: $employeeNo,
            );
        } else {
            $matchedActiveEmployeeNumbers[$employeeNo] = true;
        }

        return $defects;
    }

    /**
     * BR-37 — where the register carries a control total, it must equal
     * the sum of the rows loaded (AC-2.9.3).
     *
     * @param  array{gross_pay?: string, total_deductions?: string, net_pay?: string}|null  $fileControlTotals
     * @return array<int, ReconciliationDefect>
     */
    private function checkFileControlTotals(?array $fileControlTotals, string $sumGross, string $sumDeductions, string $sumNet): array
    {
        if ($fileControlTotals === null) {
            return [];
        }

        $defects = [];
        $computed = [
            'gross_pay' => $sumGross,
            'total_deductions' => $sumDeductions,
            'net_pay' => $sumNet,
        ];
        $labels = [
            'gross_pay' => 'gross pay',
            'total_deductions' => 'total deductions',
            'net_pay' => 'net pay',
        ];

        foreach ($computed as $field => $sum) {
            if (! array_key_exists($field, $fileControlTotals)) {
                continue;
            }

            if (bccomp($fileControlTotals[$field], $sum, 2) !== 0) {
                $defects[] = new ReconciliationDefect(
                    type: 'CONTROL_TOTAL_MISMATCH',
                    message: "The file's {$labels[$field]} control total {$fileControlTotals[$field]} does not equal the sum of loaded rows {$sum}.",
                );
            }
        }

        return $defects;
    }

    /**
     * @param  array<string, string>  $amounts
     */
    private function sumMoney(array $amounts): string
    {
        $sum = '0.00';

        foreach ($amounts as $amount) {
            $sum = bcadd($sum, $amount, 2);
        }

        return $sum;
    }
}
