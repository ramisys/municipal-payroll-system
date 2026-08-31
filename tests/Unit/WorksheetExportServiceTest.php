<?php

namespace Tests\Unit;

use App\Services\WorksheetExportException;
use App\Services\WorksheetExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

/**
 * FR-2.11 — pre-oral-demonstration-plan.md §6 Table 6, W5: "the
 * WorksheetExportService against fixtures." Rows are inserted straight
 * against the schema with DB::table(), the same evidence-code convention
 * IntakeFidelityHarnessTest uses — this proves the export mechanics
 * (header block, one row per employee, numeric-typed money columns) are
 * correct before a real screen or population exists to drive it (W7).
 */
class WorksheetExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixtures(): array
    {
        $now = now();

        DB::table('organization_profiles')->insert([
            'registered_name' => 'Municipality of Testville', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $departmentId = DB::table('departments')->insertGetId([
            'department_code' => 'ACCTG', 'department_name' => 'Accounting Office', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], 'department_id');

        $positionId = DB::table('positions')->insertGetId([
            'position_code' => 'AA-I', 'position_title' => 'Administrative Aide I', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], 'position_id');

        $employmentStatusId = DB::table('employment_statuses')->insertGetId([
            'status_name' => 'REGULAR', 'is_payroll_eligible' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], 'employment_status_id');

        $deductionTypeId = DB::table('deduction_types')->insertGetId([
            'deduction_code' => 'GSIS_LOAN', 'deduction_name' => 'GSIS Loan', 'is_statutory' => false, 'created_at' => $now, 'updated_at' => $now,
        ], 'deduction_type_id');

        $periodId = DB::table('payroll_periods')->insertGetId([
            'payroll_year' => 2025, 'period_no' => 1, 'pay_frequency' => 'MONTHLY',
            'cutoff_start' => '2025-01-01', 'cutoff_end' => '2025-01-31', 'pay_date' => '2025-02-05',
            'created_at' => $now, 'updated_at' => $now,
        ], 'payroll_period_id');

        $runId = DB::table('payroll_runs')->insertGetId([
            'payroll_period_id' => $periodId, 'run_type' => 'REGULAR', 'population_scope' => 'ALL', 'run_status' => 'DRAFT',
            'created_at' => $now, 'updated_at' => $now,
        ], 'payroll_run_id');

        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => 'E-0001', 'last_name' => 'Santos', 'first_name' => 'Maria', 'birth_date' => '1990-01-01',
            'sex' => 'F', 'civil_status' => 'SINGLE', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], 'employee_id');

        DB::table('employment_details')->insert([
            'employee_id' => $employeeId, 'department_id' => $departmentId, 'position_id' => $positionId,
            'employment_status_id' => $employmentStatusId, 'date_hired' => '2020-01-01', 'effective_from' => '2020-01-01',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('compensation_profiles')->insert([
            'employee_id' => $employeeId, 'pay_basis' => 'MONTHLY', 'basic_rate' => '25000.00',
            'effective_from' => '2020-01-01', 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('recurring_deductions')->insert([
            'employee_id' => $employeeId, 'deduction_type_id' => $deductionTypeId, 'amount' => '500.00',
            'effective_from' => '2020-01-01', 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('loan_accounts')->insert([
            'employee_id' => $employeeId, 'deduction_type_id' => $deductionTypeId, 'loan_reference' => 'LN-0001',
            'principal_amount' => '10000.00', 'amortization_amount' => '500.00', 'term_periods' => 20,
            'outstanding_balance' => '4500.00', 'start_period_id' => $periodId, 'loan_status' => 'ACTIVE',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['run_id' => $runId, 'employee_id' => $employeeId];
    }

    public function test_the_export_refuses_a_run_that_does_not_exist(): void
    {
        $this->expectException(WorksheetExportException::class);

        (new WorksheetExportService)->export(999999);
    }

    // AC-2.11.1, AC-2.11.4 — every employee in the population appears
    // exactly once; the header block names the run and period.
    public function test_every_included_employee_appears_once_with_the_header_block_naming_the_run(): void
    {
        $fixture = $this->seedFixtures();

        $spreadsheet = (new WorksheetExportService)->export($fixture['run_id']);
        $sheet = $spreadsheet->getActiveSheet();

        self::assertStringContainsString('Testville', (string) $sheet->getCell([2, 1])->getValue());
        self::assertStringContainsString((string) $fixture['run_id'], (string) $sheet->getCell([2, 2])->getValue());
        self::assertStringContainsString('2025-1', (string) $sheet->getCell([2, 3])->getValue());

        // header block (rows 1-5) + 1 blank row (6) + 1 column-header row (7) = row 8 is the first data row.
        self::assertSame('E-0001', $sheet->getCell([1, 8])->getValue());
        self::assertNull($sheet->getCell([1, 9])->getValue());
    }

    // Behavior 6 — monetary and rate columns are typed as numbers.
    public function test_monetary_columns_are_typed_as_numbers_not_text(): void
    {
        $fixture = $this->seedFixtures();

        $spreadsheet = (new WorksheetExportService)->export($fixture['run_id']);
        $sheet = $spreadsheet->getActiveSheet();

        // columns: 1 no., 2 name, 3 dept, 4 position, 5 pay basis, 6 basic rate, 7 standing deduction, 8 open loan balance
        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell([6, 8])->getDataType());
        self::assertSame(25000.0, $sheet->getCell([6, 8])->getValue());
        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell([7, 8])->getDataType());
        self::assertSame(500.0, $sheet->getCell([7, 8])->getValue());
        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell([8, 8])->getDataType());
        self::assertSame(4500.0, $sheet->getCell([8, 8])->getValue());
    }

    // AC-2.11.5 — exporting twice with unchanged inputs produces identical data.
    public function test_exporting_the_same_run_twice_produces_identical_row_data(): void
    {
        $fixture = $this->seedFixtures();
        $service = new WorksheetExportService;

        $first = $service->export($fixture['run_id'])->getActiveSheet();
        $second = $service->export($fixture['run_id'])->getActiveSheet();

        for ($row = 8; $row <= 8; $row++) {
            for ($col = 1; $col <= 8; $col++) {
                self::assertSame($first->getCell([$col, $row])->getValue(), $second->getCell([$col, $row])->getValue());
            }
        }
    }

    // A deactivated employee is excluded from a new run's population (AC-1.1.4's export-side mirror).
    public function test_a_deactivated_employee_is_excluded_from_the_export(): void
    {
        $fixture = $this->seedFixtures();

        DB::table('employees')->where('employee_id', $fixture['employee_id'])->update(['is_active' => false]);
        DB::table('employment_details')->where('employee_id', $fixture['employee_id'])->update(['effective_to' => '2025-01-15']);

        $sheet = (new WorksheetExportService)->export($fixture['run_id'])->getActiveSheet();

        self::assertNull($sheet->getCell([1, 8])->getValue());
    }
}
