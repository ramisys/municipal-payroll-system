<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\ImportColumnMap;
use App\Models\PayrollImport;
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

/**
 * ✧ UC-18 · Import computed payroll register (preview/commit/cancel) and
 * ✧ UC-33 · Review import history — the HTTP surface, exercised together
 * with UC-32's worksheet export and UC-17's run creation because that is
 * milestone P-C in full (pre-oral-demonstration-plan.md §6 Table 6):
 * "Worksheet out, register in, reconciled, superseded — the arc closes."
 * Parse/reconcile/write correctness is PayrollImportServiceTest; this
 * proves the screens wire to it correctly and stay behind the right
 * permission gate.
 */
class PayrollImportControllerTest extends TestCase
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

    private function runWithMatchingPopulation(PayrollPeriod $period, int $actorId): PayrollRun
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

        return (new PayrollRunService)->createRun($period, 'REGULAR', 'ALL', $actorId)['run'];
    }

    private function mapId(): int
    {
        return ImportColumnMap::active('CANONICAL')->import_column_map_id;
    }

    private function registerUpload(string $fixture = 'register_clean.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($fixture, file_get_contents(base_path("tests/Fixtures/{$fixture}")));
    }

    public function test_the_arc_closes_worksheet_out_register_in_reconciled_superseded(): void
    {
        $officer = $this->officer();
        $run = $this->runWithMatchingPopulation($this->period(), $officer->user_id);

        // UC-32 — worksheet out.
        $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/worksheet")->assertOk();

        // UC-18 — register in, reconciled, accepted (version 1).
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
            'import_column_map_id' => $this->mapId(),
            'file' => $this->registerUpload(),
        ])->assertOk();

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/commit")
            ->assertRedirect(route('payroll-runs.show', $run));

        $firstImport = PayrollImport::query()->where('payroll_run_id', $run->payroll_run_id)->firstOrFail();
        self::assertSame(1, $firstImport->version_no);
        self::assertTrue($firstImport->is_current);
        self::assertNotNull(AuditLog::query()->where('entity_name', 'PAYROLL_IMPORT')->where('entity_id', $firstImport->payroll_import_id)->first());

        // UC-18 A1 — a second accepted import supersedes the first.
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
            'import_column_map_id' => $this->mapId(),
            'file' => $this->registerUpload(),
        ])->assertOk();
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/commit")
            ->assertRedirect(route('payroll-runs.show', $run));

        self::assertSame(2, PayrollImport::query()->where('payroll_run_id', $run->payroll_run_id)->count());
        $firstImport->refresh();
        self::assertFalse($firstImport->is_current, 'BR-39: the first version is superseded, not deleted.');

        // UC-33 — history shows both versions, and the superseded file
        // downloads intact.
        $historyResponse = $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/imports");
        $historyResponse->assertOk();
        $historyResponse->assertSee('Superseded');
        $historyResponse->assertSee('Current');

        $download = $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/imports/{$firstImport->payroll_import_id}/download");
        $download->assertOk();
        self::assertSame(
            hash('sha256', file_get_contents(base_path('tests/Fixtures/register_clean.xlsx'))),
            hash('sha256', $download->getContent()),
        );

        // The single-version detail screen and the run's own screen both
        // render for a run carrying a superseded and a current import.
        $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}/imports/{$firstImport->payroll_import_id}")
            ->assertOk()->assertSee($firstImport->source_sha256);
        $this->actingAs($officer)->get("/payroll-runs/{$run->payroll_run_id}")
            ->assertOk()->assertSee('E-0001');
    }

    // UC-18 E2-E5, via UC-I7 — a defective register is refused and nothing
    // is written; the file's own defects are reported rather than a bare
    // exception page.
    public function test_a_defective_register_is_refused_at_preview(): void
    {
        $officer = $this->officer();
        $run = $this->runWithMatchingPopulation($this->period(), $officer->user_id);

        $response = $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
            'import_column_map_id' => $this->mapId(),
            'file' => $this->registerUpload('register_defect_unmatched_employee.xlsx'),
        ]);

        $response->assertOk();
        $response->assertSee('UNMATCHED_EMPLOYEE');
        self::assertSame(0, PayrollImport::query()->count());
    }

    // ✧ UC-33 read access — 'payroll_records.search' is granted to every
    // role including the Viewer (FR-6.2 matrix).
    public function test_a_viewer_can_read_import_history_but_not_import(): void
    {
        $viewer = User::factory()->forRole('VIEWER')->create();
        $run = $this->runWithMatchingPopulation($this->period(), $viewer->user_id);

        $this->actingAs($viewer)->get("/payroll-runs/{$run->payroll_run_id}/imports")->assertOk();
        $this->actingAs($viewer)->get("/payroll-runs/{$run->payroll_run_id}/import")->assertForbidden();
    }
}
