@extends('layouts.app')

@section('title', 'Deactivate employee')
@section('heading', 'Deactivate employee')

@section('content')
    <x-page-header
        title="Deactivate {{ $employee->fullName() }}"
        subtitle="{{ $employee->employee_no }}"
        :back="route('employees.index')" back-label="Employees" />

    <div class="max-w-2xl space-y-4">
        <x-alert type="warn" title="What deactivating does">
            The employee will be excluded from new payroll runs for periods after the separation date,
            but remains unchanged in every prior run and report (AC-1.1.4). The record is not deleted.
        </x-alert>

        <x-card>
            <form method="POST" action="{{ route('employees.deactivate', $employee) }}" class="space-y-4">
                @csrf

                <x-field label="Separation date" name="separation_date" required>
                    <input type="date" id="separation_date" name="separation_date" class="input"
                           value="{{ old('separation_date') }}" required autofocus>
                </x-field>

                <x-field label="Separation reason" name="separation_reason" required>
                    <input type="text" id="separation_reason" name="separation_reason" class="input"
                           value="{{ old('separation_reason') }}" required>
                </x-field>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-danger-solid"><x-icon name="ban" />Deactivate employee</button>
                    <a href="{{ route('employees.index') }}" class="btn btn-secondary"><x-icon name="x" />Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
