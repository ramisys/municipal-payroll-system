<?php

namespace App\Services;

// One disagreement IntakeFidelityHarness found between a source register
// value and the value read back from the database, in either direction
// (NFR-2.12). An empty array of these from a harness comparison is the
// pass condition; every non-empty case is fidelity evidence naming
// exactly what diverged.
final readonly class FidelityMismatch
{
    public function __construct(
        public string $employeeNo,
        public string $field,
        public string $expected,
        public string $actual,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_no' => $this->employeeNo,
            'field' => $this->field,
            'expected' => $this->expected,
            'actual' => $this->actual,
        ];
    }
}
