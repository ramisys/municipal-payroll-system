<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 — starter reference data for FR-0.4.
class EmploymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'REGULAR', 'payroll_eligible' => true],
            ['name' => 'CASUAL', 'payroll_eligible' => true],
            ['name' => 'JOB ORDER', 'payroll_eligible' => true],
            ['name' => 'CONSULTANT', 'payroll_eligible' => false],
        ];

        $now = now();

        foreach ($statuses as $status) {
            DB::table('employment_statuses')->insert([
                'status_name' => $status['name'],
                'is_payroll_eligible' => $status['payroll_eligible'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
