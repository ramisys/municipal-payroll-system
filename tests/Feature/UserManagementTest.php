<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-02 · Manage user accounts — FR-0.2, AC-0.2.1-0.2.4. Also the negative
 * side of the FR-6.2 matrix row "Maintain users and roles": only an
 * Administrator may reach any of these routes (AC-6.2.2 — refused if
 * invoked directly, not merely hidden).
 */
class UserManagementTest extends TestCase
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

    public function test_an_administrator_can_create_a_user_account(): void
    {
        $roleId = Role::query()->where('role_name', 'VIEWER')->value('role_id');

        $response = $this->actingAs($this->admin())->post('/users', [
            'username' => 'new.viewer',
            'role_id' => $roleId,
            'password' => 'InitialPass1',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::query()->where('username', 'new.viewer')->firstOrFail();
        self::assertSame($roleId, $user->role_id);
        self::assertTrue($user->must_change_password);
        self::assertTrue($user->verifyPassword('InitialPass1'));

        self::assertNotNull(
            AuditLog::query()->where('entity_name', 'USER')->where('entity_id', $user->user_id)->where('action', 'CREATE')->first()
        );
    }

    public function test_creating_a_user_with_a_duplicate_username_is_refused(): void
    {
        $existing = User::factory()->create(['username' => 'taken']);
        $roleId = Role::query()->where('role_name', 'VIEWER')->value('role_id');

        $response = $this->actingAs($this->admin())->post('/users', [
            'username' => 'taken',
            'role_id' => $roleId,
            'password' => 'InitialPass1',
        ]);

        $response->assertSessionHasErrors('username');
        self::assertSame(1, User::query()->where('username', 'taken')->count());
    }

    public function test_a_duplicate_username_is_refused_even_against_a_deactivated_account(): void
    {
        // AC-0.2.1 — unique across all accounts, active and inactive.
        User::factory()->create(['username' => 'former.employee', 'is_active' => false]);
        $roleId = Role::query()->where('role_name', 'VIEWER')->value('role_id');

        $response = $this->actingAs($this->admin())->post('/users', [
            'username' => 'former.employee',
            'role_id' => $roleId,
            'password' => 'InitialPass1',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_deactivating_a_user_does_not_delete_the_account(): void
    {
        $target = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->post("/users/{$target->user_id}/deactivate")
            ->assertRedirect(route('users.index'));

        $target->refresh();
        self::assertFalse($target->is_active);
        self::assertNotNull(User::query()->find($target->user_id)); // AC-0.2.2 — never deleted
    }

    public function test_reactivating_a_deactivated_user_restores_access(): void
    {
        $target = User::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())->post("/users/{$target->user_id}/reactivate");

        self::assertTrue($target->fresh()->is_active);
    }

    public function test_unlocking_a_locked_account_clears_the_lock_and_the_failed_attempt_count(): void
    {
        $target = User::factory()->locked()->create(['failed_attempt_count' => 5]);

        $this->actingAs($this->admin())->post("/users/{$target->user_id}/unlock");

        $target->refresh();
        self::assertFalse($target->is_locked);
        self::assertSame(0, $target->failed_attempt_count);
    }

    public function test_resetting_a_password_forces_a_change_at_next_sign_in_and_is_audited(): void
    {
        $target = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($this->admin())->post("/users/{$target->user_id}/reset-password", [
            'password' => 'BrandNewOne1',
        ]);

        $target->refresh();
        self::assertTrue($target->verifyPassword('BrandNewOne1'));
        self::assertTrue($target->must_change_password);

        self::assertNotNull(
            AuditLog::query()->where('entity_name', 'USER')->where('entity_id', $target->user_id)->where('action', 'UPDATE')->first()
        );
    }

    /**
     * AC-6.2.2 — refused if invoked directly, for every non-Administrator
     * role, across the whole surface this controller exposes.
     */
    public function test_no_non_administrator_role_can_reach_any_user_management_route(): void
    {
        $target = User::factory()->create();

        foreach (['PAYROLL_OFFICER', 'APPROVER', 'VIEWER'] as $roleName) {
            $actor = User::factory()->forRole($roleName)->create();

            $this->actingAs($actor)->get('/users')->assertForbidden();
            $this->actingAs($actor)->get('/users/create')->assertForbidden();
            $this->actingAs($actor)->post('/users', [])->assertForbidden();
            $this->actingAs($actor)->get("/users/{$target->user_id}/edit")->assertForbidden();
            $this->actingAs($actor)->post("/users/{$target->user_id}/deactivate")->assertForbidden();
        }
    }
}
