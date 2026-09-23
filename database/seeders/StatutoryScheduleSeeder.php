<?php

namespace Database\Seeders;

use App\Models\StatutoryBracket;
use App\Models\StatutorySchedule;
use Illuminate\Database\Seeder;

// FR-2.3 / UC-05 / BR-14, BR-20.
// Seeds baseline statutory schedules and brackets for SSS, PhilHealth, Pag-IBIG, and BIR.
class StatutoryScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. SSS Contribution Schedule
        $sss = StatutorySchedule::updateOrCreate(
            ['agency' => 'SSS', 'schedule_version' => 'SSS-2024'],
            [
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'pay_frequency' => 'MONTHLY',
                'salary_floor' => 4000.00,
                'salary_ceiling' => 30000.00,
                'issuance_reference' => 'SSS Circular No. 2023-009',
                'is_active' => true,
            ]
        );

        // Representative contiguous SSS brackets
        $sssBrackets = [
            ['bracket_sequence' => 1, 'range_from' => 0.00, 'range_to' => 4249.99, 'employee_share' => 180.00, 'employer_share' => 390.00],
            ['bracket_sequence' => 2, 'range_from' => 4250.00, 'range_to' => 9999.99, 'employee_share' => 360.00, 'employer_share' => 770.00],
            ['bracket_sequence' => 3, 'range_from' => 10000.00, 'range_to' => 14999.99, 'employee_share' => 540.00, 'employer_share' => 1150.00],
            ['bracket_sequence' => 4, 'range_from' => 15000.00, 'range_to' => 19999.99, 'employee_share' => 787.50, 'employer_share' => 1672.50],
            ['bracket_sequence' => 5, 'range_from' => 20000.00, 'range_to' => 24999.99, 'employee_share' => 1012.50, 'employer_share' => 2147.50],
            ['bracket_sequence' => 6, 'range_from' => 25000.00, 'range_to' => 29999.99, 'employee_share' => 1237.50, 'employer_share' => 2622.50],
            ['bracket_sequence' => 7, 'range_from' => 30000.00, 'range_to' => null, 'employee_share' => 1350.00, 'employer_share' => 2860.00],
        ];

        foreach ($sssBrackets as $b) {
            StatutoryBracket::updateOrCreate(
                ['statutory_schedule_id' => $sss->statutory_schedule_id, 'bracket_sequence' => $b['bracket_sequence']],
                $b
            );
        }

        // 2. PhilHealth Schedule (5% premium, 50-50 share)
        StatutorySchedule::updateOrCreate(
            ['agency' => 'PHILHEALTH', 'schedule_version' => 'PHIC-2024'],
            [
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'pay_frequency' => 'MONTHLY',
                'premium_rate' => 0.0500,
                'salary_floor' => 10000.00,
                'salary_ceiling' => 100000.00,
                'issuance_reference' => 'PhilHealth Circular No. 2024-0001',
                'is_active' => true,
            ]
        );

        // 3. Pag-IBIG Schedule (HDMF 2% EE / 2% ER with compensation cap)
        $pagibig = StatutorySchedule::updateOrCreate(
            ['agency' => 'PAGIBIG', 'schedule_version' => 'HDMF-2024'],
            [
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'pay_frequency' => 'MONTHLY',
                'compensation_cap' => 10000.00,
                'issuance_reference' => 'Pag-IBIG Fund Circular No. 460',
                'is_active' => true,
            ]
        );

        $pagibigBrackets = [
            ['bracket_sequence' => 1, 'range_from' => 0.00, 'range_to' => 1500.00, 'employee_share' => 15.00, 'employer_share' => 30.00],
            ['bracket_sequence' => 2, 'range_from' => 1500.01, 'range_to' => null, 'employee_share' => 200.00, 'employer_share' => 200.00],
        ];

        foreach ($pagibigBrackets as $b) {
            StatutoryBracket::updateOrCreate(
                ['statutory_schedule_id' => $pagibig->statutory_schedule_id, 'bracket_sequence' => $b['bracket_sequence']],
                $b
            );
        }

        // 4. BIR Withholding Tax Brackets (TRAIN Law Monthly)
        $bir = StatutorySchedule::updateOrCreate(
            ['agency' => 'BIR', 'schedule_version' => 'BIR-TRAIN-2023'],
            [
                'effective_from' => '2023-01-01',
                'effective_to' => null,
                'pay_frequency' => 'MONTHLY',
                'issuance_reference' => 'Revenue Memorandum Circular No. 24-2023',
                'is_active' => true,
            ]
        );

        $birBrackets = [
            ['bracket_sequence' => 1, 'range_from' => 0.00, 'range_to' => 20833.33, 'base_tax' => 0.00, 'marginal_rate' => 0.0000],
            ['bracket_sequence' => 2, 'range_from' => 20833.34, 'range_to' => 33333.33, 'base_tax' => 0.00, 'marginal_rate' => 0.1500],
            ['bracket_sequence' => 3, 'range_from' => 33333.34, 'range_to' => 66666.67, 'base_tax' => 1875.00, 'marginal_rate' => 0.2000],
            ['bracket_sequence' => 4, 'range_from' => 66666.68, 'range_to' => 166666.67, 'base_tax' => 8541.67, 'marginal_rate' => 0.2500],
            ['bracket_sequence' => 5, 'range_from' => 166666.68, 'range_to' => 666666.67, 'base_tax' => 33541.67, 'marginal_rate' => 0.3000],
            ['bracket_sequence' => 6, 'range_from' => 666666.68, 'range_to' => null, 'base_tax' => 183541.67, 'marginal_rate' => 0.3500],
        ];

        foreach ($birBrackets as $b) {
            StatutoryBracket::updateOrCreate(
                ['statutory_schedule_id' => $bir->statutory_schedule_id, 'bracket_sequence' => $b['bracket_sequence']],
                $b
            );
        }
    }
}
