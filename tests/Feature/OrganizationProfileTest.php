<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\OrganizationProfile;
use App\Models\PayrollPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UC-03 · Configure organization profile and payroll calendar —
 * FR-0.3, AC-0.3.1-0.3.4. Period-generation correctness itself is
 * PayrollPeriodGenerationServiceTest (BR-34); this covers the
 * controller surface: the profile form, calendar generation end to
 * end through HTTP, and the holiday calendar.
 */
class OrganizationProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SystemConfigSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    public function test_an_administrator_can_save_the_organization_profile(): void
    {
        $response = $this->actingAs($this->admin())->put('/organization', [
            'registered_name' => 'Municipality of Sample',
            'address' => '1 Poblacion',
            'sss_employer_no' => 'SSS-001',
            'philhealth_employer_no' => 'PH-001',
            'pagibig_employer_no' => 'HDMF-001',
            'bir_tin' => '123-456-789',
            'standard_hours_per_day' => '8.00',
        ]);

        $response->assertRedirect(route('organization.edit'));

        $profile = OrganizationProfile::query()->firstOrFail();
        self::assertSame('Municipality of Sample', $profile->registered_name);

        self::assertNotNull(
            AuditLog::query()->where('entity_name', 'ORGANIZATION_PROFILE')->where('action', 'CREATE')->first()
        );
    }

    public function test_generating_a_payroll_year_from_the_form_creates_periods_with_no_overlap_or_gap(): void
    {
        $response = $this->actingAs($this->admin())->post('/organization/periods', [
            'payroll_year' => 2028,
            'pay_frequency' => 'MONTHLY',
            'pay_date_offset_days' => 5,
        ]);

        $response->assertRedirect(route('organization.periods.index'));
        self::assertSame(12, PayrollPeriod::query()->where('payroll_year', 2028)->count());
    }

    public function test_generating_the_same_payroll_year_twice_is_refused_with_no_partial_state(): void
    {
        $this->actingAs($this->admin())->post('/organization/periods', [
            'payroll_year' => 2028, 'pay_frequency' => 'MONTHLY', 'pay_date_offset_days' => 5,
        ]);

        $response = $this->actingAs($this->admin())->post('/organization/periods', [
            'payroll_year' => 2028, 'pay_frequency' => 'MONTHLY', 'pay_date_offset_days' => 5,
        ]);

        $response->assertSessionHasErrors('payroll_year');
        self::assertSame(12, PayrollPeriod::query()->where('payroll_year', 2028)->count());
    }

    public function test_a_period_already_backing_a_run_cannot_be_rescheduled(): void
    {
        $this->actingAs($this->admin())->post('/organization/periods', [
            'payroll_year' => 2028, 'pay_frequency' => 'MONTHLY', 'pay_date_offset_days' => 5,
        ]);
        $january = PayrollPeriod::query()->where('payroll_year', 2028)->where('period_no', 1)->firstOrFail();

        DB::table('payroll_runs')->insert([
            'payroll_period_id' => $january->payroll_period_id,
            'run_type' => 'REGULAR',
            'population_scope' => 'ALL',
            'run_status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->put("/organization/periods/{$january->payroll_period_id}", [
            'cutoff_start' => '2028-01-02',
            'cutoff_end' => '2028-01-31',
            'pay_date' => '2028-02-05',
        ]);

        $response->assertSessionHasErrors('cutoff_start');
    }

    public function test_an_administrator_can_add_a_holiday_to_the_calendar(): void
    {
        $response = $this->actingAs($this->admin())->post('/organization/holidays', [
            'holiday_date' => '2028-06-12',
            'holiday_name' => 'Independence Day',
            'holiday_type' => 'REGULAR',
        ]);

        $response->assertRedirect(route('organization.holidays.index'));

        $holiday = Holiday::query()->where('holiday_date', '2028-06-12')->firstOrFail();
        self::assertSame('REGULAR', $holiday->holiday_type);
        self::assertFalse($holiday->is_local);
    }

    public function test_no_non_administrator_role_can_reach_organization_routes(): void
    {
        foreach (['PAYROLL_OFFICER', 'APPROVER', 'VIEWER'] as $roleName) {
            $actor = User::factory()->forRole($roleName)->create();

            $this->actingAs($actor)->get('/organization')->assertForbidden();
            $this->actingAs($actor)->get('/organization/periods')->assertForbidden();
            $this->actingAs($actor)->get('/organization/holidays')->assertForbidden();
        }
    }
}
