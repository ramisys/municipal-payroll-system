<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.2 / §5.1 — EARNING_TYPE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earning_types', function (Blueprint $table) {
            $table->bigIncrements('earning_type_id');
            $table->string('earning_code')->unique();
            $table->string('earning_name');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_thirteenth_month_base')->default(true);
            $table->boolean('is_recurring_allowed')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earning_types');
    }
};
