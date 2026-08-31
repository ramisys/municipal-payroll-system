<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Import register — run #{{ $run->payroll_run_id }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('payroll-runs.show', $run) }}">&larr; Run #{{ $run->payroll_run_id }}</a></p>

    <h1>Import computed payroll register — run #{{ $run->payroll_run_id }}</h1>

    <p><small>UC-18 preconditions — the run's input worksheet was exported and the accounting office has returned a completed register. Select the column mapping version that matches the register's layout (AD-17, BR-41).</small></p>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @if ($maps->isEmpty())
        <p><em>No column mapping is defined yet (BR-41). Maintaining the register column mapping is an Administrator function; ask an Administrator to publish one before a register can be imported.</em></p>
    @endif

    <form method="POST" action="{{ route('payroll-imports.preview', $run) }}" enctype="multipart/form-data">
        @csrf

        <label for="import_column_map_id">Column mapping</label>
        <select id="import_column_map_id" name="import_column_map_id" required>
            @foreach ($maps as $map)
                <option value="{{ $map->import_column_map_id }}">{{ $map->map_name }} v{{ $map->version_no }}</option>
            @endforeach
        </select>

        <label for="file">Register file</label>
        <input type="file" id="file" name="file" required>

        <button type="submit">Preview</button>
    </form>
</body>
</html>
