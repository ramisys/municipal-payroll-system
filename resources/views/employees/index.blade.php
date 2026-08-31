<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Employees — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>

    <h1>Employees</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p><a href="{{ route('employees.create') }}">Register new employee</a></p>

    <form method="GET" action="{{ route('employees.index') }}">
        <label for="q">Search (name or employee no.)</label>
        <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}">

        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
            <option value="">All</option>
            @foreach ($departments as $department)
                <option value="{{ $department->department_id }}" @selected(($filters['department_id'] ?? null) == $department->department_id)>{{ $department->department_name }}</option>
            @endforeach
        </select>

        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
            <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Deactivated</option>
        </select>

        <button type="submit">Filter</button>
    </form>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Employee no.</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Employment status</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($employees as $employee)
                <tr>
                    <td>{{ $employee->employee_no }}</td>
                    <td>{{ $employee->fullName() }}</td>
                    <td>{{ $employee->currentEmploymentDetail?->department?->department_name ?? '—' }}</td>
                    <td>{{ $employee->currentEmploymentDetail?->position?->position_title ?? '—' }}</td>
                    <td>{{ $employee->currentEmploymentDetail?->employmentStatus?->status_name ?? '—' }}</td>
                    <td>{{ $employee->is_active ? 'Active' : 'Deactivated' }}</td>
                    <td>
                        <a href="{{ route('employees.edit', $employee) }}">Edit</a>
                        @if ($employee->is_active)
                            <a href="{{ route('employees.deactivate-form', $employee) }}">Deactivate</a>
                        @else
                            <a href="{{ route('employees.reactivate-form', $employee) }}">Reactivate</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
