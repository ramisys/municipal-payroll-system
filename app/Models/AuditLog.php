<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// data-model.md §4.6 / §5.1 / §5.2 — AUDIT_LOG. Append-only (BR-27, enforced
// by DB grants and trg_audit_logs_no_update/no_delete): no `updated_at`, so
// $timestamps is disabled and `created_at` is set explicitly by AuditService.
// Rows are written only through AuditService::record() — never mutated
// through this model after creation.
class AuditLog extends Model
{
    protected $primaryKey = 'audit_log_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'occurred_at',
        'entity_name',
        'entity_id',
        'action',
        'previous_values',
        'new_values',
        'entry_hash',
        'prev_entry_hash',
        'created_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
