<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.3 / §5.1 / §5.2 — LEAVE_APPLICATION.
//
// "No overlapping approved range per employee" (UC-15 E2) is cross-row;
// enforced by the leave-filing service (UC-15/UC-16, Sprint 4).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->bigIncrements('leave_application_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('days_applied', 6, 2);
            $table->decimal('days_approved', 6, 2)->nullable();
            $table->string('reason')->nullable();
            $table->string('application_status');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('decision_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
            $table->foreign('leave_type_id')->references('leave_type_id')->on('leave_types')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE leave_applications ADD CONSTRAINT chk_leave_applications_status CHECK (application_status IN ('PENDING','APPROVED','RETURNED','CANCELLED'))");
        DB::statement('ALTER TABLE leave_applications ADD CONSTRAINT chk_leave_applications_dates CHECK (date_to >= date_from)');
        DB::statement('ALTER TABLE leave_applications ADD CONSTRAINT chk_leave_applications_days_approved CHECK (days_approved IS NULL OR days_approved <= days_applied)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
