<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.3 / §5.1 / §5.2 — ATTENDANCE_RECORD. Stores raw punches
// and derived figures together so a later schedule change never silently
// restates history (§4.3 prose).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->bigIncrements('attendance_record_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('attendance_type_id');
            $table->date('work_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('hours_worked', 7, 2)->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('undertime_minutes')->default(0);
            $table->decimal('overtime_hours', 7, 2)->default(0);
            $table->decimal('night_diff_hours', 7, 2)->default(0);
            $table->string('day_classification');
            $table->string('source');
            $table->string('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['employee_id', 'work_date']);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('attendance_type_id')->references('attendance_type_id')->on('attendance_types')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_day_classification CHECK (day_classification IN ('ORDINARY','REST_DAY','SPECIAL_NON_WORKING','REGULAR_HOLIDAY','REST_DAY_SPECIAL','REST_DAY_REGULAR_HOLIDAY'))");
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_source CHECK (source IN ('IMPORT','MANUAL'))");
        DB::statement('ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_time_out CHECK (time_out IS NULL OR time_out > time_in)');
        DB::statement('ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_late_minutes CHECK (late_minutes >= 0)');
        DB::statement('ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_undertime_minutes CHECK (undertime_minutes >= 0)');
        DB::statement('ALTER TABLE attendance_records ADD CONSTRAINT chk_attendance_records_hours_worked CHECK (hours_worked >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
