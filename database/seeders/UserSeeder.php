<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

// pre-oral-demonstration-plan.md §6 W2 — one demo account per role so UC-01
// (sign-in) and AuthorizationService are exercisable before UC-02 (Sprint
// 1a/W3) exists to create accounts through the application itself.
// `employee_id` is left null for all four (data-model.md §4.6: "an
// Administrator may be an external IT contact who is not an employee" —
// extended here to every seeded demo account, since no EMPLOYEE rows exist
// yet either; UC-08 is Sprint 3/W5).
//
// Every seeded account carries `must_change_password = true` (FR-0.2
// behavior 2 / AC-0.2.4) so the UC-01 A1 first-sign-in flow is exercised
// by the demo accounts themselves rather than only in a test double.
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['username' => 'admin', 'role' => 'ADMINISTRATOR'],
            ['username' => 'payroll.officer', 'role' => 'PAYROLL_OFFICER'],
            ['username' => 'approver', 'role' => 'APPROVER'],
            ['username' => 'viewer', 'role' => 'VIEWER'],
        ];

        foreach ($accounts as $account) {
            $roleId = Role::query()->where('role_name', $account['role'])->value('role_id');

            $user = new User([
                'role_id' => $roleId,
                'employee_id' => null,
                'username' => $account['username'],
                'must_change_password' => true,
                'failed_attempt_count' => 0,
                'is_locked' => false,
                'is_active' => true,
            ]);

            // Demo-only initial password. Changed at first sign-in
            // (must_change_password = true, FR-0.2 behavior 2).
            $user->setPassword('ChangeMe!123');
            $user->save();
        }
    }
}
