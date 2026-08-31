<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Preview attendance import — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('attendance-import.create') }}">&larr; Import attendance</a></p>

    <h1>Preview — {{ $period->payroll_year }} P{{ $period->period_no }} ({{ $period->cutoff_start->toDateString() }} to {{ $period->cutoff_end->toDateString() }})</h1>

    <p>
        {{ count($accepted) }} row(s) will be committed, {{ count($rejected) }} row(s) rejected.
        @if ($existingCount > 0)
            {{ $existingCount }} of the accepted employee/date combinations already have a stored record and will be replaced.
        @endif
    </p>

    <p><small>UC-13 AC-1.3.1 — nothing has been written yet. Confirming below commits every accepted row in one transaction; if any part of the commit fails, none of it is written (all-or-nothing).</small></p>

    @if (count($rejected) > 0)
        <h2>Rejected rows</h2>
        <table border="1" cellpadding="4">
            <thead>
                <tr><th>Row</th><th>Reason</th></tr>
            </thead>
            <tbody>
                @foreach ($rejected as $row)
                    <tr>
                        <td>{{ $row['row_number'] }}</td>
                        <td>{{ $row['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($accepted) > 0)
        <h2>Accepted rows</h2>
        <table border="1" cellpadding="4">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Employee no.</th>
                    <th>Date</th>
                    <th>Time in</th>
                    <th>Time out</th>
                    <th>Hours worked</th>
                    <th>Late (min)</th>
                    <th>Undertime (min)</th>
                    <th>Overtime (hrs)</th>
                    <th>Night diff. (hrs)</th>
                    <th>Day classification</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accepted as $row)
                    <tr>
                        <td>{{ $row['row_number'] }}</td>
                        <td>{{ $row['employee_no'] }}</td>
                        <td>{{ $row['work_date'] }}</td>
                        <td>{{ $row['time_in'] }}</td>
                        <td>{{ $row['time_out'] }}</td>
                        <td>{{ $row['hours_worked'] }}</td>
                        <td>{{ $row['late_minutes'] }}</td>
                        <td>{{ $row['undertime_minutes'] }}</td>
                        <td>{{ $row['overtime_hours'] }}</td>
                        <td>{{ $row['night_diff_hours'] }}</td>
                        <td>{{ $row['day_classification'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('attendance-import.commit') }}">
            @csrf
            <button type="submit">Confirm and commit {{ count($accepted) }} row(s)</button>
        </form>
    @endif

    <form method="POST" action="{{ route('attendance-import.cancel') }}">
        @csrf
        <button type="submit">Cancel</button>
    </form>
</body>
</html>
