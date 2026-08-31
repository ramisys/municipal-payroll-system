<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.1 / §5.1 — DEPARTMENT. FR-0.4 reference list.
class Department extends Model
{
    protected $primaryKey = 'department_id';

    protected $fillable = [
        'department_code',
        'department_name',
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
