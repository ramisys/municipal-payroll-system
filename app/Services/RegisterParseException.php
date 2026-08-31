<?php

namespace App\Services;

use RuntimeException;

// The E1 "structural refusal" of system-architecture.md §6.6 / UC-18:
// a malformed register file, refused by RegisterImportService before any
// database work begins. Distinct from ReconciliationService's E2-E5
// refusals (BR-37/BR-38, W3), which apply to a structurally valid file
// whose figures don't reconcile.
class RegisterParseException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $row = null, public readonly ?string $column = null)
    {
        parent::__construct($message);
    }
}
