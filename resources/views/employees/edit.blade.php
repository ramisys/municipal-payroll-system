<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit employee — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('employees.index') }}">&larr; Employees</a></p>

    <h1>Edit employee — {{ $employee->fullName() }}</h1>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('employees.update', $employee) }}">
        @csrf
        @method('PUT')

        <p><label for="employee_no">Employee no.</label><br>
        <input type="text" id="employee_no" name="employee_no" value="{{ old('employee_no', $employee->employee_no) }}" required autofocus></p>

        <p><label for="last_name">Last name</label><br>
        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $employee->last_name) }}" required></p>

        <p><label for="first_name">First name</label><br>
        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $employee->first_name) }}" required></p>

        <p><label for="middle_name">Middle name</label><br>
        <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $employee->middle_name) }}"></p>

        <p><label for="birth_date">Date of birth</label><br>
        <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date', $employee->birth_date->toDateString()) }}" required></p>

        <p><label for="sex">Sex</label><br>
        <select id="sex" name="sex" required>
            <option value="M" @selected(old('sex', $employee->sex) === 'M')>Male</option>
            <option value="F" @selected(old('sex', $employee->sex) === 'F')>Female</option>
        </select></p>

        <p><label for="civil_status">Civil status</label><br>
        <select id="civil_status" name="civil_status" required>
            @foreach (['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED'] as $status)
                <option value="{{ $status }}" @selected(old('civil_status', $employee->civil_status) === $status)>{{ ucfirst(strtolower($status)) }}</option>
            @endforeach
        </select></p>

        <p><label for="contact_no">Contact number</label><br>
        <input type="text" id="contact_no" name="contact_no" value="{{ old('contact_no', $employee->contact_no) }}"></p>

        <p><label for="address">Address</label><br>
        <input type="text" id="address" name="address" value="{{ old('address', $employee->address) }}"></p>

        <p><label for="sss_no">SSS no. (##-#######-#)</label><br>
        <input type="text" id="sss_no" name="sss_no" value="{{ old('sss_no', $employee->sss_no) }}"></p>

        <p><label for="philhealth_no">PhilHealth no. (##-#########-#)</label><br>
        <input type="text" id="philhealth_no" name="philhealth_no" value="{{ old('philhealth_no', $employee->philhealth_no) }}"></p>

        <p><label for="pagibig_mid">Pag-IBIG MID (####-####-####)</label><br>
        <input type="text" id="pagibig_mid" name="pagibig_mid" value="{{ old('pagibig_mid', $employee->pagibig_mid) }}"></p>

        <p><label for="tin">TIN (###-###-###[-####])</label><br>
        <input type="text" id="tin" name="tin" value="{{ old('tin', $employee->tin) }}"></p>

        <h2>Employment</h2>
        <p><small>UC-09 A1 — changing department, position, or employment status below closes the current employment record and opens a new one dated from the effective date, so past payroll runs keep reporting where the employee actually was.</small></p>

        <p><label for="date_hired">Date hired</label><br>
        <input type="date" id="date_hired" name="date_hired" value="{{ old('date_hired', $detail?->date_hired?->toDateString()) }}" required readonly></p>

        <p><label for="department_id">Department</label><br>
        <select id="department_id" name="department_id" required>
            @foreach ($departments as $department)
                <option value="{{ $department->department_id }}" @selected(old('department_id', $detail?->department_id) == $department->department_id)>{{ $department->department_name }}</option>
            @endforeach
        </select></p>

        <p><label for="position_id">Position</label><br>
        <select id="position_id" name="position_id" required>
            @foreach ($positions as $position)
                <option value="{{ $position->position_id }}" @selected(old('position_id', $detail?->position_id) == $position->position_id)>{{ $position->position_title }}</option>
            @endforeach
        </select></p>

        <p><label for="employment_status_id">Employment status</label><br>
        <select id="employment_status_id" name="employment_status_id" required>
            @foreach ($employmentStatuses as $status)
                <option value="{{ $status->employment_status_id }}" @selected(old('employment_status_id', $detail?->employment_status_id) == $status->employment_status_id)>{{ $status->status_name }}</option>
            @endforeach
        </select></p>

        <p><label for="transfer_effective_from">Effective date of this employment change (only used if department, position, or status above changed)</label><br>
        <input type="date" id="transfer_effective_from" name="transfer_effective_from" value="{{ old('transfer_effective_from') }}"></p>

        <button type="submit">Save</button>
    </form>
</body>
</html>
