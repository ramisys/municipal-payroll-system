<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.1 / §5.1 — WORK_SCHEDULE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->bigIncrements('work_schedule_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('standard_hours_per_day', 7, 2);
            $table->string('rest_days');
            $table->time('scheduled_time_in');
            $table->time('scheduled_time_out');
            $table->decimal('unpaid_break_hours', 7, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['employee_id', 'effective_from']);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
