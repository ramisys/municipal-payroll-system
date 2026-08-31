<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create payroll run — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('payroll-runs.index') }}">&larr; Payroll runs</a></p>

    <h1>Create payroll run</h1>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @if ($periods->isEmpty())
        <p><em>No pay periods are defined yet (UC-17 E3). UC-03 — configuring the payroll calendar — is an Administrator function; ask an Administrator to generate it before a run can be created.</em></p>
    @endif

    <form method="POST" action="{{ route('payroll-runs.store') }}">
        @csrf

        <label for="payroll_period_id">Pay period</label>
        <select id="payroll_period_id" name="payroll_period_id" required>
            <option value="">— Select —</option>
            @foreach ($periods as $period)
                <option value="{{ $period->payroll_period_id }}" @selected(old('payroll_period_id') == $period->payroll_period_id)>
                    {{ $period->payroll_year }}-{{ $period->period_no }} ({{ $period->cutoff_start->toDateString() }} to {{ $period->cutoff_end->toDateString() }})
                </option>
            @endforeach
        </select>

        <label for="run_type">Run type</label>
        <select id="run_type" name="run_type" required>
            <option value="REGULAR" @selected(old('run_type', 'REGULAR') === 'REGULAR')>Regular</option>
            <option value="THIRTEENTH_MONTH" @selected(old('run_type') === 'THIRTEENTH_MONTH')>13th month</option>
            <option value="FINAL_PAY" @selected(old('run_type') === 'FINAL_PAY')>Final pay</option>
            <option value="SPECIAL" @selected(old('run_type') === 'SPECIAL')>Special</option>
        </select>

        <fieldset>
            <legend>Population</legend>
            <label>
                <input type="radio" name="scope" value="ALL" @checked(old('scope', 'ALL') === 'ALL')>
                All active employees
            </label>
            <label>
                <input type="radio" name="scope" value="DEPARTMENT" @checked(old('scope') === 'DEPARTMENT')>
                One department
            </label>
            <select name="department_id">
                <option value="">— Select department —</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->department_id }}" @selected(old('department_id') == $department->department_id)>{{ $department->department_name }}</option>
                @endforeach
            </select>
        </fieldset>

        <button type="submit">Create run</button>
    </form>
</body>
</html>
