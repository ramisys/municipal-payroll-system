<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.6 / §5.1 — ROLE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('role_id');
            $table->string('role_name')->unique();
            $table->string('permissions');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });

        DB::statement("ALTER TABLE roles ADD CONSTRAINT chk_roles_role_name CHECK (role_name IN ('ADMINISTRATOR','PAYROLL_OFFICER','APPROVER','VIEWER'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
