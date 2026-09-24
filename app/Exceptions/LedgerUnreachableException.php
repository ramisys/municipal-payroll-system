<?php

namespace App\Exceptions;

use RuntimeException;

// system-architecture.md §7.3 / UC-31 E3 / AC-6.3.5.
// Thrown when the external permissioned Hyperledger Besu node cannot be reached
// due to network partition, daemon restart, or connection timeout.
//
// Crucial: This exception represents an absence of connectivity, NEVER a tampering
// failure or mismatch. It is caught gracefully across payroll finalization and verification.
class LedgerUnreachableException extends RuntimeException
{
    public function __construct(string $message = 'External permissioned ledger is unreachable.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
