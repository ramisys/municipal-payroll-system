<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.1 / §5.4 — SYSTEM_CONFIG. Key-value store so an
// agency-specific threshold (BR-31's lockout limit, BR-32's session
// timeout, ...) is data maintenance rather than a code change (C-01).
class SystemConfig extends Model
{
    protected $primaryKey = 'config_id';

    protected $fillable = [
        'config_key',
        'config_value',
        'data_type',
        'description',
        'created_by',
        'updated_by',
    ];

    /**
     * Read a config value, cast per its stored data_type. Returns $default
     * if the key is missing rather than throwing — callers decide whether
     * a missing key is fatal.
     */
    public static function value(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('config_key', $key)->first();

        if (! $row) {
            return $default;
        }

        return match ($row->data_type) {
            'int' => (int) $row->config_value,
            'decimal' => $row->config_value,
            default => $row->config_value,
        };
    }
}
