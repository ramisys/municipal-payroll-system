<?php

namespace App\Services;

use App\Models\CompensationProfile;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\ImportColumnMap;
use App\Models\PayrollImport;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

// ✧ UC-18 · Import computed payroll register — FR-2.5, FR-2.6, FR-2.8,
// FR-2.9. Week 7 Track A/B (pre-oral-demonstration-plan.md §6 Table 6):
// "intake core wired to real repositories; import versioning and
// supersession" — RegisterImportService and ReconciliationService, built
// as pure library code against fixtures in W2/W3, driven here against a
// real PayrollRun with real writes.
//
// preview() (A2) and commit() share the same parse-then-reconcile path but
// commit() alone writes, and only inside one transaction (E10 atomicity):
// a register that fails to parse or fails to reconcile leaves nothing
// written, because both failures are raised before the transaction opens.
//
// Line-writing follows IntakeFidelityHarness's proven shape exactly —
// insert (or find) a payroll_line, replace its earning_lines/
// deduction_lines wholesale, then UPDATE the line's totals in one
// statement — because trg_payroll_lines_reconcile_ins/upd
// (2025_08_31_000041_create_business_rule_triggers) enforce that ordering
// at the database level regardless of which code path writes it. A
// superseding import (A1) reuses the same payroll_line row rather than
// inserting a second one, since (payroll_run_id, employee_id) is unique
// (2025_08_31_000033_create_payroll_lines_table) — "replaces every
// payroll line" means UPDATE, not a parallel row.
//
// Out of this slice, and so absent below: UC-I4 exception evaluation (step
// 9) and BR-23 loan-balance reduction (step 8) both trace to requirements
// (FR-4.1, FR-2.4/UC-19) pre-oral-demonstration-plan.md §3.3/§4.1 place
// outside the 40% slice.
class PayrollImportService
{
    public function __construct(
        private readonly RegisterImportService $registerImportService,
        private readonly ReconciliationService $reconciliationService,
        private readonly PayrollRunService $payrollRunService,
    ) {}

    /**
     * UC-18 steps 1-5 / A2 — parses and reconciles without writing
     * anything. Reconciliation defects are returned, not thrown, so a
     * preview can show them; a structurally broken file (E1) still throws,
     * since there is no row to preview.
     *
     * @return array{rows: array, result: ?ReconciliationResult, defects: array<int, array{type: string, message: string, row: ?int, employee_no: ?string}>}
     */
    public function preview(PayrollRun $run, string $filePath, ImportColumnMap $map): array
    {
        $rows = $this->registerImportService->parseRows($filePath, $map);
        $population = $this->population($run);

        try {
            $result = $this->reconciliationService->reconcile($rows, $population);

            return ['rows' => $rows, 'result' => $result, 'defects' => []];
        } catch (ReconciliationException $e) {
            return ['rows' => $rows, 'result' => null, 'defects' => $e->defectsAsArray()];
        }
    }

    /**
     * UC-18 steps 4-10 / A1 supersession. Throws RegisterParseException
     * (E1) or ReconciliationException (E2-E5) before anything is written;
     * PayrollImportException for E9 or a matched employee with no
     * compensation profile in force (see the exception's own docblock).
     *
     * @return array{import: PayrollImport, changed: array<int, string>, unchanged: array<int, string>}
     */
    public function commit(PayrollRun $run, string $filePath, string $originalFilename, ImportColumnMap $map, ?int $actorUserId): array
    {
        if (! in_array($run->run_status, ['DRAFT', 'RETURNED'], true)) {
            throw new PayrollImportException(
                "UC-18 E9: run #{$run->payroll_run_id} is '{$run->run_status}' and cannot receive an import."
            );
        }

        $rows = $this->registerImportService->parseRows($filePath, $map);
        $population = $this->population($run);
        $result = $this->reconciliationService->reconcile($rows, $population);

        $period = $run->period()->firstOrFail();
        $cutoffEnd = $period->cutoff_end->toDateString();

        $employeeIdByNo = Employee::query()
            ->whereIn('employee_no', array_column($rows, 'employee_no'))
            ->pluck('employee_id', 'employee_no');

        $compensationProfileIdByEmployeeId = [];
        foreach ($employeeIdByNo as $employeeNo => $employeeId) {
            $compensationProfileId = CompensationProfile::query()
                ->where('employee_id', $employeeId)
                ->where('effective_from', '<=', $cutoffEnd)
                ->where(function ($q) use ($cutoffEnd) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $cutoffEnd);
                })
                ->orderByDesc('effective_from')
                ->value('compensation_profile_id');

            if ($compensationProfileId === null) {
                throw new PayrollImportException(
                    "UC-18: employee '{$employeeNo}' has no compensation profile in force on {$cutoffEnd} and cannot receive a payroll line."
                );
            }

