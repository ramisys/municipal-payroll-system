<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.1 / §5.1 — EMPLOYEE. Identity only; everything that
// changes over an employment lives in EMPLOYMENT_DETAIL (§4.1 prose).
//
// §5.2 requires `birth_date < CURRENT_DATE`. MySQL 8 rejects non-deterministic
// functions (CURRENT_DATE, NOW()) inside CHECK constraints (error 3814), so
// this one rule cannot be a database CHECK. It is enforced by ValidationService
// at point of entry instead (FR-1.5, UC-I1) — every other constraint below is
// a real database CHECK.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('employee_id');
            $table->string('employee_no')->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->date('birth_date');
            $table->char('sex', 1);
            $table->string('civil_status');
            $table->string('contact_no')->nullable();
            $table->string('address')->nullable();
            $table->string('sss_no')->nullable();
            $table->string('philhealth_no')->nullable();
            $table->string('pagibig_mid')->nullable();
            $table->string('tin')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
