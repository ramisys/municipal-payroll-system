<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// data-model.md §5.4 — the ten required SYSTEM_CONFIG keys, so that changing
// any of them is data maintenance rather than a code change (C-01).
//
// Several values below are placeholders pending a client policy answer
// (net pay floor, retention period) rather than an engineering decision —
// each is flagged. They are safe defaults, not assertions of client policy.
class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['key' => 'STANDARD_HOURS_PER_DAY', 'value' => '8.00', 'type' => 'decimal', 'description' => 'BR-03 — hours worked per ordinary day, used to derive the input worksheet\'s hours-worked column.'],
            ['key' => 'NET_PAY_FLOOR', 'value' => '0.00', 'type' => 'decimal', 'description' => 'BR-25, EX-03 — minimum permissible net pay. Placeholder: 0.00 (floor check effectively disabled) pending client policy.'],
            ['key' => 'GROSS_VARIANCE_THRESHOLD_PCT', 'value' => '10.00', 'type' => 'decimal', 'description' => 'EX-07 — percentage swing in gross pay period-over-period that raises a warning exception.'],
            ['key' => 'OVERTIME_HOURS_THRESHOLD', 'value' => '40.00', 'type' => 'decimal', 'description' => 'EX-08 — overtime hours in a period that raise a warning exception.'],
            ['key' => 'FAILED_LOGIN_LIMIT', 'value' => '5', 'type' => 'int', 'description' => 'BR-31 — failed sign-in attempts before an account is locked.'],
            ['key' => 'SESSION_TIMEOUT_MINUTES', 'value' => '30', 'type' => 'int', 'description' => 'BR-32 — idle minutes before a session expires.'],
            ['key' => 'RECORD_RETENTION_YEARS', 'value' => '10', 'type' => 'int', 'description' => 'DR-2.1 — placeholder pending OI-10; no archival deletion is implemented regardless of this value.'],
            ['key' => 'AUDIT_SEGMENT_INTERVAL_HOURS', 'value' => '24', 'type' => 'int', 'description' => 'FR-6.3 — how often an audit segment is closed and anchored.'],
            ['key' => 'ANCHOR_RETRY_LIMIT', 'value' => '5', 'type' => 'int', 'description' => 'FR-6.3 — retries before a pending anchor is reported as stalled.'],
            ['key' => 'ACTIVE_IMPORT_COLUMN_MAP', 'value' => 'CANONICAL', 'type' => 'varchar', 'description' => 'FR-2.8, BR-41 — map_name of the IMPORT_COLUMN_MAP version applied by default at import (see ImportColumnMapSeeder).'],
        ];

        $now = now();

        foreach ($configs as $config) {
            DB::table('system_configs')->insert([
                'config_key' => $config['key'],
                'config_value' => $config['value'],
                'data_type' => $config['type'],
                'description' => $config['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
