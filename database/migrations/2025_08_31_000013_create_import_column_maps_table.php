<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// data-model.md §4.4 / §5.1 — IMPORT_COLUMN_MAP (added by CR-01). Absorbs a
// renamed or reordered register column as configuration, not code (AD-17).
//
// "No overlapping effective_from–effective_to per map_name" (BR-41) is a
// cross-row rule; enforced by the mapping-editor service (UC-04, Sprint 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_column_maps', function (Blueprint $table) {
            $table->bigIncrements('import_column_map_id');
            $table->string('map_name');
            $table->unsignedInteger('version_no');
            $table->json('column_bindings');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique(['map_name', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_column_maps');
    }
};
