<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.5 / §5.1 / §5.2 — STATUTORY_BRACKET.
//
// "Contiguous range_from–range_to, no gap or overlap within a schedule"
// (UC-05 E2) is cross-row; enforced by the statutory-schedule maintenance
// screen (UC-05, Sprint 8) rather than a database CHECK.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_brackets', function (Blueprint $table) {
            $table->bigIncrements('statutory_bracket_id');
            $table->unsignedBigInteger('statutory_schedule_id');
            $table->unsignedInteger('bracket_sequence');
            $table->decimal('range_from', 13, 2);
            $table->decimal('range_to', 13, 2)->nullable();
            $table->decimal('employee_share', 13, 2)->nullable();
            $table->decimal('employer_share', 13, 2)->nullable();
            $table->decimal('base_tax', 13, 2)->nullable();
            $table->decimal('marginal_rate', 6, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['statutory_schedule_id', 'bracket_sequence'], 'statutory_brackets_schedule_seq_unique');
            $table->foreign('statutory_schedule_id')->references('statutory_schedule_id')->on('statutory_schedules')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_brackets');
    }
};
