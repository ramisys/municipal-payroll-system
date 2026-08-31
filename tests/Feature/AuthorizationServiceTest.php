<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthorizationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-6.2 permission matrix, spot-checked per role rather than exhaustively —
 * enough to prove AuthorizationService reads roles.permissions (seeded by
 * RoleSeeder) rather than hardcoding a decision per call.
 */
class AuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->forRole($roleName)->create();
    }

    public function test_payroll_officer_can_create_a_payroll_run_but_administrator_cannot(): void
    {
        $service = app(AuthorizationService::class);

        $officer = $this->userWithRole('PAYROLL_OFFICER');
        $admin = $this->userWithRole('ADMINISTRATOR');

        self::assertTrue($service->can($officer, 'payroll_run.create_import'));
        // AC-6.2.4 — an Administrator cannot create a payroll run, import
        // a register into one, or approve one.
        self::assertFalse($service->can($admin, 'payroll_run.create_import'));
    }

    public function test_only_administrator_can_manage_users(): void
    {
        $service = app(AuthorizationService::class);

        foreach (['PAYROLL_OFFICER', 'APPROVER', 'VIEWER'] as $role) {
            self::assertFalse($service->can($this->userWithRole($role), 'users.manage'));
        }

        self::assertTrue($service->can($this->userWithRole('ADMINISTRATOR'), 'users.manage'));
    }

    public function test_viewer_cannot_maintain_employee_records(): void
    {
        // AC-6.2.3 / AC-6.2.5 — a Viewer cannot alter any record, and its
        // read access is limited to payroll outputs, not inputs like the
        // employee master file.
        $service = app(AuthorizationService::class);

        self::assertFalse($service->can($this->userWithRole('VIEWER'), 'employees.manage'));
    }

    public function test_payroll_officer_cannot_view_the_audit_log(): void
    {
        // The FR-6.2 matrix row "View audit log" grants Approver,
        // Administrator, and Viewer, but not Payroll Officer.
        $service = app(AuthorizationService::class);

        self::assertFalse($service->can($this->userWithRole('PAYROLL_OFFICER'), 'audit_log.view'));
        self::assertTrue($service->can($this->userWithRole('APPROVER'), 'audit_log.view'));
    }

    public function test_authorize_throws_on_refusal_and_is_silent_on_grant(): void
    {
        $service = app(AuthorizationService::class);
        $officer = $this->userWithRole('PAYROLL_OFFICER');

        $service->authorize($officer, 'payroll_run.create_import');
        self::assertTrue(true); // reached without throwing

        $this->expectException(AuthorizationException::class);
        $service->authorize($officer, 'users.manage');
    }

    public function test_a_locked_or_inactive_user_is_refused_every_permission(): void
    {
        $service = app(AuthorizationService::class);

        $locked = $this->userWithRole('ADMINISTRATOR');
        $locked->is_locked = true;
        $locked->save();

        self::assertFalse($service->can($locked, 'users.manage'));

        $inactive = $this->userWithRole('ADMINISTRATOR');
        $inactive->is_active = false;
        $inactive->save();

        self::assertFalse($service->can($inactive, 'users.manage'));
    }
}
