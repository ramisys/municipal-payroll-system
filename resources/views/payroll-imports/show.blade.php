<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Import v{{ $import->version_no }} — run #{{ $run->payroll_run_id }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('payroll-imports.history', $run) }}">&larr; Import history</a></p>

    <h1>Run #{{ $run->payroll_run_id }} — import version {{ $import->version_no }} ({{ $import->is_current ? 'current' : 'superseded' }})</h1>

    <p>
        File: {{ $import->source_filename }}<br>
        SHA-256: <small>{{ $import->source_sha256 }}</small><br>
        Imported by: {{ $import->importedBy->username }} at {{ $import->imported_at->toDateTimeString() }}<br>
        Mapping: {{ $import->columnMap->map_name }} v{{ $import->columnMap->version_no }}
    </p>

    <h2>Stored reconciliation result</h2>
    <table border="1" cellpadding="4">
        <tbody>
            @foreach ($import->reconciliation_result as $key => $value)
                <tr><th>{{ $key }}</th><td>{{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <p><a href="{{ route('payroll-imports.download', [$run, $import]) }}">Download retained source file</a> — recompute its SHA-256 and compare with the value above to verify it (UC-33 A2).</p>
</body>
</html>
