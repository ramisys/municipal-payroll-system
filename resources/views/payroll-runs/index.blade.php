<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payroll runs — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>

    <h1>Payroll runs</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p><a href="{{ route('payroll-runs.create') }}">Create payroll run</a></p>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>#</th>
                <th>Period</th>
                <th>Run type</th>
                <th>Population</th>
                <th>Status</th>
                <th>Employees</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($runs as $run)
                <tr>
                    <td>{{ $run->payroll_run_id }}</td>
                    <td>{{ $run->period->payroll_year }}-{{ $run->period->period_no }} ({{ $run->period->cutoff_start->toDateString() }} to {{ $run->period->cutoff_end->toDateString() }})</td>
                    <td>{{ $run->run_type }}</td>
                    <td>{{ \App\Services\PayrollRunService::populationScopeLabel($run->population_scope) }}</td>
                    <td>{{ $run->run_status }}</td>
                    <td>{{ $run->employee_count }}</td>
                    <td><a href="{{ route('payroll-runs.show', $run) }}">Open</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No payroll runs yet — create one for a defined pay period (UC-17).</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
