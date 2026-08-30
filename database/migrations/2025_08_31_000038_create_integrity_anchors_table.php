<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.6 / §5.1 / §5.2 — INTEGRITY_ANCHOR. The outbox and the
// receipt in one row (§4.6 prose); additive, out of the pre-oral slice
// (implementation-plan.md Sprint 9).
//
// "No UPDATE permitted except anchor_status, ledger_tx_ref, ledger_block_ref,
// confirmed_at, retry_count; no DELETE" (BR-36) is enforced by
// trg_integrity_anchors_restricted_update in the
// 2025_08_31_000041_create_business_rule_triggers migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrity_anchors', function (Blueprint $table) {
            $table->bigIncrements('integrity_anchor_id');
            $table->string('scope_type');
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->unsignedBigInteger('reversal_record_id')->nullable();
            $table->unsignedBigInteger('audit_log_from')->nullable();
            $table->unsignedBigInteger('audit_log_to')->nullable();
            $table->char('payload_hash', 64)->unique();
            $table->string('hash_algorithm');
            $table->unsignedBigInteger('chain_position');
            $table->string('ledger_tx_ref')->nullable();
            $table->string('ledger_block_ref')->nullable();
            $table->string('anchor_status');
            $table->dateTime('queued_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['scope_type', 'payroll_run_id'], 'integrity_anchors_scope_run_unique');
            $table->unique(['scope_type', 'reversal_record_id'], 'integrity_anchors_scope_reversal_unique');
            $table->foreign('payroll_run_id')->references('payroll_run_id')->on('payroll_runs')->restrictOnDelete();
            $table->foreign('reversal_record_id')->references('reversal_record_id')->on('reversal_records')->restrictOnDelete();
            $table->foreign('audit_log_from')->references('audit_log_id')->on('audit_logs')->restrictOnDelete();
            $table->foreign('audit_log_to')->references('audit_log_id')->on('audit_logs')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE integrity_anchors ADD CONSTRAINT chk_integrity_anchors_scope_type CHECK (scope_type IN ('RUN','REVERSAL','AUDIT_SEGMENT'))");
        DB::statement("ALTER TABLE integrity_anchors ADD CONSTRAINT chk_integrity_anchors_status CHECK (anchor_status IN ('PENDING','CONFIRMED','FAILED'))");
        DB::statement("ALTER TABLE integrity_anchors ADD CONSTRAINT chk_integrity_anchors_hash_algorithm CHECK (hash_algorithm = 'SHA-256')");
        DB::statement("ALTER TABLE integrity_anchors ADD CONSTRAINT chk_integrity_anchors_payload_hash CHECK (payload_hash REGEXP '^[0-9a-f]{64}$')");
        // Scope integrity, NFR-6.4 — exactly one scope reference populated per scope_type.
        DB::statement("ALTER TABLE integrity_anchors ADD CONSTRAINT chk_integrity_anchors_scope_integrity CHECK (
            (scope_type = 'RUN' AND payroll_run_id IS NOT NULL AND reversal_record_id IS NULL AND audit_log_from IS NULL AND audit_log_to IS NULL)
            OR (scope_type = 'REVERSAL' AND reversal_record_id IS NOT NULL AND payroll_run_id IS NULL AND audit_log_from IS NULL AND audit_log_to IS NULL)
            OR (scope_type = 'AUDIT_SEGMENT' AND audit_log_from IS NOT NULL AND audit_log_to IS NOT NULL AND payroll_run_id IS NULL AND reversal_record_id IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_anchors');
    }
};
