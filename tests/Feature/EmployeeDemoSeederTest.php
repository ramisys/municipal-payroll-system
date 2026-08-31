<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * pre-oral-demonstration-plan.md §6 Table 6, W6 milestone P-B: "30
 * employees exist with complete compensation profiles — the NFR-2.12
 * population." Also demonstration script beat 3 (§7): "Employee list —
 * 30 employees."
 */
class EmployeeDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_demo_state_has_thirty_employees_each_with_a_complete_compensation_profile(): void
    {
        $this->seed(DatabaseSeeder::class);

        self::assertSame(30, Employee::query()->where('is_active', true)->count());

        foreach (Employee::query()->get() as $employee) {
            $profile = CompensationProfile::query()->where('employee_id', $employee->employee_id)->whereNull('effective_to')->first();
            self::assertNotNull($profile, "employee {$employee->employee_no} has no open compensation profile");
            self::assertContains($profile->pay_basis, ['MONTHLY', 'DAILY', 'HOURLY']);
            self::assertGreaterThan(0, (float) $profile->basic_rate);

            self::assertNotNull(
                WorkSchedule::query()->where('employee_id', $employee->employee_id)->whereNull('effective_to')->first(),
                "employee {$employee->employee_no} has no open work schedule"
            );

            self::assertNotNull($employee->currentEmploymentDetail, "employee {$employee->employee_no} has no open employment detail");
        }
    }
}
