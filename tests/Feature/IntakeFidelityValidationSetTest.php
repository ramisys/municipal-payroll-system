<?php

namespace Tests\Feature;

use App\Models\ImportColumnMap;
use App\Models\User;
use App\Services\IntakeFidelityHarness;
use App\Services\RegisterImportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * NFR-2.12's own validation set (FRS matrix row, problem-requirements-matrix.md
 * §5): "≥ 30 employees across 3 payroll periods, covering regular,
 * overtime, leave-affected, and loan-deducted cases. Pass = 100% agreement
 * to the centavo on both directions ... Includes a seeded-defect pass."
 *
 * IntakeFidelityHarnessTest proves the harness itself is correct against a
 * 3-row fixture (§3.3: "this test proves the harness itself is correct
 * against fixtures ... the 30-employee/3-period evidence run is W6-W7,
 * once real compensation profiles exist"). This is that run, held back
 * until W8 (pre-oral-demonstration-plan.md §8: "NFR-2.12 fidelity run" is
 * captured in W6-W7/W8) — it seeds the actual 30-employee demo population
 * (EmployeeDemoSeeder) so IntakeFidelityHarness's findOrCreateEmployee/
 * findOrCreateCompensationProfile resolve the real demo rows rather than
 * harness stand-ins, and drives three distinct register files (one period
 * each — the harness's own createScaffoldPeriod() opens a new payroll year
 * on every importAndStore() call) covering every category the validation
 * set names.
 */
class IntakeFidelityValidationSetTest extends TestCase
{
    use RefreshDatabase;

    private function harness(): IntakeFidelityHarness
    {
        return new IntakeFidelityHarness(new RegisterImportService);
    }

    /**
     * @return array<int, string> the 30 demo employee numbers, E-1000..E-1029
     */
    private function demoEmployeeNumbers(): array
    {
        return array_map(fn (int $i) => sprintf('E-%04d', 1000 + $i), range(0, 29));
    }

    /**
     * Builds one arithmetically-correct row: gross/deductions/net are
     * derived with BCMath from the earning/deduction amounts supplied,
     * never hand-computed, so 90 rows across three files carry no
     * transcription slip of their own.
     *
     * @param  array<string, string>  $earnings  code => amount, non-zero entries only
     * @param  array<string, string>  $deductions  code => amount, non-zero entries only
     * @return array<int, string> the 19-column canonical row (see GenerateTestFixtures::canonicalHeaders())
     */
    private function buildRow(string $employeeNo, array $earnings, array $deductions): array
    {
        $earningCodes = ['BASIC', 'OT', 'NIGHT_DIFF', 'HOLIDAY_PAY', 'ALLOWANCE', 'THIRTEENTH_MONTH'];
        $deductionCodes = ['SSS', 'PHILHEALTH', 'PAGIBIG', 'WTAX', 'LOAN', 'OTHER'];
        $employerShareCodes = ['SSS', 'PHILHEALTH', 'PAGIBIG'];

        $gross = '0.00';
        $earningValues = [];
        foreach ($earningCodes as $code) {
            $amount = $earnings[$code] ?? '0.00';
            $earningValues[] = $amount;
            $gross = bcadd($gross, $amount, 2);
        }

        $totalDeductions = '0.00';
        $deductionValues = [];
        foreach ($deductionCodes as $code) {
            $amount = $deductions[$code] ?? '0.00';
            $deductionValues[] = $amount;
            $totalDeductions = bcadd($totalDeductions, $amount, 2);
        }

        $employerShareValues = array_map(fn (string $code) => $deductions[$code] ?? '0.00', $employerShareCodes);

        $net = bcsub($gross, $totalDeductions, 2);

        return [$employeeNo, ...$earningValues, ...$deductionValues, ...$employerShareValues, $gross, $totalDeductions, $net];
    }

    /**
     * Every employee gets a regular BASIC every period (the "regular"
     * case, always present). Category coverage per NFR-2.12's own wording:
     * employees 0-9 additionally carry OT>0 in every period (overtime);
     * 10-19 carry a BASIC below their nominal rate, standing in for the
     * register figure an unpaid-leave day produces — the system carries
     * this figure, it does not derive it (leave-affected); 20-29 carry a
     * non-zero LOAN deduction (loan-deducted).
     *
     * @return array<int, array<int, string>>
     */
    private function periodRows(int $periodIndex): array
    {
        $rows = [];

        foreach ($this->demoEmployeeNumbers() as $i => $employeeNo) {
            $basic = (string) (20000 + ($i * 100) + ($periodIndex * 10));

            $earnings = ['BASIC' => bcadd($basic, '0', 2)];
            if ($i < 10) {
                $earnings['OT'] = number_format(500 + $i * 3.25, 2, '.', '');
            }
            if ($i >= 10 && $i < 20) {
                // Leave-affected: the carried BASIC is lower than the
                // employee's nominal rate — an unpaid leave day the
                // accounting office already priced into the register, not
                // a figure this system derived (CR-01).
                $earnings['BASIC'] = bcsub($earnings['BASIC'], '850.00', 2);
            }

            $deductions = [
                'SSS' => '900.00',
                'PHILHEALTH' => '500.00',
                'PAGIBIG' => '100.00',
                'WTAX' => number_format(700 + $i * 4.10, 2, '.', ''),
            ];
            if ($i >= 20) {
                $deductions['LOAN'] = number_format(300 + $i * 2.50, 2, '.', '');
            }

            $rows[] = $this->buildRow($employeeNo, $earnings, $deductions);
        }

        return $rows;
    }

    private function writeRegisterFile(string $path, array $rows): void
    {
        $headers = [
            'Employee No.', 'Basic Pay', 'Overtime Pay', 'Night Shift Differential',
            'Holiday Pay', 'Representation and Transportation Allowance', '13th Month Pay',
            'SSS Contribution', 'PhilHealth Contribution', 'Pag-IBIG Contribution',
            'Withholding Tax', 'Loan Amortization', 'Other Deduction',
            'SSS ER Share', 'PhilHealth ER Share', 'Pag-IBIG ER Share',
            'Gross Pay', 'Total Deductions', 'Net Pay',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            foreach ($row as $col => $value) {
                $excelCol = $col + 1;
                if ($excelCol === 1) {
                    $sheet->setCellValue([$excelCol, $excelRow], $value);
                } else {
                    $sheet->setCellValueExplicit([$excelCol, $excelRow], (float) $value, DataType::TYPE_NUMERIC);
                }
            }
        }

        (new Xlsx($spreadsheet))->save($path);
    }

    public function test_thirty_employees_across_three_periods_agree_to_the_centavo_in_both_directions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $map = ImportColumnMap::active('CANONICAL');
        $user = User::factory()->forRole('ADMINISTRATOR')->create();
        $harness = $this->harness();

        $categoriesSeen = ['regular' => false, 'overtime' => false, 'leave_affected' => false, 'loan_deducted' => false];
        $totalRowsValidated = 0;

        for ($period = 1; $period <= 3; $period++) {
            $rows = $this->periodRows($period);
            self::assertCount(30, $rows, "period {$period} must carry the full 30-employee population.");

            $path = sys_get_temp_dir()."/nfr212_period_{$period}.xlsx";
            $this->writeRegisterFile($path, $rows);

            try {
                $stored = $harness->importAndStore($path, $map, $user->user_id);
                self::assertCount(30, $stored['source_rows']);

                $toDb = $harness->compareFileToDatabase($stored['source_rows'], $stored['line_ids']);
                self::assertSame([], array_map(fn ($m) => $m->toArray(), $toDb), "period {$period}: file -> database must agree to the centavo.");

                $toFile = $harness->compareDatabaseToFile($stored['source_rows'], $stored['line_ids']);
                self::assertSame([], array_map(fn ($m) => $m->toArray(), $toFile), "period {$period}: database -> file must re-export identically.");

                $totalRowsValidated += count($stored['source_rows']);
            } finally {
                @unlink($path);
            }

            $categoriesSeen['regular'] = true; // BASIC is present on every row, every period
            $categoriesSeen['overtime'] = $categoriesSeen['overtime'] || bccomp($rows[0][2], '0.00', 2) > 0;
            $categoriesSeen['leave_affected'] = $categoriesSeen['leave_affected'] || bccomp($rows[15][1], (string) (20000 + 15 * 100 + $period * 10), 2) < 0;
            $categoriesSeen['loan_deducted'] = $categoriesSeen['loan_deducted'] || bccomp($rows[29][11], '0.00', 2) > 0;
        }

        self::assertSame(90, $totalRowsValidated, 'NFR-2.12 validation set: ≥30 employees across 3 payroll periods.');
        self::assertTrue($categoriesSeen['regular'], 'regular case not represented');
        self::assertTrue($categoriesSeen['overtime'], 'overtime case not represented');
        self::assertTrue($categoriesSeen['leave_affected'], 'leave-affected case not represented');
        self::assertTrue($categoriesSeen['loan_deducted'], 'loan-deducted case not represented');
    }

    // "Includes a seeded-defect pass: a value altered in the source file
    // must produce a different stored value, proving the comparison is
    // live" — proven once at IntakeFidelityHarnessTest's 3-row scale
    // already; this repeats it against the full 30-employee validation
    // set so the pass is not an artifact of the smaller fixture.
    public function test_an_altered_value_is_detected_within_the_full_validation_set(): void
    {
        $this->seed(DatabaseSeeder::class);

        $map = ImportColumnMap::active('CANONICAL');
        $user = User::factory()->forRole('ADMINISTRATOR')->create();
        $harness = $this->harness();

        $path = sys_get_temp_dir().'/nfr212_defect_pass.xlsx';
        $this->writeRegisterFile($path, $this->periodRows(1));

        try {
            $stored = $harness->importAndStore($path, $map, $user->user_id);
        } finally {
            @unlink($path);
        }

        $altered = $stored['source_rows'];
        $altered[7]['earnings']['BASIC'] = bcadd($altered[7]['earnings']['BASIC'], '0.01', 2);

        $mismatches = $harness->compareFileToDatabase($altered, $stored['line_ids']);

        self::assertNotSame([], $mismatches);
        self::assertSame('earnings.BASIC', $mismatches[0]->field);
        self::assertSame($altered[7]['employee_no'], $mismatches[0]->employeeNo);
    }
}
