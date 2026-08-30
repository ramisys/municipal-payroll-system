<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 and pre-oral-demonstration-plan.md §5 —
// "one IMPORT_COLUMN_MAP row against the canonical template ... so the
// import path has something to read from before OI-12 is answered."
//
// FR-2.8 behavior 1 defines the canonical fields: employee number, each
// earning type, each deduction type, gross pay, total deductions, net pay,
// and the optional employer-share columns of FR-2.3. Until the accounting
// office's actual register layout is known (OI-12), this map binds every
// canonical field to a column header of the same name in the published
// canonical template — an identity mapping. It changes no table and no
// constraint (data-model.md §9): a real client layout is a second
// IMPORT_COLUMN_MAP version, not a schema change (AD-17, BR-41).
//
// database/fixtures/register-clean.xlsx (2025_08_31_000005 test fixture) is
// written against these exact header strings.
class ImportColumnMapSeeder extends Seeder
{
    public function run(): void
    {
        $bindings = [
            'employee_no' => 'Employee No.',
            'earnings' => [
                'BASIC' => 'Basic Pay',
                'OT' => 'Overtime Pay',
                'NIGHT_DIFF' => 'Night Shift Differential',
                'HOLIDAY_PAY' => 'Holiday Pay',
                'ALLOWANCE' => 'Representation and Transportation Allowance',
                'THIRTEENTH_MONTH' => '13th Month Pay',
            ],
            'deductions' => [
                'SSS' => 'SSS Contribution',
                'PHILHEALTH' => 'PhilHealth Contribution',
                'PAGIBIG' => 'Pag-IBIG Contribution',
                'WTAX' => 'Withholding Tax',
                'LOAN' => 'Loan Amortization',
                'OTHER' => 'Other Deduction',
            ],
            'employer_shares' => [
                'SSS' => 'SSS ER Share',
                'PHILHEALTH' => 'PhilHealth ER Share',
                'PAGIBIG' => 'Pag-IBIG ER Share',
            ],
            'gross_pay' => 'Gross Pay',
            'total_deductions' => 'Total Deductions',
            'net_pay' => 'Net Pay',
        ];

        DB::table('import_column_maps')->insert([
            'map_name' => 'CANONICAL',
            'version_no' => 1,
            'column_bindings' => json_encode($bindings),
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
