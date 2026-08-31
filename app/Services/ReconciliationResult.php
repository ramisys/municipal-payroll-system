<?php

namespace App\Services;

// AC-2.9.6, AC-2.9.7 — the outcome of a register that passed every
// ReconciliationService check, in the shape PAYROLL_IMPORT.reconciliation_result
// stores (data-model.md §4.4) so a past import's result can be redisplayed
// unchanged rather than recomputed.
final readonly class ReconciliationResult
{
    public function __construct(
        public int $rowCount,
        public string $controlTotalGross,
        public string $controlTotalDeductions,
        public string $controlTotalNet,
        public int $matchedEmployeeCount,
    ) {}

    /**
     * @return array{row_count: int, control_total_gross: string, control_total_deductions: string, control_total_net: string, matched_employee_count: int, accepted: true}
     */
    public function toArray(): array
    {
        return [
            'row_count' => $this->rowCount,
            'control_total_gross' => $this->controlTotalGross,
            'control_total_deductions' => $this->controlTotalDeductions,
            'control_total_net' => $this->controlTotalNet,
            'matched_employee_count' => $this->matchedEmployeeCount,
            'accepted' => true,
        ];
    }
}
