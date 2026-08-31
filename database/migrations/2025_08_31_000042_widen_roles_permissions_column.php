<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.6 / §5.1 — ROLE.permissions. Week 4 added three keys
// to the ADMINISTRATOR role (organization.manage, reference_data.manage,
// import_column_map.manage — see RoleSeeder), and the encoded JSON array
// now exceeds the original varchar(255) (2025_08_31_000015). The column
// was never meant to be length-capped — Role::casts() already treats it
// as 'array' — so this widens it to json rather than picking a new
// arbitrary varchar length that the next added permission would just
// overflow again.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('permissions')->change();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('permissions')->change();
        });
    }
};
