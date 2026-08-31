<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentDetail;
use App\Models\EmploymentStatus;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\EmploymentStatusSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-08 · Register employee, UC-09 · Update employee record, UC-10 ·
 * Deactivate or reactivate employee — FR-1.1, FR-1.5, AC-1.1.1-1.1.5,
 * AC-1.5.1-1.5.4. pre-oral-demonstration-plan.md §6 Table 6, W5 milestone:
 * "An employee saves, and an invalid field is refused at entry."
 */
class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(PositionSeeder::class);
        $this->seed(EmploymentStatusSeeder::class);
    }

    private function officer(): User
    {
        return User::factory()->forRole('PAYROLL_OFFICER')->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_no' => 'EMP-0001',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'birth_date' => '1990-05-15',
            'sex' => 'F',
            'civil_status' => 'SINGLE',
            'date_hired' => '2024-01-15',
            'department_id' => Department::query()->value('department_id'),
            'position_id' => Position::query()->value('position_id'),
            'employment_status_id' => EmploymentStatus::query()->value('employment_status_id'),
        ], $overrides);
    }

    public function test_an_employee_saves_with_a_complete_record(): void
    {
        $response = $this->actingAs($this->officer())->post('/employees', $this->validPayload());

        $employee = Employee::query()->where('employee_no', 'EMP-0001')->firstOrFail();
        $response->assertRedirect(route('employees.edit', $employee));

        self::assertSame('Santos', $employee->last_name);
        self::assertTrue($employee->is_active);

        $detail = EmploymentDetail::query()->where('employee_id', $employee->employee_id)->firstOrFail();
        self::assertSame('2024-01-15', $detail->date_hired->toDateString());
        self::assertNull($detail->effective_to);

        self::assertNotNull(
            AuditLog::query()->where('entity_name', 'EMPLOYEE')->where('entity_id', $employee->employee_id)->where('action', 'CREATE')->first()
        );
    }

    // AC-1.5.1 — a required field missing is refused.
    public function test_a_record_missing_a_required_field_is_refused(): void
    {
        $response = $this->actingAs($this->officer())->post('/employees', $this->validPayload(['last_name' => '']));

        $response->assertSessionHasErrors('last_name');
        self::assertSame(0, Employee::query()->count());
    }

    // AC-1.5.1, UC-I1 step 3 — date hired not in the future.
    public function test_a_date_hired_in_the_future_is_refused(): void
    {
        $response = $this->actingAs($this->officer())->post('/employees', $this->validPayload(['date_hired' => now()->addDay()->toDateString()]));

        $response->assertSessionHasErrors('date_hired');
        self::assertSame(0, Employee::query()->count());
    }

    // UC-I1 step 3 — birth_date < CURRENT_DATE cannot be a MySQL CHECK
    // (non-deterministic function), so ValidationService enforces it.
    public function test_a_birth_date_of_today_or_later_is_refused(): void
    {
        $response = $this->actingAs($this->officer())->post('/employees', $this->validPayload(['birth_date' => now()->toDateString()]));

        $response->assertSessionHasErrors('birth_date');
        self::assertSame(0, Employee::query()->count());
    }

    // AC-1.1.2, AC-1.5.2 — duplicate employee numbers refused, including
    // against an inactive employee.
    public function test_a_duplicate_employee_number_is_refused_even_against_a_deactivated_record(): void
    {
        Employee::factory()->create(['employee_no' => 'EMP-0001', 'is_active' => false]);

        $response = $this->actingAs($this->officer())->post('/employees', $this->validPayload());

        $response->assertSessionHasErrors('employee_no');
        self::assertSame(1, Employee::query()->where('employee_no', 'EMP-0001')->count());
    }

    // UC-08 E2 — a probable duplicate person (same name and date of
    // birth) is a warning, not a refusal, until acknowledged.
    public function test_a_probable_duplicate_person_is_blocked_until_acknowledged(): void
    {
        Employee::factory()->create(['last_name' => 'Santos', 'first_name' => 'Maria', 'birth_date' => '1990-05-15']);

        $blocked = $this->actingAs($this->officer())->post('/employees', $this->validPayload());
        $blocked->assertOk();
        self::assertSame(1, Employee::query()->count());

        $acknowledged = $this->actingAs($this->officer())->post('/employees', $this->validPayload(['acknowledge_duplicate' => '1']));
        $acknowledged->assertRedirect();
        self::assertSame(2, Employee::query()->count());
    }

    public function test_an_employee_can_be_updated_and_the_change_is_audited(): void
    {
        $this->actingAs($this->officer())->post('/employees', $this->validPayload());
        $employee = Employee::query()->where('employee_no', 'EMP-0001')->firstOrFail();
        $detail = EmploymentDetail::query()->where('employee_id', $employee->employee_id)->firstOrFail();

        $payload = $this->validPayload(['contact_no' => '09171234567']);

        $response = $this->actingAs($this->officer())->put("/employees/{$employee->employee_id}", $payload);
        $response->assertRedirect(route('employees.edit', $employee));

        $employee->refresh();
        self::assertSame('09171234567', $employee->contact_no);

        self::assertNotNull(
            AuditLog::query()->where('entity_name', 'EMPLOYEE')->where('entity_id', $employee->employee_id)->where('action', 'UPDATE')->first()
        );
    }

    // UC-09 A1 — a transfer closes the current EMPLOYMENT_DETAIL row and
    // opens a new one rather than overwriting it in place.
    public function test_changing_department_opens_a_new_employment_detail_row_instead_of_overwriting(): void
    {
        $this->actingAs($this->officer())->post('/employees', $this->validPayload());
        $employee = Employee::query()->where('employee_no', 'EMP-0001')->firstOrFail();
        $originalDetail = EmploymentDetail::query()->where('employee_id', $employee->employee_id)->firstOrFail();

        $newDepartmentId = Department::query()->where('department_id', '!=', $originalDetail->department_id)->value('department_id');

        $payload = $this->validPayload(['department_id' => $newDepartmentId, 'transfer_effective_from' => '2024-06-01']);

        $this->actingAs($this->officer())->put("/employees/{$employee->employee_id}", $payload);

        self::assertSame(2, EmploymentDetail::query()->where('employee_id', $employee->employee_id)->count());

        $originalDetail->refresh();
        self::assertSame('2024-06-01', $originalDetail->effective_to->toDateString());

        $currentDetail = EmploymentDetail::query()->where('employee_id', $employee->employee_id)->whereNull('effective_to')->firstOrFail();
        self::assertSame($newDepartmentId, $currentDetail->department_id);
    }

    // AC-1.1.3, AC-1.1.4 — deactivation, never deletion; the record and
    // its prior runs are unchanged.
    public function test_deactivating_an_employee_does_not_delete_the_record(): void
    {
        $this->actingAs($this->officer())->post('/employees', $this->validPayload());
        $employee = Employee::query()->where('employee_no', 'EMP-0001')->firstOrFail();

        $response = $this->actingAs($this->officer())->post("/employees/{$employee->employee_id}/deactivate", [
            'separation_date' => '2024-12-31',
            'separation_reason' => 'Resigned',
        ]);

        $response->assertRedirect(route('employees.index'));

        $employee->refresh();
        self::assertFalse($employee->is_active);
        self::assertNotNull(Employee::query()->find($employee->employee_id));

        $detail = EmploymentDetail::query()->where('employee_id', $employee->employee_id)->firstOrFail();
        self::assertSame('2024-12-31', $detail->separation_date->toDateString());
        self::assertSame('Resigned', $detail->separation_reason);
    }

    // UC-10 A1 — reactivation preserves the original record and history,
    // and opens a new employment detail row.
    public function test_reactivating_a_deactivated_employee_opens_a_new_employment_detail_row(): void
    {
        $this->actingAs($this->officer())->post('/employees', $this->validPayload());
        $employee = Employee::query()->where('employee_no', 'EMP-0001')->firstOrFail();

        $this->actingAs($this->officer())->post("/employees/{$employee->employee_id}/deactivate", [
            'separation_date' => '2024-12-31',
            'separation_reason' => 'Resigned',
        ]);

        $response = $this->actingAs($this->officer())->post("/employees/{$employee->employee_id}/reactivate", $this->validPayload(['date_hired' => '2025-03-01']));

        $response->assertRedirect(route('employees.index'));

        $employee->refresh();
        self::assertTrue($employee->is_active);
        self::assertSame(2, EmploymentDetail::query()->where('employee_id', $employee->employee_id)->count());

        $currentDetail = EmploymentDetail::query()->where('employee_id', $employee->employee_id)->whereNull('effective_to')->firstOrFail();
        self::assertSame('2025-03-01', $currentDetail->date_hired->toDateString());
    }

    public function test_every_employee_screen_renders(): void
    {
        $this->actingAs($this->officer())->post('/employees', $this->validPayload());
        $employee = Employee::query()->where('employee_no', 'EMP-0001')->firstOrFail();

        $this->actingAs($this->officer())->get('/employees')->assertOk()->assertSee($employee->employee_no);
        $this->actingAs($this->officer())->get('/employees/create')->assertOk();
        $this->actingAs($this->officer())->get("/employees/{$employee->employee_id}/edit")->assertOk()->assertSee($employee->last_name);
        $this->actingAs($this->officer())->get("/employees/{$employee->employee_id}/deactivate")->assertOk();

        $this->actingAs($this->officer())->post("/employees/{$employee->employee_id}/deactivate", [
            'separation_date' => '2024-12-31',
            'separation_reason' => 'Resigned',
        ]);
        $this->actingAs($this->officer())->get("/employees/{$employee->employee_id}/reactivate")->assertOk();
    }

    // AC-6.2.2 — refused if invoked directly by a role without the grant.
    public function test_a_viewer_cannot_reach_any_employee_management_route(): void
    {
        $actor = User::factory()->forRole('VIEWER')->create();
        $this->actingAs($this->officer())->post('/employees', $this->validPayload());
        $employee = Employee::query()->firstOrFail();

        $this->actingAs($actor)->get('/employees')->assertForbidden();
        $this->actingAs($actor)->get('/employees/create')->assertForbidden();
        $this->actingAs($actor)->post('/employees', [])->assertForbidden();
        $this->actingAs($actor)->get("/employees/{$employee->employee_id}/edit")->assertForbidden();
    }
}
