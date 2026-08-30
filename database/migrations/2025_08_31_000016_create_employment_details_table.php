<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.1 / §5.1 — EMPLOYMENT_DETAIL. Dated version rows so a
// transfer creates a record rather than overwriting one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_details', function (Blueprint $table) {
            $table->bigIncrements('employment_detail_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('employment_status_id');
            $table->date('date_hired');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->date('separation_date')->nullable();
            $table->string('separation_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['employee_id', 'effective_from']);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('department_id')->references('department_id')->on('departments')->restrictOnDelete();
            $table->foreign('position_id')->references('position_id')->on('positions')->restrictOnDelete();
            $table->foreign('employment_status_id')->references('employment_status_id')->on('employment_statuses')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE employment_details ADD CONSTRAINT chk_employment_details_separation CHECK (separation_date IS NULL OR separation_date >= date_hired)');
        DB::statement('ALTER TABLE employment_details ADD CONSTRAINT chk_employment_details_effective CHECK (effective_to IS NULL OR effective_to > effective_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_details');
    }
};
