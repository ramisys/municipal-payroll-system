<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.3 / §5.1 — HOLIDAY. Relates to ATTENDANCE_RECORD by date,
// not by foreign key (§3 prose) — no column here does that join.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->bigIncrements('holiday_id');
            $table->date('holiday_date')->unique();
            $table->string('holiday_name');
            $table->string('holiday_type');
            $table->boolean('is_local')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
