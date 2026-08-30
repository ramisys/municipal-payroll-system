<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 — REVERSAL_RECORD. Carries the original totals
// and reason (FR-4.5, BR-24); anchored in its own right, not through its run.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversal_records', function (Blueprint $table) {
            $table->bigIncrements('reversal_record_id');
            $table->unsignedBigInteger('payroll_run_id')->unique();
            $table->decimal('original_total_gross', 13, 2);
            $table->decimal('original_total_net', 13, 2);
            $table->unsignedInteger('original_employee_count');
            $table->string('reason');
            $table->unsignedBigInteger('reversed_by');
            $table->dateTime('reversed_at');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('reversed_by')->references('user_id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_records');
    }
};
