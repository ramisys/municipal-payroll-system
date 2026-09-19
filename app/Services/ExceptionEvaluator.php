<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\ExceptionInstance;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// UC-I4 · Evaluate exception rules — FR-4.1. Runs after an accepted
// register import (UC-18 step 9) and replaces the run's exception_instances
// so the report always reflects the current import.
//
// EX-11–EX-14 are raised by ReconciliationService as import refusals and
// never reach this evaluator: a refused import writes no lines and no
// EXCEPTION_INSTANCE rows (AC-4.1.5's "corrected import" path is the only
// resolution for those defects). This class evaluates the post-import set
// against written payroll lines: EX-01–EX-08 and EX-10.
class ExceptionEvaluator
{
    /** @var list<string> */
    public const REIMPORT_ONLY = ExceptionInstance::REIMPORT_ONLY;

    /** @var list<string> */
    public const EXPORT_SUBSET = ['EX-01', 'EX-02', 'EX-10'];

    /** @var array<string, string> */
    public const SEVERITY = [
        'EX-01' => 'BLOCKING',
        'EX-02' => 'BLOCKING',
        'EX-03' => 'BLOCKING',
        'EX-04' => 'BLOCKING',
        'EX-05' => 'WARNING',
        'EX-06' => 'WARNING',
        'EX-07' => 'WARNING',
        'EX-08' => 'WARNING',
        'EX-10' => 'WARNING',
    ];

    /**
     * Evaluate every live post-import rule and replace the run's stored
     * exception instances inside the caller's transaction when one is open.
     *
     * @return Collection<int, ExceptionInstance>
     */
    public function evaluateAndPersist(PayrollRun $run, ?int $actorUserId): Collection
    {
        $candidates = $this->evaluate($run);

        ExceptionInstance::query()->where('payroll_run_id', $run->payroll_run_id)->delete();

        $created = collect();
        foreach ($candidates as $candidate) {
            $created->push(ExceptionInstance::create([
                'payroll_run_id' => $run->payroll_run_id,
                'payroll_line_id' => $candidate['payroll_line_id'],
                'rule_code' => $candidate['rule_code'],
                'severity' => $candidate['severity'],
                'triggering_values' => $candidate['triggering_values'],
                'is_resolved' => false,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]));
        }

        return $created;
    }

