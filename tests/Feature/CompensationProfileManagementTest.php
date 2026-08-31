<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\RecurringEarning;
use App\Models\User;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-11 · Maintain compensation profile — FR-1.2, BR-08.
 * pre-oral-demonstration-plan.md §6 Table 6, W6 milestone P-B references
 * "complete compensation profiles"; this proves the screen that produces
 * them: a profile records, a later dated change closes the current row
 * rather than overwriting it, and an out-of-order effective date is
 * refused (UC-11 E1).
 */
class CompensationProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
    }

    private function officer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    public function test_a_payroll_officer_can_record_a_compensation_profile(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->officer())->post("/employees/{$employee->employee_id}/compensation", [
            'pay_basis' => 'MONTHLY',
            'basic_rate' => '25000.00',
            'sss_covered' => '1',
            'philhealth_covered' => '1',
            'pagibig_covered' => '1',
            'effective_from' => '2026-01-01',
        ]);

        $response->assertRedirect(route('employees.compensation.index', $employee));

        $profile = CompensationProfile::query()->where('employee_id', $employee->employee_id)->sole();
        self::assertSame('MONTHLY', $profile->pay_basis);
        self::assertSame('25000.00', $profile->basic_rate);
        self::assertNull($profile->effective_to);

        self::assertNotNull(AuditLog::query()->where('entity_name', 'COMPENSATION_PROFILE')->first());
    }

    // BR-08 — a later dated change closes the current row rather than
    // overwriting it; a past period keeps the rate that was in force.
    public function test_a_later_dated_change_closes_the_current_profile_and_opens_a_new_one(): void
    {
        $employee = Employee::factory()->create();
        $officer = $this->officer();

        $this->actingAs($officer)->post("/employees/{$employee->employee_id}/compensation", [
            'pay_basis' => 'MONTHLY', 'basic_rate' => '25000.00', 'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($officer)->post("/employees/{$employee->employee_id}/compensation", [
            'pay_basis' => 'MONTHLY', 'basic_rate' => '27000.00', 'effective_from' => '2026-06-01',
        ]);

        self::assertSame(2, CompensationProfile::query()->where('employee_id', $employee->employee_id)->count());

        $closed = CompensationProfile::query()->where('employee_id', $employee->employee_id)->where('basic_rate', '25000.00')->sole();
        self::assertSame('2026-05-31', $closed->effective_to->toDateString());

        $current = CompensationProfile::query()->where('employee_id', $employee->employee_id)->whereNull('effective_to')->sole();
        self::assertSame('27000.00', $current->basic_rate);
    }

    // UC-11 E1.
    public function test_an_effective_date_not_after_the_current_entry_is_refused(): void
    {
        $employee = Employee::factory()->create();
        $officer = $this->officer();

        $this->actingAs($officer)->post("/employees/{$employee->employee_id}/compensation", [
            'pay_basis' => 'MONTHLY', 'basic_rate' => '25000.00', 'effective_from' => '2026-06-01',
        ]);

        $response = $this->actingAs($officer)->post("/employees/{$employee->employee_id}/compensation", [
            'pay_basis' => 'MONTHLY', 'basic_rate' => '27000.00', 'effective_from' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('effective_from');
        self::assertSame(1, CompensationProfile::query()->where('employee_id', $employee->employee_id)->count());
    }

    public function test_a_recurring_earning_can_be_added_and_ended(): void
    {
        $employee = Employee::factory()->create();
        $officer = $this->officer();
        $earningType = EarningType::query()->where('is_active', true)->first();

        $this->actingAs($officer)->post("/employees/{$employee->employee_id}/compensation/recurring-earnings", [
            'earning_type_id' => $earningType->earning_type_id,
            'amount' => '500.00',
            'effective_from' => '2026-01-01',
        ])->assertRedirect(route('employees.compensation.index', $employee));

        $earning = RecurringEarning::query()->where('employee_id', $employee->employee_id)->sole();
        self::assertNull($earning->effective_to);

        $this->actingAs($officer)->post("/employees/{$employee->employee_id}/compensation/recurring-earnings/{$earning->recurring_earning_id}/end", [
            'effective_to' => '2026-06-01',
        ])->assertRedirect(route('employees.compensation.index', $employee));

        $earning->refresh();
        self::assertSame('2026-06-01', $earning->effective_to->toDateString());
    }

    // AC-6.2.2/AC-6.2.4 — 'employees.manage' gates this screen the same
    // way it gates EmployeeController.
    public function test_a_role_without_the_grant_is_refused(): void
    {
        $employee = Employee::factory()->create();
        $viewer = User::factory()->forRole('VIEWER')->create();

        $this->actingAs($viewer)->get("/employees/{$employee->employee_id}/compensation")->assertForbidden();
    }
}
