<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\ExceptionInstance;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\ExceptionEvaluator;
use App\Services\PayrollImportService;
use App\Services\PayrollRunService;
use App\Services\ReconciliationService;
use App\Services\RegisterImportService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-20 · Exception report — FR-4.1 (W9 P1).
 */
class ExceptionReportControllerTest extends TestCase
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
    }

    private function importedRun(User $actor): PayrollRun
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

        $period = PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => 1,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'is_closed' => false,
        ]);

        $run = (new PayrollRunService)->createRun($period, 'REGULAR', 'ALL', $actor->user_id)['run'];

        (new PayrollImportService(
            new RegisterImportService,
            new ReconciliationService,
            new PayrollRunService,
            new ExceptionEvaluator,
        ))->commit(
            $run,
            base_path('tests/Fixtures/register_clean.xlsx'),
            'register_clean.xlsx',
            ImportColumnMap::active('CANONICAL'),
            $actor->user_id,
        );

        return $run->fresh();
    }

    public function test_payroll_officer_can_view_the_exception_report_after_import(): void
    {
        $officer = User::factory()->forRole('PAYROLL_OFFICER')->create();
        $run = $this->importedRun($officer);

        $response = $this->actingAs($officer)->get(route('exception-report.show', $run));

        $response->assertOk();
        $response->assertSee('Exception report');
        $response->assertSee('EX-01');
        $response->assertSee('corrected import');
    }

    public function test_viewer_can_read_but_cannot_acknowledge(): void
    {
        $officer = User::factory()->forRole('PAYROLL_OFFICER')->create();
        $run = $this->importedRun($officer);
        $viewer = User::factory()->forRole('VIEWER')->create();

        $this->actingAs($viewer)->get(route('exception-report.show', $run))
            ->assertOk()
            ->assertSee('acknowledgment requires Payroll Officer or Approver');

        $warning = ExceptionInstance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('severity', 'WARNING')
            ->firstOrFail();

        $this->actingAs($viewer)->post(route('exception-report.acknowledge', [$run, $warning]), [
            'acknowledgment_reason' => 'should be refused',
        ])->assertForbidden();
    }

    public function test_payroll_officer_can_acknowledge_a_warning(): void
    {
        $officer = User::factory()->forRole('PAYROLL_OFFICER')->create();
        $run = $this->importedRun($officer);

        $warning = ExceptionInstance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('severity', 'WARNING')
            ->firstOrFail();

        $this->actingAs($officer)->post(route('exception-report.acknowledge', [$run, $warning]), [
            'acknowledgment_reason' => 'Will address at remittance.',
        ])->assertRedirect();

        $warning->refresh();
        self::assertTrue($warning->is_resolved);
        self::assertSame('Will address at remittance.', $warning->acknowledgment_reason);
    }
}
