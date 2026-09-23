<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// data-model.md §4.6 / §5.1 / §5.2 — INTEGRITY_VERIFICATION.
// Records every verification check, including passed ones.
// Append-only, protected by trg_integrity_verifications_append_only.
class IntegrityVerification extends Model
{
    protected $primaryKey = 'integrity_verification_id';

    protected $fillable = [
        'integrity_anchor_id',
        'performed_by',
        'performed_at',
        'recomputed_hash',
        'result',
        'failure_position',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<IntegrityAnchor, $this>
     */
    public function anchor(): BelongsTo
    {
        return $this->belongsTo(IntegrityAnchor::class, 'integrity_anchor_id', 'integrity_anchor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by', 'user_id');
    }
}
