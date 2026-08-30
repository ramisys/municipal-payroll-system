<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.1 / §5.1 — ORGANIZATION_PROFILE. No foreign keys.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_profiles', function (Blueprint $table) {
            $table->bigIncrements('organization_id');
            $table->string('registered_name');
            $table->string('address')->nullable();
            $table->string('sss_employer_no')->nullable();
            $table->string('philhealth_employer_no')->nullable();
            $table->string('pagibig_employer_no')->nullable();
            $table->string('bir_tin')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });

        // MEDIUMBLOB (up to 16MB) — large enough for a logo image, unlike
        // Blueprint::binary()'s plain BLOB (64KB cap). Laravel has no
        // first-class builder method for it.
        DB::statement('ALTER TABLE organization_profiles ADD COLUMN logo MEDIUMBLOB NULL AFTER bir_tin');
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_profiles');
    }
};
