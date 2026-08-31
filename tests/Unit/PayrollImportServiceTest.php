<?php

namespace Tests\Unit;

use App\Models\CompensationProfile;
use App\Models\DeductionLine;
use App\Models\EarningLine;
use App\Models\Employee;
use App\Models\ImportColumnMap;
use App\Models\PayrollImport;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollImportException;
use App\Services\PayrollImportService;
use App\Services\PayrollRunService;
use App\Services\ReconciliationException;
use App\Services\ReconciliationService;
use App\Services\RegisterImportService;
use App\Services\RegisterParseException;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ✧ UC-18 · Import computed payroll register — the write path. Week 7
 * Track A/B (pre-oral-demonstration-plan.md §6 Table 6): RegisterImportService
 * and ReconciliationService (pure library code since W2/W3, proven against
 * fixtures in RegisterImportServiceTest/ReconciliationServiceTest) wired to
 * a real PayrollRun with real, atomic writes and BR-39 version supersession
 * — milestone P-C, "worksheet out, register in, reconciled, superseded."
 */
class PayrollImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
    }

    private function service(): PayrollImportService
    {
        $payrollRunService = new PayrollRunService;

        return new PayrollImportService(new RegisterImportService, new ReconciliationService, $payrollRunService);
    }

    // created_by/imported_by both FK users.user_id — a real seeded user,
    // not a bare literal (PAYROLL_IMPORT.imported_by is NOT NULL).
    private function actorId(): int
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create()->user_id;
    }

    private function period(): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => 1,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'is_closed' => false,
        ]);
    }

    /**
     * The fixture registers (ReconciliationServiceTest's own population)
     * are written against E-0001..E-0003. Compensation profile figures
     * are arbitrary — the register drives the payroll line's money, not
     * the profile — but a profile must exist in force on the cutoff
     * (BR-08) for a line to be written at all.
     */
    private function threeMatchingEmployees(): void
    {
        foreach (['E-0001', 'E-0002', 'E-0003'] as $employeeNo) {
            $employee = Employee::factory()->create(['employee_no' => $employeeNo, 'is_active' => true]);
            CompensationProfile::create([
                'employee_id' => $employee->employee_id,
                'pay_basis' => 'MONTHLY',
                'basic_rate' => '20000.00',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);
        }
    }

    private function runInDraft(PayrollPeriod $period, int $actorId): PayrollRun
    {
        return (new PayrollRunService)->createRun($period, 'REGULAR', 'ALL', $actorId)['run'];
    }

    private function canonicalMap(): ImportColumnMap
    {
        return ImportColumnMap::active('CANONICAL');
    }

    public function test_a_clean_register_reconciles_and_writes_lines_earnings_and_deductions_atomically(): void
    {
        $this->threeMatchingEmployees();
        $actorId = $this->actorId();
        $run = $this->runInDraft($this->period(), $actorId);

        $committed = $this->service()->commit($run, base_path('tests/Fixtures/register_clean.xlsx'), $this->canonicalMap(), $actorId);

        $import = $committed['import'];
        self::assertSame(1, $import->version_no);
        self::assertTrue($import->is_current);
        self::assertSame(3, $import->row_count);
        self::assertSame(['E-0001', 'E-0002', 'E-0003'], $committed['changed']);

        self::assertSame(3, PayrollLine::query()->where('payroll_run_id', $run->payroll_run_id)->count());

        $line = PayrollLine::query()->whereHas('employee', fn ($q) => $q->where('employee_no', 'E-0001'))->firstOrFail();
        self::assertSame($import->payroll_import_id, $line->payroll_import_id);
        self::assertGreaterThan(0, EarningLine::query()->where('payroll_line_id', $line->payroll_line_id)->count());
        self::assertGreaterThan(0, DeductionLine::query()->where('payroll_line_id', $line->payroll_line_id)->count());
        self::assertSame(0, bccomp($line->net_pay, bcsub($line->gross_pay, $line->total_deductions, 2), 2), 'BR-37: net_pay = gross_pay - total_deductions.');
    }

    public function test_a_defective_register_is_refused_and_nothing_is_written(): void
    {
        $this->threeMatchingEmployees();
        $run = $this->runInDraft($this->period(), $this->actorId());

        try {
            $this->service()->commit($run, base_path('tests/Fixtures/register_defect_row_imbalance.xlsx'), $this->canonicalMap(), $this->actorId());
            self::fail('Expected a ReconciliationException.');
        } catch (ReconciliationException $e) {
            self::assertNotEmpty($e->defects);
        }

        self::assertSame(0, PayrollImport::query()->count());
        self::assertSame(0, PayrollLine::query()->count());
    }

    public function test_a_structurally_broken_register_is_refused_and_nothing_is_written(): void
    {
        $this->threeMatchingEmployees();
        $run = $this->runInDraft($this->period(), $this->actorId());

        $this->expectException(RegisterParseException::class);

        try {
            $this->service()->commit($run, base_path('tests/Fixtures/register_malformed_missing_column.xlsx'), $this->canonicalMap(), $this->actorId());
        } finally {
            self::assertSame(0, PayrollImport::query()->count());
        }
    }

    // ✧ A1 — a second accepted import supersedes the first: retained, not
    // deleted (BR-39), the run's payroll lines updated in place rather
    // than duplicated (payroll_run_id, employee_id unique).
    public function test_a_second_accepted_import_supersedes_the_first_and_retains_it(): void
    {
        $this->threeMatchingEmployees();
        $actorId = $this->actorId();
        $run = $this->runInDraft($this->period(), $actorId);

        $first = $this->service()->commit($run, base_path('tests/Fixtures/register_clean.xlsx'), $this->canonicalMap(), $actorId);
        $second = $this->service()->commit($run, base_path('tests/Fixtures/register_clean.xlsx'), $this->canonicalMap(), $actorId);

        self::assertSame(2, $second['import']->version_no);
        self::assertTrue($second['import']->is_current);

        $first['import']->refresh();
        self::assertFalse($first['import']->is_current);
        self::assertNotNull($first['import']->source_file, 'BR-39: the superseded version is retained, not deleted.');

        self::assertSame(2, PayrollImport::query()->where('payroll_run_id', $run->payroll_run_id)->count());
        self::assertSame(3, PayrollLine::query()->where('payroll_run_id', $run->payroll_run_id)->count(), 'supersession updates the existing line, never duplicates it.');

        $line = PayrollLine::query()->whereHas('employee', fn ($q) => $q->where('employee_no', 'E-0001'))->firstOrFail();
        self::assertSame($second['import']->payroll_import_id, $line->payroll_import_id);
    }

    // UC-18 E9.
    public function test_an_import_into_a_non_editable_run_is_refused(): void
    {
        $this->threeMatchingEmployees();
        $run = $this->runInDraft($this->period(), $this->actorId());
        $run->run_status = 'APPROVED';
        $run->save();

        $this->expectException(PayrollImportException::class);
        $this->service()->commit($run, base_path('tests/Fixtures/register_clean.xlsx'), $this->canonicalMap(), $this->actorId());
    }

    // A matched active employee with no compensation profile in force
    // cannot receive a PAYROLL_LINE (compensation_profile_id is NOT NULL) —
    // see PayrollImportException's docblock.
    public function test_a_matched_employee_with_no_compensation_profile_refuses_the_whole_import(): void
    {
        $this->threeMatchingEmployees();
        CompensationProfile::query()->whereHas('employee', fn ($q) => $q->where('employee_no', 'E-0002'))->delete();
        $run = $this->runInDraft($this->period(), $this->actorId());

        try {
            $this->service()->commit($run, base_path('tests/Fixtures/register_clean.xlsx'), $this->canonicalMap(), $this->actorId());
            self::fail('Expected a PayrollImportException.');
        } catch (PayrollImportException $e) {
            self::assertStringContainsString('E-0002', $e->getMessage());
        }

        self::assertSame(0, PayrollImport::query()->count());
    }

    public function test_preview_reports_defects_without_writing_anything(): void
    {
        $this->threeMatchingEmployees();
        $run = $this->runInDraft($this->period(), $this->actorId());

        $preview = $this->service()->preview($run, base_path('tests/Fixtures/register_defect_unmatched_employee.xlsx'), $this->canonicalMap());

        self::assertNull($preview['result']);
        self::assertNotEmpty($preview['defects']);
        self::assertSame(0, PayrollLine::query()->count());
        self::assertSame(0, PayrollImport::query()->count());
    }
}