    /**
     * @return list<array{payroll_line_id: ?int, rule_code: string, severity: string, triggering_values: string, employee_no: ?string}>
     */
    public function evaluate(PayrollRun $run): array
    {
        $period = $run->period()->firstOrFail();
        $cutoffStart = $period->cutoff_start->toDateString();
        $cutoffEnd = $period->cutoff_end->toDateString();
        $payDate = $period->pay_date->toDateString();

        $currentImport = $run->currentImport();
        if ($currentImport === null) {
            return [];
        }

        $lines = PayrollLine::query()
            ->with(['employee', 'compensationProfile', 'deductionLines.deductionType'])
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('payroll_import_id', $currentImport->payroll_import_id)
            ->get();

        $netFloor = (string) SystemConfig::value('NET_PAY_FLOOR', '0.00');
        $grossVariancePct = (string) SystemConfig::value('GROSS_VARIANCE_THRESHOLD_PCT', '10.00');
        $overtimeThreshold = (string) SystemConfig::value('OVERTIME_HOURS_THRESHOLD', '40.00');

        $candidates = [];

        foreach ($lines as $line) {
            $employee = $line->employee;
            $employeeNo = $employee->employee_no;

            $attendanceInCutoff = AttendanceRecord::query()
                ->where('employee_id', $line->employee_id)
                ->whereBetween('work_date', [$cutoffStart, $cutoffEnd])
                ->exists();

            if (! $attendanceInCutoff) {
                $candidates[] = $this->candidate(
                    $line->payroll_line_id,
                    'EX-01',
                    "employee_no={$employeeNo}; cutoff={$cutoffStart}..{$cutoffEnd}; attendance=none",
                    $employeeNo,
                );
            }

            $profile = $line->compensationProfile;
            if ($profile === null || $profile->basic_rate === null || $profile->basic_rate === '') {
                $candidates[] = $this->candidate(
                    $line->payroll_line_id,
                    'EX-02',
                    "employee_no={$employeeNo}; compensation_profile=missing_or_no_basic_rate",
                    $employeeNo,
                );
            }

            $netPay = (string) $line->net_pay;
            if (bccomp($netPay, '0.00', 2) <= 0 || bccomp($netPay, $netFloor, 2) < 0) {
                $candidates[] = $this->candidate(
                    $line->payroll_line_id,
                    'EX-03',
                    "employee_no={$employeeNo}; net_pay={$netPay}; floor={$netFloor}; resolution=corrected_import_only",
                    $employeeNo,
                );
            }

            if (bccomp((string) $line->total_deductions, (string) $line->gross_pay, 2) > 0) {
                $candidates[] = $this->candidate(
                    $line->payroll_line_id,
                    'EX-04',
                    "employee_no={$employeeNo}; gross_pay={$line->gross_pay}; total_deductions={$line->total_deductions}; resolution=corrected_import_only",
                    $employeeNo,
                );
            }

            $missingIds = [];
            foreach (['sss_no' => 'SSS', 'philhealth_no' => 'PhilHealth', 'pagibig_mid' => 'Pag-IBIG', 'tin' => 'TIN'] as $field => $label) {
                if ($employee->{$field} === null || trim((string) $employee->{$field}) === '') {
                    $missingIds[] = $label;
                }
            }
            if ($missingIds !== []) {
                $candidates[] = $this->candidate(
                    $line->payroll_line_id,
                    'EX-06',
                    'employee_no='.$employeeNo.'; missing='.implode(',', $missingIds),
                    $employeeNo,
                );
            }

            $priorGross = $this->priorPeriodGross($run, $line->employee_id, $period->payroll_year, $period->period_no);
            if ($priorGross !== null && bccomp($priorGross, '0.00', 2) > 0) {
                $diff = bcsub((string) $line->gross_pay, $priorGross, 2);
                $pct = bcmul(bcdiv($diff, $priorGross, 6), '100', 2);
                if (bccomp(ltrim($pct, '-'), $grossVariancePct, 2) > 0) {
                    $candidates[] = $this->candidate(
                        $line->payroll_line_id,
                        'EX-07',
                        "employee_no={$employeeNo}; gross_pay={$line->gross_pay}; prior_gross={$priorGross}; variance_pct={$pct}; threshold_pct={$grossVariancePct}",
                        $employeeNo,
                    );
                }
            }

            $overtimeHours = '0.00';
            foreach (
                AttendanceRecord::query()
                    ->where('employee_id', $line->employee_id)
                    ->whereBetween('work_date', [$cutoffStart, $cutoffEnd])
                    ->pluck('overtime_hours') as $hours
            ) {
                $overtimeHours = bcadd($overtimeHours, (string) $hours, 2);
            }
            if (bccomp($overtimeHours, $overtimeThreshold, 2) > 0) {
                $candidates[] = $this->candidate(
                    $line->payroll_line_id,
                    'EX-08',
                    "employee_no={$employeeNo}; overtime_hours={$overtimeHours}; threshold={$overtimeThreshold}",
                    $employeeNo,
                );
            }

            $spillover = AttendanceRecord::query()
                ->where('employee_id', $line->employee_id)
                ->where(function ($q) use ($cutoffStart, $cutoffEnd) {
                    $q->where('work_date', '<', $cutoffStart)->orWhere('work_date', '>', $cutoffEnd);
                })
                ->whereBetween('work_date', [
                    date('Y-m-d', strtotime($cutoffStart.' -7 days')),
                    date('Y-m-d', strtotime($cutoffEnd.' +7 days')),
                ])
                ->orderBy('work_date')
                ->first();
            if ($spillover !== null) {
                $candidates[] = $this->candidate(
                    $line->payroll_line_id,
                    'EX-10',
                    "employee_no={$employeeNo}; work_date={$spillover->work_date->toDateString()}; cutoff={$cutoffStart}..{$cutoffEnd}",
                    $employeeNo,
                );
            }
        }

        foreach (['SSS', 'PHILHEALTH', 'PAGIBIG'] as $agency) {
            $hasSchedule = DB::table('statutory_schedules')
                ->where('agency', $agency)
                ->where('is_active', true)
                ->where('effective_from', '<=', $payDate)
                ->where(function ($q) use ($payDate) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $payDate);
                })
                ->exists();

            $hasImportedEmployerShare = false;
            foreach ($lines as $line) {
                foreach ($line->deductionLines as $deductionLine) {
                    if ($deductionLine->deductionType?->deduction_code === $agency
                        && $deductionLine->employer_share !== null
                        && $deductionLine->employer_share !== '') {
                        $hasImportedEmployerShare = true;
                        break 2;
                    }
                }
            }

