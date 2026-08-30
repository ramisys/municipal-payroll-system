<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §1.4 — "created_by and updated_by reference USER" on every
// entity. Table 1 (§5.1) does not list these among each entity's own
// foreign keys, and enforcing them inline would create a circular
// dependency: USER itself carries created_by/updated_by, and several
// tables that must exist before USER (roles, employees, and every Tier-0
// reference table) also carry them. Every prior migration therefore creates
// created_by/updated_by as plain nullable columns; this migration adds the
// foreign key constraint to `users.user_id` on all of them in one pass, once
// `users` exists and every table is in place.
return new class extends Migration
{
    /** Tables carrying both created_by and updated_by. */
    private const TABLES_WITH_BOTH = [
        'organization_profiles', 'system_configs', 'departments', 'positions',
        'employment_statuses', 'employees', 'earning_types', 'deduction_types',
        'attendance_types', 'holidays', 'leave_types', 'payroll_periods',
        'import_column_maps', 'statutory_schedules', 'roles', 'employment_details',
        'work_schedules', 'compensation_profiles', 'recurring_earnings',
        'recurring_deductions', 'attendance_records', 'leave_balances',
        'statutory_brackets', 'users', 'loan_accounts', 'leave_applications',
        'payroll_runs', 'payroll_imports', 'run_transitions', 'reversal_records',
        'payslip_issuances', 'payroll_lines', 'earning_lines', 'deduction_lines',
        'loan_amortizations', 'exception_instances', 'integrity_anchors',
        'integrity_verifications',
    ];

    /** AUDIT_LOG is append-only and carries created_by only (§4.6). */
    private const TABLES_WITH_CREATED_ONLY = ['audit_logs'];

    public function up(): void
    {
        foreach (self::TABLES_WITH_BOTH as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreign('created_by', "{$table}_created_by_foreign")
                    ->references('user_id')->on('users')->restrictOnDelete();
                $blueprint->foreign('updated_by', "{$table}_updated_by_foreign")
                    ->references('user_id')->on('users')->restrictOnDelete();
            });
        }

        foreach (self::TABLES_WITH_CREATED_ONLY as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreign('created_by', "{$table}_created_by_foreign")
                    ->references('user_id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES_WITH_BOTH as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign("{$table}_created_by_foreign");
                $blueprint->dropForeign("{$table}_updated_by_foreign");
            });
        }

        foreach (self::TABLES_WITH_CREATED_ONLY as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign("{$table}_created_by_foreign");
            });
        }
    }
};
