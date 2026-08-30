<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 — starter reference data for FR-0.4,
// modeled on the Civil Service Commission leave categories used by
// Philippine LGUs. Maintained by an Administrator through UC-04 after this.
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            ['code' => 'VL', 'name' => 'Vacation Leave', 'paid' => true, 'annual_credits' => 15.00, 'negative' => false, 'excludes_rest_days' => true, 'carryover' => 'ACCUMULATE_UNLIMITED'],
            ['code' => 'SL', 'name' => 'Sick Leave', 'paid' => true, 'annual_credits' => 15.00, 'negative' => false, 'excludes_rest_days' => true, 'carryover' => 'ACCUMULATE_UNLIMITED'],
            ['code' => 'SPL', 'name' => 'Special Privilege Leave', 'paid' => true, 'annual_credits' => 3.00, 'negative' => false, 'excludes_rest_days' => true, 'carryover' => 'NO_CARRYOVER'],
            ['code' => 'ML', 'name' => 'Maternity Leave', 'paid' => true, 'annual_credits' => 0.00, 'negative' => false, 'excludes_rest_days' => false, 'carryover' => 'NOT_APPLICABLE'],
            ['code' => 'PL', 'name' => 'Paternity Leave', 'paid' => true, 'annual_credits' => 7.00, 'negative' => false, 'excludes_rest_days' => false, 'carryover' => 'NO_CARRYOVER'],
            ['code' => 'SOLO', 'name' => 'Solo Parent Leave', 'paid' => true, 'annual_credits' => 7.00, 'negative' => false, 'excludes_rest_days' => false, 'carryover' => 'NO_CARRYOVER'],
            ['code' => 'LWOP', 'name' => 'Leave Without Pay', 'paid' => false, 'annual_credits' => 0.00, 'negative' => true, 'excludes_rest_days' => false, 'carryover' => 'NOT_APPLICABLE'],
        ];

        $now = now();

        foreach ($leaveTypes as $type) {
            DB::table('leave_types')->insert([
                'leave_code' => $type['code'],
                'leave_name' => $type['name'],
                'is_paid' => $type['paid'],
                'annual_credits' => $type['annual_credits'],
                'allows_negative_balance' => $type['negative'],
                'excludes_rest_days' => $type['excludes_rest_days'],
                'carryover_rule' => $type['carryover'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
