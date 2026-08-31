<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reactivate employee — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('employees.index') }}">&larr; Employees</a></p>

    <h1>Reactivate — {{ $employee->fullName() }} ({{ $employee->employee_no }})</h1>

    <p>The original record and its full history are preserved (UC-10 A1). A new compensation profile entry will be required separately.</p>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('employees.reactivate', $employee) }}">
        @csrf

        <p><label for="date_hired">New date hired</label><br>
        <input type="date" id="date_hired" name="date_hired" value="{{ old('date_hired') }}" required autofocus></p>

        <p><label for="department_id">Department</label><br>
        <select id="department_id" name="department_id" required>
            <option value="">Select</option>
            @foreach ($departments as $department)
                <option value="{{ $department->department_id }}" @selected(old('department_id') == $department->department_id)>{{ $department->department_name }}</option>
            @endforeach
        </select></p>

        <p><label for="position_id">Position</label><br>
        <select id="position_id" name="position_id" required>
            <option value="">Select</option>
            @foreach ($positions as $position)
                <option value="{{ $position->position_id }}" @selected(old('position_id') == $position->position_id)>{{ $position->position_title }}</option>
            @endforeach
        </select></p>

        <p><label for="employment_status_id">Employment status</label><br>
        <select id="employment_status_id" name="employment_status_id" required>
            <option value="">Select</option>
            @foreach ($employmentStatuses as $status)
                <option value="{{ $status->employment_status_id }}" @selected(old('employment_status_id') == $status->employment_status_id)>{{ $status->status_name }}</option>
            @endforeach
        </select></p>

        <button type="submit">Reactivate employee</button>
    </form>
</body>
</html>
