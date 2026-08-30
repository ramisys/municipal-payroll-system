<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 / §5.2 — PAYROLL_LINE. Every figure on this row
// is imported, not computed (CR-01); `payroll_import_id` names the file it
// came from and `compensation_profile_id` names the dated rate version in
// force (§4.4 prose).
//
// The three BR-37 reconciliation constraints named in gate item 0.2:
//   1. net_pay = gross_pay - total_deductions       — a real CHECK below.
//   2. gross_pay = SUM(EARNING_LINE.amount)          — trigger, see the
//   3. total_deductions = SUM(DEDUCTION_LINE.amount)   2025_08_31_000041
//      migration. Both fire on UPDATE of this table: a line is inserted
//      with zero totals (before any child line can exist, since children
//      FK to this row), then the importer sets the final totals in one
//      UPDATE once every EARNING_LINE/DEDUCTION_LINE child is written —
//      exactly the point these two triggers check.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->bigIncrements('payroll_line_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('payroll_import_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('compensation_profile_id');
            $table->decimal('days_worked', 7, 2)->nullable();
            $table->decimal('hours_worked', 7, 2)->nullable();
            $table->decimal('basic_pay', 13, 2)->default(0);
            $table->decimal('gross_pay', 13, 2)->default(0);
            $table->decimal('taxable_compensation', 13, 2)->nullable();
            $table->decimal('total_deductions', 13, 2)->default(0);
            $table->decimal('net_pay', 13, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->foreign('payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('payroll_import_id')->references('payroll_import_id')->on('payroll_imports')->restrictOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('compensation_profile_id')->references('compensation_profile_id')->on('compensation_profiles')->restrictOnDelete();
        });

        // AC-2.5.2, AC-2.5.5, BR-37 — reconciliation enforced by the database,
        // not only by the importer. One of the three headline constraints
        // (§10 point 2).
        DB::statement('ALTER TABLE payroll_lines ADD CONSTRAINT chk_payroll_lines_net_pay CHECK (net_pay = gross_pay - total_deductions)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
    }
};
