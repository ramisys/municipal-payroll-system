<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.3 / §5.1 — LEAVE_TYPE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->bigIncrements('leave_type_id');
            $table->string('leave_code')->unique();
            $table->string('leave_name');
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_credits', 6, 2)->default(0);
            $table->boolean('allows_negative_balance')->default(false);
            $table->boolean('excludes_rest_days')->default(false);
            $table->string('carryover_rule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
