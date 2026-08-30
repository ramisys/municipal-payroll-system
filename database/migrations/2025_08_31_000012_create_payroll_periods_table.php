<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 / §5.2 — PAYROLL_PERIOD. No foreign keys.
//
// "No overlap and no gap within a payroll_year" (BR-34) is a cross-row rule
// over the whole year's calendar and cannot be a single-row CHECK constraint;
// it is enforced by the calendar-generation service (UC-03, Sprint 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->bigIncrements('payroll_period_id');
            $table->unsignedSmallInteger('payroll_year');
            $table->unsignedTinyInteger('period_no');
            $table->string('pay_frequency');
            $table->date('cutoff_start');
            $table->date('cutoff_end');
            $table->date('pay_date');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['payroll_year', 'period_no']);
        });

        DB::statement("ALTER TABLE payroll_periods ADD CONSTRAINT chk_payroll_periods_pay_frequency CHECK (pay_frequency IN ('SEMI_MONTHLY','MONTHLY'))");
        DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT chk_payroll_periods_cutoff CHECK (cutoff_end > cutoff_start)');
        DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT chk_payroll_periods_pay_date CHECK (pay_date >= cutoff_end)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
