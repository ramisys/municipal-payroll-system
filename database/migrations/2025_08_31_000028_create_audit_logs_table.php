<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.6 / §5.1 / §5.2 — AUDIT_LOG. Append-only: no `updated_at`
// and no `is_active`, "because nothing ever updates or deactivates an audit
// entry" (§4.6 prose) — the one declared exception to the §1.4 audit-column
// convention.
//
// "prev_entry_hash equals the entry_hash of the preceding row, null only for
// the first row" (BR-35) is a cross-row rule and cannot be a plain CHECK; it
// is enforced by AuditService when a row is written (Sprint 1a). The
// append-only guarantee itself — no UPDATE, no DELETE (BR-27) — is enforced
// by trg_audit_logs_append_only in the
// 2025_08_31_000041_create_business_rule_triggers migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('audit_log_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('occurred_at');
            $table->string('entity_name');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action');
            $table->text('previous_values')->nullable();
            $table->text('new_values')->nullable();
            $table->char('entry_hash', 64)->unique();
            $table->char('prev_entry_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT chk_audit_logs_action CHECK (action IN ('CREATE','UPDATE','DELETE','IMPORT','EXPORT','APPROVE','FINALIZE','REVERSE','LOGIN'))");
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT chk_audit_logs_entry_hash CHECK (entry_hash REGEXP '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT chk_audit_logs_prev_entry_hash CHECK (prev_entry_hash IS NULL OR prev_entry_hash REGEXP '^[0-9a-f]{64}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
