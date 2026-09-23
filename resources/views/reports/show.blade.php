@extends('layouts.app')

@section('title', "Configure Report — {$meta['name']}")
@section('heading', 'Report parameters')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reports.index') }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to report catalogue
        </a>
    </div>

    <x-page-header title="{{ $meta['name'] }}" subtitle="{{ $meta['description'] }}">
        <x-slot:actions>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-ink border border-line">
                Priority: {{ $meta['priority'] }}
            </span>
        </x-slot:actions>
    </x-page-header>

    @if (session('warning'))
        <x-alert type="warning" class="mb-6">
            {{ session('warning') }}
        </x-alert>
    @endif

    @if ($errors->has('report'))
        <x-alert type="error" class="mb-6">
            {{ $errors->first('report') }}
        </x-alert>
    @endif

    <div class="max-w-2xl">
        <x-card title="Report parameters">
            <form method="POST" action="{{ route('reports.generate', $reportType) }}" class="space-y-4">
                @csrf

                @if ($meta['requires_period'])
                    <div>
                        <label for="payroll_period_id" class="label">Pay period <span class="text-rose-600">*</span></label>
                        <select id="payroll_period_id" name="payroll_period_id" class="select" required>
                            <option value="">Select a pay period...</option>
                            @foreach ($periods as $p)
                                <option value="{{ $p->payroll_period_id }}" @selected(old('payroll_period_id') == $p->payroll_period_id)>
                                    {{ $p->payroll_year }}-{{ $p->period_no }} ({{ $p->cutoff_start->format('M j') }} to {{ $p->cutoff_end->format('M j, Y') }}) — Pay date: {{ $p->pay_date->format('M j, Y') }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-ink-muted mt-1">Select the period for which to compile the report.</p>
                    </div>
                @endif

                @if ($meta['requires_year'])
                    <div>
                        <label for="payroll_year" class="label">Calendar year <span class="text-rose-600">*</span></label>
                        <input type="number" id="payroll_year" name="payroll_year" min="2020" max="2035"
                               value="{{ old('payroll_year', date('Y')) }}" class="input" required>
                    </div>
                @endif

                @if ($meta['supports_department'])
                    <div>
                        <label for="department_id" class="label">Department filter</label>
                        <select id="department_id" name="department_id" class="select">
                            <option value="">All departments</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->department_id }}" @selected(old('department_id') == $d->department_id)>
                                    {{ $d->department_name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-ink-muted mt-1">Optional: Leave blank to include all municipal departments.</p>
                    </div>
                @endif

                @if ($meta['supports_employee'])
                    <div>
                        <label for="employee_id" class="label">Employee filter</label>
                        <select id="employee_id" name="employee_id" class="select">
                            <option value="">All employees</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->employee_id }}" @selected(old('employee_id') == $e->employee_id)>
                                    {{ $e->fullName() }} ({{ $e->employee_no }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="pt-4 border-t border-line flex items-center justify-end gap-3">
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="file-text" class="w-4 h-4" />
                        Generate report
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
