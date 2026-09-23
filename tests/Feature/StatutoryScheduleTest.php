<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\DeductionLine;
use App\Models\DeductionType;
use App\Models\EarningLine;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\ImportColumnMap;
use App\Models\PayrollImport;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\StatutorySchedule;
use App\Models\User;
use App\Services\StatutoryScheduleService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatutoryScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// UC-05 · Maintain statutory schedules — FR-2.3, BR-14, BR-20.
// Acceptance criteria: AC-2.3.1, AC-2.3.2, AC-2.3.3, AC-2.3.4, AC-2.3.5.
class StatutoryScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(StatutoryScheduleSeeder::class);
    }

    private function administrator(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    private function payrollOfficer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    private function viewer(): User
    {
        return User::factory()->forRole('VIEWER')->create();
    }

    private function createPeriod(int $year = 2024, int $periodNo = 1): PayrollPeriod
    {
        return PayrollPeriod::create([
            'payroll_year' => $year,
            'period_no' => $periodNo,
            'pay_frequency' => 'SEMI_MONTHLY',
            'cutoff_start' => "{$year}-01-01",
            'cutoff_end' => "{$year}-01-15",
            'pay_date' => "{$year}-01-20",
            'is_closed' => false,
        ]);
    }

    private function createImport(PayrollRun $run, User $user): PayrollImport
    {
        $map = ImportColumnMap::firstOrFail();

        return PayrollImport::create([
            'payroll_run_id' => $run->payroll_run_id,
            'import_column_map_id' => $map->import_column_map_id,
            'version_no' => 1,
            'source_filename' => 'test.xlsx',
            'source_sha256' => str_repeat('a', 64),
            'imported_by' => $user->user_id,
            'imported_at' => now(),
            'row_count' => 1,
            'control_total_gross' => 18000.00,
            'control_total_deductions' => 787.50,
            'control_total_net' => 17212.50,
            'is_current' => true,
        ]);
    }

    public function test_administrator_can_view_and_create_statutory_schedules(): void
    {
        $admin = $this->administrator();

        $response = $this->actingAs($admin)->get(route('statutory-schedules.index', ['agency' => 'SSS']));
        $response->assertOk();
        $response->assertSee('SSS-2024');

        // End date the current PhilHealth to allow a 2026 test schedule (UC-05 A2)
        $existing = StatutorySchedule::where('agency', 'PHILHEALTH')->first();
        $existing->update(['effective_to' => '2025-12-31']);

        // Create new schedule version
        $createResponse = $this->actingAs($admin)
            ->from(route('statutory-schedules.create'))
            ->post(route('statutory-schedules.store'), [
                'agency' => 'PHILHEALTH',
                'schedule_version' => 'PHIC-2026',
                'effective_from' => '2026-01-01',
                'premium_rate' => 0.0500,
                'salary_floor' => 10000.00,
                'salary_ceiling' => 100000.00,
                'issuance_reference' => 'PHIC Circular 2026-001',
            ]);

        $createResponse->assertRedirect(route('statutory-schedules.index', ['agency' => 'PHILHEALTH']));
        $this->assertDatabaseHas('statutory_schedules', [
            'agency' => 'PHILHEALTH',
            'schedule_version' => 'PHIC-2026',
        ]);
    }

    public function test_viewer_has_read_only_access_to_statutory_schedules(): void
    {
        $viewer = $this->viewer();
        $schedule = StatutorySchedule::where('agency', 'SSS')->first();

        // Viewer can see index and detail (UC-05 A3)
        $this->actingAs($viewer)
            ->get(route('statutory-schedules.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('statutory-schedules.show', $schedule))
            ->assertOk();

        // Viewer cannot create, edit, or end-date
        $this->actingAs($viewer)
            ->get(route('statutory-schedules.create'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('statutory-schedules.store'), [
                'agency' => 'SSS',
                'schedule_version' => 'FORBIDDEN',
                'effective_from' => '2027-01-01',
            ])
            ->assertForbidden();
    }

    public function test_payroll_officer_cannot_access_statutory_schedule_management(): void
    {
        $officer = $this->payrollOfficer();

        $this->actingAs($officer)
            ->get(route('statutory-schedules.index'))
            ->assertForbidden();

        $this->actingAs($officer)
            ->get(route('statutory-schedules.create'))
            ->assertForbidden();
    }

    public function test_overlapping_schedule_effectivity_ranges_are_refused(): void
    {
        $admin = $this->administrator();

        // SSS-2024 is effective from 2024-01-01 to NULL (indefinite).
        // Attempting to create an overlapping schedule for SSS is rejected by BR-14 trigger.
        $response = $this->actingAs($admin)
            ->from(route('statutory-schedules.create'))
            ->post(route('statutory-schedules.store'), [
                'agency' => 'SSS',
                'schedule_version' => 'SSS-CONFLICT',
                'effective_from' => '2024-06-01',
            ]);

        $response->assertSessionHasErrors(['schedule']);
        $this->assertDatabaseMissing('statutory_schedules', [
            'schedule_version' => 'SSS-CONFLICT',
        ]);
    }

    public function test_non_contiguous_brackets_are_refused(): void
    {
        $admin = $this->administrator();

        // End date the current PhilHealth to allow a 2027 test schedule
        $existing = StatutorySchedule::where('agency', 'PHILHEALTH')->first();
        $existing->update(['effective_to' => '2026-12-31']);

        $response = $this->actingAs($admin)
            ->from(route('statutory-schedules.create'))
            ->post(route('statutory-schedules.store'), [
                'agency' => 'PHILHEALTH',
                'schedule_version' => 'PHIC-GAP-TEST',
                'effective_from' => '2027-01-01',
                'brackets' => [
                    ['range_from' => 0.00, 'range_to' => 5000.00, 'employee_share' => 100.00, 'employer_share' => 100.00],
                    ['range_from' => 6000.00, 'range_to' => 10000.00, 'employee_share' => 200.00, 'employer_share' => 200.00], // Gap
                ],
            ]);

        $response->assertSessionHasErrors(['brackets']);
        $this->assertDatabaseMissing('statutory_schedules', [
            'schedule_version' => 'PHIC-GAP-TEST',
        ]);
    }

    public function test_employer_share_derivation_from_active_schedule(): void
    {
        /** @var StatutoryScheduleService $service */
        $service = app(StatutoryScheduleService::class);

        $officer = $this->payrollOfficer();
        $admin = $this->administrator();
        $period = $this->createPeriod(2024, 1);

        $employee = Employee::factory()->create([
            'employee_no' => 'EMP-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'M',
            'civil_status' => 'SINGLE',
            'is_active' => true,
        ]);

        $run = PayrollRun::create([
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'population_scope' => 'ALL',
            'run_status' => 'DRAFT',
            'created_by' => $officer->user_id,
        ]);

        $profile = CompensationProfile::create([
            'employee_id' => $employee->employee_id,
            'pay_basis' => 'MONTHLY',
            'basic_rate' => 18000.00,
            'effective_from' => '2024-01-01',
            'created_by' => $admin->user_id,
        ]);

        $import = $this->createImport($run, $officer);

        // BR-37: Must insert line with 0 totals first
        $line = PayrollLine::create([
            'payroll_run_id' => $run->payroll_run_id,
            'payroll_import_id' => $import->payroll_import_id,
            'employee_id' => $employee->employee_id,
            'compensation_profile_id' => $profile->compensation_profile_id,
            'days_worked' => 11.0,
            'hours_worked' => 88.0,
            'gross_pay' => 0.00,
            'total_deductions' => 0.00,
            'net_pay' => 0.00,
        ]);

        $basicEarningType = EarningType::where('earning_code', 'BASIC')->first();
        EarningLine::create([
            'payroll_line_id' => $line->payroll_line_id,
            'earning_type_id' => $basicEarningType->earning_type_id,
            'amount' => 18000.00,
            'is_taxable' => true,
        ]);

        $sssDeductionType = DeductionType::where('deduction_code', 'SSS')->first();

        // Register omits employer share: employer_share is NULL
        DeductionLine::create([
            'payroll_line_id' => $line->payroll_line_id,
            'deduction_type_id' => $sssDeductionType->deduction_type_id,
            'employee_share' => 787.50,
            'employer_share' => null, // OMITTED
            'amount' => 787.50,
        ]);

        // BR-37: Update totals once children exist
        $line->update([
            'gross_pay' => 18000.00,
            'total_deductions' => 787.50,
            'net_pay' => 17212.50,
        ]);

        $result = $service->deriveEmployerShare('SSS', $line, '2024-01-20');

        $this->assertEquals('DERIVED', $result['source']);
        $this->assertEquals('SSS-2024', $result['schedule_version']);
        $this->assertEquals('1672.50', $result['amount']); // SSS-2024 bracket 4 employer share
    }

    public function test_imported_employer_share_is_preserved_when_present(): void
    {
        /** @var StatutoryScheduleService $service */
        $service = app(StatutoryScheduleService::class);

        $officer = $this->payrollOfficer();
        $period = $this->createPeriod(2024, 1);

        $employee = Employee::factory()->create([
            'employee_no' => 'EMP-002',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'sex' => 'F',
            'civil_status' => 'MARRIED',
            'is_active' => true,
        ]);

        $run = PayrollRun::create([
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'population_scope' => 'ALL',
            'run_status' => 'DRAFT',
            'created_by' => $officer->user_id,
        ]);

        $profile = CompensationProfile::create([
            'employee_id' => $employee->employee_id,
            'pay_basis' => 'MONTHLY',
            'basic_rate' => 18000.00,
            'effective_from' => '2024-01-01',
            'created_by' => $officer->user_id,
        ]);

        $import = $this->createImport($run, $officer);

        $line = PayrollLine::create([
            'payroll_run_id' => $run->payroll_run_id,
            'payroll_import_id' => $import->payroll_import_id,
            'employee_id' => $employee->employee_id,
            'compensation_profile_id' => $profile->compensation_profile_id,
            'gross_pay' => 0.00,
            'total_deductions' => 0.00,
            'net_pay' => 0.00,
        ]);

        $basicEarningType = EarningType::where('earning_code', 'BASIC')->first();
        EarningLine::create([
            'payroll_line_id' => $line->payroll_line_id,
            'earning_type_id' => $basicEarningType->earning_type_id,
            'amount' => 18000.00,
            'is_taxable' => true,
        ]);

        $phicDeductionType = DeductionType::where('deduction_code', 'PHILHEALTH')->first();

        // Register contains explicit employer share: employer_share is 450.00
        DeductionLine::create([
            'payroll_line_id' => $line->payroll_line_id,
            'deduction_type_id' => $phicDeductionType->deduction_type_id,
            'employee_share' => 450.00,
            'employer_share' => 450.00, // IMPORTED
            'amount' => 450.00,
        ]);

        $line->update([
            'gross_pay' => 18000.00,
            'total_deductions' => 450.00,
            'net_pay' => 17550.00,
        ]);

        $result = $service->deriveEmployerShare('PHILHEALTH', $line, '2024-01-20');

        $this->assertEquals('IMPORTED', $result['source']);
        $this->assertEquals('450.00', $result['amount']);
        $this->assertNull($result['schedule_version']);
    }

    public function test_editing_statutory_schedule_never_changes_employee_net_pay(): void
    {
        $officer = $this->payrollOfficer();
        $period = $this->createPeriod(2024, 1);

        $employee = Employee::factory()->create([
            'employee_no' => 'EMP-003',
            'first_name' => 'Pedro',
            'last_name' => 'Penduko',
            'sex' => 'M',
            'civil_status' => 'SINGLE',
            'is_active' => true,
        ]);

        $run = PayrollRun::create([
            'payroll_period_id' => $period->payroll_period_id,
            'run_type' => 'REGULAR',
            'population_scope' => 'ALL',
            'run_status' => 'DRAFT',
            'created_by' => $officer->user_id,
        ]);

        $profile = CompensationProfile::create([
            'employee_id' => $employee->employee_id,
            'pay_basis' => 'MONTHLY',
            'basic_rate' => 20000.00,
            'effective_from' => '2024-01-01',
            'created_by' => $officer->user_id,
        ]);

        $import = $this->createImport($run, $officer);

        $line = PayrollLine::create([
            'payroll_run_id' => $run->payroll_run_id,
            'payroll_import_id' => $import->payroll_import_id,
            'employee_id' => $employee->employee_id,
            'compensation_profile_id' => $profile->compensation_profile_id,
            'gross_pay' => 0.00,
            'total_deductions' => 0.00,
            'net_pay' => 0.00,
        ]);

        $basicEarningType = EarningType::where('earning_code', 'BASIC')->first();
        EarningLine::create([
            'payroll_line_id' => $line->payroll_line_id,
            'earning_type_id' => $basicEarningType->earning_type_id,
            'amount' => 20000.00,
            'is_taxable' => true,
        ]);

        $sssDeductionType = DeductionType::where('deduction_code', 'SSS')->first();
        DeductionLine::create([
            'payroll_line_id' => $line->payroll_line_id,
            'deduction_type_id' => $sssDeductionType->deduction_type_id,
            'employee_share' => 1000.00,
            'employer_share' => null,
            'amount' => 1000.00,
        ]);

        $line->update([
            'gross_pay' => 20000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 19000.00,
        ]);

        // Edit schedule
        $schedule = StatutorySchedule::where('agency', 'PHILHEALTH')->first();
        $schedule->update(['premium_rate' => 0.0600]);

        // Verify line is completely untouched (AC-2.3.5)
        $line->refresh();
        $this->assertEquals('20000.00', (string) $line->gross_pay);
        $this->assertEquals('1000.00', (string) $line->total_deductions);
        $this->assertEquals('19000.00', (string) $line->net_pay);
    }
}
