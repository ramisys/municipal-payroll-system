<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.2 / §5.1 / §5.2 — LOAN_AMORTIZATION. Links a loan to the
// specific payroll line that deducted it (AC-1.2.3).
//
// "amount_deducted <= prior balance_after" (BR-23, no over-deduction) is one
// of the three headline database constraints named in data-model.md §10
// point 2. It is cross-row and cannot be a plain CHECK constraint; it is
// enforced by trg_loan_amortizations_no_overdeduction in the
// 2025_08_31_000041_create_business_rule_triggers migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_amortizations', function (Blueprint $table) {
            $table->bigIncrements('loan_amortization_id');
            $table->unsignedBigInteger('loan_account_id');
            $table->unsignedBigInteger('payroll_line_id');
            $table->decimal('amount_deducted', 13, 2);
            $table->decimal('balance_after', 13, 2);
            $table->date('deducted_on');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['loan_account_id', 'payroll_line_id'], 'loan_amortizations_account_line_unique');
            $table->foreign('loan_account_id')->references('loan_account_id')->on('loan_accounts')->restrictOnDelete();
            $table->foreign('payroll_line_id')->references('payroll_line_id')->on('payroll_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_amortizations');
    }
};
