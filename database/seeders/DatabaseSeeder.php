<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// implementation-plan.md §3 item 0.3 — reference data seeded so the demo
// state reproduces from seeders alone. UserSeeder runs last: it needs
// RoleSeeder's rows to assign a role_id.
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            PositionSeeder::class,
            EmploymentStatusSeeder::class,
            EarningTypeSeeder::class,
            DeductionTypeSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            SystemConfigSeeder::class,
            RoleSeeder::class,
            ImportColumnMapSeeder::class,
            UserSeeder::class,
        ]);
    }
}
