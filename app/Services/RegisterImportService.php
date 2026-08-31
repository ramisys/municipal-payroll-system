<?php

namespace App\Services;

use App\Models\ImportColumnMap;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

// system-architecture.md §6.4, §6.6 / BR-40, AD-17, AD-18.
//
// Week 2 scope only: the parse path. Given a register file and an
// IMPORT_COLUMN_MAP, returns every row's monetary fields as decimal
// strings — never a PHP float, never an Excel serial number (BR-40) — or
// refuses the file structurally (E1) before any database work happens
// (§6.6). Reconciling those figures against each other (BR-37/BR-38) is
// ReconciliationService, Sprint 1b/W3 — this class does not sum, compare,
// or write anything.
//
// Column layout is never fixed in code: every header this class looks for
// comes from IMPORT_COLUMN_MAP.column_bindings (AD-17, AC-2.8.4), so a
// renamed or reordered register column is a new mapping version, not a
// source change.
class RegisterImportService
{
    /**
     * @return array<int, array{
     *     row_number: int,
     *     employee_no: string,
     *     earnings: array<string, string>,
     *     deductions: array<string, string>,
     *     employer_shares: array<string, string>,
     *     gross_pay: string,
     *     total_deductions: string,
     *     net_pay: string,
     * }>
     */
    public function parseRows(string $filePath, ImportColumnMap $map): array
    {
        $bindings = $map->column_bindings;

        $sheet = $this->loadSheet($filePath);

        $headerIndex = $this->indexHeaderRow($sheet);
        $columns = $this->resolveColumns($bindings, $headerIndex);

        $highestDataRow = $sheet->getHighestDataRow();

        if ($highestDataRow < 2) {
            throw new RegisterParseException('The register contains a header row but no data rows.');
        }

        $rows = [];

        for ($row = 2; $row <= $highestDataRow; $row++) {
            $employeeNo = trim((string) $sheet->getCell([$columns['employee_no'], $row])->getFormattedValue());

            if ($employeeNo === '') {
                // A blank employee_no this far inside the data range is a
                // malformed row, not a trailing blank — getHighestDataRow()
                // already excludes genuinely empty trailing rows.
                throw new RegisterParseException("Row {$row}: employee number is blank.", row: $row, column: $bindings['employee_no']);
            }

            $rows[] = [
                'row_number' => $row,
                'employee_no' => $employeeNo,
                'earnings' => $this->readMoneyGroup($sheet, $row, $columns['earnings'], $bindings['earnings']),
                'deductions' => $this->readMoneyGroup($sheet, $row, $columns['deductions'], $bindings['deductions']),
                'employer_shares' => $this->readMoneyGroup($sheet, $row, $columns['employer_shares'], $bindings['employer_shares']),
                'gross_pay' => $this->readMoneyCell($sheet, $row, $columns['gross_pay'], $bindings['gross_pay']),
                'total_deductions' => $this->readMoneyCell($sheet, $row, $columns['total_deductions'], $bindings['total_deductions']),
                'net_pay' => $this->readMoneyCell($sheet, $row, $columns['net_pay'], $bindings['net_pay']),
            ];
        }

        return $rows;
    }

    private function loadSheet(string $filePath): Worksheet
    {
        if (! is_file($filePath)) {
            throw new RegisterParseException("Register file not found: {$filePath}");
        }

        try {
            return IOFactory::load($filePath)->getActiveSheet();
        } catch (Throwable $e) {
            throw new RegisterParseException("The register file could not be read as a spreadsheet: {$e->getMessage()}");
        }
    }

    /**
     * @return array<string, int> header text => 1-based column index
     */
    private function indexHeaderRow(Worksheet $sheet): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $index = [];

        for ($col = 1; $col <= $highestColumn; $col++) {
            $header = trim((string) $sheet->getCell([$col, 1])->getFormattedValue());

            if ($header !== '') {
                $index[$header] = $col;
            }
        }

        if ($index === []) {
            throw new RegisterParseException('The register has no header row.');
        }

        return $index;
    }

    /**
     * Resolve every header string in the column map to a column index,
     * refusing the file (E1) if any required header is absent — a
     * structurally different layout, which AD-17 says is a new mapping
     * version, not something this pass can silently tolerate.
     *
     * @param  array<string, mixed>  $bindings
     * @param  array<string, int>  $headerIndex
     * @return array<string, mixed>
     */
    private function resolveColumns(array $bindings, array $headerIndex): array
    {
        $resolve = function (string $header) use ($headerIndex): int {
            if (! array_key_exists($header, $headerIndex)) {
                throw new RegisterParseException("Required column '{$header}' was not found in the register's header row.");
            }

            return $headerIndex[$header];
        };

        $resolveGroup = function (array $group) use ($resolve): array {
            return array_map($resolve, $group);
        };

        return [
            'employee_no' => $resolve($bindings['employee_no']),
            'earnings' => $resolveGroup($bindings['earnings']),
            'deductions' => $resolveGroup($bindings['deductions']),
            'employer_shares' => $resolveGroup($bindings['employer_shares']),
            'gross_pay' => $resolve($bindings['gross_pay']),
            'total_deductions' => $resolve($bindings['total_deductions']),
            'net_pay' => $resolve($bindings['net_pay']),
        ];
    }

    /**
     * @param  array<string, int>  $columnsByCode
     * @param  array<string, string>  $headersByCode
     * @return array<string, string>
     */
    private function readMoneyGroup(Worksheet $sheet, int $row, array $columnsByCode, array $headersByCode): array
    {
        $values = [];

        foreach ($columnsByCode as $code => $col) {
            $values[$code] = $this->readMoneyCell($sheet, $row, $col, $headersByCode[$code]);
        }

        return $values;
    }

    /**
     * Reads a monetary cell as a decimal string via getFormattedValue() —
     * never getValue(), which PhpSpreadsheet returns as a PHP float for a
     * numeric cell (BR-40, AD-18, proven in NoFloatParseProofTest). An
     * empty cell reads as '0.00'; anything that isn't a valid decimal is a
     * structural refusal (E1).
     */
    private function readMoneyCell(Worksheet $sheet, int $row, int $col, string $headerLabel): string
    {
        $raw = trim((string) $sheet->getCell([$col, $row])->getFormattedValue());

        if ($raw === '') {
            return '0.00';
        }

        // Accept an optional leading minus, digits, and up to two decimal
        // places — the shape BCMath's scale-2 functions expect. Thousands
        // separators are not accepted: the fixture registers (and the
        // canonical template) are written without them, and silently
        // stripping a comma is exactly the kind of parse-path float-style
        // guessing BR-40 exists to forbid.
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $raw)) {
            throw new RegisterParseException(
                "Row {$row}, column '{$headerLabel}': '{$raw}' is not a valid decimal amount.",
                row: $row,
                column: $headerLabel,
            );
        }

        return bcadd($raw, '0', 2);
    }
}
