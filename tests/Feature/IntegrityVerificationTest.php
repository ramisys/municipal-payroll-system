<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\ImportColumnMap;
use App\Models\IntegrityAnchor;
use App\Models\IntegrityVerification;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IntegrityVerificationService;
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

// UC-31 · Verify payroll record integrity — FR-6.3 / Milestone P-E.
class IntegrityVerificationTest extends TestCase
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

    private function viewer(): User
    {
        return User::factory()->forRole('VIEWER')->create();
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

    private function setupFinalizedRun(User $officer, User $approver): PayrollRun
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
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        return $run->fresh();
    }

    public function test_authorized_roles_can_access_integrity_verification_index_and_details(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $viewer = $this->viewer();
        $officer = $this->payrollOfficer();

        $run = $this->setupFinalizedRun($officer, $approver);

        foreach ([$admin, $approver, $viewer] as $user) {
            $response = $this->actingAs($user)->get('/integrity');
            $response->assertOk();
            $response->assertSee('Integrity Verification');
            $response->assertSee("Run #{$run->payroll_run_id}");

            $showResponse = $this->actingAs($user)->get("/integrity/{$run->payroll_run_id}");
            $showResponse->assertOk();
            $showResponse->assertSee('Anchored MySQL Local Fingerprint');
        }

        // Payroll Officer does not have integrity.verify permission (separation of duty)
        $this->actingAs($officer)->get('/integrity')->assertForbidden();
        $this->actingAs($officer)->get("/integrity/{$run->payroll_run_id}")->assertForbidden();
    }

    public function test_triggering_integrity_verification_recomputes_hash_and_records_match_ac_6_3_2(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $officer = $this->payrollOfficer();

        $run = $this->setupFinalizedRun($officer, $approver);

        $response = $this->actingAs($admin)->post("/integrity/{$run->payroll_run_id}/verify");
        $response->assertRedirect(route('integrity.show', $run));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('integrity_verifications', [
            'result' => 'MATCH',
            'performed_by' => $admin->user_id,
        ]);

        $detailResponse = $this->actingAs($admin)->get("/integrity/{$run->payroll_run_id}");
        $detailResponse->assertSee('INTEGRITY VERIFIED: MATCH');
    }

    public function test_tampered_database_row_reports_mismatch_ac_6_3_3_uc_31_e1(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $officer = $this->payrollOfficer();

        $run = $this->setupFinalizedRun($officer, $approver);

        // Tamper directly with live MySQL figures, bypassing the application layer
        DB::statement("UPDATE payroll_runs SET total_gross = total_gross + 1000.00 WHERE payroll_run_id = {$run->payroll_run_id}");

        // Trigger verification
        $response = $this->actingAs($admin)->post("/integrity/{$run->payroll_run_id}/verify");
        $response->assertRedirect(route('integrity.show', $run));
        $response->assertSessionHas('error');

        // Verify MISMATCH recorded in append-only table
        $this->assertDatabaseHas('integrity_verifications', [
            'result' => 'MISMATCH',
            'failure_position' => 'PAYLOAD_DIVERGENCE',
            'performed_by' => $admin->user_id,
        ]);

        $detailResponse = $this->actingAs($admin)->get("/integrity/{$run->payroll_run_id}");
        $detailResponse->assertSee('SECURITY ALERT: INTEGRITY MISMATCH (UC-31 E1)');
        $detailResponse->assertSee('The system never resolves a mismatch automatically and never re-anchors a mismatched record');
    }

    public function test_pending_anchor_reports_unverifiable_uc_31_e2(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $officer = $this->payrollOfficer();

        // Finalize run while ledger is offline so anchor stays PENDING
        LedgerGateway::setReachable(false);
        $run = $this->setupFinalizedRun($officer, $approver);

        $anchor = IntegrityAnchor::where('payroll_run_id', $run->payroll_run_id)->first();
        $this->assertSame('PENDING', $anchor->anchor_status);

        // Now attempt verification while anchor is still pending
        $service = app(IntegrityVerificationService::class);
        $outcome = $service->verifyRun($run, $admin->user_id);

        $this->assertSame('UNVERIFIABLE', $outcome['result']);
        $this->assertSame('PENDING_ANCHOR', $outcome['failure_position']);
        $this->assertStringContainsString('Not yet anchored', $outcome['remarks']);
        $this->assertStringContainsString('absence of evidence, not a failure of integrity', $outcome['remarks']);

        $this->assertDatabaseHas('integrity_verifications', [
            'result' => 'UNVERIFIABLE',
            'failure_position' => 'PENDING_ANCHOR',
        ]);
    }

    public function test_unreachable_ledger_reports_unverifiable_uc_31_e3(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $officer = $this->payrollOfficer();

        // Finalize run and confirm on ledger
        $run = $this->setupFinalizedRun($officer, $approver);
        $anchor = IntegrityAnchor::where('payroll_run_id', $run->payroll_run_id)->first();
        $this->assertSame('CONFIRMED', $anchor->anchor_status);

        // Ledger goes offline after confirmation
        LedgerGateway::setReachable(false);

        // Verification attempted during ledger outage
        $service = app(IntegrityVerificationService::class);
        $outcome = $service->verifyRun($run, $admin->user_id);

        $this->assertSame('UNVERIFIABLE', $outcome['result']);
        $this->assertSame('LEDGER_UNREACHABLE', $outcome['failure_position']);
        $this->assertStringContainsString('Verification unavailable', $outcome['remarks']);
        $this->assertStringContainsString('not a mismatch', $outcome['remarks']);

        $this->assertDatabaseHas('integrity_verifications', [
            'result' => 'UNVERIFIABLE',
            'failure_position' => 'LEDGER_UNREACHABLE',
        ]);
    }

    public function test_reversal_record_integrity_verification(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $officer = $this->payrollOfficer();

        $run = $this->setupFinalizedRun($officer, $approver);

        // Reverse the run
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/reverse", [
            'reason' => 'Reversal test for cryptographic anchoring.',
        ]);

        $reversalAnchor = IntegrityAnchor::where('scope_type', 'REVERSAL')->first();
        $this->assertNotNull($reversalAnchor);

        // Verify reversal record
        $response = $this->actingAs($admin)->post("/integrity/reversals/{$reversalAnchor->reversal_record_id}/verify");
        $response->assertRedirect(route('integrity.reversals.show', $reversalAnchor->reversal_record_id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('integrity_verifications', [
            'integrity_anchor_id' => $reversalAnchor->integrity_anchor_id,
            'result' => 'MATCH',
        ]);
    }

    public function test_period_batch_verification_uc_31_a2(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $officer = $this->payrollOfficer();

        $run = $this->setupFinalizedRun($officer, $approver);

        $response = $this->actingAs($admin)->post("/integrity/period/{$run->payroll_period_id}/verify");
        $response->assertRedirect(route('integrity.index'));
        $response->assertSessionHas('success');
    }

    public function test_audit_chain_verification_intact_and_detects_tampered_entry_ac_6_3_4_uc_31_a1(): void
    {
        $admin = $this->administrator();
        $auditService = app(AuditService::class);

        // Clean chain check
        $cleanResult = $auditService->verifyChain();
        $this->assertTrue($cleanResult['intact']);
        $this->assertNull($cleanResult['broken_at']);

        $response = $this->actingAs($admin)->post('/integrity/audit-chain/verify');
        $response->assertRedirect(route('integrity.index'));
        $response->assertSessionHas('success');

        // Now simulate malicious database administrator tampering by bypassing trigger
        $firstLog = AuditLog::orderBy('audit_log_id')->first();
        $this->assertNotNull($firstLog);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_no_update');
        DB::statement("UPDATE audit_logs SET entity_name = 'TAMPERED_ENTITY' WHERE audit_log_id = {$firstLog->audit_log_id}");

        // Audit chain check detects break at that precise log ID (AC-6.3.4)
        $tamperedResult = $auditService->verifyChain();
        $this->assertFalse($tamperedResult['intact']);
        $this->assertSame($firstLog->audit_log_id, $tamperedResult['broken_at']);

        $tamperedResponse = $this->actingAs($admin)->post('/integrity/audit-chain/verify');
        $tamperedResponse->assertRedirect(route('integrity.index'));
        $tamperedResponse->assertSessionHas('error');
    }

    public function test_pdf_verification_certificate_download_uc_31_a3(): void
    {
        $admin = $this->administrator();
        $approver = $this->approver();
        $officer = $this->payrollOfficer();

        $run = $this->setupFinalizedRun($officer, $approver);

        $this->actingAs($admin)->post("/integrity/{$run->payroll_run_id}/verify");

        $verification = IntegrityVerification::latest('integrity_verification_id')->first();
        $this->assertNotNull($verification);

        $pdfResponse = $this->actingAs($admin)->get("/integrity/verifications/{$verification->integrity_verification_id}/pdf");
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));
    }
}
