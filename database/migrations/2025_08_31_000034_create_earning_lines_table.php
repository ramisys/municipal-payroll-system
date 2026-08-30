<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 / §5.2 — EARNING_LINE. `quantity`, `rate_applied`,
// and `multiplier_applied` are imported alongside `amount` and explain it;
// the system does not verify quantity*rate*multiplier = amount, only that
// amounts sum to the line total (§4.4 prose).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earning_lines', function (Blueprint $table) {
            $table->bigIncrements('earning_line_id');
            $table->unsignedBigInteger('payroll_line_id');
            $table->unsignedBigInteger('earning_type_id');
            $table->decimal('quantity', 7, 2)->nullable();
            $table->decimal('rate_applied', 13, 2)->nullable();
            $table->decimal('multiplier_applied', 6, 4)->nullable();
            $table->decimal('amount', 13, 2);
            $table->boolean('is_taxable');
            $table->string('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('payroll_line_id')->references('payroll_line_id')->on('payroll_lines')->restrictOnDelete();
            $table->foreign('earning_type_id')->references('earning_type_id')->on('earning_types')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE earning_lines ADD CONSTRAINT chk_earning_lines_amount CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('earning_lines');
    }
};
