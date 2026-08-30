<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// data-model.md §5.3 — ROLE.role_name is a fixed four-value enum (FR-0.2).
// Seeded here, ahead of UC-02 (Sprint 1a), because no USER account can be
// created without a role to hold. `permissions` is a placeholder JSON stub;
// the FR-6.2 permission matrix is Sprint 1a work.
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'ADMINISTRATOR', 'description' => 'Full system access, including user, reference-data, and integrity administration.'],
            ['name' => 'PAYROLL_OFFICER', 'description' => 'Prepares employee, attendance, and payroll data; imports registers; cannot approve.'],
            ['name' => 'APPROVER', 'description' => 'Reviews and approves submitted payroll runs; cannot submit a run they approve (BR-28).'],
            ['name' => 'VIEWER', 'description' => 'Read-only access to records and reports.'],
        ];

        $now = now();

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'role_name' => $role['name'],
                'permissions' => json_encode(['description' => $role['description']]),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
