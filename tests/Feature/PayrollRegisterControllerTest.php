<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollRunService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

// UC-21 · Review payroll register — FR-4.2 (AC-4.2.1 – AC-4.2.4).
class PayrollRegisterControllerTest extends TestCase
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

    private function officer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    private function approver(): User
    {
        return User::factory()->forRole('APPROVER')->create();
    }

    private function viewer(): User
    {
        return User::factory()->forRole('VIEWER')->create();
    }

    private function period(int $periodNo = 1): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => $periodNo,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-'.str_pad((string) (($periodNo - 1) * 15 + 1), 2, '0', STR_PAD_LEFT),
            'cutoff_end' => '2026-01-'.str_pad((string) ($periodNo * 15), 2, '0', STR_PAD_LEFT),
            'pay_date' => '2026-01-20',
            'is_closed' => false,
        ]);
    }

    private function setupRun(User $actor, ?PayrollPeriod $period = null): PayrollRun
    {
        $period = $period ?? $this->period();

        foreach (['E-0001', 'E-0002', 'E-0003'] as $empNo) {
            $emp = Employee::factory()->create(['employee_no' => $empNo, 'is_active' => true]);
            CompensationProfile::create([
                'employee_id' => $emp->employee_id,
                'pay_basis' => 'MONTHLY',
                'basic_rate' => '25000.00',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);
        }

        return app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $actor->user_id)['run'];
    }

    private function importCleanRegister(PayrollRun $run, User $officer): void
    {
        $map = ImportColumnMap::active('CANONICAL');
        $file = UploadedFile::fake()->createWithContent('register_clean.xlsx', file_get_contents(base_path('tests/Fixtures/register_clean.xlsx')));

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
                'import_column_map_id' => $map->import_column_map_id,
                'file' => $file,
            ]);

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/import/commit");
    }

    public function test_register_renders_for_run_with_accepted_import(): void
    {
        $officer = $this->officer();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);

        $response = $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/register");
        $response->assertOk();
        $response->assertSee('Payroll Register');
        $response->assertSee('Provisional Register'); // AC-4.2.1 / A2
        $response->assertSee('E-0001');
        $response->assertSee('E-0002');
        $response->assertSee('E-0003');
    }

    public function test_register_refused_when_no_accepted_import(): void
    {
        $officer = $this->officer();
        $run = $this->setupRun($officer);

        $response = $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/register");
        $response->assertRedirect("/payroll-runs/{$run->payroll_run_id}");
        $response->assertSessionHasErrors('register');
    }

    public function test_register_accessible_by_approver_and_viewer(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $viewer = $this->viewer();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);

        $this->actingAs($approver)->get("/payroll-runs/{$run->payroll_run_id}/register")->assertOk();
        $this->actingAs($viewer)->get("/payroll-runs/{$run->payroll_run_id}/register")->assertOk();
    }

    public function test_register_excel_export(): void
    {
        $officer = $this->officer();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);

        $response = $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/register/export");
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
