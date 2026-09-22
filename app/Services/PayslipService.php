<?php

namespace App\Services;

use App\Models\OrganizationProfile;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\PayslipIssuance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// UC-27 · Generate payslips — FR-3.1, FR-3.2, FR-3.3, NFR-3.5.
// UC-28 · Reprint payslip — FR-3.4, NFR-5.5.
//
// Every figure on a payslip is read from the stored payroll line and its
// child earning/deduction lines (AC-3.1.1); there is no editing surface and
// no recomputation (BR-01, BR-37). A payslip is reissued by regenerating
// from those stored lines — never from a saved file that could diverge from
// the record (FR-3.4 behavior 2) — so a reprint three years later renders
// the amounts the run was finalized with (system-architecture.md §8 beat 1).
//
// The finished timestamp on the footer is the ORIGINAL issued_at (or the
// run's finalized_at before the first generation), a stored value that
// never changes, so regenerating a period's payslips — and reprinting them –
// reproduces byte-identical documents (AC-3.1.4, AC-3.4.2).
class PayslipService
{
    /**
     * AC-3.1.3 / AC-4.4.4 — generation and reprint are refused for any run
     * that is not Finalized. UC-28 E1 wants the current state stated; the
     * exception message carries it.
     */
    public function assertFinalized(PayrollRun $run, string $action = 'Payslip generation'): void
    {
        if ($run->run_status !== 'FINALIZED') {
            throw new PayslipException(
                "{$action} requires a Finalized run; run #{$run->payroll_run_id} is '{$run->run_status}' (AC-3.1.3 / AC-4.4.4)."
            );
        }
    }

    /**
     * UC-27 step 3/4 — the batch line set: every payroll line of the run's
     * current import, optionally filtered by department (FR-3.3 behavior 1).
     * One payslip per line, none missing, none duplicated (AC-3.3.2).
     *
     * @return Collection<int, PayrollLine>
     */
    public function runLines(PayrollRun $run, ?int $departmentId = null): Collection
    {
        $query = $run->lines()
            ->where('payroll_import_id', $run->currentImport()?->payroll_import_id)
            ->join('employees', 'employees.employee_id', '=', 'payroll_lines.employee_id')
            ->orderBy('employees.employee_no')
            ->select('payroll_lines.*')
            ->with([
                'employee.employmentDetails.department',
                'employee.employmentDetails.position',
                'employee.employmentDetails.employmentStatus',
                'earningLines.earningType',
                'deductionLines.deductionType',
            ]);

        if ($departmentId !== null) {
            $query->whereHas('employee.employmentDetails', fn ($q) => $q->where('department_id', $departmentId));
        }

        return $query->get();
    }

    /**
     * UC-27 step 6 — records one ORIGINAL issuance row per line that does
     * not already carry one, inside a single transaction. Regeneration
     * (UC-27 A1) is therefore idempotent: the rows are written once and a
     * repeat generation records nothing new (AC-3.1.4).
     *
     * @param  Collection<int, PayrollLine>  $lines
     */
    public function recordOriginalIssuances(PayrollRun $run, Collection $lines, int $actorUserId): int
    {
        return DB::transaction(function () use ($run, $lines, $actorUserId) {
            $alreadyIssued = PayslipIssuance::query()
                ->where('payroll_run_id', $run->payroll_run_id)
                ->where('issuance_type', 'ORIGINAL')
                ->pluck('employee_id')
                ->flip();

            $newIssued = 0;
            foreach ($lines as $line) {
                if ($alreadyIssued->has($line->employee_id)) {
                    continue;
                }

                PayslipIssuance::create([
                    'payroll_run_id' => $run->payroll_run_id,
                    'employee_id' => $line->employee_id,
                    'issuance_type' => 'ORIGINAL',
                    'issued_by' => $actorUserId,
                    'issued_at' => now(),
                    'created_by' => $actorUserId,
                    'updated_by' => $actorUserId,
                ]);
                $newIssued++;
            }

            return $newIssued;
        });
    }

