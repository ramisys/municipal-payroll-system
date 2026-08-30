<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 — RUN_TRANSITION. Append-only history of every
// state change a run goes through — the authority on what happened to it
// (§4.4 prose).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_transitions', function (Blueprint $table) {
            $table->bigIncrements('run_transition_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedBigInteger('performed_by');
            $table->dateTime('performed_at');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('performed_by')->references('user_id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_transitions');
    }
};
