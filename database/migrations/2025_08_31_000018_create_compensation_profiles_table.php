<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.2 / §5.1 / §5.2 — COMPENSATION_PROFILE. A dated version
// chain, never an overwrite (BR-08).
//
// "No overlapping effective_from–effective_to per employee" (BR-08) is
// cross-row; enforced by trg_compensation_profiles_no_overlap in the
// 2025_08_31_000041_create_business_rule_triggers migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_profiles', function (Blueprint $table) {
            $table->bigIncrements('compensation_profile_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('pay_basis');
            $table->decimal('basic_rate', 13, 2);
            $table->boolean('sss_covered')->default(true);
            $table->boolean('philhealth_covered')->default(true);
            $table->boolean('pagibig_covered')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['employee_id', 'effective_from']);
            $table->foreign('employee_id')->references('employee_id')->on('employees')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE compensation_profiles ADD CONSTRAINT chk_compensation_profiles_pay_basis CHECK (pay_basis IN ('MONTHLY','DAILY','HOURLY'))");
        DB::statement('ALTER TABLE compensation_profiles ADD CONSTRAINT chk_compensation_profiles_basic_rate CHECK (basic_rate >= 0)');
        DB::statement('ALTER TABLE compensation_profiles ADD CONSTRAINT chk_compensation_profiles_effective CHECK (effective_to IS NULL OR effective_to > effective_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_profiles');
    }
};
