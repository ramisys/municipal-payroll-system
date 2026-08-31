<?php

namespace App\Services;

use RuntimeException;

// UC-17 E1/E4 refusals — a period/population/run-type collision (E1) or a
// cancel attempted past Draft (E4). $existingRunId is set only for E1, so a
// controller can offer "open the existing run" per the use case's own text.
class PayrollRunException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $existingRunId = null)
    {
        parent::__construct($message);
    }
}
