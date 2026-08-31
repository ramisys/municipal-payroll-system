@extends('layouts.app')

@section('title', 'Register employee')
@section('heading', 'Register new employee')

@section('content')
    <x-page-header title="Register new employee" :back="route('employees.index')" back-label="Employees" />

    @isset($duplicate)
        {{-- UC-08 E2 — a probable duplicate by name and date of birth is a
             warning the user must explicitly acknowledge before saving,
             not an outright refusal. --}}
        <x-alert type="warn" title="Possible duplicate.">
            <p>
                An existing employee matches this name and date of birth:
                <strong>{{ $duplicate->employee_no }} — {{ $duplicate->fullName() }}</strong>
                ({{ $duplicate->is_active ? 'active' : 'deactivated' }}).
            </p>
            @if (! $duplicate->is_active)
                <p class="mt-1">
                    If this is the same person returning, consider
                    <a href="{{ route('employees.reactivate-form', $duplicate) }}">reactivating that record</a>
                    instead of registering a new one (UC-08 A2).
                </p>
            @endif
        </x-alert>
    @endisset

    <form method="POST" action="{{ route('employees.store') }}" class="space-y-4">
        @csrf

        <x-card title="Personal details">
            <div class="form-grid">
                <x-field label="Employee no." name="employee_no" required>
                    <input type="text" id="employee_no" name="employee_no" class="input"
                           value="{{ old('employee_no', $item['employee_no'] ?? '') }}" required autofocus>
                </x-field>

                <x-field label="Date of birth" name="birth_date" required>
                    <input type="date" id="birth_date" name="birth_date" class="input"
                           value="{{ old('birth_date', $item['birth_date'] ?? '') }}" required>
                </x-field>

                <x-field label="Last name" name="last_name" required>
                    <input type="text" id="last_name" name="last_name" class="input"
                           value="{{ old('last_name', $item['last_name'] ?? '') }}" required>
                </x-field>

                <x-field label="First name" name="first_name" required>
                    <input type="text" id="first_name" name="first_name" class="input"
                           value="{{ old('first_name', $item['first_name'] ?? '') }}" required>
                </x-field>

                <x-field label="Middle name" name="middle_name">
                    <input type="text" id="middle_name" name="middle_name" class="input"
                           value="{{ old('middle_name', $item['middle_name'] ?? '') }}">
                </x-field>

                <x-field label="Sex" name="sex" required>
                    <select id="sex" name="sex" class="select" required>
                        <option value="">Select</option>
                        <option value="M" @selected(old('sex', $item['sex'] ?? null) === 'M')>Male</option>
                        <option value="F" @selected(old('sex', $item['sex'] ?? null) === 'F')>Female</option>
                    </select>
                </x-field>

                <x-field label="Civil status" name="civil_status" required>
                    <select id="civil_status" name="civil_status" class="select" required>
                        <option value="">Select</option>
                        @foreach (['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED'] as $status)
                            <option value="{{ $status }}" @selected(old('civil_status', $item['civil_status'] ?? null) === $status)>{{ ucfirst(strtolower($status)) }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Contact number" name="contact_no">
                    <input type="text" id="contact_no" name="contact_no" class="input"
                           value="{{ old('contact_no', $item['contact_no'] ?? '') }}">
                </x-field>

                <x-field label="Address" name="address" :span="true">
                    <input type="text" id="address" name="address" class="input"
                           value="{{ old('address', $item['address'] ?? '') }}">
                </x-field>
            </div>
        </x-card>

        <x-card title="Government identifiers"
                subtitle="All optional here — they may be added later, but a payroll run will need them.">
            <div class="form-grid">
                <x-field label="SSS no." name="sss_no" hint="Format ##-#######-#">
                    <input type="text" id="sss_no" name="sss_no" class="input font-mono"
                           value="{{ old('sss_no', $item['sss_no'] ?? '') }}" placeholder="00-0000000-0">
                </x-field>

                <x-field label="PhilHealth no." name="philhealth_no" hint="Format ##-#########-#">
                    <input type="text" id="philhealth_no" name="philhealth_no" class="input font-mono"
                           value="{{ old('philhealth_no', $item['philhealth_no'] ?? '') }}" placeholder="00-000000000-0">
                </x-field>

                <x-field label="Pag-IBIG MID" name="pagibig_mid" hint="Format ####-####-####">
                    <input type="text" id="pagibig_mid" name="pagibig_mid" class="input font-mono"
                           value="{{ old('pagibig_mid', $item['pagibig_mid'] ?? '') }}" placeholder="0000-0000-0000">
                </x-field>

                <x-field label="TIN" name="tin" hint="Format ###-###-###[-####]">
                    <input type="text" id="tin" name="tin" class="input font-mono"
                           value="{{ old('tin', $item['tin'] ?? '') }}" placeholder="000-000-000">
                </x-field>
            </div>
        </x-card>

        <x-card title="Employment">
            <div class="form-grid">
                <x-field label="Date hired" name="date_hired" required>
                    <input type="date" id="date_hired" name="date_hired" class="input"
                           value="{{ old('date_hired', $item['date_hired'] ?? '') }}" required>
                </x-field>

                <x-field label="Department" name="department_id" required>
                    <select id="department_id" name="department_id" class="select" required>
                        <option value="">Select</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->department_id }}" @selected(old('department_id', $item['department_id'] ?? null) == $department->department_id)>{{ $department->department_name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Position" name="position_id" required>
                    <select id="position_id" name="position_id" class="select" required>
                        <option value="">Select</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->position_id }}" @selected(old('position_id', $item['position_id'] ?? null) == $position->position_id)>{{ $position->position_title }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Employment status" name="employment_status_id" required>
                    <select id="employment_status_id" name="employment_status_id" class="select" required>
                        <option value="">Select</option>
                        @foreach ($employmentStatuses as $status)
                            <option value="{{ $status->employment_status_id }}" @selected(old('employment_status_id', $item['employment_status_id'] ?? null) == $status->employment_status_id)>{{ $status->status_name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-card>

        @isset($duplicate)
            <x-card>
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="acknowledge_duplicate" value="1" class="checkbox mt-0.5">
                    <span class="text-sm text-ink">
                        I have reviewed the possible duplicate above and confirm this is a different, new employee.
                    </span>
                </label>
            </x-card>
        @endisset

        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-primary">Register employee</button>
            <a href="{{ route('employees.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
@endsection
