<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Import history — run #{{ $run->payroll_run_id }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('payroll-runs.show', $run) }}">&larr; Run #{{ $run->payroll_run_id }}</a></p>

    <h1>Import history — run #{{ $run->payroll_run_id }}</h1>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Version</th>
                <th>Current?</th>
                <th>File</th>
                <th>SHA-256</th>
                <th>Imported by</th>
                <th>Imported at</th>
                <th>Rows</th>
                <th>Control totals (gross / deductions / net)</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($imports as $import)
                <tr>
                    <td>{{ $import->version_no }}</td>
                    <td>{{ $import->is_current ? 'Current' : 'Superseded' }}</td>
                    <td>{{ $import->source_filename }}</td>
                    <td><small>{{ $import->source_sha256 }}</small></td>
                    <td>{{ $import->importedBy->username }}</td>
                    <td>{{ $import->imported_at->toDateTimeString() }}</td>
                    <td>{{ $import->row_count }}</td>
                    <td>{{ $import->control_total_gross }} / {{ $import->control_total_deductions }} / {{ $import->control_total_net }}</td>
                    <td>
                        <a href="{{ route('payroll-imports.show', [$run, $import]) }}">Detail</a>
                        <a href="{{ route('payroll-imports.download', [$run, $import]) }}">Download</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
