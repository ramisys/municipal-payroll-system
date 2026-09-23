<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\ImportColumnMap;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\User;
use App\Services\PayrollRunService;
use Database\Seeders\AttendanceTypeSeeder;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatutoryScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// UC-31 · Verify payroll record integrity — FR-6.3.
class IntegrityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(AttendanceTypeSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(StatutoryScheduleSeeder::class);
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

        $dept = Department::create([
            'department_code' => 'ACC',
            'department_name' => 'Accounting',
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
            $showResponse->assertSee('Cryptographic SHA-256 fingerprint');
        }

        // Officer does not have integrity.verify permission
        $this->actingAs($officer)->get('/integrity')->assertForbidden();
        $this->actingAs($officer)->get("/integrity/{$run->payroll_run_id}")->assertForbidden();
    }

    public function test_triggering_integrity_verification_recomputes_hash_and_records_match(): void
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
    }
}