            if (! $hasSchedule && ! $hasImportedEmployerShare) {
                $candidates[] = $this->candidate(
                    null,
                    'EX-05',
                    "agency={$agency}; pay_date={$payDate}; schedule=none; employer_share=absent",
                    null,
                );
            }
        }

        return $candidates;
    }

    public function hasUnresolvedBlocking(PayrollRun $run): bool
    {
        return ExceptionInstance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('severity', 'BLOCKING')
            ->where('is_resolved', false)
            ->exists();
    }

    /**
     * UC-20 step 5 — acknowledge a warning. Blocking exceptions cannot be
     * acknowledged away; EX-03/EX-04 require a corrected import (AC-4.1.5).
     */
    public function acknowledge(ExceptionInstance $exception, User $user, string $reason): void
    {
        if ($exception->severity !== 'WARNING') {
            throw new ExceptionEvaluationException(
                "UC-20: blocking exception {$exception->rule_code} cannot be acknowledged; resolve by the named path."
            );
        }

        if ($exception->is_resolved) {
            throw new ExceptionEvaluationException(
                "UC-20: exception #{$exception->exception_instance_id} is already acknowledged."
            );
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new ExceptionEvaluationException('UC-20: an acknowledgment reason is required.');
        }

        $exception->is_resolved = true;
        $exception->acknowledged_by = $user->user_id;
        $exception->acknowledged_at = now();
        $exception->acknowledgment_reason = $reason;
        $exception->updated_by = $user->user_id;
        $exception->save();
    }

    /**
     * Blocking exceptions that a corrected re-import clears are marked
     * resolved only by evaluateAndPersist replacing the set. Source-data
     * fixes (attendance, compensation, government IDs) also clear on the
     * next evaluation after the officer re-imports or re-evaluates.
     */
    public function resolutionPath(string $ruleCode): string
    {
        return match ($ruleCode) {
            'EX-01' => 'Import or correct attendance for the cut-off, then re-import the register if lines already exist.',
            'EX-02' => 'Add an active compensation profile with a basic rate, then re-import.',
            'EX-03', 'EX-04', 'EX-11', 'EX-12', 'EX-13', 'EX-14' => 'Corrected import only — the system will not adjust stored figures in place (AC-4.1.5).',
            'EX-05' => 'Load a statutory schedule for the pay date, or include employer-share columns in a corrected register (remittance only; does not change net pay).',
            'EX-06' => 'Update the employee government identification numbers, then acknowledge or re-evaluate.',
            'EX-07', 'EX-08', 'EX-10' => 'Acknowledge with a reason, or correct the source data and re-import.',
            default => 'Review and resolve per FR-4.1.',
        };
    }

    /**
     * @return array{payroll_line_id: ?int, rule_code: string, severity: string, triggering_values: string, employee_no: ?string}
     */
    private function candidate(?int $payrollLineId, string $ruleCode, string $triggeringValues, ?string $employeeNo): array
    {
        return [
            'payroll_line_id' => $payrollLineId,
            'rule_code' => $ruleCode,
            'severity' => self::SEVERITY[$ruleCode],
            'triggering_values' => $triggeringValues,
            'employee_no' => $employeeNo,
        ];
    }

    private function priorPeriodGross(PayrollRun $run, int $employeeId, int $year, int $periodNo): ?string
    {
        $priorPeriod = DB::table('payroll_periods')
            ->where(function ($q) use ($year, $periodNo) {
                $q->where('payroll_year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $periodNo) {
                        $q2->where('payroll_year', $year)->where('period_no', '<', $periodNo);
                    });
            })
            ->orderByDesc('payroll_year')
            ->orderByDesc('period_no')
            ->first();

        if ($priorPeriod === null) {
            return null;
        }

        $priorLine = DB::table('payroll_lines')
            ->join('payroll_runs', 'payroll_runs.payroll_run_id', '=', 'payroll_lines.payroll_run_id')
            ->join('payroll_imports', 'payroll_imports.payroll_import_id', '=', 'payroll_lines.payroll_import_id')
            ->where('payroll_runs.payroll_period_id', $priorPeriod->payroll_period_id)
            ->where('payroll_runs.run_type', $run->run_type)
            ->where('payroll_runs.run_status', '!=', 'CANCELLED')
            ->where('payroll_imports.is_current', true)
            ->where('payroll_lines.employee_id', $employeeId)
            ->value('payroll_lines.gross_pay');

        return $priorLine !== null ? (string) $priorLine : null;
    }
}
