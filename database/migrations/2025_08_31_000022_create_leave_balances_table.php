<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.3 / §5.1 — LEAVE_BALANCE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->bigIncrements('leave_balance_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->unsignedSmallInteger('payroll_year');
            $table->decimal('credits_earned', 6, 2)->default(0);
            $table->decimal('credits_used', 6, 2)->default(0);
            $table->decimal('credits_carried_over', 6, 2)->default(0);
            $table->decimal('balance_remaining', 6, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['employee_id', 'leave_type_id', 'payroll_year'], 'leave_balances_emp_type_year_unique');
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('leave_type_id')->references('leave_type_id')->on('leave_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
