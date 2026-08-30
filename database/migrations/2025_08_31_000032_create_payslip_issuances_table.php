<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 — PAYSLIP_ISSUANCE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_issuances', function (Blueprint $table) {
            $table->bigIncrements('payslip_issuance_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('issuance_type');
            $table->unsignedBigInteger('issued_by');
            $table->dateTime('issued_at');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('issued_by')->references('user_id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE payslip_issuances ADD CONSTRAINT chk_payslip_issuances_type CHECK (issuance_type IN ('ORIGINAL','REPRINT'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_issuances');
    }
};
