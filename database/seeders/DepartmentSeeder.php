<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 — starter reference data for FR-0.4.
// Maintained by an Administrator through UC-04 after this; not a fixed list.
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'OMAYOR', 'name' => 'Office of the Mayor'],
            ['code' => 'ACCTG', 'name' => 'Accounting Office'],
            ['code' => 'TREAS', 'name' => 'Treasury Office'],
            ['code' => 'HRMO', 'name' => 'Human Resource Management Office'],
            ['code' => 'ENGR', 'name' => 'Engineering Office'],
            ['code' => 'MPDO', 'name' => 'Municipal Planning and Development Office'],
            ['code' => 'MHO', 'name' => 'Municipal Health Office'],
        ];

        $now = now();

        foreach ($departments as $department) {
            DB::table('departments')->insert([
                'department_code' => $department['code'],
                'department_name' => $department['name'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
