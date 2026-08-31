<?php

namespace App\Services;

use RuntimeException;

// The E2-E5 refusals of system-architecture.md §6.6 / UC-18: a
// structurally valid register (RegisterImportService already accepted it)
// whose figures don't reconcile or whose population isn't complete
// (BR-37, BR-38). Distinct from RegisterParseException's E1, which is
// refused before a register is even a set of rows.
//
// Carries every defect found, not just the first — AC-2.8.3's "a report
// naming every failure" applies here with the same force it applies to
// RegisterImportService's structural checks.
class ReconciliationException extends RuntimeException
{
    /**
     * @param  array<int, ReconciliationDefect>  $defects
     */
    public function __construct(public readonly array $defects)
    {
        $count = count($defects);

        parent::__construct("Register refused: {$count} reconciliation ".($count === 1 ? 'defect' : 'defects').' found.');
    }

    /**
     * @return array<int, array{type: string, message: string, row: ?int, employee_no: ?string}>
     */
    public function defectsAsArray(): array
    {
        return array_map(fn (ReconciliationDefect $defect): array => $defect->toArray(), $this->defects);
    }
}
