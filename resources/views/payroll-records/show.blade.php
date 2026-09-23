@extends('layouts.app')

@section('title', "Payroll Record — {$employee->fullName()} (#{$line->payroll_line_id})")
@section('heading', 'Payroll record detail')

@section('content')
    <div class="mb-4">
        <a href="{{ route('payroll-records.index') }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to records search
        </a>
    </div>

    <x-page-header title="{{ $employee->fullName() }}" subtitle="Employee #{{ $employee->employee_no }} · Payroll line #{{ $line->payroll_line_id }} (FR-5.1 Retained Record)">
        <x-slot:actions>
            <a href="{{ route('employees.payroll-history', $employee) }}" class="btn btn-secondary btn-sm">
                <x-icon name="history" class="w-3.5 h-3.5" />
                Employee history
            </a>
            @if ($run->run_status === 'FINALIZED')
                <a href="{{ route('payslips.pdf', ['payrollRun' => $run->payroll_run_id, 'employee' => $employee->employee_id]) }}"
                   class="btn btn-secondary btn-sm" target="_blank">
                    <x-icon name="file-text" class="w-3.5 h-3.5" />
                    View payslip PDF
                </a>
            @endif
            @if ($run->currentImport())
                <a href="{{ route('payroll-register.show', $run) }}" class="btn btn-secondary btn-sm">
                    <x-icon name="wallet" class="w-3.5 h-3.5" />
                    View run register
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Top Overview Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat label="Gross pay" :value="'₱' . number_format((float) $line->gross_pay, 2)" hint="Total earnings imported" />
        <x-stat label="Total deductions" :value="'₱' . number_format((float) $line->total_deductions, 2)" hint="Mandatory and loan deductions" />
        <x-stat label="Net take-home pay" :value="'₱' . number_format((float) $line->net_pay, 2)" hint="Centavo-reconciled stored value" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Employee & Employment Profile Context --}}
        <x-card title="Employment context & rate binding">
            @php
                $periodEnd = $run->period?->cutoff_end?->toDateString() ?? now()->toDateString();
                $detail = $employee->employmentDetails
                    ->filter(fn ($row) => $row->effective_from->toDateString() <= $periodEnd
                        && ($row->effective_to === null || $row->effective_to->toDateString() >= $periodEnd))
                    ->sortByDesc('effective_from')
                    ->first() ?? $employee->employmentDetails->sortByDesc('effective_from')->first();
                $profile = $line->compensationProfile;
            @endphp
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <x-kv label="Department" :value="$detail?->department?->department_name ?? '—'" />
                <x-kv label="Position" :value="$detail?->position?->position_name ?? '—'" />
                <x-kv label="Employment status" :value="$detail?->employmentStatus?->status_name ?? '—'" />
                <x-kv label="Pay basis" :value="$profile ? ucfirst(strtolower($profile->pay_basis)) : '—'" />
                <x-kv label="Bound base rate" :value="$profile ? '₱' . number_format((float) $profile->base_rate, 2) : '—'" />
                <x-kv label="Tax Identification (TIN)" :value="$employee->tin ?? '—'" />
            </dl>
        </x-card>

        {{-- Payroll Run & Governance Metadata --}}
        <x-card title="Governance & import version">
            @php
                $period = $run->period;
                $import = $line->import;
            @endphp
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-medium text-ink-muted">Run status</dt>
                    <dd class="mt-0.5"><x-status-badge :value="$run->run_status" /></dd>
                </div>
                <x-kv label="Pay period" :value="$period ? \"{$period->payroll_year}-{$period->period_no}\" : '—'" />
                <x-kv label="Cutoff dates" :value="$period ? \"{$period->cutoff_start->toDateString()} to {$period->cutoff_end->toDateString()}\" : '—'" />
                <x-kv label="Pay date" :value="$period ? $period->pay_date->toDateString() : '—'" />
                <x-kv label="Import version" :value="$import ? \"Version #{$import->version_no}\" : 'Original'" />
                <x-kv label="Finalized at" :value="$run->finalized_at ? $run->finalized_at->format('Y-m-d H:i') : 'Unfinalized'" />
            </dl>
        </x-card>
    </div>

    {{-- Attendance & Work Inputs (FR-5.1 behavior 2) --}}
    <x-card title="Work inputs & attendance summary" class="mb-6">
        <dl class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
            <x-kv label="Days worked" :value="number_format((float) $line->days_worked, 2)" />
            <x-kv label="Hours worked" :value="number_format((float) $line->hours_worked, 2)" />
            <x-kv label="Basic pay" :value="'₱' . number_format((float) $line->basic_pay, 2)" />
            <x-kv label="Taxable compensation" :value="'₱' . number_format((float) $line->taxable_compensation, 2)" />
        </dl>
    </x-card>

    {{-- Breakdown: Earnings and Deductions --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Earnings Table --}}
        <x-card title="Earnings breakdown" :flush="true">
            <x-table>
                <x-slot:head>
                    <th>Earning type</th>
                    <th>Taxable</th>
                    <th class="num">Amount</th>
                </x-slot:head>
                @forelse ($line->earningLines as $el)
                    <tr>
                        <td class="font-medium text-ink">{{ $el->earningType?->earning_name ?? 'Basic Pay' }}</td>
                        <td>
                            @if ($el->is_taxable)
                                <span class="text-xs px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">Taxable</span>
                            @else
                                <span class="text-xs px-1.5 py-0.5 rounded bg-slate-100 text-ink-muted">Non-taxable</span>
                            @endif
                        </td>
                        <td class="num tabular font-mono">₱{{ number_format((float) $el->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-4 text-xs text-ink-muted">No itemized earning lines.</td>
                    </tr>
                @endforelse
                <tr class="bg-slate-50 font-semibold">
                    <td colspan="2" class="text-ink">Total Gross Pay</td>
                    <td class="num tabular font-mono text-ink">₱{{ number_format((float) $line->gross_pay, 2) }}</td>
                </tr>
            </x-table>
        </x-card>

        {{-- Deductions Table --}}
        <x-card title="Deductions breakdown" :flush="true">
            <x-table>
                <x-slot:head>
                    <th>Deduction type</th>
                    <th class="num">Employee share</th>
                    <th class="num">Employer share</th>
                </x-slot:head>
                @forelse ($line->deductionLines as $dl)
                    <tr>
                        <td class="font-medium text-ink">{{ $dl->deductionType?->deduction_name ?? 'Deduction' }}</td>
                        <td class="num tabular font-mono text-amber-700">₱{{ number_format((float) $dl->amount, 2) }}</td>
                        <td class="num tabular font-mono text-ink-muted">
                            {{ $dl->employer_share !== null ? '₱' . number_format((float) $dl->employer_share, 2) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-4 text-xs text-ink-muted">No itemized deduction lines.</td>
                    </tr>
                @endforelse
                <tr class="bg-slate-50 font-semibold">
                    <td class="text-ink">Total Deductions</td>
                    <td class="num tabular font-mono text-amber-700">₱{{ number_format((float) $line->total_deductions, 2) }}</td>
                    <td></td>
                </tr>
            </x-table>
        </x-card>
    </div>
@endsection
