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
use App\Services\BackupService;
use App\Services\IntegrityVerificationService;
use App\Services\PayrollRunService;
use Database\Seeders\AttendanceTypeSeeder;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatutoryScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

// UC-07 · Back up and restore database (NFR-5.4, NFR-6.3, Milestone P-D).
// NFR-5.4 Scheduled and on-demand logical backup with checksum verification.
// NFR-6.3 Documented restore with double confirmation naming exact archive.
// Milestone P-D Exit Condition: "a restored backup passes integrity verification."
class BackupAndRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    private bool $didRestore = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = storage_path('app/backups');
        if (! File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }

        $this->seed(RoleSeeder::class);
        $this->seed(AttendanceTypeSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(StatutoryScheduleSeeder::class);
    }

    protected function tearDown(): void
    {
        // Clean up created backup test files
        if (File::exists($this->backupDir)) {
            $files = File::glob("{$this->backupDir}/payroll_backup_*");
            foreach ($files as $f) {
                @unlink($f);
            }
        }

        if ($this->didRestore) {
            Artisan::call('migrate:fresh');
            RefreshDatabaseState::$migrated = true;
            $this->didRestore = false;
        }

        parent::tearDown();
    }

    private function administrator(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    private function payrollOfficer(): User
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

    private function createPeriod(int $periodNo = 1): PayrollPeriod
    {
        $start = $periodNo === 1 ? '2026-01-01' : '2026-01-16';
        $end = $periodNo === 1 ? '2026-01-15' : '2026-01-31';
        $payDate = $periodNo === 1 ? '2026-01-20' : '2026-02-05';

        return PayrollPeriod::create([
            'payroll_year' => 2026,
            'period_no' => $periodNo,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => $start,
            'cutoff_end' => $end,
            'pay_date' => $payDate,
            'is_closed' => false,
        ]);
    }

    private function acknowledgeAllWarnings(PayrollRun $run, User $actor): void
    {
        foreach ($run->exceptions()->where('severity', 'WARNING')->whereNull('acknowledged_at')->get() as $warning) {
            $this->actingAs($actor)->post("/payroll-runs/{$run->payroll_run_id}/exceptions/{$warning->exception_instance_id}", [
                'acknowledgment_reason' => 'Acknowledged and verified for processing.',
            ]);
        }
    }

    private function setupFinalizedRun(User $officer, User $approver): PayrollRun
    {
        $period = $this->createPeriod();
        $attendanceTypeId = DB::table('attendance_types')->value('attendance_type_id');

        $dept = Department::create([
            'department_code' => 'TEST_DEPT',
            'department_name' => 'Testing Department',
            'is_active' => true,
        ]);

        $pos = Position::create([
            'position_code' => 'CLERK',
            'position_title' => 'Clerk',
            'is_active' => true,
        ]);

        $stat = EmploymentStatus::create([
            'status_code' => 'REG',
            'status_name' => 'Regular',
            'is_payroll_eligible' => true,
            'is_active' => true,
        ]);

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

            if ($attendanceTypeId) {
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

        // Acknowledge non-blocking warnings before submission
        $this->acknowledgeAllWarnings($run, $officer);

        // Submit, Approve, Finalize (UC-01, UC-02, UC-03)
        $this->actingAs($officer)->post("/payroll-runs/{$run->payroll_run_id}/submit");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/approve");
        $this->actingAs($approver)->post("/payroll-runs/{$run->payroll_run_id}/finalize");

        return $run->fresh();
    }

    public function test_administrator_can_trigger_and_list_backups(): void
    {
        $admin = $this->administrator();

        $response = $this->actingAs($admin)->get('/backups');
        $response->assertOk();
        $response->assertSee('Database Backups');

        // Trigger on-demand backup via web
        $createResponse = $this->actingAs($admin)->post('/backups', [
            'description' => 'Pre-deployment backup',
        ]);
        $createResponse->assertRedirect('/backups');

        // Check backup was created on disk
        $files = File::glob("{$this->backupDir}/payroll_backup_*.sql.gz");
        $this->assertNotEmpty($files, 'Backup .sql.gz archive must exist on disk.');

        $metaFiles = File::glob("{$this->backupDir}/payroll_backup_*.json");
        $this->assertNotEmpty($metaFiles, 'Backup metadata .json must exist on disk.');

        $meta = json_decode(file_get_contents($metaFiles[0]), true);
        $this->assertArrayHasKey('sha256', $meta);
        $this->assertArrayHasKey('total_rows', $meta);
        $this->assertGreaterThan(0, $meta['size_bytes']);
    }

    public function test_non_admin_cannot_access_or_trigger_backup(): void
    {
        $officer = $this->payrollOfficer();
        $viewer = $this->viewer();

        foreach ([$officer, $viewer] as $user) {
            $this->actingAs($user)->get('/backups')->assertForbidden();
            $this->actingAs($user)->post('/backups', ['description' => 'Forbidden'])->assertForbidden();
        }
    }

    public function test_checksum_verification_detects_tampering_of_backup_file(): void
    {
        /** @var BackupService $backupService */
        $backupService = app(BackupService::class);
        $admin = $this->administrator();

        $backup = $backupService->createBackup('Integrity check test', $admin->user_id);
        $filename = $backup['filename'];

        // Clean backup passes verification
        $validRes = $backupService->verifyBackup($filename);
        $this->assertTrue($validRes['valid']);

        // Tamper with archive on disk
        $gzPath = "{$this->backupDir}/{$filename}";
        file_put_contents($gzPath, 'CORRUPTED_BYTES', FILE_APPEND);

        // Verification must detect checksum mismatch (NFR-5.4)
        $tamperedRes = $backupService->verifyBackup($filename);
        $this->assertFalse($tamperedRes['valid']);
        $this->assertStringContainsString('Checksum mismatch', $tamperedRes['error']);
    }

    public function test_restore_refused_when_open_payroll_runs_exist_without_force(): void
    {
        /** @var BackupService $backupService */
        $backupService = app(BackupService::class);
        $admin = $this->administrator();
        $officer = $this->payrollOfficer();

        $backup = $backupService->createBackup('Safe baseline', $admin->user_id);
        $filename = $backup['filename'];

        // Create open payroll run in DRAFT status
        $period = $this->createPeriod(2);
        PayrollRun::create([
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'population_scope' => 'ALL',
            'run_status' => 'DRAFT',
            'created_by' => $officer->user_id,
        ]);

        // Attempt restore without force (UC-07 E2)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/active run.*force/i');

        $backupService->restoreBackup($filename, $admin->user_id, force: false);
    }

    public function test_restore_requires_double_confirmation_matching_exact_filename(): void
    {
        /** @var BackupService $backupService */
        $backupService = app(BackupService::class);
        $admin = $this->administrator();

        $backup = $backupService->createBackup('Double confirmation test', $admin->user_id);
        $filename = $backup['filename'];

        // Mismatched confirmation text
        $response = $this->actingAs($admin)->post('/backups/restore', [
            'filename' => $filename,
            'confirmation_text' => 'wrong_filename.sql.gz',
        ]);

        $response->assertRedirect('/backups');
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Confirmation failed', session('error'));
    }

    public function test_milestone_pd_finalized_run_anchored_backup_tamper_and_restore_integrity_verification(): void
    {
        /** @var BackupService $backupService */
        $backupService = app(BackupService::class);
        /** @var IntegrityVerificationService $verificationService */
        $verificationService = app(IntegrityVerificationService::class);

        $admin = $this->administrator();
        $officer = $this->payrollOfficer();
        $approver = $this->approver();

        // 1. Setup a finalized run with imported lines
        $run = $this->setupFinalizedRun($officer, $approver);
        $this->assertEquals('FINALIZED', $run->run_status);

        // 2. Verify run is anchored
        $anchor = IntegrityAnchor::where('payroll_run_id', $run->payroll_run_id)->first();
        $this->assertNotNull($anchor, 'Finalized run must have an integrity anchor (AC-4.5.5).');

        // 3. Live verification of clean run -> MATCH
        $initialVerification = $verificationService->verifyRun($run, $admin->user_id);
        $this->assertEquals('MATCH', $initialVerification['result']);
        $this->assertEquals($anchor->payload_hash, $initialVerification['recomputed_hash']);

        // 4. Create logical backup of the clean database
        $backup = $backupService->createBackup('Milestone P-D baseline', $admin->user_id);
        $filename = $backup['filename'];

        // 5. Tamper with a live payroll record
        DB::statement('UPDATE payroll_runs SET total_gross = total_gross + 500.00 WHERE payroll_run_id = ?', [
            $run->payroll_run_id,
        ]);

        // 6. Live verification detects tampering -> MISMATCH
        $tamperedVerification = $verificationService->verifyRun($run->fresh(), $admin->user_id);
        $this->assertEquals('MISMATCH', $tamperedVerification['result']);
        $this->assertNotEquals($anchor->payload_hash, $tamperedVerification['recomputed_hash']);

        // 7. Execute restore from the backup archive (double confirmation, NFR-6.3, UC-07)
        $restoreResult = $backupService->restoreBackup($filename, $admin->user_id, force: true);
        $this->didRestore = true;
        $this->assertTrue($restoreResult['success']);

        // 8. Re-verify run integrity post-restore -> 100% MATCH! (Milestone P-D Exit Condition)
        $postRestoreVerification = $verificationService->verifyRun($run->fresh(), $admin->user_id);
        $this->assertEquals('MATCH', $postRestoreVerification['result']);
        $this->assertEquals($anchor->payload_hash, $postRestoreVerification['recomputed_hash']);
    }
}
