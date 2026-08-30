<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.3 / §5.1 — ATTENDANCE_TYPE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_types', function (Blueprint $table) {
            $table->bigIncrements('attendance_type_id');
            $table->string('attendance_code')->unique();
            $table->string('attendance_name');
            $table->boolean('counts_as_worked')->default(true);
            $table->boolean('requires_punches')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_types');
    }
};
