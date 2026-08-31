@extends('layouts.app')

@section('title', 'Run #' . $run->payroll_run_id)
@section('heading', 'Run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Run #{{ $run->payroll_run_id }}"
        subtitle="{{ $run->run_type }} · {{ \App\Services\PayrollRunService::populationScopeLabel($run->population_scope) }}"
        :back="route('payroll-runs.index')" back-label="Payroll runs">
        <x-slot:actions>
            <x-status-badge :value="$run->run_status" class="text-sm px-2.5 py-1" />
        </x-slot:actions>
    </x-page-header>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Summary                                                             --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Summary">
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-kv label="Pay period">
                <span class="tabular">{{ $run->period->payroll_year }}-{{ $run->period->period_no }}</span>
            </x-kv>
            <x-kv label="Cut-off">
                <span class="tabular">{{ $run->period->cutoff_start->toDateString() }} to {{ $run->period->cutoff_end->toDateString() }}</span>
            </x-kv>
            <x-kv label="Status"><x-status-badge :value="$run->run_status" /></x-kv>
            <x-kv label="Population">{{ $run->employee_count }} employee(s)</x-kv>
        </dl>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
            <x-stat label="Gross" :value="$totals['gross']" />
            <x-stat label="Deductions" :value="$totals['deductions']" tone="bad" />
            <x-stat label="Net" :value="$totals['net']" tone="ok" />
        </div>

        <x-note class="mt-4">
            Totals above are derived from the current import's payroll lines on every view, never stored
            on the run itself (FR-2.5/§7 beat 11).
        </x-note>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Actions                                                             --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Actions">
        <div class="flex flex-wrap items-center gap-2">
            @if ($canManage)
                <a href="{{ route('payroll-runs.worksheet', $run) }}" class="btn btn-secondary">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 15V3m0 12-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                    </svg>
                    Export input worksheet (UC-32)
                </a>

                @if (in_array($run->run_status, ['DRAFT', 'RETURNED'], true))
                    <a href="{{ route('payroll-imports.create', $run) }}" class="btn btn-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                        </svg>
                        Import computed register (UC-18)
                    </a>
                @endif
            @endif

            <a href="{{ route('payroll-imports.history', $run) }}" class="btn btn-secondary">Import history (UC-33)</a>

            @if ($canManage && $run->run_status === 'DRAFT')
                <a href="{{ route('payroll-runs.cancel-form', $run) }}" class="btn btn-danger">Cancel this run</a>
            @endif
        </div>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Payroll lines                                                       --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($currentImport === null)
        <x-card title="Payroll lines">
            <p class="note">No register has been imported into this run yet.</p>
        </x-card>
    @else
        <x-card
            title="Payroll lines"
            subtitle="Version {{ $currentImport->version_no }} · {{ $currentImport->source_filename }} · {{ $currentImport->row_count }} row(s) · imported {{ $currentImport->imported_at->toDateTimeString() }}"
            :flush="true">

            <x-table>
                <x-slot:head>
                    <th>Employee no.</th>
                    <th>Name</th>
                    <th class="num">Gross pay</th>
                    <th class="num">Total deductions</th>
                    <th class="num">Net pay</th>
                </x-slot:head>

                @forelse ($run->lines->where('payroll_import_id', $currentImport->payroll_import_id) as $line)
                    <tr>
                        <td class="font-medium tabular">{{ $line->employee->employee_no }}</td>
                        <td>{{ $line->employee->fullName() }}</td>
                        <td class="num">{{ $line->gross_pay }}</td>
                        <td class="num">{{ $line->total_deductions }}</td>
                        <td class="num font-semibold">{{ $line->net_pay }}</td>
                    </tr>
                @empty
                    <x-empty-state :colspan="5" message="This import contains no payroll lines." />
                @endforelse
            </x-table>
        </x-card>
    @endif
@endsection
