@extends('layouts.app')

@section('title', 'Edit employee')
@section('heading', 'Edit employee — ' . $employee->fullName())

@section('content')
    <x-page-header
        title="Edit employee"
        subtitle="{{ $employee->fullName() }} ({{ $employee->employee_no }})"
        :back="route('employees.index')" back-label="Employees">
        <x-slot:actions>
            <a href="{{ route('employees.compensation.index', $employee) }}" class="btn btn-secondary">Compensation</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('employees.update', $employee) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <x-card title="Personal details">
            <div class="form-grid">
                <x-field label="Employee no." name="employee_no" required>
                    <input type="text" id="employee_no" name="employee_no" class="input"
                           value="{{ old('employee_no', $employee->employee_no) }}" required autofocus>
                </x-field>

                <x-field label="Date of birth" name="birth_date" required>
                    <input type="date" id="birth_date" name="birth_date" class="input"
                           value="{{ old('birth_date', $employee->birth_date->toDateString()) }}" required>
                </x-field>

                <x-field label="Last name" name="last_name" required>
                    <input type="text" id="last_name" name="last_name" class="input"
                           value="{{ old('last_name', $employee->last_name) }}" required>
                </x-field>

                <x-field label="First name" name="first_name" required>
                    <input type="text" id="first_name" name="first_name" class="input"
                           value="{{ old('first_name', $employee->first_name) }}" required>
                </x-field>

                <x-field label="Middle name" name="middle_name">
                    <input type="text" id="middle_name" name="middle_name" class="input"
                           value="{{ old('middle_name', $employee->middle_name) }}">
                </x-field>

                <x-field label="Sex" name="sex" required>
                    <select id="sex" name="sex" class="select" required>
                        <option value="M" @selected(old('sex', $employee->sex) === 'M')>Male</option>
                        <option value="F" @selected(old('sex', $employee->sex) === 'F')>Female</option>
                    </select>
                </x-field>

                <x-field label="Civil status" name="civil_status" required>
                    <select id="civil_status" name="civil_status" class="select" required>
                        @foreach (['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED'] as $status)
                            <option value="{{ $status }}" @selected(old('civil_status', $employee->civil_status) === $status)>{{ ucfirst(strtolower($status)) }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Contact number" name="contact_no">
                    <input type="text" id="contact_no" name="contact_no" class="input"
                           value="{{ old('contact_no', $employee->contact_no) }}">
                </x-field>

                <x-field label="Address" name="address" :span="true">
                    <input type="text" id="address" name="address" class="input"
                           value="{{ old('address', $employee->address) }}">
                </x-field>
            </div>
        </x-card>

        <x-card title="Government identifiers">
            <div class="form-grid">
                <x-field label="SSS no." name="sss_no" hint="Format ##-#######-#">
                    <input type="text" id="sss_no" name="sss_no" class="input font-mono"
                           value="{{ old('sss_no', $employee->sss_no) }}" placeholder="00-0000000-0">
                </x-field>

                <x-field label="PhilHealth no." name="philhealth_no" hint="Format ##-#########-#">
                    <input type="text" id="philhealth_no" name="philhealth_no" class="input font-mono"
                           value="{{ old('philhealth_no', $employee->philhealth_no) }}" placeholder="00-000000000-0">
                </x-field>

                <x-field label="Pag-IBIG MID" name="pagibig_mid" hint="Format ####-####-####">
                    <input type="text" id="pagibig_mid" name="pagibig_mid" class="input font-mono"
                           value="{{ old('pagibig_mid', $employee->pagibig_mid) }}" placeholder="0000-0000-0000">
                </x-field>

                <x-field label="TIN" name="tin" hint="Format ###-###-###[-####]">
                    <input type="text" id="tin" name="tin" class="input font-mono"
                           value="{{ old('tin', $employee->tin) }}" placeholder="000-000-000">
                </x-field>
            </div>
        </x-card>

        <x-card title="Employment">
            <x-note class="mb-4">
                UC-09 A1 — changing department, position, or employment status below closes the current
                employment record and opens a new one dated from the effective date, so past payroll runs
                keep reporting where the employee actually was.
            </x-note>

            <div class="form-grid">
                <x-field label="Date hired" name="date_hired" hint="Set at registration; not editable here.">
                    <input type="date" id="date_hired" name="date_hired" class="input"
                           value="{{ old('date_hired', $detail?->date_hired?->toDateString()) }}" required readonly>
                </x-field>

                <x-field label="Effective date of this employment change" name="transfer_effective_from"
                         hint="Only used if department, position, or status changed.">
                    <input type="date" id="transfer_effective_from" name="transfer_effective_from" class="input"
                           value="{{ old('transfer_effective_from') }}">
                </x-field>

                <x-field label="Department" name="department_id" required>
                    <select id="department_id" name="department_id" class="select" required>
                        @foreach ($departments as $department)
                            <option value="{{ $department->department_id }}" @selected(old('department_id', $detail?->department_id) == $department->department_id)>{{ $department->department_name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Position" name="position_id" required>
                    <select id="position_id" name="position_id" class="select" required>
                        @foreach ($positions as $position)
                            <option value="{{ $position->position_id }}" @selected(old('position_id', $detail?->position_id) == $position->position_id)>{{ $position->position_title }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Employment status" name="employment_status_id" required>
                    <select id="employment_status_id" name="employment_status_id" class="select" required>
                        @foreach ($employmentStatuses as $status)
                            <option value="{{ $status->employment_status_id }}" @selected(old('employment_status_id', $detail?->employment_status_id) == $status->employment_status_id)>{{ $status->status_name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-card>

        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('employees.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
@endsection
