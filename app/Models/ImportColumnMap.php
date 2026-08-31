<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.4 / §5.1 — IMPORT_COLUMN_MAP (AD-17, BR-41). Binds the
// canonical register fields RegisterImportService reads to the column
// header strings of a particular register layout.
class ImportColumnMap extends Model
{
    protected $primaryKey = 'import_column_map_id';

    protected $fillable = [
        'map_name',
        'version_no',
        'column_bindings',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'column_bindings' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function active(string $mapName): self
    {
        return static::query()
            ->where('map_name', $mapName)
            ->where('is_active', true)
            ->orderByDesc('version_no')
            ->firstOrFail();
    }
}
