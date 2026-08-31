<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Deactivate employee — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('employees.index') }}">&larr; Employees</a></p>

    <h1>Deactivate — {{ $employee->fullName() }} ({{ $employee->employee_no }})</h1>

    <p>The employee will be excluded from new payroll runs for periods after the separation date, but remains unchanged in every prior run and report (AC-1.1.4).</p>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('employees.deactivate', $employee) }}">
        @csrf

        <p><label for="separation_date">Separation date</label><br>
        <input type="date" id="separation_date" name="separation_date" value="{{ old('separation_date') }}" required autofocus></p>

        <p><label for="separation_reason">Separation reason</label><br>
        <input type="text" id="separation_reason" name="separation_reason" value="{{ old('separation_reason') }}" required></p>

        <button type="submit">Deactivate employee</button>
    </form>
</body>
</html>
