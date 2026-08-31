@extends('layouts.app')

@section('title', 'Create payroll run')
@section('heading', 'Create payroll run')

@section('content')
    <x-page-header title="Create payroll run" :back="route('payroll-runs.index')" back-label="Payroll runs" />

    <div class="max-w-3xl space-y-4">
        @if ($periods->isEmpty())
            {{-- UC-17 E3 — the officer cannot fix this themselves, so the message
                 names who can rather than just reporting the block. --}}
            <x-alert type="warn" title="No pay periods are defined yet (UC-17 E3).">
                Configuring the payroll calendar (UC-03) is an Administrator function. Ask an
                Administrator to generate it before a run can be created.
            </x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('payroll-runs.store') }}" class="space-y-4">
                @csrf

                <div class="form-grid">
                    <x-field label="Pay period" name="payroll_period_id" required>
                        <select id="payroll_period_id" name="payroll_period_id" class="select" required>
                            <option value="">— Select —</option>
                            @foreach ($periods as $period)
                                <option value="{{ $period->payroll_period_id }}" @selected(old('payroll_period_id') == $period->payroll_period_id)>
                                    {{ $period->payroll_year }}-{{ $period->period_no }} ({{ $period->cutoff_start->toDateString() }} to {{ $period->cutoff_end->toDateString() }})
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Run type" name="run_type" required>
                        <select id="run_type" name="run_type" class="select" required>
                            <option value="REGULAR" @selected(old('run_type', 'REGULAR') === 'REGULAR')>Regular</option>
                            <option value="THIRTEENTH_MONTH" @selected(old('run_type') === 'THIRTEENTH_MONTH')>13th month</option>
                            <option value="FINAL_PAY" @selected(old('run_type') === 'FINAL_PAY')>Final pay</option>
                            <option value="SPECIAL" @selected(old('run_type') === 'SPECIAL')>Special</option>
                        </select>
                    </x-field>
                </div>

                <fieldset>
                    <legend class="label">Population</legend>

                    <div class="space-y-2">
                        <label class="flex items-start gap-2.5 px-3 py-2.5 rounded-md border border-line-strong cursor-pointer hover:bg-slate-50 transition-colors duration-200">
                            <input type="radio" name="scope" value="ALL" class="checkbox rounded-full mt-0.5"
                                   @checked(old('scope', 'ALL') === 'ALL')>
                            <span class="text-sm text-ink">All active employees</span>
                        </label>

                        <label class="flex items-start gap-2.5 px-3 py-2.5 rounded-md border border-line-strong cursor-pointer hover:bg-slate-50 transition-colors duration-200">
                            <input type="radio" name="scope" value="DEPARTMENT" class="checkbox rounded-full mt-0.5"
                                   @checked(old('scope') === 'DEPARTMENT')>
                            <span class="text-sm text-ink">One department</span>
                        </label>
                    </div>

                    <div class="mt-3">
                        <x-field label="Department" name="department_id"
                                 hint="Used only when the population above is one department.">
                            <select id="department_id" name="department_id" class="select">
                                <option value="">— Select department —</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->department_id }}" @selected(old('department_id') == $department->department_id)>{{ $department->department_name }}</option>
                                @endforeach
                            </select>
                        </x-field>
                    </div>
                </fieldset>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary" @disabled($periods->isEmpty())>Create run</button>
                    <a href="{{ route('payroll-runs.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
