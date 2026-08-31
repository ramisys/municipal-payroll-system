<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-04 · Maintain reference data — FR-0.4, AC-0.4.1-0.4.3. Departments
 * stand in for the four "plain" lists (positions, employment statuses
 * share the same shape); earning types cover the "no silent default"
 * flag requirement separately, since it is what AC-0.4.3 is about.
 */
class ReferenceDataManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    public function test_an_administrator_can_create_a_department(): void
    {
        $response = $this->actingAs($this->admin())->post('/reference-data/departments', [
            'department_code' => 'IT',
            'department_name' => 'Information Technology Office',
        ]);

        $response->assertRedirect(route('reference-data.index', 'departments'));

        $department = Department::query()->where('department_code', 'IT')->firstOrFail();
        self::assertTrue($department->is_active);

        self::assertNotNull(
            AuditLog::query()->where('entity_name', 'DEPARTMENT')->where('entity_id', $department->department_id)->where('action', 'CREATE')->first()
        );
    }

    public function test_creating_a_department_with_a_duplicate_code_is_refused(): void
    {
        Department::query()->create(['department_code' => 'HRMO', 'department_name' => 'Existing', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post('/reference-data/departments', [
            'department_code' => 'HRMO',
            'department_name' => 'Duplicate',
        ]);

        $response->assertSessionHasErrors('department_code');
    }

    public function test_the_edit_form_renders_for_an_existing_department(): void
    {
        $department = Department::query()->create(['department_code' => 'TREAS', 'department_name' => 'Treasury Office', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->get("/reference-data/departments/{$department->department_id}/edit")
            ->assertOk()
            ->assertSee('Treasury Office');
    }

    public function test_deactivating_a_department_does_not_delete_it(): void
    {
        $department = Department::query()->create(['department_code' => 'ENGR', 'department_name' => 'Engineering', 'is_active' => true]);

        $this->actingAs($this->admin())->post("/reference-data/departments/{$department->department_id}/deactivate")
            ->assertRedirect(route('reference-data.index', 'departments'));

        $department->refresh();
        self::assertFalse($department->is_active);
        self::assertNotNull(Department::query()->find($department->department_id));
    }

    // AC-0.4.3 — "no earning type defaults silently": omitting the
    // taxability flag entirely must refuse the save, not fall back to a
    // column default.
    public function test_creating_an_earning_type_without_an_explicit_taxable_flag_is_refused(): void
    {
        $response = $this->actingAs($this->admin())->post('/reference-data/earning-types', [
            'earning_code' => 'MEAL',
            'earning_name' => 'Meal Allowance',
            'is_thirteenth_month_base' => '0',
            // is_taxable intentionally omitted.
        ]);

        $response->assertSessionHasErrors('is_taxable');
        self::assertNull(EarningType::query()->where('earning_code', 'MEAL')->first());
    }

    public function test_creating_an_earning_type_with_explicit_flags_succeeds(): void
    {
        $response = $this->actingAs($this->admin())->post('/reference-data/earning-types', [
            'earning_code' => 'MEAL',
            'earning_name' => 'Meal Allowance',
            'is_taxable' => '0',
            'is_thirteenth_month_base' => '0',
        ]);

        $response->assertRedirect(route('reference-data.index', 'earning-types'));

        $earningType = EarningType::query()->where('earning_code', 'MEAL')->firstOrFail();
        self::assertFalse($earningType->is_taxable);
        self::assertFalse($earningType->is_thirteenth_month_base);
    }

    /**
     * AC-6.2.2-equivalent — this screen has no FR-6.2 matrix row of its
     * own (FR-0.4's Actor line is Administrator alone), but it is still
     * gated the same way every other Administrator-only screen is.
     */
    public function test_no_non_administrator_role_can_reach_reference_data_routes(): void
    {
        foreach (['PAYROLL_OFFICER', 'APPROVER', 'VIEWER'] as $roleName) {
            $actor = User::factory()->forRole($roleName)->create();

            $this->actingAs($actor)->get('/reference-data/departments')->assertForbidden();
            $this->actingAs($actor)->post('/reference-data/departments', [])->assertForbidden();
        }
    }
}
