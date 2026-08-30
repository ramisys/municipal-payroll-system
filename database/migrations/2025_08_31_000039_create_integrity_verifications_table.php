<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.6 / §5.1 / §5.2 — INTEGRITY_VERIFICATION. Records every
// check, including the ones that passed (§4.6 prose); additive, out of the
// pre-oral slice (implementation-plan.md Sprint 9).
//
// "No UPDATE or DELETE permitted" is enforced by
// trg_integrity_verifications_append_only in the
// 2025_08_31_000041_create_business_rule_triggers migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrity_verifications', function (Blueprint $table) {
            $table->bigIncrements('integrity_verification_id');
            $table->unsignedBigInteger('integrity_anchor_id');
            $table->unsignedBigInteger('performed_by');
            $table->dateTime('performed_at');
            $table->char('recomputed_hash', 64);
            $table->string('result');
            $table->string('failure_position')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('integrity_anchor_id')->references('integrity_anchor_id')->on('integrity_anchors')->restrictOnDelete();
            $table->foreign('performed_by')->references('user_id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE integrity_verifications ADD CONSTRAINT chk_integrity_verifications_result CHECK (result IN ('MATCH','MISMATCH','UNVERIFIABLE'))");
        DB::statement("ALTER TABLE integrity_verifications ADD CONSTRAINT chk_integrity_verifications_hash CHECK (recomputed_hash REGEXP '^[0-9a-f]{64}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_verifications');
    }
};
