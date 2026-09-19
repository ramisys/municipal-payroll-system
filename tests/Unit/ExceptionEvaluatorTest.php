<?php

namespace Tests\Unit;

use App\Models\AttendanceRecord;
use App\Models\AttendanceType;
use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\ExceptionInstance;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\ExceptionEvaluationException;
use App\Services\ExceptionEvaluator;
use App\Services\PayrollImportService;
use App\Services\PayrollRunService;
use App\Services\ReconciliationService;
use App\Services\RegisterImportService;
use Database\Seeders\AttendanceTypeSeeder;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-I4 / FR-4.1 — ExceptionEvaluator against a real import (W9 P1 start).
 */
class ExceptionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(SystemConfigSeeder::class);
        $this->seed(AttendanceTypeSeeder::class);
    }

    private function importService(): PayrollImportService
    {
        return new PayrollImportService(
            new RegisterImportService,
            new ReconciliationService,
            new PayrollRunService,
            new ExceptionEvaluator,
        );
    }

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

    private function threeMatchingEmployees(): void
    {
        foreach (['E-0001', 'E-0002', 'E-0003'] as $employeeNo) {
            $employee = Employee::factory()->create([
                'employee_no' => $employeeNo,
                'is_active' => true,
                'sss_no' => '34-1234567-1',
                'philhealth_no' => '12-345678901-2',
                'pagibig_mid' => '1234-5678-9012',
                'tin' => '123-456-789-000',
            ]);
            CompensationProfile::create([
                'employee_id' => $employee->employee_id,
                'pay_basis' => 'MONTHLY',
                'basic_rate' => '20000.00',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);
        }
    }

    private function seedAttendanceForCutoff(): void
    {
        $typeId = AttendanceType::query()->value('attendance_type_id');
        foreach (Employee::query()->get() as $employee) {
            AttendanceRecord::create([
                'employee_id' => $employee->employee_id,
                'attendance_type_id' => $typeId,
                'work_date' => '2026-01-05',
                'hours_worked' => '8.00',
                'overtime_hours' => '0.00',
                'day_classification' => 'ORDINARY',
                'source' => 'IMPORT',
            ]);
        }
    }

    private function importedRun(): PayrollRun
    {
        $this->threeMatchingEmployees();
        $this->seedAttendanceForCutoff();
        $actorId = $this->actorId();
        $run = (new PayrollRunService)->createRun($this->period(), 'REGULAR', 'ALL', $actorId)['run'];

        $this->importService()->commit(
            $run,
            base_path('tests/Fixtures/register_clean.xlsx'),
            'register_clean.xlsx',
            ImportColumnMap::active('CANONICAL'),
            $actorId,
        );

        return $run->fresh();
    }

    public function test_a_clean_import_with_attendance_and_ids_raises_no_blocking_line_exceptions(): void
    {
        $run = $this->importedRun();

        $blocking = ExceptionInstance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('severity', 'BLOCKING')
            ->get();

        self::assertTrue(
            $blocking->whereIn('rule_code', ['EX-01', 'EX-02', 'EX-03', 'EX-04'])->isEmpty(),
            'Clean register with attendance and profiles must not raise EX-01–EX-04. Got: '.$blocking->pluck('rule_code')->implode(','),
        );
    }

    public function test_missing_attendance_raises_blocking_ex_01(): void
    {
        $this->threeMatchingEmployees();
        $actorId = $this->actorId();
        $run = (new PayrollRunService)->createRun($this->period(), 'REGULAR', 'ALL', $actorId)['run'];

        $this->importService()->commit(
            $run,
            base_path('tests/Fixtures/register_clean.xlsx'),
            'register_clean.xlsx',
            ImportColumnMap::active('CANONICAL'),
            $actorId,
        );

        $codes = ExceptionInstance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('rule_code', 'EX-01')
            ->pluck('severity')
            ->all();

        self::assertCount(3, $codes);
        self::assertSame(['BLOCKING', 'BLOCKING', 'BLOCKING'], $codes);
    }

    public function test_net_below_floor_raises_blocking_ex_03_marked_reimport_only(): void
    {
        SystemConfig::query()->where('config_key', 'NET_PAY_FLOOR')->update(['config_value' => '99999.00']);

        $run = $this->importedRun();

        $ex03 = ExceptionInstance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('rule_code', 'EX-03')
            ->first();

        self::assertNotNull($ex03);
        self::assertSame('BLOCKING', $ex03->severity);
        self::assertStringContainsString('corrected_import_only', $ex03->triggering_values);
        self::assertTrue($ex03->requiresCorrectedImport());
        self::assertTrue((new ExceptionEvaluator)->hasUnresolvedBlocking($run));
    }

    public function test_warning_can_be_acknowledged_and_blocking_cannot(): void
    {
        // Leave government IDs blank so EX-06 (warning) is raised; the clean
        // fixture carries employer-share columns so EX-05 does not fire.
        $this->threeMatchingEmployees();
        Employee::query()->update([
            'sss_no' => null,
            'philhealth_no' => null,
            'pagibig_mid' => null,
            'tin' => null,
        ]);
        $this->seedAttendanceForCutoff();
        $actorId = $this->actorId();
        $run = (new PayrollRunService)->createRun($this->period(), 'REGULAR', 'ALL', $actorId)['run'];
        $this->importService()->commit(
            $run,
            base_path('tests/Fixtures/register_clean.xlsx'),
            'register_clean.xlsx',
            ImportColumnMap::active('CANONICAL'),
            $actorId,
        );

        $evaluator = new ExceptionEvaluator;
        $user = User::factory()->forRole('PAYROLL_OFFICER')->create();

        $warning = ExceptionInstance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('rule_code', 'EX-06')
            ->first();

        self::assertNotNull($warning, 'Expected EX-06 for missing government IDs.');

        $evaluator->acknowledge($warning, $user, 'IDs will be completed before remittance.');
        $warning->refresh();
        self::assertTrue($warning->is_resolved);
        self::assertSame($user->user_id, $warning->acknowledged_by);

        $blocking = ExceptionInstance::query()->create([
            'payroll_run_id' => $run->payroll_run_id,
            'payroll_line_id' => $run->lines()->first()->payroll_line_id,
            'rule_code' => 'EX-03',
            'severity' => 'BLOCKING',
            'triggering_values' => 'test',
            'is_resolved' => false,
        ]);

        $this->expectException(ExceptionEvaluationException::class);
        $evaluator->acknowledge($blocking, $user, 'should fail');
    }

    public function test_superseding_import_replaces_exception_instances(): void
    {
        $this->threeMatchingEmployees();
        $actorId = $this->actorId();
        $run = (new PayrollRunService)->createRun($this->period(), 'REGULAR', 'ALL', $actorId)['run'];

        $this->importService()->commit(
            $run,
            base_path('tests/Fixtures/register_clean.xlsx'),
            'register_clean.xlsx',
            ImportColumnMap::active('CANONICAL'),
            $actorId,
        );

        $firstCount = ExceptionInstance::query()->where('payroll_run_id', $run->payroll_run_id)->count();
        self::assertGreaterThan(0, $firstCount);

        $this->seedAttendanceForCutoff();
        foreach (Employee::query()->get() as $employee) {
            $employee->update([
                'sss_no' => '34-1234567-1',
                'philhealth_no' => '12-345678901-2',
                'pagibig_mid' => '1234-5678-9012',
                'tin' => '123-456-789-000',
            ]);
        }

        $this->importService()->commit(
            $run,
            base_path('tests/Fixtures/register_clean.xlsx'),
            'register_clean.xlsx',
            ImportColumnMap::active('CANONICAL'),
            $actorId,
        );

        $secondCount = ExceptionInstance::query()->where('payroll_run_id', $run->payroll_run_id)->count();
        self::assertLessThan($firstCount, $secondCount);
        self::assertSame(
            0,
            ExceptionInstance::query()->where('payroll_run_id', $run->payroll_run_id)->where('rule_code', 'EX-01')->count(),
        );
    }
}
