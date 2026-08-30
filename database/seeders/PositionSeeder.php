<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 — starter reference data for FR-0.4.
// Maintained by an Administrator through UC-04 after this; not a fixed list.
class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['code' => 'AA-I', 'title' => 'Administrative Aide I'],
            ['code' => 'AA-VI', 'title' => 'Administrative Assistant II'],
            ['code' => 'LGOO-II', 'title' => 'Local Government Operations Officer II'],
            ['code' => 'HRMO-II', 'title' => 'Human Resource Management Officer II'],
            ['code' => 'DEPT-HEAD', 'title' => 'Department Head'],
            ['code' => 'MUN-ACCT', 'title' => 'Municipal Accountant'],
            ['code' => 'MUN-TREAS', 'title' => 'Municipal Treasurer'],
        ];

        $now = now();

        foreach ($positions as $position) {
            DB::table('positions')->insert([
                'position_code' => $position['code'],
                'position_title' => $position['title'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
