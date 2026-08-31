<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Import attendance — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>

    <h1>Import attendance</h1>

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

    <p><small>UC-13, FR-1.3 — nothing is written until the preview below is confirmed (AC-1.3.1). Expected columns: 'Employee No', 'Date', 'Time In', 'Time Out'.</small></p>

    <form method="POST" action="{{ route('attendance-import.preview') }}" enctype="multipart/form-data">
        @csrf

        <p><label for="payroll_period_id">Cut-off period</label><br>
        <select id="payroll_period_id" name="payroll_period_id" required>
            @foreach ($periods as $period)
                <option value="{{ $period->payroll_period_id }}">
                    {{ $period->payroll_year }} P{{ $period->period_no }} ({{ $period->cutoff_start->toDateString() }} to {{ $period->cutoff_end->toDateString() }})
                </option>
            @endforeach
        </select></p>

        <p><label for="file">Attendance file (.xlsx or .csv)</label><br>
        <input type="file" id="file" name="file" required></p>

        <button type="submit">Preview</button>
    </form>
</body>
</html>
