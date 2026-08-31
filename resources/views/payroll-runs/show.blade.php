<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Run #{{ $run->payroll_run_id }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('payroll-runs.index') }}">&larr; Payroll runs</a></p>

    <h1>Run #{{ $run->payroll_run_id }} — {{ $run->run_type }}, {{ \App\Services\PayrollRunService::populationScopeLabel($run->population_scope) }}</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <p>
        Period: {{ $run->period->payroll_year }}-{{ $run->period->period_no }}
        ({{ $run->period->cutoff_start->toDateString() }} to {{ $run->period->cutoff_end->toDateString() }}) &middot;
        Status: <strong>{{ $run->run_status }}</strong> &middot;
        Population: {{ $run->employee_count }} employee(s)
    </p>

    <p><small>Totals below are derived from the current import's payroll lines on every view, never stored on the run itself (FR-2.5/§7 beat 11).</small></p>
    <p>
        Gross: {{ $totals['gross'] }} &middot;
        Deductions: {{ $totals['deductions'] }} &middot;
        Net: {{ $totals['net'] }}
    </p>

    <ul>
        <li><a href="{{ route('payroll-runs.worksheet', $run) }}">Export input worksheet (UC-32)</a></li>
        @if (in_array($run->run_status, ['DRAFT', 'RETURNED'], true))
            <li><a href="{{ route('payroll-imports.create', $run) }}">Import computed register (UC-18)</a></li>
        @endif
        <li><a href="{{ route('payroll-imports.history', $run) }}">Import history (UC-33)</a></li>
        @if ($run->run_status === 'DRAFT')
            <li><a href="{{ route('payroll-runs.cancel-form', $run) }}">Cancel this run</a></li>
        @endif
    </ul>

    @if ($currentImport === null)
        <p><em>No register has been imported into this run yet.</em></p>
    @else
        <p>Current import: version {{ $currentImport->version_no }}, {{ $currentImport->source_filename }}, {{ $currentImport->row_count }} row(s), imported {{ $currentImport->imported_at->toDateTimeString() }}.</p>

        <table border="1" cellpadding="4">
            <thead>
                <tr>
                    <th>Employee no.</th>
                    <th>Name</th>
                    <th>Gross pay</th>
                    <th>Total deductions</th>
                    <th>Net pay</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($run->lines->where('payroll_import_id', $currentImport->payroll_import_id) as $line)
                    <tr>
                        <td>{{ $line->employee->employee_no }}</td>
                        <td>{{ $line->employee->fullName() }}</td>
                        <td>{{ $line->gross_pay }}</td>
                        <td>{{ $line->total_deductions }}</td>
                        <td>{{ $line->net_pay }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
