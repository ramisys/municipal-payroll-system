<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\ImportColumnMap;
use App\Models\IntegrityAnchor;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\User;
use App\Services\LedgerAnchorService;
use App\Services\LedgerGateway;
use App\Services\PayrollRunService;
use Database\Seeders\AttendanceTypeSeeder;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatutoryScheduleSeeder;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// FR-6.3 / AC-4.5.5 / AC-6.3.1 / AC-6.3.5 / Milestone P-E.
class IntegrityOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SystemConfigSeeder::class);
        $this->seed(AttendanceTypeSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(StatutoryScheduleSeeder::class);

        LedgerGateway::fake(true);
    }

    protected function tearDown(): void
    {
        LedgerGateway::restore();
        parent::tearDown();
    }

    private function administrator(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    private function approver(): User
    {
        return User::factory()->forRole('APPROVER')->create();
    }

    private function payrollOfficer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    private function createPeriod(): PayrollPeriod
    {
        return PayrollPeriod::create([
            'payroll_year' => 2026,
            'period_no' => 1,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-20',
            'is_closed' => false,
        ]);
    }

    private function setupApprovedRun(User $officer, User $approver): PayrollRun
    {
        $period = $this->createPeriod();
        $attendanceTypeId = DB::table('attendance_types')->value('attendance_type_id');

        $dept = Department::create(['department_code' => 'ACC', 'department_name' => 'Accounting', 'is_active' => true]);
        $pos = Position::create(['position_code' => 'CLERK', 'position_title' => 'Clerk', 'is_active' => true]);
        $stat = EmploymentStatus::create(['status_code' => 'REG', 'status_name' => 'Regular', 'is_payroll_eligible' => true, 'is_active' => true]);

        foreach (['E-0001', 'E-0002', 'E-0003'] as $empNo) {
            $emp = Employee::factory()->create([
                'employee_no' => $empNo,
                'last_name' => "LastName_{$empNo}",
                'first_name' => "FirstName_{$empNo}",
                'sex' => 'M',
                'civil_status' => 'SINGLE',
                'is_active' => true,
                'sss_no' => '01-2345678-9',
                'philhealth_no' => '01-234567890-1',
                'pagibig_mid' => '1234-5678-9012',
                'tin' => '123-456-789-000',
            ]);

            EmploymentDetail::create([
                'employee_id' => $emp->employee_id,
                'department_id' => $dept->department_id,
                'position_id' => $pos->position_id,
                'employment_status_id' => $stat->employment_status_id,
                'date_hired' => '2020-01-01',
                'effective_from' => '2020-01-01',
            ]);

            CompensationProfile::create([
                'employee_id' => $emp->employee_id,
                'pay_basis' => 'MONTHLY',
                'basic_rate' => '25000.00',
                'effective_from' => '2020-01-01',
            ]);

            AttendanceRecord::create([
                'employee_id' => $emp->employee_id,
                'attendance_type_id' => $attendanceTypeId,
                'work_date' => $period->cutoff_start->toDateString(),
                'hours_worked' => '8.00',
                'overtime_hours' => '0.00',
                'day_classification' => 'ORDINARY',
                'source' => 'IMPORT',
            ]);
        }

        $runService = app(PayrollRunService::class);
        $run = $runService->createRun($period, 'REGULAR', 'ALL', $officer->user_id)['run'];

        $map = ImportColumnMap::active('CANONICAL');
        $file = UploadedFile::fake()->createWithContent('register_clean.xlsx', file_get_contents(base_path('tests/Fixtures/register_clean.xlsx')));

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
            'import_column_map_id' => $map->import_column_map_id,
            'file' => $file,
        ]);
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/import/commit");

        foreach ($run->exceptions()->where('severity', 'WARNING')->whereNull('acknowledged_at')->get() as $warning) {
            $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/exceptions/{$warning->exception_instance_id}", [
                'acknowledgment_reason' => 'Acknowledged for test.',
            ]);
        }

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve");

        return $run->fresh();
    }

    public function test_milestone_pe_ledger_outage_does_not_block_run_finalization_ac_6_3_5(): void
    {
        $officer = $this->payrollOfficer();
        $approver = $this->approver();
        $run = $this->setupApprovedRun($officer, $approver);

        // Simulate complete ledger outage / offline network partition
        LedgerGateway::setReachable(false);

        // Approver finalizes run while ledger is down
        $response = $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");
        $response->assertRedirect(route('payroll-runs.show', $run));

        // Core Milestone P-E Guarantee: Run is finalized successfully despite ledger outage!
        $run->refresh();
        $this->assertSame('FINALIZED', $run->run_status);
        $this->assertNotNull($run->finalized_at);

        // Exactly one anchor exists in MySQL outbox with status PENDING (AC-6.3.1, AC-6.3.5)
        $anchor = IntegrityAnchor::query()
            ->where('scope_type', 'RUN')
            ->where('payroll_run_id', $run->payroll_run_id)
            ->first();

        $this->assertNotNull($anchor);
        $this->assertSame('PENDING', $anchor->anchor_status);
        $this->assertNull($anchor->confirmed_at);
        $this->assertNull($anchor->ledger_tx_ref);
    }

    public function test_milestone_pe_ledger_outage_does_not_block_run_reversal(): void
    {
        $officer = $this->payrollOfficer();
        $approver = $this->approver();
        $run = $this->setupApprovedRun($officer, $approver);

        // Finalize while online
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        // Now ledger goes offline
        LedgerGateway::setReachable(false);

        // Approver reverses the finalized run
        $response = $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/reverse", [
            'reason' => 'Emergency correction during ledger maintenance outage.',
        ]);
        $response->assertRedirect(route('payroll-runs.show', $run));

        // Reversal completes cleanly in MySQL
        $run->refresh();
        $this->assertSame('DRAFT', $run->run_status);

        // Reversal anchor exists as PENDING
        $reversalAnchor = IntegrityAnchor::query()
            ->where('scope_type', 'REVERSAL')
            ->first();

        $this->assertNotNull($reversalAnchor);
        $this->assertSame('PENDING', $reversalAnchor->anchor_status);
    }

    public function test_outbox_processing_transmits_pending_anchors_when_ledger_comes_online(): void
    {
        $officer = $this->payrollOfficer();
        $approver = $this->approver();
        $run = $this->setupApprovedRun($officer, $approver);

        // Finalize while ledger is offline
        LedgerGateway::setReachable(false);
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        $anchor = IntegrityAnchor::where('payroll_run_id', $run->payroll_run_id)->first();
        $this->assertSame('PENDING', $anchor->anchor_status);

        // Ledger comes back online!
        LedgerGateway::setReachable(true);

        // Run outbox processing command
        $this->artisan('integrity:process-outbox')
            ->assertSuccessful()
            ->expectsOutputToContain('Successfully confirmed 1 anchor(s)');

        // Anchor is now CONFIRMED with tx hash and block number
        $anchor->refresh();
        $this->assertSame('CONFIRMED', $anchor->anchor_status);
        $this->assertNotNull($anchor->confirmed_at);
        $this->assertNotNull($anchor->ledger_tx_ref);
        $this->assertNotNull($anchor->ledger_block_ref);
    }

    public function test_retry_counter_increments_and_flags_stalled_anchor(): void
    {
        $officer = $this->payrollOfficer();
        $approver = $this->approver();
        $run = $this->setupApprovedRun($officer, $approver);

        LedgerGateway::setReachable(false);
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        $anchor = IntegrityAnchor::where('payroll_run_id', $run->payroll_run_id)->first();
        $anchorService = app(LedgerAnchorService::class);

        // finalizeRun attempted immediate post-commit transmission while offline, so retry_count is 1
        $this->assertSame(1, $anchor->retry_count);
        $this->assertFalse($anchorService->isStalled($anchor));

        // Process outbox 4 more times while offline to reach limit of 5
        for ($i = 2; $i <= 5; $i++) {
            $anchorService->transmitAnchor($anchor);
            $anchor->refresh();
            $this->assertSame($i, $anchor->retry_count);
        }

        // Retry count has reached 5 (ANCHOR_RETRY_LIMIT) -> flagged as stalled!
        $this->assertTrue($anchorService->isStalled($anchor));
    }
}
