<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\IntegrityAnchor;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\ReversalRecord;
use App\Models\RunTransition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

// UC-17 · Create payroll run — FR-2.6, FR-4.4 (A2 cancel), BR-34.
// UC-23 · Submit payroll run — FR-4.4, AC-4.1.2, AC-2.6.4.
// UC-24 · Approve or return payroll run — FR-4.4, BR-28, AC-4.4.2, AC-4.4.6.
// UC-25 · Finalize payroll run — FR-4.4, FR-4.5, AC-4.5.5, AC-6.3.1.
// UC-26 · Reverse finalized payroll run — FR-4.5, BR-24, AC-4.5.2, AC-4.5.3.
class PayrollRunService
{
    private const RUN_TYPES = ['REGULAR', 'THIRTEENTH_MONTH', 'FINAL_PAY', 'SPECIAL'];

    public function __construct(
        private readonly ?IntegrityService $integrityService = null,
    ) {}

    /**
     * @return array{run: PayrollRun, includedCount: int, excluded: array<int, string>}
     */
    public function createRun(PayrollPeriod $period, string $runType, string $populationScope, ?int $actorUserId): array
    {
        if (! in_array($runType, self::RUN_TYPES, true)) {
            throw new PayrollRunException("Unknown run type '{$runType}'.");
        }

        // UC-17 E1/E2 — check collisions against non-cancelled runs.
        $existing = PayrollRun::query()
            ->where('payroll_period_id', $period->payroll_period_id)
            ->where('population_scope', $populationScope)
            ->where('run_type', $runType)
            ->where('run_status', '<>', 'CANCELLED')
            ->first();

        if ($existing !== null) {
            if ($existing->run_status === 'FINALIZED') {
                throw new PayrollRunException(
                    "AC-2.6.5 / UC-17 E2: run #{$existing->payroll_run_id} for this period, population, and run type is already FINALIZED. A reversal (UC-26) or retroactive adjustment (UC-19) is required.",
                    $existing->payroll_run_id,
                );
            }

            throw new PayrollRunException(
                "UC-17 E1: an open run (#{$existing->payroll_run_id}, {$existing->run_status}) already exists for this period, population, and run type.",
                $existing->payroll_run_id,
            );
        }

        $included = $this->populationEmployees($period, $populationScope);
        $excluded = Employee::query()
            ->where('is_active', true)
            ->whereNotIn('employee_id', $included->pluck('employee_id'))
            ->orderBy('employee_no')
            ->pluck('employee_no')
            ->all();

        $run = DB::transaction(function () use ($period, $runType, $populationScope, $included, $actorUserId) {
            $run = PayrollRun::create([
                'payroll_period_id' => $period->payroll_period_id,
                'run_type' => $runType,
                'population_scope' => $populationScope,
                'run_status' => 'DRAFT',
                'employee_count' => $included->count(),
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => null,
                'to_status' => 'DRAFT',
                'performed_by' => $actorUserId,
                'performed_at' => now(),
                'reason' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            return $run;
        });

        return ['run' => $run, 'includedCount' => $included->count(), 'excluded' => $excluded];
    }

    /**
     * UC-23 · Submit payroll run for review.
     * Preconditions: Run in DRAFT or RETURNED, has accepted import, no unresolved blocking exceptions, all warnings acknowledged.
     */
    public function submitRun(PayrollRun $run, int $actorUserId): PayrollRun
    {
        if (! in_array($run->run_status, ['DRAFT', 'RETURNED'], true)) {
            throw new PayrollRunException(
                "UC-23: run #{$run->payroll_run_id} is '{$run->run_status}' and cannot be submitted for review."
            );
        }

        $currentImport = $run->currentImport();
        if ($currentImport === null) {
            throw new PayrollRunException(
                "UC-23 E2 / AC-2.6.4: run #{$run->payroll_run_id} holds no accepted import and cannot be submitted."
            );
        }

        $hasBlockingExceptions = $run->exceptions()->where('severity', 'BLOCKING')->exists();
        if ($hasBlockingExceptions) {
            throw new PayrollRunException(
                "UC-23 E1 / AC-4.1.2: run #{$run->payroll_run_id} has unresolved blocking exceptions and cannot be submitted."
            );
        }

        $hasUnacknowledgedWarnings = $run->exceptions()
            ->where('severity', 'WARNING')
            ->whereNull('acknowledged_at')
            ->exists();
        if ($hasUnacknowledgedWarnings) {
            throw new PayrollRunException(
                'UC-23 / FR-4.1: every warning exception must be acknowledged before submission.'
            );
        }

        return DB::transaction(function () use ($run, $actorUserId) {
            $fromStatus = $run->run_status;
            $now = now();

            $run->run_status = 'FOR_REVIEW';
            $run->submitted_by = $actorUserId;
            $run->submitted_at = $now;
            $run->updated_by = $actorUserId;
            $run->save();

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => $fromStatus,
                'to_status' => 'FOR_REVIEW',
                'performed_by' => $actorUserId,
                'performed_at' => $now,
                'reason' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            return $run;
        });
    }

    /**
     * UC-24 · Approve payroll run.
     * Preconditions: Run in FOR_REVIEW; reviewer is not the submitter (BR-28).
     */
    public function approveRun(PayrollRun $run, int $actorUserId): PayrollRun
    {
        if ($run->run_status !== 'FOR_REVIEW') {
            throw new PayrollRunException(
                "UC-24: run #{$run->payroll_run_id} is '{$run->run_status}', not 'FOR_REVIEW', and cannot be approved."
            );
        }

        if ((int) $run->submitted_by === (int) $actorUserId) {
            throw new PayrollRunException(
                'BR-28 / AC-4.4.2 / UC-24 E1: the user who submitted a payroll run for review may not approve it.'
            );
        }

        return DB::transaction(function () use ($run, $actorUserId) {
            $now = now();

            $run->run_status = 'APPROVED';
            $run->approved_by = $actorUserId;
            $run->approved_at = $now;
            $run->updated_by = $actorUserId;
            $run->save();

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => 'FOR_REVIEW',
                'to_status' => 'APPROVED',
                'performed_by' => $actorUserId,
                'performed_at' => $now,
                'reason' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            return $run;
        });
    }

    /**
     * UC-24 A1/A2 · Return payroll run.
     * Preconditions: Run in FOR_REVIEW or APPROVED; reason required.
     * If from APPROVED, clears approved_by and approved_at (AC-4.4.6).
     */
    public function returnRun(PayrollRun $run, string $reason, int $actorUserId): PayrollRun
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new PayrollRunException(
                'UC-24 E2 / AC-4.4.3: a reason is required to return a payroll run.'
            );
        }

        if (! in_array($run->run_status, ['FOR_REVIEW', 'APPROVED'], true)) {
            throw new PayrollRunException(
                "UC-24: run #{$run->payroll_run_id} is '{$run->run_status}' and cannot be returned."
            );
        }

        return DB::transaction(function () use ($run, $cleanReason, $actorUserId) {
            $fromStatus = $run->run_status;
            $now = now();

            $run->run_status = 'RETURNED';
            if ($fromStatus === 'APPROVED') {
                $run->approved_by = null;
                $run->approved_at = null;
            }
            $run->updated_by = $actorUserId;
            $run->save();

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => $fromStatus,
                'to_status' => 'RETURNED',
                'performed_by' => $actorUserId,
                'performed_at' => $now,
                'reason' => $cleanReason,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            return $run;
        });
    }

