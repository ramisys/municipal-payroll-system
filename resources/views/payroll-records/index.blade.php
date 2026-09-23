@extends('layouts.app')

@section('title', 'Search payroll records')
@section('heading', 'Payroll records')

@section('content')
    <x-page-header title="Search payroll records" subtitle="Locate historical payroll records and runs across all periods (UC-29 / FR-5.2).">
        <x-slot:actions>
            @if ($lines->isNotEmpty())
                <a href="{{ route('payroll-records.export.pdf', request()->query()) }}" class="btn btn-secondary btn-sm" target="_blank">
                    <x-icon name="printer" class="w-3.5 h-3.5" />
                    Export PDF
                </a>
                <a href="{{ route('payroll-records.export.excel', request()->query()) }}" class="btn btn-secondary btn-sm">
                    <x-icon name="download" class="w-3.5 h-3.5" />
                    Export Excel
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Filter bar --}}
    <x-card class="mb-6">
        <form method="GET" action="{{ route('payroll-records.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Employee partial search (AC-5.2.1) --}}
                <div>
                    <label for="q" class="label">Employee</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}"
                           placeholder="Name or employee no."
                           class="input">
                </div>

                {{-- Pay period select --}}
                <div>
                    <label for="payroll_period_id" class="label">Pay period</label>
                    <select id="payroll_period_id" name="payroll_period_id" class="select">
                        <option value="">All periods</option>
                        @foreach ($periods as $p)
                            <option value="{{ $p->payroll_period_id }}" @selected(($filters['payroll_period_id'] ?? '') == $p->payroll_period_id)>
                                {{ $p->payroll_year }}-{{ $p->period_no }} ({{ $p->cutoff_start->format('M j') }} - {{ $p->cutoff_end->format('M j, Y') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Department filter --}}
                <div>
                    <label for="department_id" class="label">Department</label>
                    <select id="department_id" name="department_id" class="select">
                        <option value="">All departments</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->department_id }}" @selected(($filters['department_id'] ?? '') == $d->department_id)>
                                {{ $d->department_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Run status filter --}}
                <div>
                    <label for="run_status" class="label">Run status</label>
                    <select id="run_status" name="run_status" class="select">
                        <option value="">All statuses</option>
                        @foreach ($runStatuses as $st)
                            <option value="{{ $st }}" @selected(($filters['run_status'] ?? '') == $st)>
                                {{ $st }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end pt-2 border-t border-line">
                <div>
                    <label for="date_from" class="label">Cutoff start date (from)</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="input">
                </div>
                <div>
                    <label for="date_to" class="label">Cutoff end date (to)</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="input">
                </div>
                <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-2">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="search" class="w-4 h-4" />
                        Search records
                    </button>
                    @if ($hasSearched)
                        <a href="{{ route('payroll-records.index') }}" class="btn btn-secondary">
                            <x-icon name="x" class="w-4 h-4" />
                            Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </x-card>

    {{-- Matching payroll runs if found --}}
    @if ($runs->isNotEmpty())
        <div class="mb-6">
            <h2 class="text-sm font-semibold text-ink mb-2">Matching Payroll Runs ({{ $runs->count() }})</h2>
            <x-card :flush="true">
                <x-table>
                    <x-slot:head>
                        <th class="num">Run</th>
                        <th>Period</th>
                        <th>Cutoff dates</th>
                        <th>Status</th>
                        <th class="num">Employees</th>
                        <th class="actions"><span class="sr-only">Actions</span></th>
                    </x-slot:head>
                    @foreach ($runs as $r)
                        <tr>
                            <td class="num font-medium">#{{ $r->payroll_run_id }}</td>
                            <td><span class="font-medium tabular">{{ $r->period->payroll_year }}-{{ $r->period->period_no }}</span></td>
                            <td class="text-ink-muted text-xs tabular">{{ $r->period->cutoff_start->toDateString() }} to {{ $r->period->cutoff_end->toDateString() }}</td>
                            <td><x-status-badge :value="$r->run_status" /></td>
                            <td class="num">{{ $r->employee_count }}</td>
                            <td class="actions">
                                <a href="{{ route('payroll-runs.show', $r) }}" class="btn btn-secondary btn-sm">
                                    <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                                    Open run
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>
    @endif

    {{-- Matching payroll lines table --}}
    <x-card :flush="true">
        <div class="px-4 py-3 border-b border-line flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink">
                Matching Payroll Lines
                <span class="text-xs font-normal text-ink-muted ml-1.5">({{ $lines->total() }} total records located)</span>
            </h2>
            @if ($lines->total() > 0)
                <p class="text-xs text-ink-muted">
                    Showing {{ $lines->firstItem() }} to {{ $lines->lastItem() }} of {{ $lines->total() }}
                </p>
            @endif
        </div>

        <x-table>
            <x-slot:head>
                <th>Employee</th>
                <th>Department</th>
                <th>Period</th>
                <th class="num">Gross pay</th>
                <th class="num">Deductions</th>
                <th class="num">Net pay</th>
                <th>Status</th>
                <th class="actions"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @forelse ($lines as $line)
                @php
                    $emp = $line->employee;
                    $dept = $emp->currentEmploymentDetail?->department?->department_name ?? '—';
                    $period = $line->run?->period;
                @endphp
                <tr>
                    <td>
                        <span class="font-medium text-ink">{{ $emp->fullName() }}</span>
                        <span class="block text-[12px] text-ink-muted tabular">{{ $emp->employee_no }}</span>
                    </td>
                    <td class="text-xs text-ink-muted">{{ $dept }}</td>
                    <td>
                        <span class="font-medium text-xs tabular">{{ $period ? "{$period->payroll_year}-{$period->period_no}" : '—' }}</span>
                        @if ($period)
                            <span class="block text-[11px] text-ink-muted tabular">{{ $period->cutoff_end->toDateString() }}</span>
                        @endif
                    </td>
                    <td class="num tabular font-mono text-xs">₱{{ number_format((float) $line->gross_pay, 2) }}</td>
                    <td class="num tabular font-mono text-xs text-amber-700">₱{{ number_format((float) $line->total_deductions, 2) }}</td>
                    <td class="num tabular font-mono text-xs font-semibold text-emerald-700">₱{{ number_format((float) $line->net_pay, 2) }}</td>
                    <td><x-status-badge :value="$line->run->run_status" /></td>
                    <td class="actions">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="{{ route('payroll-records.show', $line) }}" class="btn btn-secondary btn-sm" title="View retained record details">
                                <x-icon name="eye" class="w-3.5 h-3.5" />
                                Details
                            </a>
                            @if ($line->run->run_status === 'FINALIZED')
                                <a href="{{ route('payslips.pdf', ['payrollRun' => $line->payroll_run_id, 'employee' => $emp->employee_id]) }}"
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
                               message="No payroll records match the specified search criteria.">
                    @if (! empty($appliedCriteria))
                        <div class="mt-3 text-xs text-ink-muted text-left max-w-md mx-auto p-3 bg-slate-50 rounded border border-line">
                            <p class="font-semibold text-ink mb-1">Criteria applied (AC-5.2.3):</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($appliedCriteria as $crit)
                                    <li>{{ $crit }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <x-slot:action>
                        @if ($hasSearched)
                            <a href="{{ route('payroll-records.index') }}" class="link">Reset search filters</a>
                        @endif
                    </x-slot:action>
                </x-empty-state>
            @endforelse
        </x-table>

        @if ($lines->hasPages())
            <div class="px-4 py-3 border-t border-line">
                {{ $lines->links() }}
            </div>
        @endif
    </x-card>
@endsection