    /**
     * UC-28 step 4 — record one REPRINT row with user and timestamp
     * (AC-3.4.3). Guarded like generation: only a Finalized run can have a
     * payslip reissued (UC-28 E1).
     */
    public function recordReprint(PayrollRun $run, PayrollLine $line, int $actorUserId): PayslipIssuance
    {
        $this->assertFinalized($run, 'A payslip reprint');

        return PayslipIssuance::create([
            'payroll_run_id' => $run->payroll_run_id,
            'employee_id' => $line->employee_id,
            'issuance_type' => 'REPRINT',
            'issued_by' => $actorUserId,
            'issued_at' => now(),
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }

    /**
     * Build the complete render datum for one payslip. Every figure comes
     * straight off the stored line and its children (AC-3.1.1); nothing is
     * recomputed, so gross less total deductions prints to the centavo as
     * net (AC-3.2.2) because chk_payroll_lines_net_pay already guarantees
     * it at write time.
     *
     * @return array{
     *     org: array{name: string, address: string, logo_uri: ?string},
     *     label: string,
     *     period: string,
     *     cutoff: string,
     *     pay_date: string,
     *     employee: array{no: string, name: string, department: string, position: string, status: string},
     *     earnings: array<int, array{name: string, detail: string, amount: string, is_taxable: bool}>,
     *     gross_pay: string,
     *     deductions: array<int, array{name: string, detail: string, amount: string}>,
     *     total_deductions: string,
     *     net_pay: string,
     *     import_version: ?int,
     *     generated_at: string,
     *     is_reprint: bool,
     *     reprinted_by: ?string,
     * }
     */
    public function payslipData(
        PayrollRun $run,
        PayrollLine $line,
        ?OrganizationProfile $org = null,
        ?Carbon $generatedAt = null,
        bool $isReprint = false,
        ?Carbon $reprintedAt = null,
        ?string $reprintedBy = null,
    ): array {
        $org = $org ?? $this->organizationProfile();
        $period = $run->relationLoaded('period') ? $run->period : $run->period()->first();
        $employee = $line->employee;
        $employment = $employee->employmentDetails
            ->filter(fn ($row) => $row->effective_from->toDateString() <= $period->cutoff_end->toDateString()
                && ($row->effective_to === null || $row->effective_to->toDateString() >= $period->cutoff_end->toDateString()))
            ->sortByDesc('effective_from')
            ->first();

        $generatedAt = $generatedAt ?? $run->finalized_at ?? now();

        return [
            'org' => [
                'name' => $org?->registered_name ?? 'Municipal Payroll System',
                'address' => $org?->address ?? '',
                'logo_uri' => $this->logoUri($org),
            ],
            'label' => 'Payslip',
            'period' => "{$period->payroll_year} · Period {$period->period_no}",
            'cutoff' => "{$period->cutoff_start->toDateString()} to {$period->cutoff_end->toDateString()}",
            'pay_date' => $period->pay_date->toDateString(),
            'employee' => [
                'no' => $employee->employee_no,
                'name' => $employee->fullName(),
                'department' => $employment?->department?->department_name ?? '—',
                'position' => $employment?->position?->position_title ?? '—',
                'status' => $employment?->employmentStatus?->status_name ?? '—',
            ],
            'earnings' => $this->earnings($line),
            'gross_pay' => self::formatAmount((string) $line->gross_pay),
            'deductions' => $this->deductions($line),
            'total_deductions' => self::formatAmount((string) $line->total_deductions),
            'net_pay' => self::formatAmount((string) $line->net_pay),
            'import_version' => $run->currentImport()?->version_no,
            'generated_at' => $generatedAt->format('Y-m-d H:i:s'),
            'is_reprint' => $isReprint,
            'reprinted_by' => $isReprint ? ($reprintedBy ?? 'A system user') : null,
            'reprinted_at' => $isReprint && $reprintedAt !== null ? $reprintedAt->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * UC-27 step 5 / FR-3.3 — the whole set as one multi-page PDF. The
     * batch view renders one payslip per line with a page break between, so
     * the exported PDF carries exactly one payslip per payroll line
     * (AC-3.3.2). Stored presentation data only; see payslipData().
     *
     * @param  Collection<int, PayrollLine>  $lines
     */
    public function renderBatchPdf(PayrollRun $run, Collection $lines): \Barryvdh\DomPDF\PDF
    {
        $run->loadMissing('period');
        $generatedAtByEmployee = $this->originalIssuanceTimes($run);

        $payslips = $lines->map(
            fn (PayrollLine $line) => $this->payslipData(
                $run,
                $line,
                generatedAt: $generatedAtByEmployee[$line->employee_id] ?? $run->finalized_at,
            )
        );

        return Pdf::loadView('payslips.pdf.batch', ['payslips' => $payslips]);
    }

    /**
     * One payslip for one employee of a finalized run — the read/reprint
     * path (UC-28 step 3). $reprintIssuance, when present, marks the
     * document as a reprint and supplies who/when for the footer; every
     * figure still comes from the stored line, identical to the original
     * (AC-3.4.2).
     */
    public function renderSinglePdf(PayrollRun $run, PayrollLine $line, ?PayslipIssuance $reprintIssuance = null): \Barryvdh\DomPDF\PDF
    {
        $run->loadMissing('period');
        $original = $this->originalIssuanceTimes($run)[$line->employee_id] ?? null;

        $payslip = $this->payslipData(
            $run,
            $line,
            generatedAt: $original ?? $run->finalized_at,
            isReprint: $reprintIssuance !== null,
            reprintedAt: $reprintIssuance?->issued_at,
            reprintedBy: $reprintIssuance?->issuer?->full_name,
        );

        return Pdf::loadView('payslips.pdf.single', ['payslip' => $payslip]);
    }

    public function organizationProfile(): ?OrganizationProfile
    {
        return OrganizationProfile::query()->orderBy('organization_id')->first();
    }

    /**
     * @return array<int, array{name: string, detail: string, amount: string, is_taxable: bool}>
     */
    private function earnings(PayrollLine $line): array
    {
        $earnings = [];

        foreach ($line->earningLines as $earning) {
            if (bccomp((string) $earning->amount, '0.00', 2) === 0) {
                continue; // zero-amount lines suppressed (FR-3.2 behavior 2)
            }

            $type = $earning->earningType;
            $detail = '';

            $quantity = $earning->quantity;
            $rate = $earning->rate_applied;
            if ($quantity !== null && bccomp((string) $quantity, '0.00', 2) !== 0) {
                $detail = trim("{$quantity}".($rate !== null ? " × {$rate}" : ''));
            }

            // Basic pay carries its days or hours from the payroll line
            // (FR-3.2 earnings row) when the register provided them.
            if ($detail === '' && $type?->earning_code === 'BASIC') {
                $detail = $line->hours_worked !== null && bccomp((string) $line->hours_worked, '0.00', 2) > 0
                    ? "{$line->hours_worked} hour(s)"
                    : ($line->days_worked !== null && bccomp((string) $line->days_worked, '0.00', 2) > 0
                        ? "{$line->days_worked} day(s)"
                        : '');
            }

            $earnings[] = [
                'name' => $type?->earning_name ?? 'Earning',
                'detail' => $detail,
                'amount' => self::formatAmount((string) $earning->amount),
                'is_taxable' => (bool) $earning->is_taxable,
            ];
        }

        return $earnings;
    }

    /**
     * @return array<int, array{name: string, detail: string, amount: string}>
     */
    private function deductions(PayrollLine $line): array
    {
        $deductions = [];

        foreach ($line->deductionLines as $deduction) {
            if (bccomp((string) $deduction->amount, '0.00', 2) === 0) {
                continue;
            }

            $type = $deduction->deductionType;
            $detail = $type?->statutory_agency ?? '';

            if ($deduction->remarks !== null && trim((string) $deduction->remarks) !== '') {
                $detail = trim(($detail !== '' ? "{$detail} — " : '').$deduction->remarks);
            }

            $deductions[] = [
                'name' => $type?->deduction_name ?? 'Deduction',
                'detail' => $detail,
                'amount' => self::formatAmount((string) $deduction->amount),
            ];
        }

        return $deductions;
    }

    /**
     * map<int employee_id, Carbon original issued_at> for the run's ORIGINAL
     * issuance rows, so the footer timestamp is the fixed first-generation
     * time and regeneration/reprint stay byte-identical (AC-3.1.4, AC-3.4.2).
     *
     * @return array<int, Carbon>
     */
    private function originalIssuanceTimes(PayrollRun $run): array
    {
        return PayslipIssuance::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('issuance_type', 'ORIGINAL')
            ->pluck('issued_at', 'employee_id')
            ->map(fn ($value) => Carbon::parse($value))
            ->all();
    }

    private function logoUri(?OrganizationProfile $org): ?string
    {
        if ($org === null || $org->logo === null || $org->logo === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($org->logo);

        return $mime !== false && str_starts_with($mime, 'image/')
            ? "data:{$mime};base64,".base64_encode($org->logo)
            : null;
    }

    /**
     * Thousands-separate a DECIMAL(13,2) string without converting to float
     * (the no-float discipline of CR-01): split on the decimal point and
     * regroup the integer part only.
     */
    public static function formatAmount(string $amount): string
    {
        $sign = '';
        if (str_starts_with($amount, '-')) {
            $sign = '-';
            $amount = substr($amount, 1);
        }

        [$int, $frac] = array_pad(explode('.', $amount, 2), 2, '00');
        $int = ltrim($int, '0') === '' ? '0' : ltrim($int, '0');

        $groups = [];
        while (strlen($int) > 3) {
            $groups[] = substr($int, -3);
            $int = substr($int, 0, -3);
        }
        $groups[] = $int;

        return $sign.implode(',', array_reverse($groups)).'.'.substr($frac, 0, 2);
    }
}
