<?php

namespace Tests\Unit;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

/**
 * implementation-plan.md §3 item 0.5 / pre-oral-demonstration-plan.md §5 —
 * "One test that reads a monetary cell from a real .xlsx as a float and
 * shows it failing, then reads the same cell as a decimal string and shows
 * it holding — committed as the executable statement of BR-40 and AD-18."
 *
 * The fixture (tests/Fixtures/no_float_proof.xlsx, written by
 * App\Console\Commands\GenerateTestFixtures) holds two cells: 0.10 and 0.20.
 * Their sum is the textbook demonstration that IEEE-754 binary addition is
 * not decimal addition — every mainstream platform reproduces it, which is
 * exactly why it is the right fixture for a proof that has to hold up under
 * questioning at a defense.
 */
class NoFloatParseProofTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__.'/../Fixtures/no_float_proof.xlsx';
    }

    public function test_reading_the_cells_as_floats_and_summing_them_fails_to_equal_the_decimal_sum(): void
    {
        $sheet = IOFactory::load($this->fixturePath())->getActiveSheet();

        // This is what a naive parser does: Cell::getValue() on a numeric
        // cell returns a PHP float, and payroll code sums floats with `+`.
        $a = $sheet->getCell('A2')->getValue();
        $b = $sheet->getCell('A3')->getValue();

        self::assertIsFloat($a);
        self::assertIsFloat($b);

        $floatSum = $a + $b;

        // The failure: 0.10 + 0.20 read and summed as binary floats is not
        // identically 0.30. This is BR-40's prohibition made concrete —
        // not a hypothetical, the literal value computed one line above.
        self::assertNotSame(0.30, $floatSum, 'Expected float addition to NOT equal 0.30 exactly — if this assertion fails, PHP\'s float representation changed and this proof needs a new fixture value.');

        // The drift is real, not a display artifact: at 17 significant
        // digits it is visible even though PHP's default float-to-string
        // conversion (round-trip shortest representation) hides it at
        // ordinary precision.
        self::assertStringContainsString('0.300000000000000', sprintf('%.18f', $floatSum));
    }

    public function test_reading_the_cells_as_decimal_strings_and_summing_them_with_bcmath_holds_exactly(): void
    {
        $sheet = IOFactory::load($this->fixturePath())->getActiveSheet();

        // BR-40 / AD-18's prescribed path: read the cell's formatted text
        // (the '0.00' number format applied by the fixture, see
        // GenerateTestFixtures) rather than its native float value, and
        // treat that text as the decimal value — never converting through
        // a binary float.
        $a = $sheet->getCell('A2')->getFormattedValue();
        $b = $sheet->getCell('A3')->getFormattedValue();

        self::assertSame('0.10', $a);
        self::assertSame('0.20', $b);

        $decimalSum = bcadd($a, $b, 2);

        self::assertSame('0.30', $decimalSum);
    }

    /**
     * The three PAYROLL_LINE amounts in tests/Fixtures/register_clean.xlsx
     * were computed with bcadd/bcsub before being written to the fixture
     * (see GenerateTestFixtures doc comment). This test re-derives them
     * independently, through the same decimal-string read path, so the
     * fixture's own claim to "arithmetic that reconciles" (gate item 0.5)
     * is verified here rather than merely asserted in a comment.
     */
    public function test_the_clean_register_fixture_reconciles_when_read_as_decimal_strings(): void
    {
        $sheet = IOFactory::load(__DIR__.'/../Fixtures/register_clean.xlsx')->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        // Column layout written by GenerateTestFixtures::writeCleanRegisterFixture:
        // 1 employee_no, 2-7 earnings, 8-13 deductions, 14-16 employer shares,
        // 17 gross_pay, 18 total_deductions, 19 net_pay.
        for ($row = 2; $row <= $highestRow; $row++) {
            $earningsSum = '0.00';
            for ($col = 2; $col <= 7; $col++) {
                $earningsSum = bcadd($earningsSum, $sheet->getCell([$col, $row])->getFormattedValue(), 2);
            }

            $deductionsSum = '0.00';
            for ($col = 8; $col <= 13; $col++) {
                $deductionsSum = bcadd($deductionsSum, $sheet->getCell([$col, $row])->getFormattedValue(), 2);
            }

            $grossPay = $sheet->getCell([17, $row])->getFormattedValue();
            $totalDeductions = $sheet->getCell([18, $row])->getFormattedValue();
            $netPay = $sheet->getCell([19, $row])->getFormattedValue();

            self::assertSame($grossPay, $earningsSum, "Row {$row}: Gross Pay does not equal the sum of the earning columns");
            self::assertSame($totalDeductions, $deductionsSum, "Row {$row}: Total Deductions does not equal the sum of the deduction columns");
            self::assertSame($netPay, bcsub($grossPay, $totalDeductions, 2), "Row {$row}: Net Pay does not equal Gross Pay minus Total Deductions");
        }

        self::assertSame(4, $highestRow, 'Expected a one-row header plus three employee rows');
        self::assertSame(19, $highestColumn, 'Expected 19 columns matching ImportColumnMapSeeder::CANONICAL');
    }
}