    /**
     * UC-25 · Finalize payroll run.
     * Preconditions: Run in APPROVED state.
     * Effects: Stored totals fixed, status FINALIZED, immutable lines, queued integrity anchor (AC-4.5.5, AC-6.3.1).
     */
    public function finalizeRun(PayrollRun $run, int $actorUserId): PayrollRun
    {
        if ($run->run_status !== 'APPROVED') {
            throw new PayrollRunException(
                "UC-25 E1 / AC-4.4.1: run #{$run->payroll_run_id} is '{$run->run_status}', not 'APPROVED', and cannot be finalized."
            );
        }

        $anchor = null;

        $finalizedRun = DB::transaction(function () use ($run, $actorUserId, &$anchor) {
            $currentImport = $run->currentImport();
            $lines = $run->lines()->where('payroll_import_id', $currentImport?->payroll_import_id)->get();

            $gross = '0.00';
            $deductions = '0.00';
            $net = '0.00';
            foreach ($lines as $line) {
                $gross = bcadd($gross, (string) $line->gross_pay, 2);
                $deductions = bcadd($deductions, (string) $line->total_deductions, 2);
                $net = bcadd($net, (string) $line->net_pay, 2);
            }

            $now = now();
            $run->run_status = 'FINALIZED';
            $run->finalized_at = $now;
            $run->total_gross = $gross;
            $run->total_deductions = $deductions;
            $run->total_net = $net;
            $run->employee_count = $lines->count();
            $run->updated_by = $actorUserId;
            $run->save();

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => 'APPROVED',
                'to_status' => 'FINALIZED',
                'performed_by' => $actorUserId,
                'performed_at' => $now,
                'reason' => null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            // AC-4.5.5 / AC-6.3.1 / BR-36 — queue integrity anchor in same transaction.
            $integrity = $this->integrityService ?? app(IntegrityService::class);
            $anchor = $integrity->queueRunAnchor($run, $actorUserId);

            return $run;
        });

        // UC-I6 / AC-6.3.5 / AD-12 — Asynchronous outbox transmission after transaction commit.
        // A ledger outage or latency NEVER fails or delays the finalize action.
        if ($anchor) {
            try {
                app(LedgerAnchorService::class)->transmitAnchor($anchor);
            } catch (\Throwable) {
                // Outbox survives outages; retried via integrity:process-outbox (AC-6.3.5)
            }
        }

        return $finalizedRun;
    }

