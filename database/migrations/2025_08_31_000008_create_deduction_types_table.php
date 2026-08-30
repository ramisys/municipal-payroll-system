<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.2 / §5.1 — DEDUCTION_TYPE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->bigIncrements('deduction_type_id');
            $table->string('deduction_code')->unique();
            $table->string('deduction_name');
            $table->boolean('is_statutory')->default(false);
            $table->string('statutory_agency')->nullable();
            $table->boolean('participates_in_floor_check')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_types');
    }
};
