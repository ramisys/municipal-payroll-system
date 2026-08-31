<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.1 / §5.1 — POSITION. FR-0.4 reference list.
class Position extends Model
{
    protected $primaryKey = 'position_id';

    protected $fillable = [
        'position_code',
        'position_title',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
