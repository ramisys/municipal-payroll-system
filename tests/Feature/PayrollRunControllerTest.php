<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-17 · Create payroll run, A2 cancel — the HTTP surface. Population and
 * refusal logic are PayrollRunServiceTest; this covers the screen flow and
 * its permission gate (AC-6.2.4 — the Administrator cannot create or
 * cancel a run; only 'payroll_run.create_import' can).
 */
class PayrollRunControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
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

    public function test_a_payroll_officer_creates_a_draft_run_and_lands_on_its_screen(): void
    {
        Employee::factory()->create(['employee_no' => 'E-0001', 'is_active' => true]);
        $period = $this->period();
        $officer = $this->officer();

        $this->actingAs($officer)->get('/payroll-runs')->assertOk();
        $this->actingAs($officer)->get('/payroll-runs/create')->assertOk();

        $response = $this->actingAs($officer)->post('/payroll-runs', [
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'scope' => 'ALL',
        ]);

        $run = PayrollRun::query()->firstOrFail();
        $response->assertRedirect(route('payroll-runs.show', $run));
        self::assertSame('DRAFT', $run->run_status);
        self::assertNotNull(AuditLog::query()->where('entity_name', 'PAYROLL_RUN')->where('action', 'CREATE')->first());

        $this->actingAs($officer)->get(route('payroll-runs.show', $run))->assertOk()->assertSee('DRAFT');
    }

    // UC-17 E1.
    public function test_a_second_run_for_the_same_period_population_and_type_is_refused_and_opens_the_existing_run(): void
    {
        Employee::factory()->create(['employee_no' => 'E-0001', 'is_active' => true]);
        $period = $this->period();
        $officer = $this->officer();

        $this->actingAs($officer)->post('/payroll-runs', [
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'scope' => 'ALL',
        ]);
        $existing = PayrollRun::query()->firstOrFail();

        $response = $this->actingAs($officer)->post('/payroll-runs', [
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'scope' => 'ALL',
        ]);

        $response->assertRedirect(route('payroll-runs.show', $existing));
        self::assertSame(1, PayrollRun::query()->count());
    }

    public function test_a_draft_run_can_be_cancelled_with_a_reason(): void
    {
        Employee::factory()->create(['employee_no' => 'E-0001', 'is_active' => true]);
        $period = $this->period();
        $officer = $this->officer();

        $this->actingAs($officer)->post('/payroll-runs', [
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'scope' => 'ALL',
        ]);
        $run = PayrollRun::query()->firstOrFail();

        $response = $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/cancel", ['reason' => 'Created for the wrong period']);

        $response->assertRedirect(route('payroll-runs.index'));
        self::assertSame('CANCELLED', $run->refresh()->run_status);
    }

    // W8 demo polish — empty states (pre-oral-demonstration-plan.md §6
    // Table 6, W8 Track C).
    public function test_the_index_and_create_screens_render_with_nothing_defined_yet(): void
    {
        $officer = $this->officer();

        $this->actingAs($officer)->get('/payroll-runs')->assertOk()->assertSee('No payroll runs yet');
        $this->actingAs($officer)->get('/payroll-runs/create')->assertOk()->assertSee('No pay periods are defined yet');
    }

    // AC-6.2.4 — an Administrator cannot create a payroll run.
    public function test_an_administrator_cannot_create_a_payroll_run(): void
    {
        $admin = User::factory()->forRole('ADMINISTRATOR')->create();

        $this->actingAs($admin)->get('/payroll-runs/create')->assertForbidden();
    }

    // ✧ UC-33 step 1 — "User opens a payroll run" is shared by every
    // review-history actor (Payroll Officer, Approver, Administrator,
    // Viewer), so a Viewer can read the run's own screen even though it
    // cannot create, import into, or cancel one (AC-6.2.5's "outputs of
    // payroll, not its inputs" applies here: a run and its figures are an
    // output, unlike the create/cancel/import actions).
    public function test_a_viewer_can_open_the_run_screen_but_sees_no_write_actions(): void
    {
        Employee::factory()->create(['employee_no' => 'E-0001', 'is_active' => true]);
        $period = $this->period();
        $officer = $this->officer();
        $this->actingAs($officer)->post('/payroll-runs', [
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'scope' => 'ALL',
        ]);
        $run = PayrollRun::query()->firstOrFail();

        $viewer = User::factory()->forRole('VIEWER')->create();

        $this->actingAs($viewer)->get('/payroll-runs')->assertOk();
        $response = $this->actingAs($viewer)->get(route('payroll-runs.show', $run));
        $response->assertOk();
        $response->assertDontSee('Cancel this run');
        $response->assertDontSee('Import computed register');

        // The write routes themselves stay refused even though the
        // screen is now readable.
        $this->actingAs($viewer)->get(route('payroll-runs.cancel-form', $run))->assertForbidden();
    }
}
