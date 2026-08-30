<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.2 / §5.1 / §5.2 — LOAN_ACCOUNT.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_accounts', function (Blueprint $table) {
            $table->bigIncrements('loan_account_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('deduction_type_id');
            $table->string('loan_reference')->unique();
            $table->decimal('principal_amount', 13, 2);
            $table->decimal('amortization_amount', 13, 2);
            $table->unsignedInteger('term_periods');
            $table->decimal('outstanding_balance', 13, 2);
            $table->unsignedBigInteger('start_period_id');
            $table->string('loan_status');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('deduction_type_id')->references('deduction_type_id')->on('deduction_types')->restrictOnDelete();
            $table->foreign('start_period_id')->references('payroll_period_id')->on('payroll_periods')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE loan_accounts ADD CONSTRAINT chk_loan_accounts_outstanding_balance CHECK (outstanding_balance >= 0)');
        DB::statement('ALTER TABLE loan_accounts ADD CONSTRAINT chk_loan_accounts_amortization_amount CHECK (amortization_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_accounts');
    }
};
