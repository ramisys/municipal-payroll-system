<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 / §5.2 — EXCEPTION_INSTANCE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exception_instances', function (Blueprint $table) {
            $table->bigIncrements('exception_instance_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('payroll_line_id')->nullable();
            $table->string('rule_code');
            $table->string('severity');
            $table->string('triggering_values')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->string('acknowledgment_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('payroll_line_id')->references('payroll_line_id')->on('payroll_lines')->restrictOnDelete();
            $table->foreign('acknowledged_by')->references('user_id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE exception_instances ADD CONSTRAINT chk_exception_instances_severity CHECK (severity IN ('BLOCKING','WARNING'))");
        DB::statement("ALTER TABLE exception_instances ADD CONSTRAINT chk_exception_instances_rule_code CHECK (rule_code IN ('EX-01','EX-02','EX-03','EX-04','EX-05','EX-06','EX-07','EX-08','EX-10','EX-11','EX-12','EX-13','EX-14'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('exception_instances');
    }
};
