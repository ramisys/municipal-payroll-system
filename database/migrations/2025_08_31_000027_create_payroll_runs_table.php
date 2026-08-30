<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 / §5.2 — PAYROLL_RUN.
//
// The unique constraint is `payroll_period_id + population_scope + run_type
// WHERE NOT CANCELLED` (§4.4 prose) so an abandoned Draft run can be
// replaced. MySQL has no partial/filtered unique index, so this is realized
// with the standard MySQL workaround: a stored generated column that is 1
// when the run counts toward uniqueness and NULL when it is CANCELLED —
// MySQL unique indexes treat every NULL as distinct, so cancelled rows never
// collide.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->bigIncrements('payroll_run_id');
            $table->unsignedBigInteger('payroll_period_id');
            $table->string('run_type');
            $table->string('population_scope');
            $table->string('run_status');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->decimal('total_gross', 13, 2)->default(0);
            $table->decimal('total_deductions', 13, 2)->default(0);
            $table->decimal('total_net', 13, 2)->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('payroll_period_id')->references('payroll_period_id')->on('payroll_periods')->restrictOnDelete();
            $table->foreign('submitted_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT chk_payroll_runs_run_type CHECK (run_type IN ('REGULAR','THIRTEENTH_MONTH','FINAL_PAY','SPECIAL'))");
        DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT chk_payroll_runs_run_status CHECK (run_status IN ('DRAFT','FOR_REVIEW','APPROVED','RETURNED','FINALIZED','CANCELLED'))");
        // BR-28 — separation of duty. One of the three headline constraints (§10 point 2).
        DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT chk_payroll_runs_separation_of_duty CHECK (run_status <> 'APPROVED' OR approved_by <> submitted_by)");

        DB::statement("ALTER TABLE payroll_runs ADD COLUMN uncancelled_flag TINYINT GENERATED ALWAYS AS (CASE WHEN run_status <> 'CANCELLED' THEN 1 ELSE NULL END) STORED");
        DB::statement('ALTER TABLE payroll_runs ADD CONSTRAINT uq_payroll_runs_period_scope_type UNIQUE (payroll_period_id, population_scope, run_type, uncancelled_flag)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
