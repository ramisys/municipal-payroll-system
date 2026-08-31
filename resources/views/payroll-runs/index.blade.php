@extends('layouts.app')

@section('title', 'Payroll runs')
@section('heading', 'Payroll runs')

@section('content')
    <x-page-header title="Payroll runs" subtitle="One run per pay period, population, and run type.">
        <x-slot:actions>
            <a href="{{ route('payroll-runs.create') }}" class="btn btn-primary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Create payroll run
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th class="num">Run</th>
                <th>Period</th>
                <th>Run type</th>
                <th>Population</th>
                <th>Status</th>
                <th class="num">Employees</th>
                <th class="actions"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @forelse ($runs as $run)
                <tr>
                    <td class="num font-medium">#{{ $run->payroll_run_id }}</td>
                    <td>
                        <span class="font-medium tabular">{{ $run->period->payroll_year }}-{{ $run->period->period_no }}</span>
                        <span class="block text-[13px] text-ink-muted tabular">
                            {{ $run->period->cutoff_start->toDateString() }} to {{ $run->period->cutoff_end->toDateString() }}
                        </span>
                    </td>
                    <td>{{ $run->run_type }}</td>
                    <td>{{ \App\Services\PayrollRunService::populationScopeLabel($run->population_scope) }}</td>
                    <td><x-status-badge :value="$run->run_status" /></td>
                    <td class="num">{{ $run->employee_count }}</td>
                    <td class="actions">
                        <a href="{{ route('payroll-runs.show', $run) }}" class="btn btn-secondary btn-sm">Open</a>
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="7"
                               message="No payroll runs yet — create one for a defined pay period (UC-17).">
                    <x-slot:action>
                        <a href="{{ route('payroll-runs.create') }}" class="link">Create the first run</a>
                    </x-slot:action>
                </x-empty-state>
            @endforelse
        </x-table>
    </x-card>
@endsection
