<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\ExceptionInstance;
use App\Models\ImportColumnMap;
use App\Models\IntegrityAnchor;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\ReversalRecord;
use App\Models\RunTransition;
use App\Models\User;
use App\Services\PayrollRunService;
use Database\Seeders\AttendanceTypeSeeder;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// FR-4.4 / FR-4.5 / Milestone P-B: Governed Payroll Lifecycle
// Tests submit, return, approve, finalize, cancel, reverse, period locking,
// immutable finalized lines, audit entries, transition records, and separation of duty.
class PayrollRunStateMachineTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    private function period(int $periodNo = 1, ?string $payDate = null): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_year' => 2026,
            'period_no' => $periodNo,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => '2026-01-'.str_pad((string) (($periodNo - 1) * 15 + 1), 2, '0', STR_PAD_LEFT),
            'cutoff_end' => '2026-01-'.str_pad((string) ($periodNo * 15), 2, '0', STR_PAD_LEFT),
            'pay_date' => $payDate ?? '2026-01-20',
            'is_closed' => false,
        ]);
    }

    private function setupRun(User $actor, ?PayrollPeriod $period = null): PayrollRun
    {
        $period = $period ?? $this->period();
        $typeId = DB::table('attendance_types')->value('attendance_type_id');

        foreach (['E-0001', 'E-0002', 'E-0003'] as $empNo) {
            $emp = Employee::factory()->create([
                'employee_no' => $empNo,
                'is_active' => true,
                'sss_no' => '01-2345678-9',
                'philhealth_no' => '01-234567890-1',
                'pagibig_mid' => '1234-5678-9012',
                'tin' => '123-456-789-000',
            ]);
            CompensationProfile::create([
                'employee_id' => $emp->employee_id,
                'pay_basis' => 'MONTHLY',
                'basic_rate' => '25000.00',
                'effective_from' => '2020-01-01',
                'effective_to' => null,
            ]);
            AttendanceRecord::create([
                'employee_id' => $emp->employee_id,
                'attendance_type_id' => $typeId,
                'work_date' => $period->cutoff_start->toDateString(),
                'hours_worked' => '8.00',
                'overtime_hours' => '0.00',
                'day_classification' => 'ORDINARY',
                'source' => 'IMPORT',
            ]);
        }

        return app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $actor->user_id)['run'];
    }

    private function acknowledgeAllWarnings(PayrollRun $run, User $actor): void
    {
        foreach ($run->exceptions()->where('severity', 'WARNING')->whereNull('acknowledged_at')->get() as $warning) {
            $this->actingAs($actor)->post("/payroll-runs/{$run->payroll_run_id}/exceptions/{$warning->exception_instance_id}", [
                'acknowledgment_reason' => 'Acknowledged and verified for processing.',
            ]);
        }
    }

    private function importCleanRegister(PayrollRun $run, User $officer): void
    {
        $map = ImportColumnMap::active('CANONICAL');
        $file = UploadedFile::fake()->createWithContent('register_clean.xlsx', file_get_contents(base_path('tests/Fixtures/register_clean.xlsx')));

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/import/preview", [
                'import_column_map_id' => $map->import_column_map_id,
                'file' => $file,
            ])
            ->assertOk();

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/import/commit")
            ->assertRedirect(route('payroll-runs.show', $run));

    }

    public function test_full_governed_lifecycle_happy_path(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);

        $this->assertEquals('DRAFT', $run->run_status);

        // 1. Import clean register
        $this->importCleanRegister($run, $officer);
        $run->refresh();
        $this->assertNotNull($run->currentImport());

        // Acknowledge any warning exceptions so submission is permitted
        $this->acknowledgeAllWarnings($run, $officer);

        // 2. Submit for review (UC-23)
        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/submit")
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('FOR_REVIEW', $run->run_status);
        $this->assertEquals($officer->user_id, $run->submitted_by);
        $this->assertNotNull($run->submitted_at);

        $transition = RunTransition::where('payroll_run_id', $run->payroll_run_id)->latest('run_transition_id')->first();
        $this->assertEquals('DRAFT', $transition->from_status);
        $this->assertEquals('FOR_REVIEW', $transition->to_status);
        $this->assertEquals($officer->user_id, $transition->performed_by);

        // 3. Approver approves run (UC-24)
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/approve")
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('APPROVED', $run->run_status);
        $this->assertEquals($approver->user_id, $run->approved_by);
        $this->assertNotNull($run->approved_at);

        $approveTransition = RunTransition::where('payroll_run_id', $run->payroll_run_id)->latest('run_transition_id')->first();
        $this->assertEquals('FOR_REVIEW', $approveTransition->from_status);
        $this->assertEquals('APPROVED', $approveTransition->to_status);
        $this->assertEquals($approver->user_id, $approveTransition->performed_by);

        $approveAudit = AuditLog::where('entity_name', 'PAYROLL_RUN')
            ->where('entity_id', $run->payroll_run_id)
            ->where('action', 'APPROVE')
            ->first();
        $this->assertNotNull($approveAudit);

        // 4. Approver finalizes run (UC-25 / FR-4.5)
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/finalize")
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('FINALIZED', $run->run_status);
        $this->assertNotNull($run->finalized_at);
        $this->assertGreaterThan(0, (float) $run->total_gross);
        $this->assertGreaterThan(0, (float) $run->total_net);
        $this->assertEquals(3, $run->employee_count);

        $finalizeTransition = RunTransition::where('payroll_run_id', $run->payroll_run_id)->latest('run_transition_id')->first();
        $this->assertEquals('APPROVED', $finalizeTransition->from_status);
        $this->assertEquals('FINALIZED', $finalizeTransition->to_status);

        // AC-4.5.5 / AC-6.3.1: Exactly one integrity anchor queued for the finalized run
        $anchor = IntegrityAnchor::where('payroll_run_id', $run->payroll_run_id)->where('scope_type', 'RUN')->first();
        $this->assertNotNull($anchor);
        $this->assertEquals('PENDING', $anchor->anchor_status);
        $this->assertEquals(64, strlen($anchor->payload_hash));
        $this->assertEquals('SHA-256', $anchor->hash_algorithm);
    }

    public function test_separation_of_duty_refuses_approval_by_submitter(): void
    {
        $officer = $this->officer();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");
        $run->refresh();
        $this->assertEquals('FOR_REVIEW', $run->run_status);

        // Submitter attempts to approve
        $response = $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/approve");
        $run->refresh();
        $this->assertEquals('FOR_REVIEW', $run->run_status);
        $this->assertNull($run->approved_by);

        // Database constraint chk_payroll_runs_separation_of_duty enforcement test
        $this->expectException(QueryException::class);
        DB::statement("UPDATE payroll_runs SET run_status = 'APPROVED', approved_by = {$officer->user_id} WHERE payroll_run_id = {$run->payroll_run_id}");
    }

    public function test_approver_returns_run_from_for_review(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");

        // Approver returns run with reason
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/return", [
                'reason' => 'Attendance hours do not match biometric logs for E-0002.',
            ])
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('RETURNED', $run->run_status);

        $transition = RunTransition::where('payroll_run_id', $run->payroll_run_id)->latest('run_transition_id')->first();
        $this->assertEquals('FOR_REVIEW', $transition->from_status);
        $this->assertEquals('RETURNED', $transition->to_status);
        $this->assertEquals('Attendance hours do not match biometric logs for E-0002.', $transition->reason);

        // Resubmission from RETURNED (UC-23 A1)
        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/submit")
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('FOR_REVIEW', $run->run_status);
    }

    public function test_return_from_approved_clears_approved_by_and_at(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve");

        $run->refresh();
        $this->assertEquals('APPROVED', $run->run_status);
        $this->assertEquals($approver->user_id, $run->approved_by);
        $this->assertNotNull($run->approved_at);

        // Approver returns from APPROVED before finalization (AC-4.4.6)
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/return", [
                'reason' => 'Late tax adjustment requested by accounting office.',
            ])
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('RETURNED', $run->run_status);
        $this->assertNull($run->approved_by);
        $this->assertNull($run->approved_at);

        $transitions = RunTransition::where('payroll_run_id', $run->payroll_run_id)->orderBy('run_transition_id')->get();
        $this->assertTrue($transitions->contains('to_status', 'APPROVED'));
        $this->assertTrue($transitions->contains('to_status', 'RETURNED'));
    }

    public function test_return_refused_without_reason(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");

        // Return without reason
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/return", ['reason' => '   '])
            ->assertSessionHasErrors('reason');

        $run->refresh();
        $this->assertEquals('FOR_REVIEW', $run->run_status);
    }

    public function test_reversal_happy_path_and_integrity_anchor(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();

        // Period with pay date in future (30 days from now)
        $futurePeriod = $this->period(1, now()->addDays(15)->toDateString());
        $run = $this->setupRun($officer, $futurePeriod);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        $run->refresh();
        $this->assertEquals('FINALIZED', $run->run_status);

        // Reversal by Approver (UC-26 / FR-4.5)
        $this->actingAs($approver)
            ->post("/payroll-runs/{$run->payroll_run_id}/reverse", [
                'reason' => 'Critical error: duplicate salary bracket applied to entire department.',
            ])
            ->assertRedirect("/payroll-runs/{$run->payroll_run_id}");

        $run->refresh();
        $this->assertEquals('DRAFT', $run->run_status);
        $this->assertNull($run->finalized_at);

        $reversalRecord = ReversalRecord::where('payroll_run_id', $run->payroll_run_id)->first();
        $this->assertNotNull($reversalRecord);
        $this->assertEquals('Critical error: duplicate salary bracket applied to entire department.', $reversalRecord->reason);
        $this->assertEquals($approver->user_id, $reversalRecord->reversed_by);
        $this->assertGreaterThan(0, (float) $reversalRecord->original_total_gross);

        // Integrity anchor queued for reversal
        $reversalAnchor = IntegrityAnchor::where('reversal_record_id', $reversalRecord->reversal_record_id)
            ->where('scope_type', 'REVERSAL')
            ->first();
        $this->assertNotNull($reversalAnchor);
        $this->assertEquals('PENDING', $reversalAnchor->anchor_status);

        $reversalAudit = AuditLog::where('entity_name', 'PAYROLL_RUN')
            ->where('entity_id', $run->payroll_run_id)
            ->where('action', 'REVERSE')
            ->first();
        $this->assertNotNull($reversalAudit);
    }

    public function test_reversal_refused_when_payslips_issued_and_pay_date_passed(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();

        // Past pay date
        $pastPeriod = $this->period(1, now()->subDays(5)->toDateString());
        $run = $this->setupRun($officer, $pastPeriod);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        // Simulate payslip issuance
        DB::table('payslip_issuances')->insert([
            'payroll_run_id' => $run->payroll_run_id,
            'employee_id' => $run->lines()->first()->employee_id,
            'issuance_type' => 'ORIGINAL',
            'issued_at' => now()->subDays(2),
            'issued_by' => $officer->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/reverse", [
            'reason' => 'Trying to reverse past pay date',
        ]);

        $run->refresh();
        $this->assertEquals('FINALIZED', $run->run_status);
        $this->assertNull(ReversalRecord::where('payroll_run_id', $run->payroll_run_id)->first());
    }

    public function test_submission_refused_with_blocking_exceptions(): void
    {
        $officer = $this->officer();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        // Inject an unresolved blocking exception
        ExceptionInstance::create([
            'payroll_run_id' => $run->payroll_run_id,
            'rule_code' => 'EX-03',
            'severity' => 'BLOCKING',
            'affected_employee_id' => $run->lines()->first()->employee_id,
            'message' => 'Net pay below floor.',
            'created_by' => $officer->user_id,
        ]);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");

        $run->refresh();
        $this->assertEquals('DRAFT', $run->run_status);
    }

    public function test_submission_refused_without_accepted_import(): void
    {
        $officer = $this->officer();
        $run = $this->setupRun($officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");

        $run->refresh();
        $this->assertEquals('DRAFT', $run->run_status);
    }

    public function test_cancellation_releases_period_for_new_run(): void
    {
        $officer = $this->officer();
        $period = $this->period();
        $run = $this->setupRun($officer, $period);

        $this->actingAs($officer)
            ->post("/payroll-runs/{$run->payroll_run_id}/cancel", ['reason' => 'Created by mistake'])
            ->assertRedirect('/payroll-runs');

        $run->refresh();
        $this->assertEquals('CANCELLED', $run->run_status);

        // A new run can now be created for the same period and population (UC-17 A2)
        $newRunData = app(PayrollRunService::class)->createRun($period, 'REGULAR', 'ALL', $officer->user_id);
        $this->assertNotNull($newRunData['run']);
        $this->assertEquals('DRAFT', $newRunData['run']->run_status);
    }

    public function test_finalized_payroll_lines_are_immutable_in_database(): void
    {
        $officer = $this->officer();
        $approver = $this->approver();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);
        $this->acknowledgeAllWarnings($run, $officer);

        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        $run->refresh();
        $this->assertEquals('FINALIZED', $run->run_status);

        $line = $run->lines()->first();

        // Attempt direct SQL update on payroll_line
        $this->expectException(QueryException::class);
        DB::statement("UPDATE payroll_lines SET net_pay = 99999.00 WHERE payroll_line_id = {$line->payroll_line_id}");
    }

    public function test_unauthorized_roles_refused_transitions(): void
    {
        $officer = $this->officer();
        $viewer = $this->viewer();
        $admin = $this->admin();
        $run = $this->setupRun($officer);
        $this->importCleanRegister($run, $officer);

        // Viewer cannot submit
        $this->actingAs($viewer)->post("/payroll-runs/{$run->payroll_run_id}/submit")->assertForbidden();

        // Admin cannot submit
        $this->actingAs($admin)->post("/payroll-runs/{$run->payroll_run_id}/submit")->assertForbidden();

        // Admin cannot approve
        $this->actingAs($admin)->post("/payroll-runs/{$run->payroll_run_id}/approve")->assertForbidden();

        // Viewer cannot approve
        $this->actingAs($viewer)->post("/payroll-runs/{$run->payroll_run_id}/approve")->assertForbidden();
    }
}
