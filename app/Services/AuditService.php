<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

// system-architecture.md §6.5 / FR-6.1 / BR-26, BR-27, BR-35.
//
// Does not open or commit its own transaction: it is called inside
// whatever transaction the caller already has open, so an audit row and
// the change it records commit together or roll back together (BR-26).
// For a single-statement action like sign-in there is no surrounding
// transaction to join — the INSERT this method issues is itself atomic.
//
// Hash chain (BR-35, decision #1/#2 of the W2 plan): one global chain over
// audit_logs, ordered by audit_log_id. entry_hash = SHA-256 over a
// canonical JSON encoding of this row's own content plus the
// prev_entry_hash it links to; prev_entry_hash = the entry_hash of the
// immediately preceding row, null only when the table is empty. Reading
// "the last row" and inserting are not wrapped in an application-level
// lock here — see AuditServiceTest for the concurrency note.
class AuditService
{
    public function record(
        User $user,
        string $entityName,
        ?int $entityId,
        string $action,
        ?array $previousValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $occurredAt = now();

        $previousValuesJson = $previousValues === null ? null : json_encode($previousValues, JSON_UNESCAPED_SLASHES);
        $newValuesJson = $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_SLASHES);

        $previousEntryHash = AuditLog::query()->orderByDesc('audit_log_id')->value('entry_hash');

        // format(), not toISOString(): the dateTime column stores no
        // sub-second precision, so the hash must be computed over the
        // same second-precision string a later read of the row will
        // reproduce (AC-6.1.5 verification round-trips through storage).
        $occurredAtForHash = $occurredAt->format('Y-m-d H:i:s');

        $entryHash = $this->computeHash(
            userId: $user->user_id,
            occurredAt: $occurredAtForHash,
            entityName: $entityName,
            entityId: $entityId,
            action: $action,
            previousValuesJson: $previousValuesJson,
            newValuesJson: $newValuesJson,
            prevEntryHash: $previousEntryHash,
        );

        return AuditLog::create([
            'user_id' => $user->user_id,
            'occurred_at' => $occurredAt,
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'action' => $action,
            'previous_values' => $previousValuesJson,
            'new_values' => $newValuesJson,
            'entry_hash' => $entryHash,
            'prev_entry_hash' => $previousEntryHash,
            'created_at' => $occurredAt,
            'created_by' => $user->user_id,
        ]);
    }

    /**
     * Recompute the hash a stored row *should* carry, from its own
     * content and its recorded predecessor link — used both when writing
     * a new row and when verifying an existing one (AC-6.1.5).
     */
    public function computeHash(
        int $userId,
        string $occurredAt,
        string $entityName,
        ?int $entityId,
        string $action,
        ?string $previousValuesJson,
        ?string $newValuesJson,
        ?string $prevEntryHash,
    ): string {
        $canonical = json_encode([
            'user_id' => $userId,
            'occurred_at' => $occurredAt,
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'action' => $action,
            'previous_values' => $previousValuesJson,
            'new_values' => $newValuesJson,
            'prev_entry_hash' => $prevEntryHash,
        ], JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical);
    }
}
