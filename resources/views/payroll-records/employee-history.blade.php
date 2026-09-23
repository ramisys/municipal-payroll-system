@extends('layouts.app')

@section('title', "Payroll History — {$employee->fullName()} ({$employee->employee_no})")
@section('heading', 'Employee payroll history')

@section('content')
    <div class="mb-4">
        <a href="{{ route('employees.index') }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to employees
        </a>
    </div>

    <x-page-header title="{{ $employee->fullName() }}" subtitle="Employee #{{ $employee->employee_no }} · Chronological compensation and payroll history (UC-29 A2)">
        <x-slot:actions>
            <a href="{{ route('payroll-records.index', ['q' => $employee->employee_no]) }}" class="btn btn-secondary btn-sm">
                <x-icon name="search" class="w-3.5 h-3.5" />
                Search records
            </a>
            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-secondary btn-sm">
                <x-icon name="pencil" class="w-3.5 h-3.5" />
                Edit employee
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card :flush="true">
        <div class="px-4 py-3 border-b border-line flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink">
                Payroll History
                <span class="text-xs font-normal text-ink-muted ml-1.5">({{ $lines->count() }} periods on record)</span>
            </h2>
            <div class="text-xs text-ink-muted">
                {{ $employee->currentEmploymentDetail?->department?->department_name ?? 'Unassigned' }}
            </div>
        </div>

        <x-table>
            <x-slot:head>
                <th>Period</th>
                <th>Cutoff dates</th>
                <th class="num">Days</th>
                <th class="num">Gross pay</th>
                <th class="num">Deductions</th>
                <th class="num">Net pay</th>
                <th>Status</th>
                <th class="actions"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @forelse ($lines as $line)
                @php
                    $period = $line->run?->period;
                @endphp
                <tr>
                    <td>
                        <span class="font-medium text-ink tabular">{{ $period ? "{$period->payroll_year}-{$period->period_no}" : '—' }}</span>
                        <span class="block text-[11px] text-ink-muted">Run #{{ $line->payroll_run_id }}</span>
                    </td>
                    <td class="text-xs text-ink-muted tabular">
                        {{ $period ? "{$period->cutoff_start->toDateString()} to {$period->cutoff_end->toDateString()}" : '—' }}
                    </td>
                    <td class="num tabular text-xs">{{ number_format((float) $line->days_worked, 2) }}</td>
                    <td class="num tabular font-mono text-xs">₱{{ number_format((float) $line->gross_pay, 2) }}</td>
                    <td class="num tabular font-mono text-xs text-amber-700">₱{{ number_format((float) $line->total_deductions, 2) }}</td>
                    <td class="num tabular font-mono text-xs font-semibold text-emerald-700">₱{{ number_format((float) $line->net_pay, 2) }}</td>
                    <td><x-status-badge :value="$line->run->run_status" /></td>
                    <td class="actions">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="{{ route('payroll-records.show', $line) }}" class="btn btn-secondary btn-sm" title="View retained record">
                                <x-icon name="eye" class="w-3.5 h-3.5" />
                                Details
                            </a>
                            @if ($line->run->run_status === 'FINALIZED')
                                <a href="{{ route('payslips.pdf', ['payrollRun' => $line->payroll_run_id, 'employee' => $employee->employee_id]) }}"
                                   class="btn btn-secondary btn-sm" target="_blank" title="View payslip PDF">
                                    <x-icon name="file-text" class="w-3.5 h-3.5" />
                                    Payslip
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="8"
                               message="No historical payroll records found for this employee.">
                </x-empty-state>
            @endforelse
        </x-table>
    </x-card>
@endsection
