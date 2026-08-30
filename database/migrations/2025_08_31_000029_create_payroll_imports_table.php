<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 / §5.2 — PAYROLL_IMPORT (added by CR-01). Names
// the file, hash, user, and mapping behind every payroll figure (BR-39).
//
// Same MySQL filtered-unique-index technique as payroll_runs: `is_current`
// participates in a stored generated column so at most one row per
// payroll_run_id can be current, while every superseded version keeps its
// own (payroll_run_id, version_no) row (BR-39).
//
// "No UPDATE permitted except is_current; no DELETE" is enforced by
// trg_payroll_imports_restricted_update in the
// 2025_08_31_000041_create_business_rule_triggers migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_imports', function (Blueprint $table) {
            $table->bigIncrements('payroll_import_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('import_column_map_id');
            $table->unsignedInteger('version_no');
            $table->string('source_filename');
            $table->char('source_sha256', 64);
            $table->unsignedBigInteger('imported_by');
            $table->dateTime('imported_at');
            $table->unsignedInteger('row_count');
            $table->decimal('control_total_gross', 13, 2);
            $table->decimal('control_total_deductions', 13, 2);
            $table->decimal('control_total_net', 13, 2);
            $table->json('reconciliation_result')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['payroll_run_id', 'version_no'], 'payroll_imports_run_version_unique');
            $table->foreign('payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('import_column_map_id')->references('import_column_map_id')->on('import_column_maps')->restrictOnDelete();
            $table->foreign('imported_by')->references('user_id')->on('users')->restrictOnDelete();
        });

        // LONGBLOB (up to 4GB) — the retained register file itself; Laravel
        // has no first-class builder method for it.
        DB::statement('ALTER TABLE payroll_imports ADD COLUMN source_file LONGBLOB NULL AFTER source_sha256');

        DB::statement("ALTER TABLE payroll_imports ADD CONSTRAINT chk_payroll_imports_sha256 CHECK (source_sha256 REGEXP '^[0-9a-fA-F]{64}$')");

        DB::statement('ALTER TABLE payroll_imports ADD COLUMN current_flag TINYINT GENERATED ALWAYS AS (CASE WHEN is_current THEN 1 ELSE NULL END) STORED');
        DB::statement('ALTER TABLE payroll_imports ADD CONSTRAINT uq_payroll_imports_run_current UNIQUE (payroll_run_id, current_flag)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_imports');
    }
};
