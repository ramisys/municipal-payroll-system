<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 — starter reference data for FR-0.4,
// BR-25. Also the canonical deduction columns the import worksheet exports
// and the register import expects (FR-2.8, FR-2.11).
class DeductionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $deductionTypes = [
            ['code' => 'SSS', 'name' => 'SSS Contribution', 'statutory' => true, 'agency' => 'SSS', 'floor_check' => true],
            ['code' => 'PHILHEALTH', 'name' => 'PhilHealth Contribution', 'statutory' => true, 'agency' => 'PHILHEALTH', 'floor_check' => true],
            ['code' => 'PAGIBIG', 'name' => 'Pag-IBIG Contribution', 'statutory' => true, 'agency' => 'PAGIBIG', 'floor_check' => true],
            ['code' => 'WTAX', 'name' => 'Withholding Tax', 'statutory' => true, 'agency' => 'BIR', 'floor_check' => false],
            ['code' => 'LOAN', 'name' => 'Loan Amortization', 'statutory' => false, 'agency' => null, 'floor_check' => true],
            ['code' => 'OTHER', 'name' => 'Other Deduction', 'statutory' => false, 'agency' => null, 'floor_check' => false],
        ];

        $now = now();

        foreach ($deductionTypes as $type) {
            DB::table('deduction_types')->insert([
                'deduction_code' => $type['code'],
                'deduction_name' => $type['name'],
                'is_statutory' => $type['statutory'],
                'statutory_agency' => $type['agency'],
                'participates_in_floor_check' => $type['floor_check'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
