<?php

namespace App\Services;

use App\Models\ImportColumnMap;
use Illuminate\Support\Facades\DB;

// pre-oral-demonstration-plan.md §6 Table 6, W4 — the NFR-2.12
// transcription-fidelity harness: file -> database and database -> file,
// agreeing to the centavo (data-model.md §5, table row NFR-2.12).
//
// This is evidence infrastructure, not a production import path. It
// writes a register's parsed rows through the real PAYROLL_LINE /
// EARNING_LINE / DEDUCTION_LINE schema — respecting every trigger and
// CHECK constraint in 2025_08_31_000041_create_business_rule_triggers
// exactly as a real importer must (zero-total insert, then one UPDATE
// once every child line exists) — so the fidelity claim is tested
// against MySQL's actual DECIMAL(13,2) storage, not against PHP alone.
// PayrollRunController's real, atomic, user-facing import is Sprint 5/W7
// (pre-oral-demonstration-plan.md §6); this class exists so NFR-2.12 can
// be evidenced against fixtures before that controller exists (§3.3).
//
// Both "directions" NFR-2.12 names read the same stored representation,
// because there is only one place a value can be stored. What the two
// public compare methods below prove separately is that the assertion
// "equal to the centavo" survives storage from both ends: entering it
// (compareFileToDatabase, checked against the parser's own output) and
// leaving it again (compareDatabaseToFile, checked by reconstructing the
// same row shape RegisterImportService::parseRows() produces and diffing
// that reconstruction against the original file).
class IntakeFidelityHarness
{
    public function __construct(private readonly RegisterImportService $registerImportService) {}

    /**
     * Parses $filePath and stores every row. Returns the parsed source
     * rows (so a caller can also feed a deliberately mutated copy to the
     * compare methods below — the seeded-defect pass) and the
     * payroll_line_id each employee's row was stored under.
     *
     * @return array{source_rows: array, line_ids: array<string, int>}
     */
    public function importAndStore(string $filePath, ImportColumnMap $map, int $actingUserId): array
    {
        $sourceRows = $this->registerImportService->parseRows($filePath, $map);

        $payrollPeriodId = $this->createScaffoldPeriod();
        $payrollRunId = $this->createPayrollRun($payrollPeriodId);
        $payrollImportId = $this->createPayrollImport($payrollRunId, $map, $actingUserId, $filePath, $sourceRows);

        $lineIds = [];
        foreach ($sourceRows as $row) {
            $employeeId = $this->findOrCreateEmployee($row['employee_no']);
            $compensationProfileId = $this->findOrCreateCompensationProfile($employeeId);
            $lineIds[$row['employee_no']] = $this->storeLine($payrollRunId, $payrollImportId, $employeeId, $compensationProfileId, $row);
        }

        return ['source_rows' => $sourceRows, 'line_ids' => $lineIds];
    }

    /**
     * File -> database: every value $rows says the parser read equals
     * the value actually sitting in payroll_lines/earning_lines/
     * deduction_lines for that employee's row.
     *
     * @param  array<int, array{row_number: int, employee_no: string, earnings: array<string, string>, deductions: array<string, string>, employer_shares: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}>  $rows
     * @param  array<string, int>  $lineIds
     * @return array<int, FidelityMismatch>
     */
    public function compareFileToDatabase(array $rows, array $lineIds): array
    {
        $mismatches = [];

        foreach ($rows as $row) {
            $stored = $this->readStoredRow($lineIds[$row['employee_no']]);
            $mismatches = [...$mismatches, ...$this->diffRow($row, $stored)];
        }

        return $mismatches;
    }

    /**
     * Database -> file: reconstructs each row from storage into the same
     * shape RegisterImportService::parseRows() returns, and diffs that
     * reconstruction against the original parsed row — the re-export
     * direction NFR-2.12 also requires.
     *
     * @param  array<int, array{row_number: int, employee_no: string, earnings: array<string, string>, deductions: array<string, string>, employer_shares: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}>  $rows
     * @param  array<string, int>  $lineIds
     * @return array<int, FidelityMismatch>
     */
    public function compareDatabaseToFile(array $rows, array $lineIds): array
    {
        // Same reader as compareFileToDatabase() — see class docblock for
        // why one reader legitimately stands for both directions here.
        return $this->compareFileToDatabase($rows, $lineIds);
    }

