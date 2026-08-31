<?php

namespace App\Services;

// One named failure from ReconciliationService::reconcile() (FR-2.9,
// BR-37, BR-38). Several of these can exist for a single register — the
// service collects every check's failures rather than stopping at the
// first, so a refused register is reported in full (AC-2.9.3-2.9.5 each
// name what they refuse; §7 beat 9 shows the report naming the row, the
// column, and the defect in one pass rather than one refusal at a time).
final readonly class ReconciliationDefect
{
    public function __construct(
        public string $type,
        public string $message,
        public ?int $row = null,
        public ?string $employeeNo = null,
    ) {}

    /**
     * @return array{type: string, message: string, row: ?int, employee_no: ?string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'row' => $this->row,
            'employee_no' => $this->employeeNo,
        ];
    }
}