            $compensationProfileIdByEmployeeId[$employeeId] = $compensationProfileId;
        }

        $earningTypeIdByCode = EarningType::query()->pluck('earning_type_id', 'earning_code');
        $isTaxableByCode = EarningType::query()->pluck('is_taxable', 'earning_code');
        $deductionTypeIdByCode = DeductionType::query()->pluck('deduction_type_id', 'deduction_code');

        return DB::transaction(function () use (
            $run, $map, $filePath, $originalFilename, $rows, $result, $actorUserId,
            $employeeIdByNo, $compensationProfileIdByEmployeeId,
            $earningTypeIdByCode, $isTaxableByCode, $deductionTypeIdByCode,
        ) {
            // Serializes concurrent commits into the same run: without this
            // lock, two overlapping requests could both read the same
            // MAX(version_no) and both attempt to become the current
            // version, colliding on uq_payroll_imports_run_current with a
            // raw QueryException instead of the second one simply
            // superseding the first's version as version_no+1.
            PayrollRun::query()->where('payroll_run_id', $run->payroll_run_id)->lockForUpdate()->first();

            $nextVersion = (int) (PayrollImport::query()->where('payroll_run_id', $run->payroll_run_id)->max('version_no') ?? 0) + 1;

            PayrollImport::query()
                ->where('payroll_run_id', $run->payroll_run_id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'updated_by' => $actorUserId]);

            $import = PayrollImport::create([
                'payroll_run_id' => $run->payroll_run_id,
                'import_column_map_id' => $map->import_column_map_id,
                'version_no' => $nextVersion,
                'source_filename' => $originalFilename,
                'source_sha256' => hash_file('sha256', $filePath),
                'source_file' => file_get_contents($filePath),
                'imported_by' => $actorUserId,
                'imported_at' => now(),
                'row_count' => $result->rowCount,
                'control_total_gross' => $result->controlTotalGross,
                'control_total_deductions' => $result->controlTotalDeductions,
                'control_total_net' => $result->controlTotalNet,
                'reconciliation_result' => $result->toArray(),
                'is_current' => true,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            $changed = [];
            $unchanged = [];

            foreach ($rows as $row) {
                $employeeId = $employeeIdByNo[$row['employee_no']];
                $compensationProfileId = $compensationProfileIdByEmployeeId[$employeeId];

                $line = PayrollLine::query()
                    ->where('payroll_run_id', $run->payroll_run_id)
                    ->where('employee_id', $employeeId)
                    ->first();

                $previousNetPay = $line?->net_pay;

                if ($line === null) {
                    $line = PayrollLine::create([
                        'payroll_run_id' => $run->payroll_run_id,
                        'payroll_import_id' => $import->payroll_import_id,
                        'employee_id' => $employeeId,
                        'compensation_profile_id' => $compensationProfileId,
                        'gross_pay' => '0.00',
                        'total_deductions' => '0.00',
                        'net_pay' => '0.00',
                        'created_by' => $actorUserId,
                        'updated_by' => $actorUserId,
                    ]);
                } else {
                    $line->earningLines()->delete();
                    $line->deductionLines()->delete();
                }

                foreach ($row['earnings'] as $code => $amount) {
                    $line->earningLines()->create([
                        'earning_type_id' => $earningTypeIdByCode[$code],
                        'amount' => $amount,
                        'is_taxable' => (bool) $isTaxableByCode[$code],
                        'created_by' => $actorUserId,
                        'updated_by' => $actorUserId,
                    ]);
                }

                foreach ($row['deductions'] as $code => $amount) {
                    $line->deductionLines()->create([
                        'deduction_type_id' => $deductionTypeIdByCode[$code],
                        'employee_share' => $amount,
                        'employer_share' => $row['employer_shares'][$code] ?? null,
                        'amount' => $amount,
                        'created_by' => $actorUserId,
                        'updated_by' => $actorUserId,
                    ]);
                }

                $line->payroll_import_id = $import->payroll_import_id;
                $line->compensation_profile_id = $compensationProfileId;
                $line->basic_pay = $row['earnings']['BASIC'] ?? '0.00';
                $line->gross_pay = $row['gross_pay'];
                $line->total_deductions = $row['total_deductions'];
                $line->net_pay = $row['net_pay'];
                $line->updated_by = $actorUserId;
                $line->save();

                if ($previousNetPay === null || bccomp((string) $previousNetPay, $row['net_pay'], 2) !== 0) {
                    $changed[] = $row['employee_no'];
                } else {
                    $unchanged[] = $row['employee_no'];
                }
            }

            return ['import' => $import, 'changed' => $changed, 'unchanged' => $unchanged];
        });
    }

    /**
     * @return array<int, array{employee_no: string, is_active: bool}>
     */
    private function population(PayrollRun $run): array
    {
        $period = $run->period()->firstOrFail();

        return $this->payrollRunService->populationEmployees($period, $run->population_scope)
            ->map(fn ($employee) => ['employee_no' => $employee->employee_no, 'is_active' => true])
            ->all();
    }
}
