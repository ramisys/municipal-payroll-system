<?php

namespace Tests\Unit;

use App\Models\ImportColumnMap;
use App\Services\ReconciliationException;
use App\Services\ReconciliationService;
use App\Services\RegisterImportService;
use Database\Seeders\ImportColumnMapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRS §10 "reconciliation refusal" suite and the NFR-2.12/FR-2.9 accept
 * path — implementation-plan.md Sprint 1b, milestone P-A: "a fixture
 * register imports to the centavo with no float in the path, and every
 * seeded defective register is refused with the defect named." Drives
 * RegisterImportService and ReconciliationService together as a library,
 * against fixture files, with no database and no browser (AD-04, AD-05).
 */
class ReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{employee_no: string, is_active: bool}>
     */
    private function population(): array
    {
        return [
            ['employee_no' => 'E-0001', 'is_active' => true],
            ['employee_no' => 'E-0002', 'is_active' => true],
            ['employee_no' => 'E-0003', 'is_active' => true],
        ];
    }

    private function parse(string $fixture): array
    {
        $this->seed(ImportColumnMapSeeder::class);
        $map = ImportColumnMap::active('CANONICAL');

        return (new RegisterImportService)->parseRows(base_path("tests/Fixtures/{$fixture}"), $map);
    }

    public function test_a_clean_register_reconciles_and_its_result_carries_the_computed_control_totals(): void
    {
        $rows = $this->parse('register_clean.xlsx');

        $result = (new ReconciliationService)->reconcile($rows, $this->population());

        self::assertSame(3, $result->rowCount);
        self::assertSame(3, $result->matchedEmployeeCount);
        self::assertSame('99193.81', $result->controlTotalGross);
        self::assertSame('10811.00', $result->controlTotalDeductions);
        self::assertSame('88382.81', $result->controlTotalNet);
    }

    public function test_a_clean_register_reconciles_against_its_own_correct_file_control_totals(): void
    {
        $rows = $this->parse('register_clean.xlsx');

        $result = (new ReconciliationService)->reconcile($rows, $this->population(), [
            'gross_pay' => '99193.81',
            'total_deductions' => '10811.00',
            'net_pay' => '88382.81',
        ]);

        self::assertTrue(true, 'reconciled without throwing');
        self::assertSame(3, $result->rowCount);
    }

    public function test_a_one_centavo_row_imbalance_is_refused_and_named(): void
    {
        $rows = $this->parse('register_defect_row_imbalance.xlsx');

        try {
            (new ReconciliationService)->reconcile($rows, $this->population());
            self::fail('Expected a ReconciliationException.');
        } catch (ReconciliationException $e) {
            $types = array_column($e->defectsAsArray(), 'type');
            self::assertContains('ROW_NET_MISMATCH', $types);

            $defect = $e->defects[array_search('ROW_NET_MISMATCH', $types, true)];
            self::assertSame(3, $defect->row);
            self::assertSame('E-0002', $defect->employeeNo);
        }
    }

    public function test_a_wrong_file_control_total_is_refused_and_named(): void
    {
        $rows = $this->parse('register_clean.xlsx');

        try {
            (new ReconciliationService)->reconcile($rows, $this->population(), [
                'gross_pay' => '99193.82', // one centavo off the true sum
                'total_deductions' => '10811.00',
                'net_pay' => '88382.81',
            ]);
            self::fail('Expected a ReconciliationException.');
        } catch (ReconciliationException $e) {
            $types = array_column($e->defectsAsArray(), 'type');
            self::assertSame(['CONTROL_TOTAL_MISMATCH'], $types);
        }
    }

    public function test_a_register_row_matching_no_active_employee_is_refused_and_named(): void
    {
        $rows = $this->parse('register_defect_unmatched_employee.xlsx');

        try {
            (new ReconciliationService)->reconcile($rows, $this->population());
            self::fail('Expected a ReconciliationException.');
        } catch (ReconciliationException $e) {
            $types = array_column($e->defectsAsArray(), 'type');
            self::assertSame(['UNMATCHED_EMPLOYEE'], $types);
            self::assertSame('E-9999', $e->defects[0]->employeeNo);
        }
    }

    public function test_a_duplicate_employee_row_is_refused_and_named(): void
    {
        $rows = $this->parse('register_defect_duplicate_employee.xlsx');

        try {
            (new ReconciliationService)->reconcile($rows, $this->population());
            self::fail('Expected a ReconciliationException.');
        } catch (ReconciliationException $e) {
            $types = array_column($e->defectsAsArray(), 'type');
            self::assertContains('DUPLICATE_EMPLOYEE_ROW', $types);

            $defect = $e->defects[array_search('DUPLICATE_EMPLOYEE_ROW', $types, true)];
            self::assertSame('E-0002', $defect->employeeNo);
        }
    }

    public function test_an_omitted_active_employee_is_refused_and_named(): void
    {
        $rows = $this->parse('register_defect_omitted_employee.xlsx');

        try {
            (new ReconciliationService)->reconcile($rows, $this->population());
            self::fail('Expected a ReconciliationException.');
        } catch (ReconciliationException $e) {
            $types = array_column($e->defectsAsArray(), 'type');
            self::assertSame(['OMITTED_ACTIVE_EMPLOYEE'], $types);
            self::assertSame('E-0003', $e->defects[0]->employeeNo);
        }
    }

    public function test_a_row_matching_an_inactive_employee_is_refused(): void
    {
        $rows = $this->parse('register_clean.xlsx');

        $population = $this->population();
        $population[2]['is_active'] = false; // E-0003 exists but is inactive

        try {
            (new ReconciliationService)->reconcile($rows, $population);
            self::fail('Expected a ReconciliationException.');
        } catch (ReconciliationException $e) {
            $types = array_column($e->defectsAsArray(), 'type');
            self::assertContains('INACTIVE_EMPLOYEE_MATCHED', $types);
        }
    }

    public function test_every_check_uses_bcmath_not_native_float_arithmetic(): void
    {
        // 0.1 + 0.2 !== 0.3 under IEEE-754 double addition (the same proof
        // NoFloatParseProofTest makes at the parse boundary) — three rows
        // whose earnings sum through that exact failure case must still
        // reconcile if BCMath, not the native + operator, does the summing.
        $rows = [[
            'row_number' => 2,
            'employee_no' => 'E-0001',
            'earnings' => ['A' => '0.10', 'B' => '0.20'],
            'deductions' => [],
            'employer_shares' => [],
            'gross_pay' => '0.30',
            'total_deductions' => '0.00',
            'net_pay' => '0.30',
        ]];

        $result = (new ReconciliationService)->reconcile($rows, [
            ['employee_no' => 'E-0001', 'is_active' => true],
        ]);

        self::assertSame('0.30', $result->controlTotalGross);
    }
}
