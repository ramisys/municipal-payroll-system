<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// implementation-plan.md §3 item 0.3 — starter reference data for FR-0.4.
//
// Fixed-date national holidays for calendar year 2026 under RA 9849 / EO 292.
// The movable dates (Maundy Thursday, Good Friday, Black Saturday, Chinese
// New Year) are computed here, not looked up from an official source —
// verify them against the annual Presidential proclamation before relying
// on them for a real payroll run. Locally-declared holidays (`is_local`)
// are not seeded: they are specific to the client's municipality and
// unknown until OI-12/OI-13-style client input is available.
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['date' => '2026-01-01', 'name' => "New Year's Day", 'type' => 'REGULAR'],
            ['date' => '2026-02-17', 'name' => 'Chinese New Year', 'type' => 'SPECIAL_NON_WORKING'],
            ['date' => '2026-04-02', 'name' => 'Maundy Thursday', 'type' => 'REGULAR'],
            ['date' => '2026-04-03', 'name' => 'Good Friday', 'type' => 'REGULAR'],
            ['date' => '2026-04-04', 'name' => 'Black Saturday', 'type' => 'SPECIAL_NON_WORKING'],
            ['date' => '2026-04-09', 'name' => 'Araw ng Kagitingan', 'type' => 'REGULAR'],
            ['date' => '2026-05-01', 'name' => 'Labor Day', 'type' => 'REGULAR'],
            ['date' => '2026-06-12', 'name' => 'Independence Day', 'type' => 'REGULAR'],
            ['date' => '2026-08-21', 'name' => 'Ninoy Aquino Day', 'type' => 'SPECIAL_NON_WORKING'],
            ['date' => '2026-08-31', 'name' => 'National Heroes Day', 'type' => 'REGULAR'],
            ['date' => '2026-11-01', 'name' => "All Saints' Day", 'type' => 'SPECIAL_NON_WORKING'],
            ['date' => '2026-11-02', 'name' => "All Souls' Day", 'type' => 'SPECIAL_NON_WORKING'],
            ['date' => '2026-11-30', 'name' => 'Bonifacio Day', 'type' => 'REGULAR'],
            ['date' => '2026-12-08', 'name' => 'Feast of the Immaculate Conception', 'type' => 'SPECIAL_NON_WORKING'],
            ['date' => '2026-12-24', 'name' => 'Christmas Eve', 'type' => 'SPECIAL_NON_WORKING'],
            ['date' => '2026-12-25', 'name' => 'Christmas Day', 'type' => 'REGULAR'],
            ['date' => '2026-12-30', 'name' => 'Rizal Day', 'type' => 'REGULAR'],
            ['date' => '2026-12-31', 'name' => 'Last Day of the Year', 'type' => 'SPECIAL_NON_WORKING'],
        ];

        $now = now();

        foreach ($holidays as $holiday) {
            DB::table('holidays')->insert([
                'holiday_date' => $holiday['date'],
                'holiday_name' => $holiday['name'],
                'holiday_type' => $holiday['type'],
                'is_local' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
