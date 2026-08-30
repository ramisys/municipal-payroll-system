<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 / §5.2 — DEDUCTION_LINE. `statutory_schedule_id`
// is populated only where an employer share was derived rather than
// imported (§4.4 prose) — null is itself the record that no derivation
// took place.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_lines', function (Blueprint $table) {
            $table->bigIncrements('deduction_line_id');
            $table->unsignedBigInteger('payroll_line_id');
            $table->unsignedBigInteger('deduction_type_id');
            $table->unsignedBigInteger('statutory_schedule_id')->nullable();
            $table->decimal('employee_share', 13, 2)->nullable();
            $table->decimal('employer_share', 13, 2)->nullable();
            $table->decimal('amount', 13, 2);
            $table->string('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('payroll_line_id')->references('payroll_line_id')->on('payroll_lines')->restrictOnDelete();
            $table->foreign('deduction_type_id')->references('deduction_type_id')->on('deduction_types')->restrictOnDelete();
            $table->foreign('statutory_schedule_id')->references('statutory_schedule_id')->on('statutory_schedules')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE deduction_lines ADD CONSTRAINT chk_deduction_lines_amount CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_lines');
    }
};
