<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// implementation-plan.md §3 item 0.3 — reference data seeded so the demo
// state reproduces from seeders alone. No USER account is seeded here:
// authentication (UC-01) and account administration (UC-02) are Sprint 1a
// work, and app/Models/User.php still carries the Laravel skeleton's default
// shape until that sprint replaces it with the data-model.md §4.6 schema.
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
        ]);
    }
}
