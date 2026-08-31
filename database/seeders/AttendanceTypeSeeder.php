<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// data-model.md §5.1 — ATTENDANCE_TYPE starter reference data for FR-1.3.
// AttendanceImportService (UC-13) requires 'ORDINARY' to exist before an
// import can run; 'OFFICIAL_BUSINESS' covers UC-14 A1 (a day worked
// without punches).
class AttendanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'ORDINARY', 'name' => 'Ordinary attendance', 'counts_as_worked' => true, 'requires_punches' => true],
            ['code' => 'OFFICIAL_BUSINESS', 'name' => 'Official business / field work', 'counts_as_worked' => true, 'requires_punches' => false],
        ];

        $now = now();

        foreach ($types as $type) {
            DB::table('attendance_types')->insert([
                'attendance_code' => $type['code'],
                'attendance_name' => $type['name'],
                'counts_as_worked' => $type['counts_as_worked'],
                'requires_punches' => $type['requires_punches'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
