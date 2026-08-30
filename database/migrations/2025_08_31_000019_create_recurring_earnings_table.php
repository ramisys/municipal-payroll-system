<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.2 / §5.1 — RECURRING_EARNING.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_earnings', function (Blueprint $table) {
            $table->bigIncrements('recurring_earning_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('earning_type_id');
            $table->decimal('amount', 13, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['employee_id', 'earning_type_id', 'effective_from'], 'recurring_earnings_emp_type_from_unique');
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('earning_type_id')->references('earning_type_id')->on('earning_types')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE recurring_earnings ADD CONSTRAINT chk_recurring_earnings_amount CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_earnings');
    }
};
