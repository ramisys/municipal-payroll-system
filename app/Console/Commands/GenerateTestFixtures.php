<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// implementation-plan.md §3 item 0.5 — writes the two fixture spreadsheets
// the no-float parse proof and the intake tests read. Re-run this whenever
// a fixture needs to change; the generated files are committed to
// tests/Fixtures so tests do not depend on this command having been run.
class GenerateTestFixtures extends Command
{
    protected $signature = 'fixtures:generate';

    protected $description = 'Regenerate the committed .xlsx test fixtures in tests/Fixtures';

    public function handle(): int
    {
        $dir = base_path('tests/Fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->writeNoFloatProofFixture("{$dir}/no_float_proof.xlsx");
        $this->info('Wrote tests/Fixtures/no_float_proof.xlsx');

        $this->writeCleanRegisterFixture("{$dir}/register_clean.xlsx");
        $this->info('Wrote tests/Fixtures/register_clean.xlsx');

        $this->writeMalformedRegisterFixture("{$dir}/register_malformed_missing_column.xlsx");
        $this->info('Wrote tests/Fixtures/register_malformed_missing_column.xlsx');

        return self::SUCCESS;
    }

    /**
     * The E1 structural-refusal fixture for RegisterImportService (week 2):
     * the clean register with its 'Net Pay' header renamed, so the
     * CANONICAL IMPORT_COLUMN_MAP cannot resolve that required column.
     * AD-17's whole premise is that a *renamed* header is the ordinary
     * failure case a mapping absorbs — this fixture is what happens when
     * no mapping version has been created for the new name yet.
     */
    private function writeMalformedRegisterFixture(string $path): void
    {
        $headers = [
            'Employee No.', 'Basic Pay', 'Overtime Pay', 'Night Shift Differential',
            'Holiday Pay', 'Representation and Transportation Allowance', '13th Month Pay',
            'SSS Contribution', 'PhilHealth Contribution', 'Pag-IBIG Contribution',
            'Withholding Tax', 'Loan Amortization', 'Other Deduction',
            'SSS ER Share', 'PhilHealth ER Share', 'Pag-IBIG ER Share',
            'Gross Pay', 'Total Deductions', 'Take-Home Pay', // was 'Net Pay'
        ];

        $rows = [
            ['E-0001', '20000.00', '1500.50', '300.25', '0.00', '2000.00', '0.00',
                '900.00', '500.00', '100.00', '1200.35', '0.00', '0.00',
                '1800.00', '500.00', '100.00', '23800.75', '2700.35', '21100.40'],
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Register');

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $moneyColumns = range(2, count($headers));

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            foreach ($row as $col => $value) {
                $excelCol = $col + 1;
                if (in_array($excelCol, $moneyColumns, true)) {
                    $sheet->setCellValueExplicit([$excelCol, $excelRow], (float) $value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValue([$excelCol, $excelRow], $value);
                }
            }
        }

        $lastRow = count($rows) + 1;
        foreach ($moneyColumns as $col) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }

        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * Two cells — 0.10 and 0.20 — chosen because their sum is the textbook
     * demonstration that IEEE-754 double addition does not equal decimal
     * addition (0.1 + 0.2 !== 0.3 at the bit level, PHP_ROUND_HALF_UP
     * display formatting notwithstanding). This is BR-40's failure case in
     * its smallest possible form.
     */
    private function writeNoFloatProofFixture(string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'amount');
        $sheet->setCellValue('A2', 0.10);
        $sheet->setCellValue('A3', 0.20);
        $sheet->getStyle('A2:A3')->getNumberFormat()->setFormatCode('0.00');

        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * One period, three employees, arithmetic that reconciles to the
     * centavo — the first fixture register named in gate item 0.5. Headers
     * match ImportColumnMapSeeder's CANONICAL column_bindings exactly, so
     * this file is readable through that seeded mapping once
     * RegisterImportService exists (Sprint 1b).
     */
    private function writeCleanRegisterFixture(string $path): void
    {
        $headers = [
            'Employee No.', 'Basic Pay', 'Overtime Pay', 'Night Shift Differential',
            'Holiday Pay', 'Representation and Transportation Allowance', '13th Month Pay',
            'SSS Contribution', 'PhilHealth Contribution', 'Pag-IBIG Contribution',
            'Withholding Tax', 'Loan Amortization', 'Other Deduction',
            'SSS ER Share', 'PhilHealth ER Share', 'Pag-IBIG ER Share',
            'Gross Pay', 'Total Deductions', 'Net Pay',
        ];

        // Each row reconciles: Gross Pay = sum of the six earning columns;
        // Total Deductions = sum of the six deduction columns (employer
        // shares are informational and do not affect Net Pay); Net Pay =
        // Gross Pay - Total Deductions. Verified with bcadd/bcsub, not float
        // arithmetic, before being written here.
        $rows = [
            ['E-0001', '20000.00', '1500.50', '300.25', '0.00', '2000.00', '0.00',
                '900.00', '500.00', '100.00', '1200.35', '0.00', '0.00',
                '1800.00', '500.00', '100.00', '23800.75', '2700.35', '21100.40'],
            ['E-0002', '18000.00', '0.00', '0.00', '692.31', '2000.00', '0.00',
                '810.00', '450.00', '100.00', '950.10', '500.00', '0.00',
                '1620.00', '450.00', '100.00', '20692.31', '2810.10', '17882.21'],
            ['E-0003', '25000.00', '2250.75', '450.00', '0.00', '2000.00', '25000.00',
                '1125.00', '625.00', '100.00', '3200.55', '0.00', '250.00',
                '2250.00', '625.00', '100.00', '54700.75', '5300.55', '49400.20'],
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Register');

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $moneyColumns = range(2, count($headers)); // every column except Employee No.

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            foreach ($row as $col => $value) {
                $excelCol = $col + 1;
                if (in_array($excelCol, $moneyColumns, true)) {
                    $sheet->setCellValueExplicit(
                        [$excelCol, $excelRow],
                        (float) $value,
                        DataType::TYPE_NUMERIC
                    );
                } else {
                    $sheet->setCellValue([$excelCol, $excelRow], $value);
                }
            }
        }

        $lastRow = count($rows) + 1;
        foreach ($moneyColumns as $col) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")
                ->getNumberFormat()->setFormatCode('0.00');
        }

        (new Xlsx($spreadsheet))->save($path);
    }
}
