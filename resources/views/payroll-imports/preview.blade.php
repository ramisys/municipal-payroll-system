<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Preview register import — run #{{ $run->payroll_run_id }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('payroll-imports.create', $run) }}">&larr; Import computed register</a></p>

    <h1>Preview — run #{{ $run->payroll_run_id }}, {{ $map->map_name }} v{{ $map->version_no }}</h1>

    <p><small>UC-18 A2 — nothing has been written yet. Confirming below reconciles and commits this exact file in one transaction (AC-2.8.7); if any part fails, none of it is written.</small></p>

    @if ($defects !== [])
        <h2>Refused — {{ count($defects) }} reconciliation defect(s)</h2>
        <table border="1" cellpadding="4">
            <thead>
                <tr><th>Type</th><th>Row</th><th>Employee no.</th><th>Message</th></tr>
            </thead>
            <tbody>
                @foreach ($defects as $defect)
                    <tr>
                        <td>{{ $defect['type'] }}</td>
                        <td>{{ $defect['row'] ?? '—' }}</td>
                        <td>{{ $defect['employee_no'] ?? '—' }}</td>
                        <td>{{ $defect['message'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('payroll-imports.cancel', $run) }}">
            @csrf
            <button type="submit">Cancel</button>
        </form>
    @else
        <p>
            {{ $result->rowCount }} row(s) reconcile — gross {{ $result->controlTotalGross }},
            deductions {{ $result->controlTotalDeductions }}, net {{ $result->controlTotalNet }},
            {{ $result->matchedEmployeeCount }} employee(s) matched.
        </p>

        <table border="1" cellpadding="4">
            <thead>
                <tr><th>Row</th><th>Employee no.</th><th>Gross</th><th>Deductions</th><th>Net</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['row_number'] }}</td>
                        <td>{{ $row['employee_no'] }}</td>
                        <td>{{ $row['gross_pay'] }}</td>
                        <td>{{ $row['total_deductions'] }}</td>
                        <td>{{ $row['net_pay'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('payroll-imports.commit', $run) }}">
            @csrf
            <button type="submit">Confirm and commit</button>
        </form>

        <form method="POST" action="{{ route('payroll-imports.cancel', $run) }}">
            @csrf
            <button type="submit">Cancel</button>
        </form>
    @endif
</body>
</html>
