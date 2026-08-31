<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Organization profile — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>

    <h1>Organization profile and payroll calendar</h1>

    <nav>
        <ul>
            <li><strong>Organization profile</strong></li>
            <li><a href="{{ route('organization.periods.index') }}">Pay periods</a></li>
            <li><a href="{{ route('organization.holidays.index') }}">Holiday calendar</a></li>
            <li><a href="{{ route('reference-data.index', 'departments') }}">Reference data</a></li>
            <li><a href="{{ route('import-column-maps.index') }}">Register column mapping</a></li>
        </ul>
    </nav>

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

    <form method="POST" action="{{ route('organization.update') }}">
        @csrf
        @method('PUT')

        <p>
            <label for="registered_name">Registered name</label><br>
            <input type="text" id="registered_name" name="registered_name" value="{{ old('registered_name', $profile->registered_name) }}" required>
        </p>
        <p>
            <label for="address">Address</label><br>
            <input type="text" id="address" name="address" value="{{ old('address', $profile->address) }}">
        </p>
        <p>
            <label for="sss_employer_no">SSS employer number</label><br>
            <input type="text" id="sss_employer_no" name="sss_employer_no" value="{{ old('sss_employer_no', $profile->sss_employer_no) }}">
        </p>
        <p>
            <label for="philhealth_employer_no">PhilHealth employer number</label><br>
            <input type="text" id="philhealth_employer_no" name="philhealth_employer_no" value="{{ old('philhealth_employer_no', $profile->philhealth_employer_no) }}">
        </p>
        <p>
            <label for="pagibig_employer_no">Pag-IBIG employer number</label><br>
            <input type="text" id="pagibig_employer_no" name="pagibig_employer_no" value="{{ old('pagibig_employer_no', $profile->pagibig_employer_no) }}">
        </p>
        <p>
            <label for="bir_tin">BIR TIN</label><br>
            <input type="text" id="bir_tin" name="bir_tin" value="{{ old('bir_tin', $profile->bir_tin) }}">
        </p>
        <p>
            <label for="standard_hours_per_day">Standard hours per day</label><br>
            <input type="text" id="standard_hours_per_day" name="standard_hours_per_day" value="{{ old('standard_hours_per_day', $standardHoursPerDay) }}" required>
            <br><small>BR-03 — used only to derive the input worksheet's hours-worked column. The system holds no day factor and applies no rate (A-05, OI-03 closed).</small>
        </p>

        <button type="submit">Save</button>
    </form>
</body>
</html>