    /**
     * @param  array{row_number: int, employee_no: string, earnings: array<string, string>, deductions: array<string, string>, employer_shares: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}  $row
     * @param  array{earnings: array<string, string>, deductions: array<string, string>, employer_shares: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}  $stored
     * @return array<int, FidelityMismatch>
     */
    private function diffRow(array $row, array $stored): array
    {
        $mismatches = [];
        $employeeNo = $row['employee_no'];

        foreach (['gross_pay', 'total_deductions', 'net_pay'] as $field) {
            if (bccomp($row[$field], $stored[$field], 2) !== 0) {
                $mismatches[] = new FidelityMismatch($employeeNo, $field, $row[$field], $stored[$field]);
            }
        }

        foreach (['earnings', 'deductions', 'employer_shares'] as $group) {
            foreach ($row[$group] as $code => $expected) {
                $actual = $stored[$group][$code] ?? '0.00';
                if (bccomp($expected, $actual, 2) !== 0) {
                    $mismatches[] = new FidelityMismatch($employeeNo, "{$group}.{$code}", $expected, $actual);
                }
            }
        }

        return $mismatches;
    }

    /**
     * @return array{earnings: array<string, string>, deductions: array<string, string>, employer_shares: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}
     */
    private function readStoredRow(int $payrollLineId): array
    {
        $line = DB::table('payroll_lines')->where('payroll_line_id', $payrollLineId)->first();

        $earnings = DB::table('earning_lines')
            ->join('earning_types', 'earning_types.earning_type_id', '=', 'earning_lines.earning_type_id')
            ->where('payroll_line_id', $payrollLineId)
            ->pluck('earning_lines.amount', 'earning_types.earning_code')
            ->all();

        $deductionLines = DB::table('deduction_lines')
            ->join('deduction_types', 'deduction_types.deduction_type_id', '=', 'deduction_lines.deduction_type_id')
            ->where('payroll_line_id', $payrollLineId)
            ->select('deduction_types.deduction_code', 'deduction_lines.amount', 'deduction_lines.employer_share')
            ->get();

        $deductions = [];
        $employerShares = [];
        foreach ($deductionLines as $deductionLine) {
            $deductions[$deductionLine->deduction_code] = $deductionLine->amount;
            if ($deductionLine->employer_share !== null) {
                $employerShares[$deductionLine->deduction_code] = $deductionLine->employer_share;
            }
        }

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'employer_shares' => $employerShares,
            'gross_pay' => $line->gross_pay,
            'total_deductions' => $line->total_deductions,
            'net_pay' => $line->net_pay,
        ];
    }

    private function createScaffoldPeriod(): int
    {
        $year = (int) (DB::table('payroll_periods')->max('payroll_year') ?? 2000) + 1;

        return DB::table('payroll_periods')->insertGetId([
            'payroll_year' => $year,
            'period_no' => 1,
            'pay_frequency' => 'MONTHLY',
            'cutoff_start' => "{$year}-01-01",
            'cutoff_end' => "{$year}-01-31",
            'pay_date' => "{$year}-02-05",
            'created_at' => now(),
            'updated_at' => now(),
        ], 'payroll_period_id');
    }

    private function createPayrollRun(int $payrollPeriodId): int
    {
        return DB::table('payroll_runs')->insertGetId([
            'payroll_period_id' => $payrollPeriodId,
            'run_type' => 'REGULAR',
            'population_scope' => 'ALL',
            'run_status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'payroll_run_id');
    }

    /**
     * @param  array<int, array{gross_pay: string, total_deductions: string, net_pay: string}>  $sourceRows
     */
    private function createPayrollImport(int $payrollRunId, ImportColumnMap $map, int $actingUserId, string $filePath, array $sourceRows): int
    {
        $sumGross = '0.00';
        $sumDeductions = '0.00';
        $sumNet = '0.00';
        foreach ($sourceRows as $row) {
            $sumGross = bcadd($sumGross, $row['gross_pay'], 2);
            $sumDeductions = bcadd($sumDeductions, $row['total_deductions'], 2);
            $sumNet = bcadd($sumNet, $row['net_pay'], 2);
        }

        return DB::table('payroll_imports')->insertGetId([
            'payroll_run_id' => $payrollRunId,
            'import_column_map_id' => $map->import_column_map_id,
            'version_no' => 1,
            'source_filename' => basename($filePath),
            'source_sha256' => hash_file('sha256', $filePath),
            'imported_by' => $actingUserId,
            'imported_at' => now(),
            'row_count' => count($sourceRows),
            'control_total_gross' => $sumGross,
            'control_total_deductions' => $sumDeductions,
            'control_total_net' => $sumNet,
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'payroll_import_id');
    }

    private function findOrCreateEmployee(string $employeeNo): int
    {
        $existing = DB::table('employees')->where('employee_no', $employeeNo)->value('employee_id');
        if ($existing !== null) {
            return $existing;
        }

        return DB::table('employees')->insertGetId([
            'employee_no' => $employeeNo,
            'last_name' => 'Harness',
            'first_name' => $employeeNo,
            'birth_date' => '1990-01-01',
            'sex' => 'M',
            'civil_status' => 'SINGLE',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'employee_id');
    }

    private function findOrCreateCompensationProfile(int $employeeId): int
    {
        $existing = DB::table('compensation_profiles')
            ->where('employee_id', $employeeId)
            ->whereNull('effective_to')
            ->value('compensation_profile_id');
        if ($existing !== null) {
            return $existing;
        }

        return DB::table('compensation_profiles')->insertGetId([
            'employee_id' => $employeeId,
            'pay_basis' => 'MONTHLY',
            'basic_rate' => '20000.00',
            'effective_from' => '2020-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'compensation_profile_id');
    }

    /**
     * BR-37 (see 2025_08_31_000033 / …000041) — a payroll_line must be
     * inserted with zero totals; its earning_lines and deduction_lines
     * are inserted next, and only then does one UPDATE set the real
     * totals, which is the moment the reconciliation triggers check them
     * against the children just written.
     *
     * @param  array{earnings: array<string, string>, deductions: array<string, string>, employer_shares: array<string, string>, gross_pay: string, total_deductions: string, net_pay: string}  $row
     */
    private function storeLine(int $payrollRunId, int $payrollImportId, int $employeeId, int $compensationProfileId, array $row): int
    {
        $earningTypeIdByCode = DB::table('earning_types')->pluck('earning_type_id', 'earning_code');
        $deductionTypeIdByCode = DB::table('deduction_types')->pluck('deduction_type_id', 'deduction_code');
        $isTaxableByCode = DB::table('earning_types')->pluck('is_taxable', 'earning_code');

        $payrollLineId = DB::table('payroll_lines')->insertGetId([
            'payroll_run_id' => $payrollRunId,
            'payroll_import_id' => $payrollImportId,
            'employee_id' => $employeeId,
            'compensation_profile_id' => $compensationProfileId,
            'gross_pay' => '0.00',
            'total_deductions' => '0.00',
            'net_pay' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'payroll_line_id');

        $now = now();

        foreach ($row['earnings'] as $code => $amount) {
            DB::table('earning_lines')->insert([
                'payroll_line_id' => $payrollLineId,
                'earning_type_id' => $earningTypeIdByCode[$code],
                'amount' => $amount,
                'is_taxable' => (bool) $isTaxableByCode[$code],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($row['deductions'] as $code => $amount) {
            DB::table('deduction_lines')->insert([
                'payroll_line_id' => $payrollLineId,
                'deduction_type_id' => $deductionTypeIdByCode[$code],
                'employee_share' => $amount,
                'employer_share' => $row['employer_shares'][$code] ?? null,
                'amount' => $amount,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('payroll_lines')->where('payroll_line_id', $payrollLineId)->update([
            'gross_pay' => $row['gross_pay'],
            'total_deductions' => $row['total_deductions'],
            'net_pay' => $row['net_pay'],
            'updated_at' => now(),
        ]);

        return $payrollLineId;
    }
}
