<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.2 / §5.1 — RECURRING_DEDUCTION.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_deductions', function (Blueprint $table) {
            $table->bigIncrements('recurring_deduction_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('deduction_type_id');
            $table->decimal('amount', 13, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['employee_id', 'deduction_type_id', 'effective_from'], 'recurring_deductions_emp_type_from_unique');
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('deduction_type_id')->references('deduction_type_id')->on('deduction_types')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE recurring_deductions ADD CONSTRAINT chk_recurring_deductions_amount CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_deductions');
    }
};
