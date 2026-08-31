<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.6 / §5.1 — ROLE. `permissions` holds the FR-6.2 matrix
// column for this role, as a JSON array of permission keys (see
// RoleSeeder for the full key list and its mapping back to the matrix).
class Role extends Model
{
    protected $primaryKey = 'role_id';

    protected $fillable = [
        'role_name',
        'permissions',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissions ?? [], true);
    }
}
