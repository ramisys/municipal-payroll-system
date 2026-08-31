<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register employee — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('employees.index') }}">&larr; Employees</a></p>

    <h1>Register new employee</h1>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @isset($duplicate)
        {{-- UC-08 E2 — a probable duplicate by name and date of birth is a
             warning the user must explicitly acknowledge before saving,
             not an outright refusal. --}}
        <div role="alert">
            <p><strong>Possible duplicate.</strong> An existing employee matches this name and date of birth: {{ $duplicate->employee_no }} — {{ $duplicate->fullName() }} ({{ $duplicate->is_active ? 'active' : 'deactivated' }}).</p>
            @if (! $duplicate->is_active)
                <p>If this is the same person returning, consider <a href="{{ route('employees.reactivate-form', $duplicate) }}">reactivating that record</a> instead of registering a new one (UC-08 A2).</p>
            @endif
        </div>
    @endisset

    <form method="POST" action="{{ route('employees.store') }}">
        @csrf

        <p><label for="employee_no">Employee no.</label><br>
        <input type="text" id="employee_no" name="employee_no" value="{{ old('employee_no', $item['employee_no'] ?? '') }}" required autofocus></p>

        <p><label for="last_name">Last name</label><br>
        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $item['last_name'] ?? '') }}" required></p>

        <p><label for="first_name">First name</label><br>
        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $item['first_name'] ?? '') }}" required></p>

        <p><label for="middle_name">Middle name</label><br>
        <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $item['middle_name'] ?? '') }}"></p>

        <p><label for="birth_date">Date of birth</label><br>
        <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date', $item['birth_date'] ?? '') }}" required></p>

        <p><label for="sex">Sex</label><br>
        <select id="sex" name="sex" required>
            <option value="">Select</option>
            <option value="M" @selected(old('sex', $item['sex'] ?? null) === 'M')>Male</option>
            <option value="F" @selected(old('sex', $item['sex'] ?? null) === 'F')>Female</option>
        </select></p>

        <p><label for="civil_status">Civil status</label><br>
        <select id="civil_status" name="civil_status" required>
            <option value="">Select</option>
            @foreach (['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED'] as $status)
                <option value="{{ $status }}" @selected(old('civil_status', $item['civil_status'] ?? null) === $status)>{{ ucfirst(strtolower($status)) }}</option>
            @endforeach
        </select></p>

        <p><label for="contact_no">Contact number</label><br>
        <input type="text" id="contact_no" name="contact_no" value="{{ old('contact_no', $item['contact_no'] ?? '') }}"></p>

        <p><label for="address">Address</label><br>
        <input type="text" id="address" name="address" value="{{ old('address', $item['address'] ?? '') }}"></p>

        <p><label for="sss_no">SSS no. (##-#######-#)</label><br>
        <input type="text" id="sss_no" name="sss_no" value="{{ old('sss_no', $item['sss_no'] ?? '') }}" placeholder="optional — may be added later"></p>

        <p><label for="philhealth_no">PhilHealth no. (##-#########-#)</label><br>
        <input type="text" id="philhealth_no" name="philhealth_no" value="{{ old('philhealth_no', $item['philhealth_no'] ?? '') }}" placeholder="optional — may be added later"></p>

        <p><label for="pagibig_mid">Pag-IBIG MID (####-####-####)</label><br>
        <input type="text" id="pagibig_mid" name="pagibig_mid" value="{{ old('pagibig_mid', $item['pagibig_mid'] ?? '') }}" placeholder="optional — may be added later"></p>

        <p><label for="tin">TIN (###-###-###[-####])</label><br>
        <input type="text" id="tin" name="tin" value="{{ old('tin', $item['tin'] ?? '') }}" placeholder="optional — may be added later"></p>

        <h2>Employment</h2>

        <p><label for="date_hired">Date hired</label><br>
        <input type="date" id="date_hired" name="date_hired" value="{{ old('date_hired', $item['date_hired'] ?? '') }}" required></p>

        <p><label for="department_id">Department</label><br>
        <select id="department_id" name="department_id" required>
            <option value="">Select</option>
            @foreach ($departments as $department)
                <option value="{{ $department->department_id }}" @selected(old('department_id', $item['department_id'] ?? null) == $department->department_id)>{{ $department->department_name }}</option>
            @endforeach
        </select></p>

        <p><label for="position_id">Position</label><br>
        <select id="position_id" name="position_id" required>
            <option value="">Select</option>
            @foreach ($positions as $position)
                <option value="{{ $position->position_id }}" @selected(old('position_id', $item['position_id'] ?? null) == $position->position_id)>{{ $position->position_title }}</option>
            @endforeach
        </select></p>

        <p><label for="employment_status_id">Employment status</label><br>
        <select id="employment_status_id" name="employment_status_id" required>
            <option value="">Select</option>
            @foreach ($employmentStatuses as $status)
                <option value="{{ $status->employment_status_id }}" @selected(old('employment_status_id', $item['employment_status_id'] ?? null) == $status->employment_status_id)>{{ $status->status_name }}</option>
            @endforeach
        </select></p>

        @isset($duplicate)
            <p>
                <label>
                    <input type="checkbox" name="acknowledge_duplicate" value="1">
                    I have reviewed the possible duplicate above and confirm this is a different, new employee.
                </label>
            </p>
        @endisset

        <button type="submit">Register employee</button>
    </form>
</body>
</html>
