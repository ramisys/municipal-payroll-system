<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Signed in</h1>

    <p>Signed in as <strong>{{ $user->username }}</strong> ({{ $user->role->role_name }}).</p>

    @if (session('status'))
        <p>{{ session('status') }}</p>
    @endif

    <nav>
        <ul>
            @if ($canManageEmployees)
                <li><a href="{{ route('employees.index') }}">Employees</a></li>
            @endif
            @if ($canImportAttendance)
                <li><a href="{{ route('attendance-import.create') }}">Import attendance</a></li>
            @endif
            @if ($canManagePayrollRuns)
                <li><a href="{{ route('payroll-runs.index') }}">Payroll runs</a></li>
            @endif
            @if ($canManageUsers)
                <li><a href="{{ route('users.index') }}">Manage users</a></li>
            @endif
            @if ($canViewAuditLog)
                <li><a href="{{ route('audit-log.index') }}">Audit log</a></li>
            @endif
            @if ($canManageOrganization)
                <li><a href="{{ route('organization.edit') }}">Organization profile &amp; payroll calendar</a></li>
                <li><a href="{{ route('reference-data.index', 'departments') }}">Reference data</a></li>
                <li><a href="{{ route('import-column-maps.index') }}">Register column mapping</a></li>
            @endif
        </ul>
    </nav>

    <p><em>Remaining per-role module screens are not built yet — this is the pre-oral W3 milestone landing view.</em></p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sign out</button>
    </form>
</body>
</html>
