<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.5 / §5.1 — STATUTORY_SCHEDULE.
//
// "No overlapping effective_from–effective_to per agency" (BR-14, AC-2.3.2)
// is one of the three headline database constraints named in data-model.md
// §10 point 2. It is cross-row and cannot be a plain CHECK constraint; it is
// enforced by the trg_statutory_schedules_no_overlap trigger created in the
// 2025_08_31_000041_create_business_rule_triggers migration, once this table
// exists.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_schedules', function (Blueprint $table) {
            $table->bigIncrements('statutory_schedule_id');
            $table->string('agency');
            $table->string('schedule_version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('pay_frequency')->nullable();
            $table->decimal('premium_rate', 6, 4)->nullable();
            $table->decimal('salary_floor', 13, 2)->nullable();
            $table->decimal('salary_ceiling', 13, 2)->nullable();
            $table->decimal('compensation_cap', 13, 2)->nullable();
            $table->string('issuance_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['agency', 'effective_from']);
        });

        DB::statement("ALTER TABLE statutory_schedules ADD CONSTRAINT chk_statutory_schedules_agency CHECK (agency IN ('SSS','PHILHEALTH','PAGIBIG','BIR'))");
        DB::statement('ALTER TABLE statutory_schedules ADD CONSTRAINT chk_statutory_schedules_effective CHECK (effective_to IS NULL OR effective_to > effective_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_schedules');
    }
};
