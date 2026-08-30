<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 — starter reference data for FR-0.4,
// BR-12. Also the canonical earning columns the import worksheet exports
// and the register import expects (FR-2.8, FR-2.11).
class EarningTypeSeeder extends Seeder
{
    public function run(): void
    {
        $earningTypes = [
            ['code' => 'BASIC', 'name' => 'Basic Pay', 'taxable' => true, 'thirteenth_month_base' => true, 'recurring_allowed' => false],
            ['code' => 'OT', 'name' => 'Overtime Pay', 'taxable' => true, 'thirteenth_month_base' => false, 'recurring_allowed' => false],
            ['code' => 'NIGHT_DIFF', 'name' => 'Night Shift Differential', 'taxable' => true, 'thirteenth_month_base' => false, 'recurring_allowed' => false],
            ['code' => 'HOLIDAY_PAY', 'name' => 'Holiday Pay', 'taxable' => true, 'thirteenth_month_base' => false, 'recurring_allowed' => false],
            ['code' => 'ALLOWANCE', 'name' => 'Representation and Transportation Allowance', 'taxable' => false, 'thirteenth_month_base' => false, 'recurring_allowed' => true],
            ['code' => 'THIRTEENTH_MONTH', 'name' => '13th Month Pay', 'taxable' => false, 'thirteenth_month_base' => false, 'recurring_allowed' => false],
        ];

        $now = now();

        foreach ($earningTypes as $type) {
            DB::table('earning_types')->insert([
                'earning_code' => $type['code'],
                'earning_name' => $type['name'],
                'is_taxable' => $type['taxable'],
                'is_thirteenth_month_base' => $type['thirteenth_month_base'],
                'is_recurring_allowed' => $type['recurring_allowed'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
