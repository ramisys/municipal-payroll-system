@extends('layouts.app')

@section('title', 'Reactivate employee')
@section('heading', 'Reactivate employee')

@section('content')
    <x-page-header
        title="Reactivate {{ $employee->fullName() }}"
        subtitle="{{ $employee->employee_no }}"
        :back="route('employees.index')" back-label="Employees" />

    <div class="max-w-2xl space-y-4">
        <x-alert type="info" title="What reactivating does">
            The original record and its full history are preserved (UC-10 A1). A new compensation
            profile entry will be required separately before this employee can be paid.
        </x-alert>

        <x-card>
            <form method="POST" action="{{ route('employees.reactivate', $employee) }}" class="space-y-4">
                @csrf

                <x-field label="New date hired" name="date_hired" required>
                    <input type="date" id="date_hired" name="date_hired" class="input"
                           value="{{ old('date_hired') }}" required autofocus>
                </x-field>

                <x-field label="Department" name="department_id" required>
                    <select id="department_id" name="department_id" class="select" required>
                        <option value="">Select</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->department_id }}" @selected(old('department_id') == $department->department_id)>{{ $department->department_name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Position" name="position_id" required>
                    <select id="position_id" name="position_id" class="select" required>
                        <option value="">Select</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->position_id }}" @selected(old('position_id') == $position->position_id)>{{ $position->position_title }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Employment status" name="employment_status_id" required>
                    <select id="employment_status_id" name="employment_status_id" class="select" required>
                        <option value="">Select</option>
                        @foreach ($employmentStatuses as $status)
                            <option value="{{ $status->employment_status_id }}" @selected(old('employment_status_id') == $status->employment_status_id)>{{ $status->status_name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary">Reactivate employee</button>
                    <a href="{{ route('employees.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
