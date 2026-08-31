<?php

namespace Tests\Unit;

use App\Models\ImportColumnMap;
use App\Services\RegisterImportService;
use App\Services\RegisterParseException;
use Database\Seeders\ImportColumnMapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Week 2 scope: the parse path only (system-architecture.md §6.4, BR-40,
 * AD-18). No reconciliation, no database write — that is
 * ReconciliationService, W3. This test only proves every returned
 * monetary value is a decimal string, never a float, and that a
 * structurally malformed register is refused (E1) rather than silently
 * misread.
 */
class RegisterImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function canonicalMap(): ImportColumnMap
    {
        $this->seed(ImportColumnMapSeeder::class);

        return ImportColumnMap::active('CANONICAL');
    }

    public function test_every_monetary_value_is_returned_as_a_decimal_string_never_a_float(): void
    {
        $map = $this->canonicalMap();
        $rows = (new RegisterImportService)->parseRows(
            base_path('tests/Fixtures/register_clean.xlsx'),
            $map,
        );

        self::assertCount(3, $rows);

        foreach ($rows as $row) {
            foreach (['gross_pay', 'total_deductions', 'net_pay'] as $field) {
                self::assertIsString($row[$field], "{$field} must be a string, never a float");
            }

            foreach (['earnings', 'deductions', 'employer_shares'] as $group) {
                foreach ($row[$group] as $code => $value) {
                    self::assertIsString($value, "{$group}.{$code} must be a string, never a float");
                }
            }
        }
    }

    public function test_the_parsed_figures_match_the_decimal_strings_independently_verified_in_the_no_float_proof(): void
    {
        $map = $this->canonicalMap();
        $rows = (new RegisterImportService)->parseRows(
            base_path('tests/Fixtures/register_clean.xlsx'),
            $map,
        );

        self::assertSame('E-0001', $rows[0]['employee_no']);
        self::assertSame('20000.00', $rows[0]['earnings']['BASIC']);
        self::assertSame('23800.75', $rows[0]['gross_pay']);
        self::assertSame('2700.35', $rows[0]['total_deductions']);
        self::assertSame('21100.40', $rows[0]['net_pay']);

        self::assertSame('E-0003', $rows[2]['employee_no']);
        self::assertSame('49400.20', $rows[2]['net_pay']);
    }

    public function test_a_register_missing_a_required_mapped_column_is_refused_structurally(): void
    {
        $map = $this->canonicalMap();

        $this->expectException(RegisterParseException::class);

        (new RegisterImportService)->parseRows(
            base_path('tests/Fixtures/register_malformed_missing_column.xlsx'),
            $map,
        );
    }

    public function test_a_missing_file_is_refused_structurally(): void
    {
        $map = $this->canonicalMap();

        $this->expectException(RegisterParseException::class);

        (new RegisterImportService)->parseRows(
            base_path('tests/Fixtures/does_not_exist.xlsx'),
            $map,
        );
    }
}
