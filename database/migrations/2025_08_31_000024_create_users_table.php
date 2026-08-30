<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.6 / §5.1 — USER. `employee_id` is nullable: an
// Administrator may be an external IT contact who is not an employee.
// USER rows are never deleted (AC-0.2.2) so AUDIT_LOG.user_id always
// resolves to a name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->string('password_salt');
            $table->boolean('must_change_password')->default(true);
            $table->unsignedInteger('failed_attempt_count')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->dateTime('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('role_id')->references('role_id')->on('roles')->restrictOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