    /**
     * UC-26 · Reverse finalized payroll run.
     * Preconditions: Run in FINALIZED; payslips not issued or pay date not passed (BR-24, AC-4.5.3); reason required.
     * Effects: Permanent ReversalRecord created, run reopened to DRAFT, integrity anchor queued.
     */
    public function reverseRun(PayrollRun $run, string $reason, int $actorUserId): PayrollRun
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new PayrollRunException(
                'UC-26 E2 / AC-4.5.2: a reason is required to reverse a finalized payroll run.'
            );
        }

        if ($run->run_status !== 'FINALIZED') {
            throw new PayrollRunException(
                "UC-26: run #{$run->payroll_run_id} is '{$run->run_status}', not 'FINALIZED', and cannot be reversed."
            );
        }

        // BR-24 / AC-4.5.3 / UC-26 E1: A run whose payslips have been issued and whose pay date has passed may not be reversed.
        $hasIssuedPayslips = DB::table('payslip_issuances')->where('payroll_run_id', $run->payroll_run_id)->exists();
        $payDatePassed = $run->period->pay_date->isPast();

        if ($hasIssuedPayslips && $payDatePassed) {
            throw new PayrollRunException(
                'BR-24 / AC-4.5.3 / UC-26 E1: reversal is refused once payslips are issued and the pay date has passed. A retroactive adjustment is required.'
            );
        }

        $reversalRecordId = null;

        $reversedRun = DB::transaction(function () use ($run, $cleanReason, $actorUserId, &$reversalRecordId) {
            $now = now();

            $reversalRecord = ReversalRecord::create([
                'payroll_run_id' => $run->payroll_run_id,
                'original_total_gross' => $run->total_gross,
                'original_total_net' => $run->total_net,
                'original_employee_count' => $run->employee_count,
                'reason' => $cleanReason,
                'reversed_by' => $actorUserId,
                'reversed_at' => $now,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            $reversalRecordId = $reversalRecord->reversal_record_id;

            $run->run_status = 'DRAFT';
            $run->finalized_at = null;
            $run->approved_by = null;
            $run->approved_at = null;
            $run->submitted_by = null;
            $run->submitted_at = null;
            $run->updated_by = $actorUserId;
            $run->save();

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => 'FINALIZED',
                'to_status' => 'DRAFT',
                'performed_by' => $actorUserId,
                'performed_at' => $now,
                'reason' => $cleanReason,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            // AC-4.5.2 / AC-6.3.1 — queue integrity anchor for reversal record.
            $integrity = $this->integrityService ?? app(IntegrityService::class);
            $integrity->queueReversalAnchor($reversalRecord, $actorUserId);

            return $run;
        });

        // UC-I6 / AC-6.3.5 / AD-12 — Asynchronous outbox transmission for reversal
        if ($reversalRecordId !== null) {
            try {
                $anchor = IntegrityAnchor::query()
                    ->where('scope_type', 'REVERSAL')
                    ->where('reversal_record_id', $reversalRecordId)
                    ->where('anchor_status', 'PENDING')
                    ->first();
                if ($anchor) {
                    app(LedgerAnchorService::class)->transmitAnchor($anchor);
                }
            } catch (\Throwable) {
                // Outbox survives outages (AC-6.3.5)
            }
        }

        return $reversedRun;
    }

    // UC-17 A2 — Draft only (E4); a run past Draft is returned (UC-24) or
    // reversed (UC-26) instead. Must not have been previously approved.
    public function cancelRun(PayrollRun $run, string $reason, ?int $actorUserId): PayrollRun
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new PayrollRunException('UC-17 A2: a reason is required to cancel a draft run.');
        }

        if ($run->run_status !== 'DRAFT') {
            throw new PayrollRunException(
                "UC-17 E4: run #{$run->payroll_run_id} is '{$run->run_status}', not 'DRAFT', and cannot be cancelled this way."
            );
        }

        $previouslyApproved = $run->transitions()->where('to_status', 'APPROVED')->exists();
        if ($previouslyApproved) {
            throw new PayrollRunException(
                "UC-17 E4: run #{$run->payroll_run_id} was previously approved and cannot be cancelled; it must be returned or reversed instead."
            );
        }

        return DB::transaction(function () use ($run, $cleanReason, $actorUserId) {
            $run->run_status = 'CANCELLED';
            $run->updated_by = $actorUserId;
            $run->save();

            RunTransition::create([
                'payroll_run_id' => $run->payroll_run_id,
                'from_status' => 'DRAFT',
                'to_status' => 'CANCELLED',
                'performed_by' => $actorUserId,
                'performed_at' => now(),
                'reason' => $cleanReason,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            return $run;
        });
    }

    /**
     * The run's population as of today — every active employee the scope
     * string selects.
     *
     * @return Collection<int, Employee>
     */
    public function populationEmployees(PayrollPeriod $period, string $populationScope): Collection
    {
        $query = Employee::query()->where('is_active', true);

        if ($populationScope !== 'ALL') {
            if (! str_starts_with($populationScope, 'DEPARTMENT:')) {
                throw new PayrollRunException("Unknown population scope '{$populationScope}'.");
            }

            $departmentId = (int) substr($populationScope, strlen('DEPARTMENT:'));
            $cutoffEnd = $period->cutoff_end->toDateString();

            $query->whereHas('employmentDetails', function ($q) use ($departmentId, $cutoffEnd) {
                $q->where('department_id', $departmentId)
                    ->where('effective_from', '<=', $cutoffEnd)
                    ->where(function ($q) use ($cutoffEnd) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>=', $cutoffEnd);
                    });
            });
        }

        return $query->orderBy('employee_no')->get();
    }

    public static function populationScopeLabel(string $populationScope): string
    {
        return $populationScope === 'ALL' ? 'All active employees' : $populationScope;
    }
}
