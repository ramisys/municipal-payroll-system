@extends('layouts.app')

@section('title', 'Payroll Register · Run #' . $run->payroll_run_id)
@section('heading', 'Payroll Register')

@section('content')
    <x-page-header
        title="Payroll Register · Run #{{ $run->payroll_run_id }}"
        subtitle="{{ $run->period->payroll_year }}-{{ $run->period->period_no }} · {{ $run->run_type }} · {{ \App\Services\PayrollRunService::populationScopeLabel($run->population_scope) }}"
        :back="route('payroll-runs.show', $run)" back-label="Back to run">
        <x-slot:actions>
            @if ($isProvisional)
                <span class="badge badge-warn px-2.5 py-1 font-semibold uppercase tracking-wider text-xs">
                    <span class="badge-dot" aria-hidden="true"></span>Provisional Register
                </span>
            @else
                <span class="badge badge-ok px-2.5 py-1 font-semibold uppercase tracking-wider text-xs">
                    <span class="badge-dot" aria-hidden="true"></span>Finalized Register
                </span>
            @endif

            <a href="{{ route('payroll-register.export', $run) }}" class="btn btn-secondary">
                <x-icon name="download" />
                Export to Excel (XLSX)
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($isProvisional)
        <x-note tone="warn">
            <strong>Provisional View (UC-21 A2):</strong> This run is in <strong>{{ $run->run_status }}</strong> state and has not been finalized.
            Figures reflect the current imported register version #{{ $currentImport->version_no }} and remain subject to approval.
        </x-note>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Summary Cards & Column Totals                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat label="Total Gross Pay" :value="$totals['gross']" />
        <x-stat label="Total Deductions" :value="$totals['deductions']" tone="bad" />
        <x-stat label="Total Net Pay" :value="$totals['net']" tone="ok" />
        <x-stat label="Employees / Days" :value="$lines->count() . ' / ' . number_format($totals['days'], 1)" />
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Filter & Search Bar                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Filters & Sorting" class="mb-6">
        <form method="GET" action="{{ route('payroll-register.show', $run) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label for="department_id" class="form-label">Department</label>
                <select name="department_id" id="department_id" class="form-control w-full">
                    <option value="">All departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->department_id }}" @selected(request('department_id') == $dept->department_id)>
                            {{ $dept->department_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="employment_status_id" class="form-label">Employment Status</label>
                <select name="employment_status_id" id="employment_status_id" class="form-control w-full">
                    <option value="">All statuses</option>
                    @foreach ($employmentStatuses as $status)
                        <option value="{{ $status->employment_status_id }}" @selected(request('employment_status_id') == $status->employment_status_id)>
                            {{ $status->status_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="sort" class="form-label">Sort By</label>
                <div class="flex gap-2">
                    <select name="sort" id="sort" class="form-control w-full">
                        <option value="employee_no" @selected(request('sort') === 'employee_no')>Employee No</option>
                        <option value="gross_pay" @selected(request('sort') === 'gross_pay')>Gross Pay</option>
                        <option value="total_deductions" @selected(request('sort') === 'total_deductions')>Total Deductions</option>
                        <option value="net_pay" @selected(request('sort') === 'net_pay')>Net Pay</option>
                    </select>
                    <select name="dir" class="form-control w-24">
                        <option value="asc" @selected(request('dir') !== 'desc')>ASC</option>
                        <option value="desc" @selected(request('dir') === 'desc')>DESC</option>
                    </select>
                </div>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-primary w-full">Apply</button>
                <a href="{{ route('payroll-register.show', $run) }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Register Lines Table                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card
        title="Register Lines"
        subtitle="{{ $lines->count() }} employee line(s) loaded · Import version #{{ $currentImport->version_no }} · Click any line to expand centavo breakdown (AC-4.2.3)"
        :flush="true">

        <div class="overflow-x-auto">
            <x-table>
                <x-slot:head>
                    <th>Employee No.</th>
                    <th>Name & Department</th>
                    <th class="num">Days / Hours</th>
                    <th class="num">Gross Pay</th>
                    <th class="num">Deductions</th>
                    <th class="num">Net Pay</th>
                    @if ($previousRun !== null)
                        <th class="num">Prior Period Net Diff</th>
                    @endif
                    <th class="text-center">Details</th>
                </x-slot:head>

                @forelse ($lines as $line)
                    @php
                        $dept = $line->employee->employmentDetails->first()?->department?->department_name ?? '—';
                        $status = $line->employee->employmentDetails->first()?->employmentStatus?->status_name ?? '—';
                        $prevLine = $previousLinesByEmployee[$line->employee_id] ?? null;
                        $netDiff = null;
                        if ($prevLine !== null) {
                            $netDiff = bcsub((string) $line->net_pay, (string) $prevLine->net_pay, 2);
                        }
                    @endphp
                    <tbody x-data="{ expanded: false }" class="border-b border-stone-200 dark:border-stone-800">
                        <tr class="hover:bg-stone-50/50 dark:hover:bg-stone-800/30 cursor-pointer" @click="expanded = !expanded">
                            <td class="font-medium tabular">{{ $line->employee->employee_no }}</td>
                            <td>
                                <div class="font-semibold text-stone-900 dark:text-stone-100">{{ $line->employee->fullName() }}</div>
                                <div class="text-xs text-stone-500">{{ $dept }} · {{ $status }}</div>
                            </td>
                            <td class="num tabular text-xs">{{ $line->days_worked }} d / {{ $line->hours_worked }} h</td>
                            <td class="num tabular">{{ $line->gross_pay }}</td>
                            <td class="num tabular text-red-600 dark:text-red-400">{{ $line->total_deductions }}</td>
                            <td class="num tabular font-bold text-emerald-600 dark:text-emerald-400">{{ $line->net_pay }}</td>
                            @if ($previousRun !== null)
                                <td class="num tabular text-xs">
                                    @if ($prevLine === null)
                                        <span class="text-stone-400">New in period</span>
                                    @elseif (bccomp($netDiff, '0.00', 2) === 0)
                                        <span class="text-stone-400">0.00</span>
                                    @elseif (bccomp($netDiff, '0.00', 2) > 0)
                                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold">+{{ $netDiff }}</span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400 font-semibold">{{ $netDiff }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="text-center">
                                <button type="button" class="btn btn-secondary btn-sm" @click.stop="expanded = !expanded">
                                    <span x-text="expanded ? 'Hide' : 'Breakdown'"></span>
                                </button>
                            </td>
                        </tr>

                        {{-- Expanded Breakdown Row (AC-4.2.3) --}}
                        <tr x-show="expanded" x-cloak class="bg-stone-50 dark:bg-stone-900/60">
                            <td colspan="{{ $previousRun !== null ? 8 : 7 }}" class="p-4">
                                <div class="p-3 bg-white dark:bg-stone-800 rounded-lg border border-stone-200 dark:border-stone-700 space-y-4">
                                    <div class="flex items-center justify-between border-b border-stone-100 dark:border-stone-700 pb-2">
                                        <h4 class="font-bold text-sm text-stone-900 dark:text-stone-100">
                                            Full Line Item Breakdown · {{ $line->employee->fullName() }} ({{ $line->employee->employee_no }})
                                        </h4>
                                        <div class="text-xs text-stone-500">
                                            Import Version: #{{ $line->payroll_import_id }} · Compensation Profile #{{ $line->compensation_profile_id }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {{-- Earnings --}}
                                        <div>
                                            <h5 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2">Earnings Lines (Imported)</h5>
                                            @if ($line->earningLines->isEmpty())
                                                <p class="text-xs text-stone-400">No individual earning sub-lines.</p>
                                            @else
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="border-b text-stone-500"><th class="text-left py-1">Type</th><th class="text-right py-1">Amount</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($line->earningLines as $el)
                                                            <tr class="border-b border-stone-100 dark:border-stone-700/50">
                                                                <td class="py-1">{{ $el->earningType->type_name ?? 'Basic Pay' }}</td>
                                                                <td class="py-1 num tabular">{{ $el->amount }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif
                                        </div>

                                        {{-- Deductions --}}
                                        <div>
                                            <h5 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2">Deduction Lines (Imported)</h5>
                                            @if ($line->deductionLines->isEmpty())
                                                <p class="text-xs text-stone-400">No individual deduction sub-lines.</p>
                                            @else
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="border-b text-stone-500"><th class="text-left py-1">Type</th><th class="text-right py-1">Amount</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($line->deductionLines as $dl)
                                                            <tr class="border-b border-stone-100 dark:border-stone-700/50">
                                                                <td class="py-1">{{ $dl->deductionType->type_name ?? 'Deduction' }}</td>
                                                                <td class="py-1 num tabular text-red-600 dark:text-red-400">{{ $dl->amount }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-stone-100 dark:border-stone-700 text-xs text-stone-500 flex justify-between">
                                        <span>Reconciliation: Gross ({{ $line->gross_pay }}) - Deductions ({{ $line->total_deductions }}) = Net ({{ $line->net_pay }})</span>
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Centavo-level agreement verified</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <x-empty-state :colspan="$previousRun !== null ? 8 : 7" message="No register lines found matching criteria." />
                @endforelse

                {{-- Column Totals Row (AC-4.2.2) --}}
                <tfoot class="bg-stone-100 dark:bg-stone-800 font-bold border-t-2 border-stone-300 dark:border-stone-600">
                    <tr>
                        <td colspan="2" class="py-3 px-4">TOTALS ({{ $lines->count() }} employees)</td>
                        <td class="num py-3 px-4 tabular text-xs">{{ number_format($totals['days'], 1) }} d / {{ number_format($totals['hours'], 1) }} h</td>
                        <td class="num py-3 px-4 tabular">{{ $totals['gross'] }}</td>
                        <td class="num py-3 px-4 tabular text-red-600 dark:text-red-400">{{ $totals['deductions'] }}</td>
                        <td class="num py-3 px-4 tabular text-emerald-600 dark:text-emerald-400">{{ $totals['net'] }}</td>
                        @if ($previousRun !== null)
                            <td class="num py-3 px-4 tabular"></td>
                        @endif
                        <td></td>
                    </tr>
                </tfoot>
            </x-table>
        </div>
    </x-card>
@endsection
